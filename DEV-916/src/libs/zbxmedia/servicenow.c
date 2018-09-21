/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

#include "db.h"
#include "log.h"
#include "zbxmedia.h"
#include "zbxserver.h"
#include "base64.h"

#if defined(HAVE_LIBCURL)
typedef struct
{
	CURL			*handle;
	struct curl_slist	*headers;
	char			*data;
	char			*base_url;
	size_t			data_alloc;
	size_t			data_offset;
}
zbx_servicenow_conn_t;

/*
 * cURL callbacks
 */

static size_t	curl_write_cb(void *ptr, size_t size, size_t nmemb, void *userdata)
{
	size_t			r_size = size * nmemb;
	zbx_servicenow_conn_t	*conn = (zbx_servicenow_conn_t *)userdata;

	zbx_strncpy_alloc(&conn->data, &conn->data_alloc, &conn->data_offset, (const char *)ptr, r_size);

	return r_size;
}

static size_t	curl_header_cb(void *ptr, size_t size, size_t nmemb, void *userdata)
{
	ZBX_UNUSED(ptr);
	ZBX_UNUSED(userdata);

	return size * nmemb;
}

/******************************************************************************
 *                                                                            *
 * Function: servicenow_clear                                                 *
 *                                                                            *
 * Purpose: release resource allocated by servicenow connection               *
 *                                                                            *
 ******************************************************************************/
static void	servicenow_clear(zbx_servicenow_conn_t *conn)
{
	if (NULL != conn->headers)
		curl_slist_free_all(conn->headers);

	if (NULL != conn->handle)
		curl_easy_cleanup(conn->handle);

	zbx_free(conn->data);
	zbx_free(conn->base_url);
}

/******************************************************************************
 *                                                                            *
 * Function: servicenow_init                                                  *
 *                                                                            *
 * Purpose: initialize servicenow connection                                  *
 *                                                                            *
 * Parameters: conn     - [IN] the servicenow connection                      *
 *             url      - [IN] the servicenow base url                        *
 *             username - [IN] the servicenow integration id                  *
 *             password - [IN] the servicenow integration password            *
 *             proxy    - [IN] the proxy url (optional, can be NULL)          *
 *             timeout  - [IN] the connection timeout                         *
 *             error    - [OUT] the error message                             *
 *                                                                            *
 * Return value: SUCCEED - the connection was initialized successfully        *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: Successfully initialized connection must be released with        *
 *           servicenow_clear() function                                      *
 *                                                                            *
 ******************************************************************************/
