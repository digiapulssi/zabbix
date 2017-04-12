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

#ifdef HAVE_LDAP

#include "log.h"
#include "db.h"
#include "zbxself.h"
#include "comms.h"
#include "zbxjson.h"
#include "./ldapsyncer.h"
#include <ldap.h>

#define ZBX_LDAPSYNCER_PERIOD		60	/* TODO: make period configurable in frontend */

#define ZBX_LDAP_USE_TLS_UNENCRYPTED	0
#define ZBX_LDAP_USE_TLS_STARTTLS	1
#define ZBX_LDAP_USE_TLS_LDAPS		2

extern unsigned char	process_type, program_type;
extern int		server_num, process_num;
extern int		CONFIG_TIMEOUT;

typedef struct
{
	char	*host;		/* LDAP server hostname or IP address */
	char	*bind_dn;	/* bind user */
	char	*bind_pw;	/* bind passowrd */
	int	port;		/* port number, often 389 */
	int	use_tls;	/* 0 - unencrypted, 1 - reserved for StartTLS, 2 - LDAPS */
	int	net_timeout;	/* network timeout, seconds */
	int	proc_timeout;	/* processing (API) timeout, seconds */
}
zbx_ldap_server_t;

/******************************************************************************
 *                                                                            *
 * Function: zbx_ldap_connect                                                 *
 *                                                                            *
 * Purpose: connect to LDAP server                                            *
 *                                                                            *
 * Return value: SUCCEED or FAIL                                              *
 *                                                                            *
 ******************************************************************************/
