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
#include "memalloc.h"
#include "ipc.h"
#include "dbcache.h"
#include "threads.h"
#include "zbxjson.h"
#include "valuecache.h"

#include "history.h"

#define		ZBX_HISTORY_STORAGE_DOWN	10000 /* Timeout in milliseconds */

const char	*HISTORY_STORAGE_URL	= NULL;

/* The bitmask for this variable is defined by the item types as defined*/
/*  in the enum zbx_item_value_type_t in include/common.h.*/
static int		HISTORY_STORAGE_OPTS	= 0;

#if defined (HAVE_LIBCURL)

static CURLM	*multi = NULL;

typedef struct
{
	struct zbx_json	json;
	CURL		*handle;
	const char	*type;
	char		*url;
	unsigned char	value_type;
}
ZBX_SENDER;

static ZBX_SENDER	senders[] = {
	{.handle = NULL, .value_type = ITEM_VALUE_TYPE_UINT64, .url = NULL, .type = "unum"},
	{.handle = NULL, .value_type = ITEM_VALUE_TYPE_FLOAT, .url = NULL, .type = "float"},
	{.handle = NULL, .value_type = ITEM_VALUE_TYPE_STR, .url = NULL, .type = "char"},
	{.handle = NULL, .value_type = ITEM_VALUE_TYPE_TEXT, .url = NULL, .type = "text"},
	{.handle = NULL, .value_type = ITEM_VALUE_TYPE_LOG, .url = NULL, .type = "log"},
	{.handle = NULL, .value_type = ITEM_VALUE_TYPE_MAX, .url = NULL, .type = ""}
};

typedef struct
{
	char	*data;
	size_t	alloc;
	size_t	offset;
}
ZBX_HTTPPAGE;

static ZBX_HTTPPAGE	page;

static size_t	curl_write_cb(void *ptr, size_t size, size_t nmemb, void *userdata)
{
	size_t	r_size = size * nmemb;

	ZBX_UNUSED(userdata);

	zbx_strncpy_alloc(&page.data, &page.alloc, &page.offset, ptr, r_size);

	return r_size;
}


static void	history_init(void)
{
	static int 	initialized = 0;

	if (0 != initialized)
		return;

	if (0 != curl_global_init(CURL_GLOBAL_ALL))
	{
		zbx_error("Cannot initialize cURL library");
		exit(EXIT_FAILURE);
	}

	initialized = 1;
}

static const char *history_value2str(ZBX_DC_HISTORY *h)
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

