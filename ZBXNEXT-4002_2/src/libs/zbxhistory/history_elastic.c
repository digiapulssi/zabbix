/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "common.h"
#include "log.h"
#include "zbxjson.h"
#include "zbxalgo.h"
#include "dbcache.h"
#include "zbxhistory.h"
#include "history.h"

#ifdef HAVE_LIBCURL

#define		ZBX_HISTORY_STORAGE_DOWN	10000 /* Timeout in milliseconds */

#define		ZBX_IDX_JSON_ALLOCATE		256
#define		ZBX_JSON_ALLOCATE		2048


const char	*value_type_str[] = {"dbl", "str", "log", "uint", "text"};

extern char	*CONFIG_HISTORY_STORAGE_URL;

typedef struct
{
	char	*base_url;
	char	*post_url;
	char	*buf;
	CURL	*handle;
}
zbx_elastic_data_t;

typedef struct
{
	unsigned char		initialized;
	zbx_vector_ptr_t	ifaces;

	CURLM			*handle;
}
zbx_elastic_writer_t;

static zbx_elastic_writer_t	writer;

typedef struct
{
	char	*data;
	size_t	alloc;
	size_t	offset;
}
zbx_httppage_t;

static zbx_httppage_t	page;

static size_t	curl_write_cb(void *ptr, size_t size, size_t nmemb, void *userdata)
{
	size_t	r_size = size * nmemb;

	ZBX_UNUSED(userdata);

	zbx_strncpy_alloc(&page.data, &page.alloc, &page.offset, ptr, r_size);

	return r_size;
}

/* Stub function for avoiding LibCURL to print on the standard output. */
/* In case of success, elasticsearch return a JSON, but the HTTP error code is enough */
static size_t	curl_write_send_cb(void *ptr, size_t size, size_t nmemb, void *userdata)
{
	ZBX_UNUSED(ptr);
	ZBX_UNUSED(userdata);
	return size * nmemb;
}

static history_value_t	history_str2value(char *str, unsigned char value_type)
{
	history_value_t	value;

	switch (value_type)
	{
		case ITEM_VALUE_TYPE_LOG:
			value.log = zbx_malloc(NULL, sizeof(zbx_log_value_t));
			memset(value.log, 0, sizeof(zbx_log_value_t));
			value.log->value = zbx_strdup(NULL, str);
			break;
		case ITEM_VALUE_TYPE_STR:
		case ITEM_VALUE_TYPE_TEXT:
			value.str = zbx_strdup(NULL, str);
			break;
		case ITEM_VALUE_TYPE_FLOAT:
			value.dbl = atof(str);
			break;
		case ITEM_VALUE_TYPE_UINT64:
			ZBX_STR2UINT64(value.ui64, str);
			break;
	}

	return value;
}

static const char	*history_value2str(const ZBX_DC_HISTORY *h)
{
	static char	buffer[MAX_ID_LEN + 1];

	switch (h->value_type)
	{
		case ITEM_VALUE_TYPE_STR:
		case ITEM_VALUE_TYPE_TEXT:
			return h->value.str;
		case ITEM_VALUE_TYPE_LOG:
			return h->value.log->value;
		case ITEM_VALUE_TYPE_FLOAT:
			zbx_snprintf(buffer, sizeof(buffer), ZBX_FS_DBL, h->value.dbl);
			break;
		case ITEM_VALUE_TYPE_UINT64:
			zbx_snprintf(buffer, sizeof(buffer), ZBX_FS_UI64, h->value.ui64);
			break;
	}

	return buffer;
}