static int	zbx_ldap_connect(LDAP **ld, zbx_ldap_server_t *ldap_server, char **error)
{
	int		res, opt_protocol_version = LDAP_VERSION3, opt_deref = LDAP_DEREF_NEVER, ret = FAIL;
	struct timeval	tv;
	char		*uri = NULL;

	if (ZBX_LDAP_USE_TLS_UNENCRYPTED == ldap_server->use_tls)
	{
		uri = zbx_dsprintf(uri, "ldap://%s:%d", ldap_server->host, ldap_server->port);
	}
	else if (ZBX_LDAP_USE_TLS_LDAPS == ldap_server->use_tls)
	{
		uri = zbx_dsprintf(uri, "ldaps://%s:%d", ldap_server->host, ldap_server->port);
	}
	else if (ZBX_LDAP_USE_TLS_STARTTLS == ldap_server->use_tls)
	{
		*error = zbx_strdup(*error, "connection to LDAP with STARTTLS not implemented");
		goto out;
	}

	/* initialize LDAP data struture (without opening a connection) */

	if (LDAP_SUCCESS != (res = ldap_initialize(ld, uri)))
	{
		*error = zbx_dsprintf(*error, "ldap_initialize() failed for \"%s\": %d %s",
				uri, res, ldap_err2string(res));
		goto out;
	}

	/* TODO: (optionally) check LDAP_OPT_API_FEATURE_INFO, LDAP_OPT_API_INFO to detect library version mismatch */

	/* set LDAP protocol v3 */

	if (LDAP_OPT_SUCCESS != (res = ldap_set_option(*ld, LDAP_OPT_PROTOCOL_VERSION, &opt_protocol_version)))
	{
		*error = zbx_dsprintf(*error, "ldap_set_option(,LDAP_OPT_PROTOCOL_VERSION,) failed: %d %s",
				res, ldap_err2string(res));
		goto out;
	}

	/* do not use asynchronous connect */

	if (LDAP_OPT_SUCCESS != (res = ldap_set_option(*ld, LDAP_OPT_CONNECT_ASYNC, LDAP_OPT_OFF)))
	{
		*error = zbx_dsprintf(*error, "ldap_set_option(,LDAP_OPT_CONNECT_ASYNC,) failed: %d %s",
				res, ldap_err2string(res));
		goto out;
	}

	/* TODO: (optionally) set LDAP_OPT_DEBUG_LEVEL for using with DebugLevel=5 */

	/* do not dereference aliases (default) */

	if (LDAP_OPT_SUCCESS != (res = ldap_set_option(*ld, LDAP_OPT_DEREF, &opt_deref)))
	{
		*error = zbx_dsprintf(*error, "ldap_set_option(,LDAP_OPT_DEREF,) failed: %d %s",
				res, ldap_err2string(res));
		goto out;
	}

	/* set connection timeout */

	tv.tv_sec = ldap_server->net_timeout;
	tv.tv_usec = 0;

	if (LDAP_OPT_SUCCESS != (res = ldap_set_option(*ld, LDAP_OPT_NETWORK_TIMEOUT, &tv)))
	{
		*error = zbx_dsprintf(*error, "ldap_set_option(,LDAP_OPT_NETWORK_TIMEOUT,) failed: %d %s",
				res, ldap_err2string(res));
		goto out;
	}

	/* do not chase referrals */

	if (LDAP_OPT_SUCCESS != (res = ldap_set_option(*ld, LDAP_OPT_REFERRALS, LDAP_OPT_OFF)))
	{
		*error = zbx_dsprintf(*error, "ldap_set_option(,LDAP_OPT_REFERRALS,) failed: %d %s",
				res, ldap_err2string(res));
		goto out;
	}

	/* set timeout for synchronous API call */

	tv.tv_sec = ldap_server->proc_timeout;

	if (LDAP_OPT_SUCCESS != (res = ldap_set_option(*ld, LDAP_OPT_TIMEOUT, &tv)))
	{
		*error = zbx_dsprintf(*error, "ldap_set_option(,LDAP_OPT_TIMEOUT,) failed: %d %s",
				res, ldap_err2string(res));
		goto out;
	}

	/* simple bind with user/password */

	if (LDAP_SUCCESS != (res= ldap_simple_bind_s(*ld, ldap_server->bind_dn, ldap_server->bind_pw)))
	{
		*error = zbx_dsprintf(*error, "ldap_simple_bind_s() failed: %d %s", res, ldap_err2string(res));
		goto out;
	}

	ret = SUCCEED;
out:
	zbx_free(uri);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_ldap_search                                                  *
 *                                                                            *
 * Purpose: search in LDAP database                                           *
 *                                                                            *
 * Return value: SUCCEED or FAIL                                              *
 *                                                                            *
 ******************************************************************************/
static int	zbx_ldap_search(LDAP *ld, char *base_dn, int scope, char *filter, char **attributes_list, int timeout,
		LDAPMessage **result, char **error)
{
	int		res;
	struct timeval	tv;

	tv.tv_sec = timeout;
	tv.tv_usec = 0;

	/* TODO: add handling of LDAP_SIZELIMIT_EXCEEDED (too many entries found to return them in one response) */

	if (LDAP_SUCCESS != (res = ldap_search_ext_s(ld, base_dn, scope, filter, attributes_list, 0, NULL, NULL, &tv,
			-1, result)))
	{
		*error = zbx_dsprintf(*error, "ldap_search_ext_s() failed: %d %s", res, ldap_err2string(res));
		return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_ldap_free                                                    *
 *                                                                            *
 * Purpose: close connection, release LDAP resources                          *
 *                                                                            *
 ******************************************************************************/
static void	zbx_ldap_free(LDAP **ld)
{
	int	res;

	if (LDAP_SUCCESS == (res = ldap_unbind_ext_s(*ld, NULL, NULL)))
		*ld = NULL;
	else
		zabbix_log(LOG_LEVEL_WARNING, "ldap_unbind_ext_s() failed: %d %s", res, ldap_err2string(res));
}

/******************************************************************************
 *                                                                            *
 * Function: synchronize_from_ldap                                            *
 *                                                                            *
 * Purpose: synchronize users and groups from LDAP database                   *
 *                                                                            *
 * Return value: SUCCEED or FAIL                                              *
 *                                                                            *
 ******************************************************************************/
static int      zbx_synchronize_from_ldap(int *user_num)
{
	const char		*__function_name = "zbx_synchronize_from_ldap";
	LDAP			*ld = NULL;
	LDAPMessage		*result;
	char			*error = NULL;
	int			res, ret = FAIL;
	int			timeout = CONFIG_TIMEOUT;
	zbx_ldap_server_t	ldap_server = { "127.0.0.1", "bind user name", "bind password", 389, 0, 10, 10 };

	/* TODO: replace hardcoded host, port, bind user, bind password with values from database. */
	/* TODO: consider supporting a list of URIs - ldap_initialize() in zbx_ldap_connect() can take a list of them */
	/* TODO: consider supporting 'ldaps' (LDAP over TLS) protocol. */

	char		*base_dn = "OU=people,DC=example,DC=com";
	int		scope = LDAP_SCOPE_SUBTREE;
	char		*filter = "(ou=IT operations)";
	char		**attributes_list = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (SUCCEED != (res = zbx_ldap_connect(&ld, &ldap_server, &error)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "%s() cannot connect to LDAP server: %s", __function_name, error);
		goto out;
	}

	/* TODO: synchronization */

	/* TODO: this is just learning to search */

	if (SUCCEED != (res = zbx_ldap_search(ld, base_dn, scope, filter, attributes_list, timeout, &result, &error)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "%s() cannot search in LDAP server: %s", __function_name, error);
		goto out;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "%s(): %d entries found", __function_name, ldap_count_messages(ld, result));

	ret = SUCCEED;
out:
	zbx_free(error);

	if (NULL != ld)
		zbx_ldap_free(&ld);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s error:'%s'", __function_name, zbx_result_string(ret),
			ZBX_NULL2EMPTY_STR(error));
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_ldap_server_test                                             *
 *                                                                            *
 * Purpose: connect to LDAP server and test bind operation, then disconnect   *
 *                                                                            *
 * Parameters: ldap_server - [IN] LDAP server data                            *
 *             error       - [OUT] error message (to be freed by caller)      *
 *                                                                            *
 * Return value: SUCCEED or FAIL                                              *
 *                                                                            *
 ******************************************************************************/
static int	zbx_ldap_server_test(zbx_ldap_server_t *ldap_server, char **error)
{
	LDAP	*ld = NULL;
	int	ret = FAIL;

	if (SUCCEED == zbx_ldap_connect(&ld, ldap_server, error))
		ret = SUCCEED;

	if (NULL != ld)
		zbx_ldap_free(&ld);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: ldap_json_deserialize_server                                     *
 *                                                                            *
 * Purpose: deserialize LDAP server data from JSON                            *
 *                                                                            *
 * Parameters: jp          - [IN] the json data                               *
 *             ldap_server - [OUT] result                                     *
 *             error       - [OUT] error message (to be freed by caller)      *
 *                                                                            *
 * Return value: SUCCEED or FAIL                                              *
 *                                                                            *
 * Comments:                                                                  *
 *   Example of LDAP server data:                                             *
 *      [{                                                                    *
 *          "host":"192.168.123.123",                                         *
 *          "port":389,                                                       *
 *          "use_tls":0,                                                      *
 *          "bind_dn":"cn=ldap_search,dc=example,dc=com",                     *
 *          "bind_pw":"********",                                             *
 *          "net_timeout":10,                                                 *
 *          "proc_timeout":10}'                                               *
 *      }]                                                                    *
 *                                                                            *
 *   Processing stops on the first error, some (even invalid) values may be   *
 *   written into 'ldap_server'. It is a responsibility of the caller to      *
 *   free 'ldap_server' resources even in case of error.                      *
 *                                                                            *
 ******************************************************************************/
static int	ldap_json_deserialize_server(struct zbx_json_parse *jp, zbx_ldap_server_t *ldap_server, char **error)
{
#define xstr(s)	str(s)
#define str(s)	#s

	size_t	host_alloc = 0, bind_dn_alloc = 0, bind_pw_alloc = 0;
	char	value[MAX_STRING_LEN];

	/* "host" */

	if (SUCCEED != zbx_json_value_by_name_dyn(jp, ZBX_PROTO_TAG_HOST, &ldap_server->host, &host_alloc))
	{
		*error = zbx_strdup(*error, "no \"" ZBX_PROTO_TAG_HOST "\" tag");
		return FAIL;
	}

	/* "port" */

	if (SUCCEED != zbx_json_value_by_name(jp, ZBX_PROTO_TAG_PORT, value, sizeof(value)))
	{
		*error = zbx_strdup(*error, "no \"" ZBX_PROTO_TAG_PORT "\" tag");
		return FAIL;
	}

	if ('\0' == *value)
	{
		*error = zbx_strdup(*error, "\"" ZBX_PROTO_TAG_PORT "\" tag value is empty");
		return FAIL;
	}

	if (0 > (ldap_server->port = atoi(value)) || 65535 < ldap_server->port)
	{
		*error = zbx_strdup(*error, "invalid \"" ZBX_PROTO_TAG_PORT "\" tag value ");
		return FAIL;
	}

	/* "use_tls" */

	if (SUCCEED != zbx_json_value_by_name(jp, ZBX_PROTO_TAG_LDAP_USE_TLS, value, sizeof(value)))
	{
		*error = zbx_strdup(*error, "no \"" ZBX_PROTO_TAG_LDAP_USE_TLS "\" tag");
		return FAIL;
	}

	if ('\0' == *value)
	{
		*error = zbx_strdup(*error, "\"" ZBX_PROTO_TAG_LDAP_USE_TLS "\" tag value is empty");
		return FAIL;
	}

	if (0 == strcmp(xstr(ZBX_LDAP_USE_TLS_UNENCRYPTED), value) ||
			0 == strcmp(xstr(ZBX_LDAP_USE_TLS_STARTTLS), value) ||
			0 == strcmp(xstr(ZBX_LDAP_USE_TLS_LDAPS), value))
	{
		ldap_server->use_tls = atoi(value);
	}
	else
	{
		*error = zbx_strdup(*error, "invalid \"" ZBX_PROTO_TAG_LDAP_USE_TLS "\" tag value ");
		return FAIL;
	}

	/* "bind_dn" */

	if (SUCCEED != zbx_json_value_by_name_dyn(jp, ZBX_PROTO_TAG_LDAP_BIND_DN, &ldap_server->bind_dn,
			&bind_dn_alloc))
	{
		*error = zbx_strdup(*error, "no \"" ZBX_PROTO_TAG_LDAP_BIND_DN "\" tag");
		return FAIL;
	}

	/* "bind_pw" */

	if (SUCCEED != zbx_json_value_by_name_dyn(jp, ZBX_PROTO_TAG_LDAP_BIND_PW, &ldap_server->bind_pw,
			&bind_pw_alloc))
	{
		*error = zbx_strdup(*error, "no \"" ZBX_PROTO_TAG_LDAP_BIND_PW "\" tag");
		return FAIL;
	}

	/* "net_timeout" */

	if (SUCCEED != zbx_json_value_by_name(jp, ZBX_PROTO_TAG_LDAP_NET_TIMEOUT, value, sizeof(value)))
	{
		*error = zbx_strdup(*error, "no \"" ZBX_PROTO_TAG_LDAP_NET_TIMEOUT "\" tag");
		return FAIL;
	}

	if ('\0' == *value)
	{
		*error = zbx_strdup(*error, "\"" ZBX_PROTO_TAG_LDAP_NET_TIMEOUT "\" tag value is empty");
		return FAIL;
	}

	if (0 >= (ldap_server->net_timeout = atoi(value)))
	{
		*error = zbx_strdup(*error, "invalid \"" ZBX_PROTO_TAG_LDAP_NET_TIMEOUT "\" tag value ");
		return FAIL;
	}

	/* "proc_timeout" */

	if (SUCCEED != zbx_json_value_by_name(jp, ZBX_PROTO_TAG_LDAP_PROC_TIMEOUT, value, sizeof(value)))
	{
		*error = zbx_strdup(*error, "no \"" ZBX_PROTO_TAG_LDAP_PROC_TIMEOUT "\" tag");
		return FAIL;
	}

	if ('\0' == *value)
	{
		*error = zbx_strdup(*error, "\"" ZBX_PROTO_TAG_LDAP_PROC_TIMEOUT "\" tag value is empty");
		return FAIL;
	}

	if (0 >= (ldap_server->proc_timeout = atoi(value)))
	{
		*error = zbx_strdup(*error, "invalid \"" ZBX_PROTO_TAG_LDAP_PROC_TIMEOUT "\" tag value ");
		return FAIL;
	}

	return SUCCEED;
}

#define ZBX_LDAP_UNKNOWN	-1
#define ZBX_LDAP_TEST_SERVER	0
#define ZBX_LDAP_TEST_SEARCH	1
#define ZBX_LDAP_TEST_SYNC	2
#define ZBX_LDAP_SYNC_NOW	3

/******************************************************************************
 *                                                                            *
 * Function: zbx_ldap_sync                                                    *
 *                                                                            *
 * Purpose: process LDAP test and synchronization requests from PHP frontend  *
 *                                                                            *
 * Parameters: sock - [IN] network socket for sending reply to frontend       *
 *             jp   - [IN] the json data                                      *
 *                                                                            *
 * Comments:                                                                  *
 *   Example of LDAP server test request:                                     *
 *    '{"request":"ldap_sync",                                                *
 *      "sid":"d6....",                                                       *
 *      "type":"test_server",                                                 *
 *      "data": [{                                                            *
 *          "host":"192.168.123.123",                                         *
 *          "port":389,                                                       *
 *          "use_tls":0,                                                      *
 *          "bind_dn":"cn=ldap_search,dc=example,dc=com",                     *
 *          "bind_pw":"********",                                             *
 *          "net_timeout":10,                                                 *
 *          "proc_timeout":10}'                                               *
 *       }]                                                                   *
 *     }'                                                                     *
 *                                                                            *
 * Return value: SUCCEED or FAIL                                              *
 *                                                                            *
 ******************************************************************************/
int	zbx_ldap_sync(zbx_socket_t *sock, struct zbx_json_parse *jp)
{
	const char		*__function_name = "zbx_ldap_sync";
	int			ret = FAIL, request_type = ZBX_LDAP_UNKNOWN;
	char			*error = NULL, type[MAX_STRING_LEN];
	struct zbx_json_parse	jp_data;
	const char		*p = NULL;
	zbx_ldap_server_t	ldap_server = { NULL, NULL, NULL, 0, 0, 0, 0 };

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	/* Start with "type" tag. "request" and "sid" tags were processed earlier. */

	if (SUCCEED == zbx_json_value_by_name(jp, ZBX_PROTO_TAG_TYPE, type, sizeof(type)))
	{
		if (0 == strcmp(type, ZBX_PROTO_VALUE_LDAP_TEST_SERVER))
			request_type = ZBX_LDAP_TEST_SERVER;
		else if (0 == strcmp(type, ZBX_PROTO_VALUE_LDAP_TEST_SEARCH))
			request_type = ZBX_LDAP_TEST_SEARCH;
		else if (0 == strcmp(type, ZBX_PROTO_VALUE_LDAP_TEST_SYNC))
			request_type = ZBX_LDAP_TEST_SYNC;
		else if (0 == strcmp(type, ZBX_PROTO_VALUE_LDAP_SYNC_NOW))
			request_type = ZBX_LDAP_SYNC_NOW;
	}

	if (ZBX_LDAP_UNKNOWN == request_type)
	{
		zbx_send_response_raw(sock, FAIL, "Unsupported request type.", CONFIG_TIMEOUT);
		goto out;
	}

	/* "data" tag */

	if (SUCCEED != zbx_json_brackets_by_name(jp, ZBX_PROTO_TAG_DATA, &jp_data))
	{
		zbx_send_response_raw(sock, FAIL, "no \"" ZBX_PROTO_TAG_DATA "\" tag", CONFIG_TIMEOUT);
		goto out;
	}

	while (NULL != (p = zbx_json_next(&jp_data, p)))
	{
		struct zbx_json_parse	jp_server;

		if (SUCCEED != (ret = zbx_json_brackets_open(p, &jp_server)))
		{
			zbx_send_response_raw(sock, FAIL, zbx_json_strerror(), CONFIG_TIMEOUT);
			goto out;
		}

		if (SUCCEED != ldap_json_deserialize_server(&jp_server, &ldap_server, &error))
		{
			zbx_send_response_raw(sock, FAIL, error, CONFIG_TIMEOUT);
			goto out;
		}

		if (ZBX_LDAP_TEST_SERVER == request_type)
		{
			if (SUCCEED == zbx_ldap_server_test(&ldap_server, &error))
				zbx_send_response_raw(sock, SUCCEED, "LDAP server test successful", CONFIG_TIMEOUT);
			else
				zbx_send_response_raw(sock, FAIL, error, CONFIG_TIMEOUT);

			goto out;
		}

		/* TODO: process "test_search", "test_sync" and "sync_now" requests */
	}
out:
	zbx_free(error);
	zbx_free(ldap_server.host);
	zbx_free(ldap_server.bind_dn);

	if (NULL != ldap_server.bind_pw)
	{
		zbx_guaranteed_memset(ldap_server.bind_pw, 0, strlen(ldap_server.bind_pw));
		zbx_free(ldap_server.bind_pw);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

ZBX_THREAD_ENTRY(ldap_syncer_thread, args)
{
	double	sec1, sec2;
	int	sleeptime;

	process_type = ((zbx_thread_args_t *)args)->process_type;
	server_num = ((zbx_thread_args_t *)args)->server_num;
	process_num = ((zbx_thread_args_t *)args)->process_num;

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(program_type),
			server_num, get_process_type_string(process_type), process_num);

	zbx_setproctitle("%s [connecting to the database]", get_process_type_string(process_type));
	DBconnect(ZBX_DB_CONNECT_NORMAL);

	sec1 = zbx_time();
	sec2 = sec1;

	sleeptime = ZBX_LDAPSYNCER_PERIOD - (int)sec1 % ZBX_LDAPSYNCER_PERIOD;

	zbx_setproctitle("%s [started, idle %d sec]", get_process_type_string(process_type), sleeptime);

	for (;;)
	{
		int	user_num = 0, nextsync;

		zbx_sleep_loop(sleeptime);

		zbx_handle_log();

		zbx_setproctitle("%s [synchronizing LDAP users]", get_process_type_string(process_type));

		sec1 = zbx_time();

		if (SUCCEED != zbx_synchronize_from_ldap(&user_num))
		{
			/* TODO: communicate error to frontend */
		}

		sec2 = zbx_time();

		nextsync = (int)sec1 - (int)sec1 % ZBX_LDAPSYNCER_PERIOD + ZBX_LDAPSYNCER_PERIOD;

		if (0 > (sleeptime = nextsync - (int)sec2))
			sleeptime = 0;

		zbx_setproctitle("%s [synchronized %d LDAP users(s) in " ZBX_FS_DBL " sec, idle %d sec]",
				get_process_type_string(process_type), user_num, sec2 - sec1, sleeptime);
	}
}
#endif