static int	history_parse_value(struct zbx_json_parse *jp, unsigned char value_type, zbx_history_record_t *hr)
{
	char	*value = NULL;
	size_t	value_alloc = 0;
	int	ret = FAIL;

	if (SUCCEED != zbx_json_value_by_name_dyn(jp, "sec", &value, &value_alloc))
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

static void	zbx_history_free_handles(void)
{
	ZBX_SENDER	*sender;

	if (NULL == multi)
		return;

	for (sender = senders; ITEM_VALUE_TYPE_MAX != sender->value_type; sender++)
	{
		if (NULL == sender->handle)
			continue;

		curl_multi_remove_handle(multi, sender->handle);
		curl_easy_cleanup(sender->handle);
		sender->handle = NULL;

		zbx_json_free(&sender->json);

		zbx_free(sender->url);
		sender->url = NULL;
	}

	curl_multi_cleanup(multi);
	multi = NULL;
}

static void	zbx_sender_prepare(ZBX_SENDER *sender)
{
	history_init();

	if (NULL == multi)
	{
		if (NULL == (multi = curl_multi_init()))
		{
			zbx_error("Cannot initialize cURL multi session");

			return;
		}
	}

	if (NULL == (sender->handle = curl_easy_init()))
	{
		zbx_error("Cannot initialize cURL session");

		return;
	}

	curl_easy_setopt(sender->handle, CURLOPT_URL, sender->url);
	curl_easy_setopt(sender->handle, CURLOPT_POST, 1);
	curl_easy_setopt(sender->handle, CURLOPT_POSTFIELDS, sender->json.buffer);
	curl_easy_setopt(sender->handle, CURLOPT_WRITEFUNCTION, NULL);

	curl_multi_add_handle(multi, sender->handle);
}

int	zbx_init_history_service(const char *url, const char *types)
{
	char	*str = NULL, *tok = NULL;
	int	ret = SUCCEED;

	if (NULL == url)
		return SUCCEED;

	HISTORY_STORAGE_URL = url;

	str = zbx_strdup(str, types);

	for (tok = strtok(str, ","); NULL != tok; tok = strtok(NULL, ","))
	{
		if (0 == strcmp(ZBX_HISTORY_TYPE_UNUM_STR, tok))
		{
			HISTORY_STORAGE_OPTS |= 1 << ITEM_VALUE_TYPE_UINT64;
		}
		else if (0 == strcmp(ZBX_HISTORY_TYPE_FLOAT_STR, tok))
		{
			HISTORY_STORAGE_OPTS |= 1 << ITEM_VALUE_TYPE_FLOAT;
		}
		else if (0 == strcmp(ZBX_HISTORY_TYPE_CHAR_STR, tok))
		{
			HISTORY_STORAGE_OPTS |= 1 << ITEM_VALUE_TYPE_STR;
		}
		else if (0 == strcmp(ZBX_HISTORY_TYPE_TEXT_STR, tok))
		{
			HISTORY_STORAGE_OPTS |= 1 << ITEM_VALUE_TYPE_TEXT;
		}
		else if (0 == strcmp(ZBX_HISTORY_TYPE_LOG_STR, tok))
		{
			HISTORY_STORAGE_OPTS |= 1 << ITEM_VALUE_TYPE_LOG;
		}
		else
		{
			zbx_error("Invalid history service type; %s", tok);
			ret = FAIL;

			break;
		}
	}

	zbx_free(str);

	return ret;
}

void	zbx_send_data(void)
{
	struct curl_slist	*curl_headers = NULL;
	ZBX_SENDER	*sender;
	int		running, previous, msgnum;
	CURLMsg		*msg;

	curl_headers = curl_slist_append(curl_headers, "Content-Type:application/json");

	for (sender = senders; ITEM_VALUE_TYPE_MAX != sender->value_type; sender++)
	{
		if (NULL != sender->handle)
			curl_easy_setopt(sender->handle, CURLOPT_HTTPHEADER, curl_headers);
	}

	previous = 0;

	do
	{
		int		fds;
		CURLMcode	code;

		code = curl_multi_perform(multi, &running);

		if (CURLM_OK == code)
			code = curl_multi_wait(multi, NULL, 0, ZBX_HISTORY_STORAGE_DOWN, &fds);

		if (CURLM_OK != code)
		{
			zbx_error("Can not wait on curl multi handle");

			break;
		}

		if (previous == running)
			continue;

		while (NULL != (msg = curl_multi_info_read(multi, &msgnum)))
		{
			if (CURLE_OK != msg->data.result)
			{
				zabbix_log(LOG_LEVEL_WARNING, "Error on sending to history service: %s",
						curl_easy_strerror(msg->data.result));

				/* Add back the handle and set the flag to 1 for retrying what failed */
				curl_multi_remove_handle(multi, msg->easy_handle);
				curl_multi_add_handle(multi, msg->easy_handle);

				running = 1;
			}
		}

		previous = running;
	}
	while (running);

	curl_slist_free_all(curl_headers);

	zbx_history_free_handles();
}

void	zbx_history_add_values(zbx_vector_ptr_t *history, unsigned char value_type)
{
	int			i, num = 0;
	ZBX_DC_HISTORY		*h;
	size_t			url_alloc = 0, url_offset = 0;
	ZBX_SENDER		*sender;

	for (sender = senders; ITEM_VALUE_TYPE_MAX != sender->value_type; sender++)
	{
		if (value_type == sender->value_type)
			break;
	}

	if (ITEM_VALUE_TYPE_MAX == sender->value_type)
	{
		zbx_error("Error on preparing for sending to history service: Unsupported value type");

		return;
	}

	zbx_json_initarray(&sender->json, history->values_num * 100);

	for (i = 0; i < history->values_num; i++)
	{
		h = (ZBX_DC_HISTORY *)history->values[i];

		if (value_type != h->value_type)
			continue;

		zbx_json_addobject(&sender->json, NULL);
		zbx_json_adduint64(&sender->json, "itemid", h->itemid);

		if (ITEM_VALUE_TYPE_LOG == h->value_type)
		{
			const zbx_log_value_t	*log;

			log = h->value.log;

			zbx_json_addobject(&sender->json, "value");
			zbx_json_addstring(&sender->json, "value", history_value2str(h), ZBX_JSON_TYPE_STRING);
			zbx_json_adduint64(&sender->json, "timestamp", log->timestamp);
			zbx_json_addstring(&sender->json, "source", ZBX_NULL2EMPTY_STR(log->source), ZBX_JSON_TYPE_STRING);
			zbx_json_adduint64(&sender->json, "severity", log->severity);
			zbx_json_adduint64(&sender->json, "logeventid", log->logeventid);

			zbx_json_close(&sender->json);
		}
		else
			zbx_json_addstring(&sender->json, "value", history_value2str(h), ZBX_JSON_TYPE_STRING);

		zbx_json_adduint64(&sender->json, "sec", h->ts.sec);
		zbx_json_adduint64(&sender->json, "ns", h->ts.ns);
		zbx_json_adduint64(&sender->json, "ttl", h->ttl);

		zbx_json_close(&sender->json);

		num++;
	}
	zbx_json_close(&sender->json);

	if (num > 0)
	{
		zbx_snprintf_alloc(&sender->url, &url_alloc, &url_offset, "%s/", HISTORY_STORAGE_URL);

		zbx_sender_prepare(sender);
	}
	else
		zbx_json_free(&sender->json);
}

void	zbx_history_get_values(zbx_uint64_t itemid, int value_type, int start, int count, int end,
		zbx_vector_history_record_t *values)
{
	size_t		url_alloc = 0, url_offset = 0;
	int		err;
	long		http_code;
	ZBX_SENDER	*sender;

	history_init();

	for (sender = senders; ITEM_VALUE_TYPE_MAX != sender->value_type; sender++)
	{
		if (value_type == sender->value_type)
			break;
	}

	if (NULL == (sender->handle = curl_easy_init()))
	{
		zbx_error("Cannot initialize cURL session");

		return;
	}

	zbx_snprintf_alloc(&sender->url, &url_alloc, &url_offset, "%s/" HISTORY_API_VERSION "/history/%s/" ZBX_FS_UI64,
			HISTORY_STORAGE_URL, sender->type, itemid);

	zbx_snprintf_alloc(&sender->url, &url_alloc, &url_offset, "?end=%d", end);

	if (0 != start)
		zbx_snprintf_alloc(&sender->url, &url_alloc, &url_offset, "&start=%d", start);

	if (0 != count)
		zbx_snprintf_alloc(&sender->url, &url_alloc, &url_offset, "&count=%d", count);

	curl_easy_setopt(sender->handle, CURLOPT_URL, sender->url);
	curl_easy_setopt(sender->handle, CURLOPT_HTTPHEADER, NULL);
	curl_easy_setopt(sender->handle, CURLOPT_POST, 0);
	curl_easy_setopt(sender->handle, CURLOPT_WRITEFUNCTION, curl_write_cb);

	zabbix_log(LOG_LEVEL_WARNING, "Requesting %s", sender->url);

	page.offset = 0;
	if (CURLE_OK != (err = curl_easy_perform(sender->handle)))
		zabbix_log(LOG_LEVEL_ERR, "Failed to get values from history service: %s", curl_easy_strerror(err));

	curl_easy_getinfo(sender->handle, CURLINFO_RESPONSE_CODE, &http_code);

	if (200 == http_code)
	{
		struct zbx_json_parse	jp, jp_values, jp_item;
		zbx_history_record_t	hr;
		const char		*p = NULL;
;
		if (NULL != page.data)
		{
			zbx_json_open(page.data, &jp);
			zbx_json_brackets_open(jp.start, &jp_values);

			while (NULL != (p = zbx_json_next(&jp_values, p)))
			{
				if (SUCCEED != zbx_json_brackets_open(p, &jp_item))
					continue;

				if (SUCCEED != history_parse_value(&jp_item, value_type, &hr))
					continue;

				zbx_vector_history_record_append_ptr(values, &hr);
			}
		}
	}

	curl_easy_cleanup(sender->handle);
	sender->handle = NULL;

	zbx_free(sender->url);
	sender->url = NULL;
}

int	zbx_history_check_type(int value_type)
{
	return HISTORY_STORAGE_OPTS & (1 << value_type);
}

#else

/* Stub functions if LibCURL support is not compiled in zabbix */

int	zbx_init_history_service(const char *url, const char *types)
{
	ZBX_UNUSED(url);
	ZBX_UNUSED(types);

	return SUCCEED;
}

void	zbx_send_data(void)
{
	return;
}

void	zbx_history_add_values(zbx_vector_ptr_t *history, unsigned char value_type)
{
	ZBX_UNUSED(history);
	ZBX_UNUSED(value_type);
}

void	zbx_history_get_values(zbx_uint64_t itemid, int value_type, int start, int count, int end,
		zbx_vector_history_record_t *values)
{
	ZBX_UNUSED(itemid);
	ZBX_UNUSED(value_type);
	ZBX_UNUSED(start);
	ZBX_UNUSED(count);
	ZBX_UNUSED(end);
	ZBX_UNUSED(values);
}

int	zbx_history_check_type(int value_type)
{
	ZBX_UNUSED(value_type);

	return 0;
}



#endif /* HAVE_LIBCURL */