static int	servicenow_init(zbx_servicenow_conn_t *conn, const char *url, const char *username,
		const char *password, const char *proxy, int timeout, char **error)
{
	const char	*__function_name = "servicenow_init";

	char		*auth = NULL, *auth_base64 = NULL;
	size_t		auth_alloc = 0, auth_offset = 0;
	CURLcode	err;
	CURLoption	opt;
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	memset(conn, 0, sizeof(zbx_servicenow_conn_t));

	if (NULL == (conn->handle = curl_easy_init()))
	{
		*error = zbx_strdup(NULL, "Cannot initialize cURL library");
		goto out;
	}

	conn->headers = curl_slist_append(conn->headers, "Content-Type: application/json");
	conn->headers = curl_slist_append(conn->headers, "Accept: application/json");

	zbx_snprintf_alloc(&auth, &auth_alloc, &auth_offset, "%s:%s", username, password);
	str_base64_encode_dyn(auth, &auth_base64, auth_offset);
	auth_offset = 0;
	zbx_snprintf_alloc(&auth, &auth_alloc, &auth_offset, "Authorization: Basic %s", auth_base64);
	conn->headers = curl_slist_append(conn->headers, auth);
	zbx_free(auth_base64);
	zbx_free(auth);

	if (CURLE_OK != (err = curl_easy_setopt(conn->handle, opt = CURLOPT_PRIVATE, conn)) ||
			CURLE_OK != (err = curl_easy_setopt(conn->handle, opt = CURLOPT_WRITEDATA, conn)) ||
			CURLE_OK != (err = curl_easy_setopt(conn->handle, opt = CURLOPT_HTTPHEADER, conn->headers)) ||
			CURLE_OK != (err = curl_easy_setopt(conn->handle, opt = CURLOPT_COOKIEFILE, "")) ||
			CURLE_OK != (err = curl_easy_setopt(conn->handle, opt = CURLOPT_FOLLOWLOCATION, 1L)) ||
			CURLE_OK != (err = curl_easy_setopt(conn->handle, opt = CURLOPT_WRITEFUNCTION, curl_write_cb)) ||
			CURLE_OK != (err = curl_easy_setopt(conn->handle, opt = CURLOPT_HEADERFUNCTION, curl_header_cb)) ||
			CURLE_OK != (err = curl_easy_setopt(conn->handle, opt = CURLOPT_SSL_VERIFYPEER, 0L)) ||
			CURLE_OK != (err = curl_easy_setopt(conn->handle, opt = CURLOPT_TIMEOUT, (long)timeout)) ||
			CURLE_OK != (err = curl_easy_setopt(conn->handle, opt = CURLOPT_SSL_VERIFYHOST, 0L)))
	{
		*error = zbx_dsprintf(*error, "Cannot set cURL option %d: %s.", (int)opt, curl_easy_strerror(err));
		goto out;
	}

	if (NULL != proxy && CURLE_OK != (err = curl_easy_setopt(conn->handle, opt = CURLOPT_PROXY, proxy)))
	{
		*error = zbx_dsprintf(NULL, "Cannot set cURL option %d: %s.", (int)opt, curl_easy_strerror(err));
		goto out;
	}

	conn->base_url = zbx_strdup(NULL, url);

	ret = SUCCEED;
out:
	if (FAIL == ret)
		servicenow_clear(conn);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s %s", __function_name, zbx_result_string(ret),
			ZBX_NULL2EMPTY_STR(*error));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: servicenow_invoke                                                *
 *                                                                            *
 * Purpose: invokes servicenow API call                                       *
 *                                                                            *
 * Parameters: conn  - [IN] the servicenow connection                         *
 *             path  - [IN] the path to api endpoint + query parameters       *
 *             data  - [IN] the data to post (optional, can be NULL)          *
 *             error - [OUT] the error message                                *
 *                                                                            *
 * Return value: SUCCEED - the API call was invoked successfully              *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: If data is not null this function will issue POST request to the *
 *           specified connection api endpoint. Otherwise GET request will be *
 *           made.                                                            *
 *                                                                            *
 ******************************************************************************/
static int	servicenow_invoke(zbx_servicenow_conn_t *conn, const char *path, const char *data, char **error)
{
	const char		*__function_name = "servicenow_invoke";

	CURLcode		err;
	CURLoption		opt;
	char			*url = NULL;
	size_t			url_alloc = 0, url_offset = 0;
	int			ret = FAIL;
	struct zbx_json_parse	jp, jp_error;


	zabbix_log(LOG_LEVEL_DEBUG, "In %s() url:%s/%s", __function_name, conn->base_url, path);
	zabbix_log(LOG_LEVEL_TRACE, "post:%s", ZBX_NULL2EMPTY_STR(data));

	if (NULL != data)
	{
		if (CURLE_OK != (err = curl_easy_setopt(conn->handle, opt = CURLOPT_POST, 1L)) ||
				CURLE_OK != (err = curl_easy_setopt(conn->handle, opt = CURLOPT_POSTFIELDS, data)))
		{
			*error = zbx_dsprintf(NULL, "Cannot set cURL option %d: %s.", (int)opt,
					curl_easy_strerror(err));
			goto out;
		}
	}
	else
	{
		if (CURLE_OK != (err = curl_easy_setopt(conn->handle, opt = CURLOPT_HTTPGET, 1L)))
		{
			*error = zbx_dsprintf(NULL, "Cannot set cURL option %d: %s.", (int)opt,
					curl_easy_strerror(err));
			goto out;
		}
	}

	conn->data_offset = 0;

	zbx_snprintf_alloc(&url, &url_alloc, &url_offset, "%s/%s", conn->base_url, path);

	if (CURLE_OK != (err = curl_easy_setopt(conn->handle, opt = CURLOPT_URL, url)))
	{
		*error = zbx_dsprintf(NULL, "Cannot set cURL option %d: %s.", (int)opt, curl_easy_strerror(err));
		goto out;
	}

	if (CURLE_OK != (err = curl_easy_perform(conn->handle)))
	{
		*error = zbx_strdup(NULL, curl_easy_strerror(err));
		goto out;
	}

	if (0 == conn->data_offset)
	{
		*error = zbx_dsprintf(NULL, "Received empty response");
		goto out;
	}

	/* check for error response */

	zabbix_log(LOG_LEVEL_TRACE, "received:%s", conn->data);

	if (FAIL == zbx_json_open(conn->data, &jp))
	{
		*error = zbx_strdup(NULL, "Received data is not in JSON format");
		goto out;
	}

	if (SUCCEED == zbx_json_brackets_by_name(&jp, "error", &jp_error))
	{
		size_t	error_alloc = 0, error_offset = 0, buf_alloc = 0;
		char	*buf = NULL;

		if (SUCCEED == zbx_json_value_by_name_dyn(&jp_error, "message", &buf, &buf_alloc))
		{
			zbx_strcpy_alloc(error, &error_alloc, &error_offset, buf);
			zbx_strcpy_alloc(error, &error_alloc, &error_offset, ": ");
		}

		if (SUCCEED == zbx_json_value_by_name_dyn(&jp_error, "detail", &buf, &buf_alloc))
			zbx_strcpy_alloc(error, &error_alloc, &error_offset, buf);

		if (0 == error_offset)
		{
			zbx_strcpy_alloc(error, &error_alloc, &error_offset,
					"Unknown error returned by ServiceNow");
		}

		zbx_free(buf);

		goto out;
	}

	ret = SUCCEED;
out:
	zbx_free(url);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s %s", __function_name, zbx_result_string(ret),
			ZBX_NULL2EMPTY_STR(*error));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: servicenow_get_incident                                          *
 *                                                                            *
 * Purpose: get servicenow incident data                                      *
 *                                                                            *
 * Parameters: conn     - [IN] the servicenow connection                      *
 *             incident - [IN] the incident number                            *
 *             sysid    - [OUT] internal incident service identifier          *
 *             state    - [OUT] the incident state                            *
 *             assignee - [OUT] the incident assignee                         *
 *             error    - [OUT] the error message                             *
 *                                                                            *
 * Return value: SUCCEED - the incident data was retrieved successfully       *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: In the case of successful call the caller must free the output   *
 *           parameters.                                                      *
 *                                                                            *
 ******************************************************************************/