static int	history_parse_value(struct zbx_json_parse *jp, unsigned char value_type, zbx_history_record_t *hr)
{
	char	*value = NULL;
	size_t	value_alloc = 0;
	int	ret = FAIL;

	if (SUCCEED != zbx_json_value_by_name_dyn(jp, "clock", &value, &value_alloc))
		goto out;

	hr->timestamp.sec = atoi(value);

	if (SUCCEED != zbx_json_value_by_name_dyn(jp, "ns", &value, &value_alloc))
		goto out;

	hr->timestamp.ns = atoi(value);

	if (ITEM_VALUE_TYPE_LOG == value_type)
	{
		struct zbx_json_parse	jp_value;

		if (SUCCEED != zbx_json_brackets_by_name(jp, "value", &jp_value))
			goto out;

		if (SUCCEED != zbx_json_value_by_name_dyn(&jp_value, "value", &value, &value_alloc))
			goto out;

		hr->value = history_str2value(value, value_type);

		if (SUCCEED != zbx_json_value_by_name_dyn(&jp_value, "timestamp", &value, &value_alloc))
			goto out;

		hr->value.log->timestamp = atoi(value);

		if (SUCCEED != zbx_json_value_by_name_dyn(&jp_value, "logeventid", &value, &value_alloc))
			goto out;

		hr->value.log->logeventid = atoi(value);

		if (SUCCEED != zbx_json_value_by_name_dyn(&jp_value, "severity", &value, &value_alloc))
			goto out;

		hr->value.log->severity = atoi(value);

		if (SUCCEED != zbx_json_value_by_name_dyn(&jp_value, "source", &value, &value_alloc))
			goto out;

		hr->value.log->source = zbx_strdup(NULL, value);
	}
	else
	{
		if (SUCCEED != zbx_json_value_by_name_dyn(jp, "value", &value, &value_alloc))
			goto out;

		hr->value = history_str2value(value, value_type);
	}

	ret = SUCCEED;

out:
	zbx_free(value);

	return ret;
}

/************************************************************************************
 *                                                                                  *
 * Function: elastic_close                                                          *
 *                                                                                  *
 * Purpose: closes connection and releases allocated resources                      *
 *                                                                                  *
 * Parameters:  hist - [IN] the history storage interface                           *
 *                                                                                  *
 ************************************************************************************/
static void	elastic_close(zbx_history_iface_t *hist)
{
	zbx_elastic_data_t	*data = (zbx_elastic_data_t *)hist->data;

	zbx_free(data->buf);
	zbx_free(data->post_url);

	if (NULL != data->handle)
	{
		if (NULL != writer.handle)
			curl_multi_remove_handle(writer.handle, data->handle);

		curl_easy_cleanup(data->handle);
		data->handle = NULL;
	}
}

/******************************************************************************************************************
 *                                                                                                                *
 * common sql service support                                                                                     *
 *                                                                                                                *
 ******************************************************************************************************************/

/************************************************************************************
 *                                                                                  *
 * Function: elastic_writer_init                                                    *
 *                                                                                  *
 * Purpose: initializes elastic writer for a new batch of history values            *
 *                                                                                  *
 ************************************************************************************/
static void	elastic_writer_init()
{
	if (0 != writer.initialized)
		return;

	zbx_vector_ptr_create(&writer.ifaces);

	if (NULL == (writer.handle = curl_multi_init()))
	{
		zbx_error("Cannot initialize cURL multi session");
		exit(EXIT_FAILURE);
	}

	writer.initialized = 1;
}

/************************************************************************************
 *                                                                                  *
 * Function: elastic_writer_release                                                 *
 *                                                                                  *
 * Purpose: releases initialized elastic writer by freeing allocated resources and  *
 *          setting its state to uninitialized.                                     *
 *                                                                                  *
 ************************************************************************************/
static void	elastic_writer_release()
{
	int	i;

	for (i = 0; i < writer.ifaces.values_num; i++)
		elastic_close(writer.ifaces.values[i]);

	curl_multi_cleanup(writer.handle);
	zbx_vector_ptr_destroy(&writer.ifaces);

	writer.initialized = 0;
}

/************************************************************************************
 *                                                                                  *
 * Function: elastic_writer_add_iface                                               *
 *                                                                                  *
 * Purpose: adds history storage interface to be flushed later                      *
 *                                                                                  *
 * Parameters: db_insert - [IN] bulk insert data                                    *
 *                                                                                  *
 ************************************************************************************/
static void	elastic_writer_add_iface(zbx_history_iface_t *hist)
{
	zbx_elastic_data_t	*data = (zbx_elastic_data_t *)hist->data;

	elastic_writer_init();

	if (NULL == (data->handle = curl_easy_init()))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot initialize cURL session");
		return;
	}

	curl_easy_setopt(data->handle, CURLOPT_URL, data->post_url);
	curl_easy_setopt(data->handle, CURLOPT_POST, 1);
	curl_easy_setopt(data->handle, CURLOPT_POSTFIELDS, data->buf);
	curl_easy_setopt(data->handle, CURLOPT_WRITEFUNCTION, curl_write_send_cb);

	curl_multi_add_handle(writer.handle, data->handle);

	zbx_vector_ptr_append(&writer.ifaces, hist);
}

