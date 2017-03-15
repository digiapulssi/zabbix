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
#include "./ldapsyncer.h"
#include <ldap.h>

#define ZBX_LDAPSYNCER_PERIOD		60	/* TODO: make period configurable */

extern unsigned char	process_type, program_type;
extern int		server_num, process_num;
extern int		CONFIG_TIMEOUT;

/******************************************************************************
 *                                                                            *
 * Function: zbx_ldap_connect                                                 *
 *                                                                            *
 * Purpose: connect to LDAP server                                            *
 *                                                                            *
 * Return value: SUCCEED or FAIL                                              *
 *                                                                            *
 ******************************************************************************/
static int	zbx_ldap_connect(LDAP **ld, const char *uri, const char *bind_user, const char *bind_passwd,
		int timeout, char **error)
{
	int		res, opt_protocol_version = LDAP_VERSION3, opt_deref = LDAP_DEREF_NEVER;
	struct timeval	tv;

	/* initialize LDAP data struture (without opening a connection) */

	if (LDAP_SUCCESS != (res = ldap_initialize(ld, uri)))
	{
		*error = zbx_dsprintf(*error, "ldap_initialize() failed for \"%s\": %d %s",
				uri, res, ldap_err2string(res));
		return FAIL;
	}

	/* TODO: (optionally) check LDAP_OPT_API_FEATURE_INFO, LDAP_OPT_API_INFO to detect library version mismatch */

	/* set LDAP protocol v3 */

	if (LDAP_OPT_SUCCESS != (res = ldap_set_option(*ld, LDAP_OPT_PROTOCOL_VERSION, &opt_protocol_version)))
	{
		*error = zbx_dsprintf(*error, "ldap_set_option(,LDAP_OPT_PROTOCOL_VERSION,) failed: %d %s",
				res, ldap_err2string(res));
		return FAIL;
	}

	/* do not use asynchronous connect */

	if (LDAP_OPT_SUCCESS != (res = ldap_set_option(*ld, LDAP_OPT_CONNECT_ASYNC, LDAP_OPT_OFF)))
	{
		*error = zbx_dsprintf(*error, "ldap_set_option(,LDAP_OPT_CONNECT_ASYNC,) failed: %d %s",
				res, ldap_err2string(res));
		return FAIL;
	}

	/* TODO: (optionally) set LDAP_OPT_DEBUG_LEVEL for using with DebugLevel=5 */

	/* do not dereference aliases (default) */

	if (LDAP_OPT_SUCCESS != (res = ldap_set_option(*ld, LDAP_OPT_DEREF, &opt_deref)))
	{
		*error = zbx_dsprintf(*error, "ldap_set_option(,LDAP_OPT_DEREF,) failed: %d %s",
				res, ldap_err2string(res));
		return FAIL;
	}

	/* set connection timeout */

	tv.tv_sec = timeout;
	tv.tv_usec = 0;

	if (LDAP_OPT_SUCCESS != (res = ldap_set_option(*ld, LDAP_OPT_NETWORK_TIMEOUT, &tv)))
	{
		*error = zbx_dsprintf(*error, "ldap_set_option(,LDAP_OPT_NETWORK_TIMEOUT,) failed: %d %s",
				res, ldap_err2string(res));
		return FAIL;
	}

	/* do not chase referrals */

	if (LDAP_OPT_SUCCESS != (res = ldap_set_option(*ld, LDAP_OPT_REFERRALS, LDAP_OPT_OFF)))
	{
		*error = zbx_dsprintf(*error, "ldap_set_option(,LDAP_OPT_REFERRALS,) failed: %d %s",
				res, ldap_err2string(res));
		return FAIL;
	}

	/* set timeout for synchronous API call. TODO: currently set CONFIG_TIMEOUT, maybe need to be changed */

	if (LDAP_OPT_SUCCESS != (res = ldap_set_option(*ld, LDAP_OPT_TIMEOUT, &tv)))
	{
		*error = zbx_dsprintf(*error, "ldap_set_option(,LDAP_OPT_TIMEOUT,) failed: %d %s",
				res, ldap_err2string(res));
		return FAIL;
	}

	/* simple bind with user/password */

	if (LDAP_SUCCESS != (res= ldap_simple_bind_s(*ld, bind_user, bind_passwd)))
	{
		*error = zbx_dsprintf(*error, "ldap_simple_bind_s() failed: %d %s", res, ldap_err2string(res));
		return FAIL;
	}

	return SUCCEED;
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
 * Purpose: release LDAP resources                                            *
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
	const char	*__function_name = "zbx_synchronize_from_ldap";
	LDAP		*ld = NULL;
	LDAPMessage	*result;
	char		*error = NULL;
	int		res, ret = FAIL;
	int		timeout = CONFIG_TIMEOUT;

	/* TODO: replace hardcoded uri, bind_user, bind_passwd with values from database. */
	/* TODO: consider supporting a list of URIs - ldap_initialize() in zbx_ldap_connect() can take a list of them */
	/* TODO: consider supporting 'ldaps' (LDAP over TLS) protocol. */

	const char	*uri = "ldap://127.0.0.1:389";
	const char	*bind_user = "bind user name here";
	const char	*bind_passwd = "bind password here";

	char		*base_dn = "OU=people,DC=example,DC=com";
	int		scope = LDAP_SCOPE_SUBTREE;
	char		*filter = "(ou=IT operations)";
	char		**attributes_list = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (SUCCEED != (res = zbx_ldap_connect(&ld, uri, bind_user, bind_passwd, timeout, &error)))
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