static int	servicenow_get_incident(zbx_servicenow_conn_t *conn, const char *incident, char **sysid, char **state,
		char **assignee, char **error)
{
	const char	*__function_name = "servicenow_get_incident";
	char		*path = NULL;
	size_t		path_alloc = 0, path_offset = 0;
	int		ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() incident:%s", __function_name, incident);

	zbx_snprintf_alloc(&path, &path_alloc, &path_offset, "/incident?sysparm_display_value=true&"
			"sysparm_exclude_reference_link=true&sysparm_query=number=%s", incident);

	if (SUCCEED == (ret = servicenow_invoke(conn, path, NULL, error)))
	{
		struct zbx_json_parse	jp, jp_result;

		zbx_json_open(conn->data, &jp);

		if (SUCCEED == zbx_json_path_open(&jp, "$.result[0]", &jp_result))
		{
			size_t	out_alloc = 0;

			zbx_json_value_by_name_dyn(&jp_result, "sys_id", sysid, &out_alloc);
			out_alloc = 0;
			zbx_json_value_by_name_dyn(&jp_result, "state", state, &out_alloc);
			out_alloc = 0;
			zbx_json_value_by_name_dyn(&jp_result, "assigned_to", assignee, &out_alloc);
		}
	}

	zbx_free(path);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s state:'%s' assignee:'%s'", __function_name, zbx_result_string(ret),
			ZBX_NULL2EMPTY_STR(*state), ZBX_NULL2EMPTY_STR(*assignee));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: servicenow_create_incident                                       *
 *                                                                            *
 * Purpose: create servicenow incident                                        *
 *                                                                            *
 * Parameters: conn     - [IN] the servicenow connection                      *
 *             ...      - [IN] incident properties                            *
 *             sysid    - [OUT] internal incident service identifier          *
 *             incident - [OUT] the incident number                           *
 *             error    - [OUT] the error message                             *
 *                                                                            *
 * Return value: SUCCEED - the incident was created successfully              *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: In the case of successful call the caller must free the output   *
 *           parameters.                                                      *
 *                                                                            *
 ******************************************************************************/