/************************************************************************************
 *                                                                                  *
 * Function: elastic_writer_flush                                                   *
 *                                                                                  *
 * Purpose: posts historical data to elastic storage                                *
 *                                                                                  *
 ************************************************************************************/
static void	elastic_writer_flush()
{
	struct curl_slist	*curl_headers = NULL;
	int			i, running, previous, msgnum;
	CURLMsg			*msg;

	if (0 == writer.initialized)
		return;

	curl_headers = curl_slist_append(curl_headers, "Content-Type: application/x-ndjson");

	for (i = 0; i < writer.ifaces.values_num; i++)
	{
		zbx_history_iface_t		*hist = (zbx_history_iface_t *)writer.ifaces.values[i];
		zbx_elastic_data_t	*data = (zbx_elastic_data_t *)hist->data;

		curl_easy_setopt(data->handle, CURLOPT_HTTPHEADER, curl_headers);
	}

	previous = 0;

	do
	{
		int		fds;
		CURLMcode	code;

		code = curl_multi_perform(writer.handle, &running);

		if (CURLM_OK == code)
			code = curl_multi_wait(writer.handle, NULL, 0, ZBX_HISTORY_STORAGE_DOWN, &fds);

		if (CURLM_OK != code)
		{
			zabbix_log(LOG_LEVEL_ERR, "elastic history interface failed to wait on curl multi handle"
					" when posting new values");
			break;
		}

		if (previous == running)
			continue;

		while (NULL != (msg = curl_multi_info_read(writer.handle, &msgnum)))
		{
			if (CURLE_OK != msg->data.result)
			{
				zabbix_log(LOG_LEVEL_WARNING, "Error on sending to history storage: %s",
						curl_easy_strerror(msg->data.result));

				/* Add back the handle and set the flag to 1 for retrying what failed */
				curl_multi_remove_handle(writer.handle, msg->easy_handle);
				curl_multi_add_handle(writer.handle, msg->easy_handle);

				running = 1;
			}
		}

		previous = running;
	}
	while (running);

	curl_slist_free_all(curl_headers);

	elastic_writer_release();
}

/******************************************************************************************************************
 *                                                                                                                *
 * history interface support                                                                                      *
 *                                                                                                                *
 ******************************************************************************************************************/

/************************************************************************************
 *                                                                                  *
 * Function: elastic_destroy                                                        *
 *                                                                                  *
 * Purpose: destroys history storage interface                                      *
 *                                                                                  *
 * Parameters:  hist - [IN] the history storage interface                           *
 *                                                                                  *
 ************************************************************************************/
static void	elastic_destroy(zbx_history_iface_t *hist)
{
	zbx_elastic_data_t	*data = (zbx_elastic_data_t *)hist->data;

	elastic_close(hist);

	zbx_free(data->base_url);
	zbx_free(data);
}

/************************************************************************************
 *                                                                                  *
 * Function: elastic_get_values                                                     *
 *                                                                                  *
 * Purpose: gets item history data from history storage                             *
 *                                                                                  *
 * Parameters:  hist    - [IN] the history storage interface                        *
 *              itemid  - [IN] the itemid                                           *
 *              start   - [IN] the period start timestamp                           *
 *              count   - [IN] the number of values to read                         *
 *              end     - [IN] the period end timestamp                             *
 *              values  - [OUT] the item history data values                        *
 *                                                                                  *
 * Return value: SUCCEED - the history data were read successfully                  *
 *               FAIL - otherwise                                                   *
 *                                                                                  *
 * Comments: This function reads <count> values from ]<start>,<end>] interval or    *
 *           all values from the specified interval if count is zero.               *
 *                                                                                  *
 ************************************************************************************/
