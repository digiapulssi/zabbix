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
#include "dbcache.h"

#if defined(HAVE_LIBCURL)

/* servicenow incident states */
#define ZBX_SERVICENOW_STATE_QUEUED	"Queued"
#define ZBX_SERVICENOW_STATE_INPROGRESS	"In Progress"
#define ZBX_SERVICENOW_STATE_HOLD	"SLA Hold"
#define ZBX_SERVICENOW_STATE_CLOSED	"Closed"
#define ZBX_SERVICENOW_STATE_CANCELLED	"Cancelled"
#define ZBX_SERVICENOW_STATE_RESOLVED	"Resolved"

#define ZBX_SERVICENOW_REOPEN_STATE	"inprogress"


#define ZBX_SERVICENOW_DB_NONE			0x00
#define ZBX_SERVICENOW_DB_ACKNOWLEDGE_EVENT	0x01
#define ZBX_SERVICENOW_DB_REGISTER_INCIDENT	0x02

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

extern int	CONFIG_REMEDY_SERVICE_TIMEOUT;
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


	zabbix_log(LOG_LEVEL_DEBUG, "In %s() url:%s%s", __function_name, conn->base_url, path);
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

	zbx_strcpy_alloc(&path, &path_alloc, &path_offset, "/u_integration_web_services_incident?"
			"sysparm_display_value=true");

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
			if (SUCCEED == zbx_json_brackets_by_name(&jp_result, "sys_target_sys_id", &jp_sysid))
			{
				char	*buf = NULL;
				size_t	out_alloc = 0;

				if (SUCCEED == zbx_json_value_by_name_dyn(&jp_sysid, "display_value", &buf, &out_alloc))
				{
					/* parse out incident number from display_value 'Incident: <number>' */
					if (0 == strncmp(buf, "Incident: ", 10))
						*incident = zbx_strdup(*incident, buf + 10);
				}

				if (SUCCEED == zbx_json_value_by_name_dyn(&jp_sysid, "link", &buf, &out_alloc))
				{
					char	*ptr;

					if (NULL != (ptr = strrchr(buf, '/')))
						*sysid = zbx_strdup(*sysid, ptr + 1);
				}

				zbx_free(buf);
			}
		}

		if (NULL == *incident)
		{
			*error = zbx_strdup(NULL, "Cannot retrieve created incident number");
			ret = FAIL;
		}

		if (NULL == *sysid)
		{
			*error = zbx_strdup(NULL, "Cannot retrieve created incident internal number");
			ret = FAIL;
		}
	}

	zbx_json_free(&json);
	zbx_free(path);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s incident:%s error:%s", __function_name, zbx_result_string(ret),
			ZBX_NULL2EMPTY_STR(*incident), ZBX_NULL2EMPTY_STR(*error));

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
		const char *work_notes, char **error)
{
	const char	*__function_name = "servicenow_update_incident";
	char		*path = NULL;
	size_t		path_alloc = 0, path_offset = 0;
	int		ret;
	struct zbx_json	json;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() incident:%s state:'%s' work_notes:'%s'", __function_name, incident,
			ZBX_NULL2EMPTY_STR(state), work_notes);

	zbx_strcpy_alloc(&path, &path_alloc, &path_offset, "/u_integration_web_services_incident?"
			"sysparm_display_value=true");

	zbx_json_init(&json, 1024);
	zbx_json_addstring(&json, "u_servicenow_number", incident, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(&json, "u_work_notes", work_notes, ZBX_JSON_TYPE_STRING);
	if (NULL != state)
		zbx_json_addstring(&json, "u_state", state, ZBX_JSON_TYPE_STRING);

	ret = servicenow_invoke(conn, path, json.buffer, error);

	zbx_json_free(&json);
	zbx_free(path);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: servicenow_resolve_problem                                       *
 *                                                                            *
 * Purpose: updates servicenow incident in response to resolved problem in    *
 *          Zabbix                                                            *
 *                                                                            *
 * Parameters: conn     - [IN] the servicenow connection                      *
 *             incident - [IN] the associated incident number                 *
 *             state    - [IN] the new state (optional, can be NULL)          *
 *             message  - [IN] the comment to post (trigger name)             *
 *             action   - [OUT] the performed action                          *
 *             error    - [OUT] the error message                             *
 *                                                                            *
 * Return value: SUCCEED - the incident was updated successfully              *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: Problem resolving is called only by automatic incident update.   *
 *                                                                            *
 ******************************************************************************/
static int	servicenow_resolve_problem(zbx_servicenow_conn_t *conn, const char *incident, const char *state,
		const char *message, int *action, char **error)
{
	if (NULL == incident || NULL == state)
		return SUCCEED;

	if (0 != strcmp(state, ZBX_SERVICENOW_STATE_QUEUED) && 0 != strcmp(state, ZBX_SERVICENOW_STATE_INPROGRESS) &&
			0 != strcmp(state, ZBX_SERVICENOW_STATE_HOLD))
	{
		return SUCCEED;
	}

	if (SUCCEED == servicenow_update_incident(conn, incident, NULL, message, error))
	{
		*action = ZBX_XMEDIA_ACTION_RESOLVE;
		return SUCCEED;
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: servicenow_update_problem                                        *
 *                                                                            *
 * Purpose: updates servicenow incident in response to new problem in Zabbix  *
 *                                                                            *
 * Parameters: conn      - [IN] the servicenow connection                     *
 *             incident  - [IN] the associated incident number (if exists)    *
 *             subject   - [IN] the message subject                           *
 *             message   - [IN] the message body                              *
 *             mediatype - [IN] the corresponding media tpe data              *
 *             mode      - [IN] the update mode (manual/automatic), see       *
 *                                ZBX_XMEDIA_PROCESS_? defines                *
 *             trigger_expression - [IN] the trigger expression               *
 *             trigger_severity   - [IN] the trigger severity                 *
 *             action   - [OUT] the performed action                          *
 *             state    - [OUT] the new state                                 *
 *             sysid    - [OUT] internal servicenow incident identifier       *
 *             error    - [OUT] the error message                             *
 *                                                                            *
 * Return value: SUCCEED - the incident was updated successfully              *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: Problem update is called by automatic and manual updates.        *
 *           Depending on state the associated incident can be updated or     *
 *           a new incident can be created.                                   *
 *                                                                            *
 ******************************************************************************/
static int	servicenow_update_problem(zbx_servicenow_conn_t *conn, char **incident, const char *subject,
		const char *message, const DB_MEDIATYPE *mediatype, int mode, const char *trigger_expression,
		int trigger_severity, int *action, char **state, char **sysid, char **error)
{
	int		ret, priority;
	const char	*new_state = NULL;
	char		*severity_name = NULL;

	if (NULL == *incident || NULL == *state || 0 == strcmp(*state, ZBX_SERVICENOW_STATE_CANCELLED) ||
			0 == strcmp(*state, ZBX_SERVICENOW_STATE_CLOSED))
	{
		/* process incident creation */

		zbx_uint64_t		functionid;
		zbx_vector_uint64_t	hostids;
		DC_HOST			host;

		if (SUCCEED != (ret = get_N_functionid(trigger_expression, 1, &functionid, NULL)))
		{
			*error = zbx_strdup(*error, "Failed to extract function from the trigger expression");
			goto out;
		}

		zbx_vector_uint64_create(&hostids);

		zbx_dc_get_hostids_by_functionids(&functionid, 1, &hostids);

		if (0 == hostids.values_num)
			ret = FAIL;
		else
			DCget_host_by_hostid(&host, hostids.values[0]);

		zbx_vector_uint64_destroy(&hostids);

		if (FAIL == ret)
		{
			*error = zbx_strdup(*error, "Failed to get function host");
			goto out;
		}

		/* map trigger severity */
		switch (trigger_severity)
		{
			case TRIGGER_SEVERITY_WARNING:
				priority = 3;
				break;
			case TRIGGER_SEVERITY_AVERAGE:
			case TRIGGER_SEVERITY_HIGH:
			case TRIGGER_SEVERITY_DISASTER:
				priority = 2;
				break;
			default:
				if (SUCCEED != zbx_get_trigger_severity_name(trigger_severity, &severity_name))
					severity_name = zbx_dsprintf(severity_name, "[%d]", trigger_severity);

				*error = zbx_dsprintf(*error, "Unsupported trigger severity: %s", severity_name);
				zbx_free(severity_name);
				ret = FAIL;
				goto out;
		}

		if (SUCCEED == (ret = servicenow_create_incident(conn, mediatype->smtp_email, subject, message,
				host.host, priority, sysid, incident, error)))
		{
			*action = ZBX_XMEDIA_ACTION_CREATE;

		}
	}
	else
	{
		/* process incident update */

		if (0 == strcmp(*state, ZBX_SERVICENOW_STATE_RESOLVED))
		{
			new_state = ZBX_SERVICENOW_REOPEN_STATE;
			*state = zbx_strdup(*state, ZBX_SERVICENOW_STATE_INPROGRESS);

			*action = ZBX_XMEDIA_ACTION_REOPEN;
		}
		else
			*action = ZBX_XMEDIA_ACTION_UPDATE;

		ret = servicenow_update_incident(conn, *incident, new_state,
				(ZBX_XMEDIA_PROCESS_AUTOMATED == mode ? subject : message), error);
	}
out:
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: servicenow_update_ticket                                         *
 *                                                                            *
 * Purpose: updates ticket properties                                         *
 *                                                                            *
 * Parameters: ticket    - [OUT] the ticket                                   *
 *             incident  - [IN] the associated incident number                *
 *             status    - [IN] the incident status                           *
 *             assignee  - [IN] the assigned user                             *
 *             action    - [IN] the performed action                          *
 *             sysid     - [IN] the incident internal identifier              *
 *             mediatype - [IN] the media type data                           *
 *                                                                            *
 ******************************************************************************/
static void	servicenow_update_ticket(zbx_ticket_t *ticket, const char *incident, const char *status,
		const char *assignee, int action, const char *sysid, const DB_MEDIATYPE *mediatype)
{
	const char	*ptr;
	size_t		url_alloc = 0, url_offset = 0;

	ticket->ticketid = zbx_strdup(NULL, incident);

	if (ZBX_TICKET_ACTION_NONE == (ticket->action = action))
	{
		/* calculated allowed action based on incident status */
		if (NULL != status)
		{
			if (0 == strcmp(status, ZBX_SERVICENOW_STATE_CLOSED) ||
					0 == strcmp(status, ZBX_SERVICENOW_STATE_CANCELLED))
			{
				ticket->action = ZBX_TICKET_ACTION_CREATE;
			}
			else if (0 == strcmp(status, ZBX_SERVICENOW_STATE_RESOLVED))
			{
				ticket->action = ZBX_TICKET_ACTION_REOPEN;
			}
			else
			{
				ticket->action = ZBX_TICKET_ACTION_UPDATE;
			}
		}
	}

	if (NULL != assignee)
		ticket->assignee = zbx_strdup(NULL, assignee);

	if (NULL != status)
		ticket->status = zbx_strdup(NULL, status);

	ticket->clock = zbx_xmedia_get_ticket_creation_time(ticket->ticketid);

	if (NULL != sysid && NULL != (ptr = strstr(mediatype->smtp_server, "://")) &&
			NULL != (ptr = strchr(ptr + 3, '/')))
	{
		zbx_strncpy_alloc(&ticket->url, &url_alloc, &url_offset, mediatype->smtp_server,
				ptr - mediatype->smtp_server);
		zbx_snprintf_alloc(&ticket->url, &url_alloc, &url_offset, "/nav_to.do?uri=/incident.do?sys_id=%s",
				sysid);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: servicenow_process_event                                         *
 *                                                                            *
 * Purpose: creates/updates servicenow incident in response to Zabbix event   *
 *                                                                            *
 * Parameters: eventid   - [IN] the event identifier                          *
 *             userid    - [IN] the target user identifier                    *
 *             subject   - [IN] the message subject                           *
 *             message   - [IN] the message body                              *
 *             mediatype - [IN] the corresponding media tpe data              *
 *             mode      - [IN] the update mode (manual/automatic), see       *
 *                                ZBX_XMEDIA_PROCESS_? defines                *
 *             ticket    - [OUT] the incident data                            *
 *             error    - [OUT] the error message                             *
 *                                                                            *
 * Return value: SUCCEED - the event was processed successfully               *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	servicenow_process_event(zbx_uint64_t eventid, zbx_uint64_t userid, const char *subject,
		const char *message, const DB_MEDIATYPE *mediatype, int mode, zbx_ticket_t *ticket, char **error)
{
	DB_RESULT		result;
	DB_ROW			row;
	int			ret = FAIL, is_registered = 0, event_value = TRIGGER_VALUE_UNKNOWN,
				action = ZBX_XMEDIA_ACTION_NONE, db_action = ZBX_SERVICENOW_DB_NONE;
	zbx_uint64_t		triggerid;
	char			*incident = NULL, *sysid = NULL, *state = NULL, *assignee = NULL;
	zbx_servicenow_conn_t	conn;

	result = DBselect("select e.value,t.priority,t.triggerid,t.expression from events e,triggers t"
				" where e.eventid=" ZBX_FS_UI64
					" and e.source=%d"
					" and e.object=%d"
					" and t.triggerid=e.objectid",
				eventid, EVENT_SOURCE_TRIGGERS,  EVENT_OBJECT_TRIGGER);

	if (NULL == (row = DBfetch(result)))
	{
		*error = zbx_strdup(*error, "Cannot find corresponding event in database");
		DBfree_result(result);
		return FAIL;
	}

	ZBX_STR2UINT64(triggerid, row[2]);
	event_value = atoi(row[0]);

	/* get a ticket directly linked to the event or the latest linked to event generated by the same trigger */
	if (NULL == (incident = zbx_xmedia_get_incident_by_eventid(eventid, mediatype->mediatypeid)))
		incident = zbx_xmedia_get_incident_by_triggerid(triggerid, mediatype->mediatypeid);
	else
		is_registered = 1;

	if (FAIL == servicenow_init(&conn, mediatype->smtp_server, mediatype->username, mediatype->passwd,
			mediatype->smtp_helo, CONFIG_REMEDY_SERVICE_TIMEOUT, error))
	{
		goto out;
	}

	if (NULL != incident)
	{
		if (FAIL == servicenow_get_incident(&conn, incident, &sysid, &state, &assignee, error))
			goto out;
	}

	switch (event_value)
	{
		case TRIGGER_VALUE_OK:
			ret = servicenow_resolve_problem(&conn, incident, state, subject, &action, error);
			break;
		case TRIGGER_VALUE_PROBLEM:
			ret = servicenow_update_problem(&conn, &incident, subject, message, mediatype, mode,
					row[3], atoi(row[1]), &action, &state, &sysid, error);

			/* check if any database updates must be done afterwards */
			if (SUCCEED == ret)
			{
				if (ZBX_XMEDIA_ACTION_CREATE == action)
				{
					/* reset assignee & state for created events */
					assignee = zbx_strdup(assignee, "");
					state = zbx_strdup(state, ZBX_SERVICENOW_STATE_QUEUED);

					db_action |= ZBX_SERVICENOW_DB_REGISTER_INCIDENT;
				}

				if (0 == is_registered)
					db_action |= ZBX_SERVICENOW_DB_REGISTER_INCIDENT;

				if (ZBX_XMEDIA_PROCESS_AUTOMATED == mode)
					db_action |= ZBX_SERVICENOW_DB_ACKNOWLEDGE_EVENT;
			}
			break;
		default:
			*error = zbx_strdup(*error, "Cannot update event in unknown state");
			break;
	}
out:
	if (SUCCEED == ret)
	{
		if (ZBX_SERVICENOW_DB_NONE != db_action)
		{
			DBbegin();

			if (0 != (db_action & ZBX_SERVICENOW_DB_ACKNOWLEDGE_EVENT))
				zbx_xmedia_acknowledge_event(eventid, userid, incident, action);

			if (0 != (db_action & ZBX_SERVICENOW_DB_REGISTER_INCIDENT))
			{
				zbx_xmedia_register_incident(incident, eventid, triggerid, mediatype->mediatypeid,
						action);
			}

			DBcommit();
		}
	}

	if (NULL != ticket)
	{
		if (SUCCEED == ret)
			servicenow_update_ticket(ticket, incident, state, assignee, action, sysid, mediatype);
	}

	zbx_free(assignee);
	zbx_free(state);
	zbx_free(sysid);
	zbx_free(incident);

	servicenow_clear(&conn);

	DBfree_result(result);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_servicenow_process_alert                                     *
 *                                                                            *
 * Purpose: processes alert message to be registered into servicenow          *
 *                                                                            *
 * Parameters: eventid   - [IN] the event identifier                          *
 *             userid    - [IN] the target user identifier                    *
 *             subject   - [IN] the message subject                           *
 *             message   - [IN] the message body                              *
 *             mediatype - [IN] the corresponding media tpe data              *
 *             error     - [OUT] the error messagee                           *
 *                                                                            *
 * Return value: SUCCEED - the alert was processed successfully               *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_servicenow_process_alert(zbx_uint64_t eventid, zbx_uint64_t userid, const char *subject,
		const char *message, const struct DB_MEDIATYPE *mediatype, char **error)
{
	const char	*__function_name = "zbx_servicenow_process_alert";

	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	ret = servicenow_process_event(eventid, userid, subject, message, mediatype, ZBX_XMEDIA_PROCESS_AUTOMATED, NULL,
			error);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_servicenow_query_events                                      *
 *                                                                            *
 * Purpose: retrieves status of servicenow incidents associated to the        *
 *          specified events                                                  *
 *                                                                            *
 * Parameters: mediatype  - [IN] the remedy mediatype data                    *
 *             eventids   - [IN] the events to query                          *
 *             tickets    - [OUT] the incident data                           *
 *             error      - [OUT] the error messagee                          *
 *                                                                            *
 * Comments: The caller must free the error description if it was set and     *
 *           tickets vector contents.                                         *
 *                                                                            *
 ******************************************************************************/
void	zbx_servicenow_query_events(const DB_MEDIATYPE *mediatype, zbx_vector_uint64_t *eventids,
		zbx_vector_ptr_t *tickets)
{
	const char		*__function_name = "zbx_servicenow_query_events";
	int			i, ret = FAIL;
	zbx_servicenow_conn_t	conn;
	char			*error = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	ret = servicenow_init(&conn, mediatype->smtp_server, mediatype->username, mediatype->passwd,
			mediatype->smtp_helo, CONFIG_REMEDY_SERVICE_TIMEOUT, &error);

	for (i = 0; i < eventids->values_num; i++)
	{
		zbx_ticket_t	*ticket;
		char		*externalid = NULL, *sysid = NULL, *state = NULL, *assignee = NULL;

		ticket = zbx_malloc(NULL, sizeof(zbx_ticket_t));
		memset(ticket, 0, sizeof(zbx_ticket_t));
		ticket->eventid = eventids->values[i];

		if (FAIL != ret)
		{
			if (SUCCEED == zbx_xmedia_get_last_ticketid(ticket->eventid, mediatype->mediatypeid,
							&externalid) &&
					SUCCEED == servicenow_get_incident(&conn, externalid, &sysid, &state,
							&assignee, &ticket->error))
			{
				servicenow_update_ticket(ticket, externalid, state, assignee, ZBX_XMEDIA_ACTION_NONE,
						sysid, mediatype);
			}
		}
		else
			ticket->error = zbx_strdup(NULL, error);

		zbx_vector_ptr_append(tickets, ticket);

		zbx_free(externalid);
		zbx_free(state);
		zbx_free(sysid);
		zbx_free(assignee);
	}

	servicenow_clear(&conn);

	zbx_free(error);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_servicenow_acknowledge_events                                *
 *                                                                            *
 * Purpose: acknowledges events in servicenow with the specified message      *
 *          subjects and contents                                             *
 *                                                                            *
 * Parameters: mediatype     - [IN] the remedy mediatype data                 *
 *             userid        - [IN] the user acknowledging events             *
 *             acknowledges  - [IN] the event acknowledgment data             *
 *             tickets       - [OUT] the incident data                        *
 *             error         - [OUT] the error description                    *
 *                                                                            *
 * Return value: SUCCEED - the events were acknowledged successfully          *
 *               FAIL - otherwise                                             *
 *                                                                            *
 * Comments: The caller must free the error description if it was set and     *
 *           tickets vector contents.                                         *
 *                                                                            *
 ******************************************************************************/
void	zbx_servicenow_acknowledge_events(const DB_MEDIATYPE *mediatype, zbx_uint64_t userid,
		zbx_vector_ptr_t *acknowledges, zbx_vector_ptr_t *tickets)
{
	const char	*__function_name = "zbx_remedy_acknowledge_events";

	int		i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	for (i = 0; i < acknowledges->values_num; i++)
	{
		zbx_acknowledge_t	*ack = acknowledges->values[i];
		zbx_ticket_t		*ticket;

		ticket = zbx_malloc(NULL, sizeof(zbx_ticket_t));
		memset(ticket, 0, sizeof(zbx_ticket_t));
		ticket->eventid = ack->eventid;

		servicenow_process_event(ack->eventid, userid, ack->subject, ack->message, mediatype,
				ZBX_XMEDIA_PROCESS_MANUAL, ticket, &ticket->error);

		zbx_vector_ptr_append(tickets, ticket);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

#else

int	zbx_servicenow_process_alert(zbx_uint64_t eventid, zbx_uint64_t userid, const char *subject,
		const char *message, const struct DB_MEDIATYPE *mediatype, char **error)
{
	ZBX_UNUSED(eventid);
	ZBX_UNUSED(userid);
	ZBX_UNUSED(subject);
	ZBX_UNUSED(message);
	ZBX_UNUSED(mediatype);
	ZBX_UNUSED(error);

	*error = zbx_strdup(*error, "Zabbix server is built without ServiceNow ticket support");
	return FAIL;
}

void	zbx_servicenow_query_events(const DB_MEDIATYPE *mediatype, zbx_vector_uint64_t *eventids,
		zbx_vector_ptr_t *tickets)
{
	int	i;

	ZBX_UNUSED(mediatype);

	for (i = 0; i < eventids->values_num; i++)
	{
		zbx_ticket_t	*ticket;

		ticket = zbx_malloc(NULL, sizeof(zbx_ticket_t));
		memset(ticket, 0, sizeof(zbx_ticket_t));
		ticket->eventid = eventids->values[i];

		ticket->error = zbx_strdup(NULL, "Zabbix server is built without ServiceNow ticket support");
		zbx_vector_ptr_append(tickets, ticket);
	}
}

void	zbx_servicenow_acknowledge_events(const DB_MEDIATYPE *mediatype, zbx_uint64_t userid,
		zbx_vector_ptr_t *acknowledges, zbx_vector_ptr_t *tickets)
{
	int	i;

	ZBX_UNUSED(mediatype);
	ZBX_UNUSED(userid);

	for (i = 0; i < acknowledges->values_num; i++)
	{
		zbx_acknowledge_t	*ack = acknowledges->values[i];
		zbx_ticket_t		*ticket;


		ticket = zbx_malloc(NULL, sizeof(zbx_ticket_t));
		memset(ticket, 0, sizeof(zbx_ticket_t));
		ticket->eventid = ack->eventid;

		ticket->error = zbx_strdup(NULL, "Zabbix server is built without ServiceNow ticket support");
		zbx_vector_ptr_append(tickets, ticket);
	}
}



#endif