static int	servicenow_create_incident(zbx_servicenow_conn_t *conn, const char *assignment_group,
		const char *short_description, const char *work_notes, const char *configuration_item,
		int priority, char **sysid, char **incident, char **error)
{
	const char	*__function_name = "servicenow_create_incident";
	char		*path = NULL;
	size_t		path_alloc = 0, path_offset = 0;
	int		ret;
	struct zbx_json	json;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() short_description:'%s' work_notes:'%s' configuration_item:'%s'"
			" priority:%d", __function_name, short_description, work_notes, configuration_item, priority);

	zbx_snprintf_alloc(&path, &path_alloc, &path_offset, "/u_integration_web_services_incident?"
			"sysparm_display_value=true", incident);

	zbx_json_init(&json, 1024);
	zbx_json_addstring(&json, "u_assignment_group", assignment_group, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(&json, "u_external_tool", "Zabbix", ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(&json, "u_short_description", short_description, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(&json, "u_work_notes", work_notes, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(&json, "u_configuration_item", configuration_item, ZBX_JSON_TYPE_STRING);
	zbx_json_adduint64(&json, "u_priority", (zbx_uint64_t)priority);

	if (SUCCEED == (ret = servicenow_invoke(conn, path, json.buffer, error)))
	{
		struct zbx_json_parse	jp, jp_result, jp_sysid;

		zbx_json_open(conn->data, &jp);

		if (SUCCEED == zbx_json_brackets_by_name(&jp, "result", &jp_result))
		{
			size_t	out_alloc = 0;

			if (SUCCEED == zbx_json_brackets_by_name(&jp_result, "sys_target_sys_id", &jp_sysid))
			{
				char	*buf = NULL;

				if (SUCCEED == zbx_json_value_by_name_dyn(&jp_sysid, "display_value", &buf, &out_alloc))
				{
					/* parse out incident number from display_value 'Incident: <number>' */
					if (0 == strncmp(buf, "Incident: ", 10))
						*incident = zbx_strdup(NULL, buf + 10);

					zbx_free(buf);
				}
				out_alloc = 0;
			}

			zbx_json_value_by_name_dyn(&jp_result, "sys_id", sysid, &out_alloc);
		}
	}

	zbx_json_free(&json);
	zbx_free(path);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s incident:%s", __function_name, zbx_result_string(ret),
			ZBX_NULL2EMPTY_STR(*incident));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: servicenow_update_incident                                       *
 *                                                                            *
 * Purpose: update servicenow incident                                        *
 *                                                                            *
 * Parameters: conn       - [IN] the servicenow connection                    *
 *             incident   - [IN] the incident number                          *
 *             state      - [IN] the new state (optional, can be NULL)        *
 *             work_notes - [IN] the work notes (comment)                     *
 *             sysid      - [OUT] internal incident service identifier        *
 *             error      - [OUT] the error message                           *
 *                                                                            *
 * Return value: SUCCEED - the incident was updated successfully              *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: In the case of successful call the caller must free the output   *
 *           parameters.                                                      *
 *                                                                            *
 ******************************************************************************/
static int	servicenow_update_incident(zbx_servicenow_conn_t *conn, const char *incident, const char *state,
		const char *work_notes, char **sysid, char **error)
{
	const char	*__function_name = "servicenow_update_incident";
	char		*path = NULL;
	size_t		path_alloc = 0, path_offset = 0;
	int		ret;
	struct zbx_json	json;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() incident:%s state:'%s' work_notes:'%s'", __function_name, incident,
			ZBX_NULL2EMPTY_STR(state), work_notes);

	zbx_snprintf_alloc(&path, &path_alloc, &path_offset, "/u_integration_web_services_incident?"
			"sysparm_display_value=true", incident);

	zbx_json_init(&json, 1024);
	zbx_json_addstring(&json, "u_servicenow_number", incident, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(&json, "u_work_notes", work_notes, ZBX_JSON_TYPE_STRING);
	if (NULL != state)
		zbx_json_addstring(&json, "u_state", state, ZBX_JSON_TYPE_STRING);

	if (SUCCEED == (ret = servicenow_invoke(conn, path, json.buffer, error)))
	{
		struct zbx_json_parse	jp, jp_result;

		zbx_json_open(conn->data, &jp);

		if (SUCCEED == zbx_json_brackets_by_name(&jp, "result", &jp_result))
		{
			size_t	out_alloc = 0;

			zbx_json_value_by_name_dyn(&jp_result, "sys_id", sysid, &out_alloc);
		}
	}

	zbx_json_free(&json);
	zbx_free(path);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

#else


#endif