static int	elastic_get_values(zbx_history_iface_t *hist, zbx_uint64_t itemid, int start, int count, int end,
		zbx_vector_history_record_t *values)
{
	zbx_elastic_data_t	*data = (zbx_elastic_data_t *)hist->data;
	size_t			url_alloc = 0, url_offset = 0;
	int			err;
	long			http_code;
	struct zbx_json		query;
	struct curl_slist	*curl_headers = NULL;

	if (NULL == (data->handle = curl_easy_init()))
	{
		zabbix_log(LOG_LEVEL_ERR, "Cannot initialize cURL session");

		return FAIL;
	}

	zbx_snprintf_alloc(&data->post_url, &url_alloc, &url_offset, "%s/%s/values/_search", data->base_url,
			value_type_str[hist->value_type]);

	zbx_json_init(&query, ZBX_JSON_ALLOCATE);

	if (0 < count)
	{
		zbx_json_adduint64(&query, "size", count);
		zbx_json_addarray(&query, "sort");
		zbx_json_addobject(&query, NULL);
		zbx_json_addobject(&query, "clock");
		zbx_json_addstring(&query, "order", "desc", ZBX_JSON_TYPE_STRING);
		zbx_json_close(&query);
		zbx_json_close(&query);
		zbx_json_close(&query);
	}

	zbx_json_addobject(&query, "query");
	zbx_json_addobject(&query, "bool");
	zbx_json_addarray(&query, "must");
	zbx_json_addobject(&query, NULL);
	zbx_json_addobject(&query, "match");
	zbx_json_adduint64(&query, "itemid", itemid);
	zbx_json_close(&query);
	zbx_json_close(&query);
	zbx_json_close(&query);
	zbx_json_addarray(&query, "filter");
	zbx_json_addobject(&query, NULL);
	zbx_json_addobject(&query, "range");
	zbx_json_addobject(&query, "clock");

	if (0 < start)
		zbx_json_adduint64(&query, "gt", start);

	if (0 < end)
		zbx_json_adduint64(&query, "lte", end);

	zbx_json_close(&query);
	zbx_json_close(&query);
	zbx_json_close(&query);
	zbx_json_close(&query);
	zbx_json_close(&query);
	zbx_json_close(&query);
	zbx_json_close(&query);

	curl_headers = curl_slist_append(curl_headers, "Content-Type: application/json");

	curl_easy_setopt(data->handle, CURLOPT_URL, data->post_url);
	curl_easy_setopt(data->handle, CURLOPT_POSTFIELDS, query.buffer);
	curl_easy_setopt(data->handle, CURLOPT_WRITEFUNCTION, curl_write_cb);
	curl_easy_setopt(data->handle, CURLOPT_HTTPHEADER, curl_headers);

	page.offset = 0;
	if (CURLE_OK != (err = curl_easy_perform(data->handle)))
		zabbix_log(LOG_LEVEL_ERR, "Failed to get values from history storage: %s", curl_easy_strerror(err));

	curl_easy_getinfo(data->handle, CURLINFO_RESPONSE_CODE, &http_code);

	if (200 == http_code)
	{
		struct zbx_json_parse	jp, jp_values, jp_item, jp_sub, jp_hits, jp_source;
		zbx_history_record_t	hr;
		const char		*p = NULL;

		if (NULL != page.data)
		{
			zbx_json_open(page.data, &jp);
			zbx_json_brackets_open(jp.start, &jp_values);
			zbx_json_brackets_by_name(&jp_values, "hits", &jp_sub);
			zbx_json_brackets_by_name(&jp_sub, "hits", &jp_hits);

			while (NULL != (p = zbx_json_next(&jp_hits, p)))
			{
				if (SUCCEED != zbx_json_brackets_open(p, &jp_item))
					continue;

				if (SUCCEED != zbx_json_brackets_by_name(&jp_item, "_source", &jp_source))
					continue;

				if (SUCCEED != history_parse_value(&jp_source, hist->value_type, &hr))
					continue;

				zbx_vector_history_record_append_ptr(values, &hr);
			}
		}
	}

	elastic_close(hist);

	curl_slist_free_all(curl_headers);

	zbx_json_free(&query);

	return SUCCEED;
}

/************************************************************************************
 *                                                                                  *
 * Function: elastic_add_values                                                     *
 *                                                                                  *
 * Purpose: sends history data to the storage                                       *
 *                                                                                  *
 * Parameters:  hist    - [IN] the history storage interface                        *
 *              history - [IN] the history data vector (may have mixed value types) *
 *                                                                                  *
 ************************************************************************************/
static int	elastic_add_values(zbx_history_iface_t *hist, const zbx_vector_ptr_t *history)
{
	zbx_elastic_data_t	*data = (zbx_elastic_data_t *)hist->data;
	int				i, num = 0;
	ZBX_DC_HISTORY			*h;
	struct zbx_json			json_idx, json;
	size_t				buf_alloc = 0, buf_offset = 0;

	zbx_json_init(&json_idx, ZBX_IDX_JSON_ALLOCATE);

	zbx_json_addobject(&json_idx, "index");
	zbx_json_addstring(&json_idx, "_index", value_type_str[hist->value_type], ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(&json_idx, "_type", "values", ZBX_JSON_TYPE_STRING);

	zbx_json_close(&json_idx);
	zbx_json_close(&json_idx);

	for (i = 0; i < history->values_num; i++)
	{
		h = (ZBX_DC_HISTORY *)history->values[i];

		if (hist->value_type != h->value_type)
			continue;

		zbx_json_init(&json, ZBX_JSON_ALLOCATE);

		zbx_json_adduint64(&json, "itemid", h->itemid);

		if (ITEM_VALUE_TYPE_LOG == h->value_type)
		{
			const zbx_log_value_t	*log;

			log = h->value.log;

			zbx_json_addobject(&json, "value");
			zbx_json_addstring(&json, "value", history_value2str(h), ZBX_JSON_TYPE_STRING);
			zbx_json_adduint64(&json, "timestamp", log->timestamp);
			zbx_json_addstring(&json, "source", ZBX_NULL2EMPTY_STR(log->source), ZBX_JSON_TYPE_STRING);
			zbx_json_adduint64(&json, "severity", log->severity);
			zbx_json_adduint64(&json, "logeventid", log->logeventid);

			zbx_json_close(&json);
		}
		else
			zbx_json_addstring(&json, "value", history_value2str(h), ZBX_JSON_TYPE_STRING);

		zbx_json_adduint64(&json, "clock", h->ts.sec);
		zbx_json_adduint64(&json, "ns", h->ts.ns);
		zbx_json_adduint64(&json, "ttl", h->ttl);

		zbx_json_close(&json);

		zbx_snprintf_alloc(&data->buf, &buf_alloc, &buf_offset, "%s\n%s\n", json_idx.buffer, json.buffer);

		zbx_json_free(&json);

		num++;
	}

	if (num > 0)
	{
		data->post_url = zbx_dsprintf(NULL, "%s/_bulk?refresh=true", data->base_url);
		elastic_writer_add_iface(hist);
	}

	zbx_json_free(&json_idx);

	return num;
}

/************************************************************************************
 *                                                                                  *
 * Function: elastic_flush                                                          *
 *                                                                                  *
 * Purpose: flushes the history data to storage                                     *
 *                                                                                  *
 * Parameters:  hist    - [IN] the history storage interface                        *
 *                                                                                  *
 * Comments: This function will try to flush the data until it succeeds or          *
 *           unrecoverable error occurs                                             *
 *                                                                                  *
 ************************************************************************************/
static void	elastic_flush(zbx_history_iface_t *hist)
{
	ZBX_UNUSED(hist);

	elastic_writer_flush();
}

/************************************************************************************
 *                                                                                  *
 * Function: zbx_history_elastic_init                                               *
 *                                                                                  *
 * Purpose: initializes history storage interface                                   *
 *                                                                                  *
 * Parameters:  hist       - [IN] the history storage interface                     *
 *              value_type - [IN] the target value type                             *
 *              error      - [OUT] the error message                                *
 *                                                                                  *
 * Return value: SUCCEED - the history storage interface was initialized            *
 *               FAIL    - otherwise                                                *
 *                                                                                  *
 ************************************************************************************/
int	zbx_history_elastic_init(zbx_history_iface_t *hist, unsigned char value_type, char **error)
{
	zbx_elastic_data_t	*data;

	data = (zbx_elastic_data_t *)zbx_malloc(NULL, sizeof(zbx_elastic_data_t));
	data->base_url = zbx_strdup(NULL, CONFIG_HISTORY_STORAGE_URL);
	data->buf = NULL;
	data->post_url = NULL;
	data->handle = NULL;

	if (0 != curl_global_init(CURL_GLOBAL_ALL))
	{
		*error = zbx_strdup(*error, "Cannot initialize cURL library");
		return FAIL;
	}

	hist->value_type = value_type;

	data = (zbx_elastic_data_t *)zbx_malloc(NULL, sizeof(zbx_elastic_data_t));
	memset(data, 0, sizeof(zbx_elastic_data_t));
	data->base_url = zbx_strdup(NULL, CONFIG_HISTORY_STORAGE_URL);
	hist->data = (void *)data;

	hist->destroy = elastic_destroy;
	hist->add_values = elastic_add_values;
	hist->flush = elastic_flush;
	hist->get_values = elastic_get_values;

	hist->requires_trends = 0;

	return SUCCEED;
}

#else
int	zbx_history_elastic_init(zbx_history_iface_t *hist, unsigned char value_type, char **error)
{
	*error = zbx_strdup(*error, "cURL library support is required for ElasticSearch history hist");
	return FAIL;
}
#endif

