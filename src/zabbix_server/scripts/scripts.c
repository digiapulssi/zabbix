/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
#include "../poller/checks_agent.h"
#include "../ipmi/ipmi.h"
#include "../poller/checks_ssh.h"
#include "../poller/checks_telnet.h"
#include "zbxexec.h"
#include "zbxserver.h"
#include "db.h"
#include "log.h"
#include "zbxtasks.h"
#include "scripts.h"

extern int	CONFIG_TRAPPER_TIMEOUT;

static int	zbx_execute_script_on_agent(const DC_HOST *host, const char *command, char **result,
		char *error, size_t max_error_len)
{
	int		ret;
	AGENT_RESULT	agent_result;
	char		*param = NULL, *port = NULL;
	DC_ITEM		item;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	*error = '\0';
	memset(&item, 0, sizeof(item));
	memcpy(&item.host, host, sizeof(item.host));

	if (SUCCEED != (ret = DCconfig_get_interface_by_type(&item.interface, host->hostid, INTERFACE_TYPE_AGENT)))
	{
		zbx_snprintf(error, max_error_len, "Zabbix agent interface is not defined for host [%s]", host->host);
		goto fail;
	}

	port = zbx_strdup(port, item.interface.port_orig);
	substitute_simple_macros(NULL, NULL, NULL, NULL, &host->hostid, NULL, NULL, NULL, NULL,
			&port, MACRO_TYPE_COMMON, NULL, 0);

	if (SUCCEED != (ret = is_ushort(port, &item.interface.port)))
	{
		zbx_snprintf(error, max_error_len, "Invalid port number [%s]", item.interface.port_orig);
		goto fail;
	}

	param = zbx_strdup(param, command);
	if (SUCCEED != (ret = quote_key_param(&param, 0)))
	{
		zbx_snprintf(error, max_error_len, "Invalid param [%s]", param);
		goto fail;
	}

	item.key = zbx_dsprintf(item.key, "system.run[%s,%s]", param, NULL == result ? "nowait" : "wait");
	item.value_type = ITEM_VALUE_TYPE_TEXT;

	init_result(&agent_result);

	zbx_alarm_on(CONFIG_TIMEOUT);

	if (SUCCEED != (ret = get_value_agent(&item, &agent_result)))
	{
		if (ISSET_MSG(&agent_result))
			zbx_strlcpy(error, agent_result.msg, max_error_len);
		ret = FAIL;
	}
	else if (NULL != result && ISSET_TEXT(&agent_result))
		*result = zbx_strdup(*result, agent_result.text);

	zbx_alarm_off();

	free_result(&agent_result);

	zbx_free(item.key);
fail:
	zbx_free(port);
	zbx_free(param);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

static int	zbx_execute_script_on_terminal(const DC_HOST *host, const zbx_script_t *script, char **result,
		char *error, size_t max_error_len)
{
	int		ret = FAIL, i;
	AGENT_RESULT	agent_result;
	DC_ITEM		item;
	int             (*function)(DC_ITEM *, AGENT_RESULT *);

#ifdef HAVE_SSH2
	assert(ZBX_SCRIPT_TYPE_SSH == script->type || ZBX_SCRIPT_TYPE_TELNET == script->type);
#else
	assert(ZBX_SCRIPT_TYPE_TELNET == script->type);
#endif

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	*error = '\0';
	memset(&item, 0, sizeof(item));
	memcpy(&item.host, host, sizeof(item.host));

	for (i = 0; INTERFACE_TYPE_COUNT > i; i++)
	{
		if (SUCCEED == (ret = DCconfig_get_interface_by_type(&item.interface, host->hostid,
				INTERFACE_TYPE_PRIORITY[i])))
		{
			break;
		}
	}

	if (FAIL == ret)
	{
		zbx_snprintf(error, max_error_len, "No interface defined for host [%s]", host->host);
		goto fail;
	}

	switch (script->type)
	{
		case ZBX_SCRIPT_TYPE_SSH:
			item.authtype = script->authtype;
			item.publickey = script->publickey;
			item.privatekey = script->privatekey;
			ZBX_FALLTHROUGH;
		case ZBX_SCRIPT_TYPE_TELNET:
			item.username = script->username;
			item.password = script->password;
			break;
	}

#ifdef HAVE_SSH2
	if (ZBX_SCRIPT_TYPE_SSH == script->type)
	{
		item.key = zbx_dsprintf(item.key, "ssh.run[,,%s]", script->port);
		function = get_value_ssh;
	}
	else
	{
#endif
		item.key = zbx_dsprintf(item.key, "telnet.run[,,%s]", script->port);
		function = get_value_telnet;
#ifdef HAVE_SSH2
	}
#endif
	item.value_type = ITEM_VALUE_TYPE_TEXT;
	item.params = zbx_strdup(item.params, script->command);

	init_result(&agent_result);

	zbx_alarm_on(CONFIG_TIMEOUT);

	if (SUCCEED != (ret = function(&item, &agent_result)))
	{
		if (ISSET_MSG(&agent_result))
			zbx_strlcpy(error, agent_result.msg, max_error_len);
		ret = FAIL;
	}
	else if (NULL != result && ISSET_TEXT(&agent_result))
		*result = zbx_strdup(*result, agent_result.text);

	zbx_alarm_off();

	free_result(&agent_result);

	zbx_free(item.params);
	zbx_free(item.key);
fail:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

static int	DBget_script_by_scriptid(zbx_uint64_t scriptid, zbx_script_t *script, zbx_uint64_t *groupid)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	result = DBselect(
			"select type,execute_on,command,groupid,host_access"
			" from scripts"
			" where scriptid=" ZBX_FS_UI64,
			scriptid);

	if (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UCHAR(script->type, row[0]);
		ZBX_STR2UCHAR(script->execute_on, row[1]);
		script->command = zbx_strdup(script->command, row[2]);
		ZBX_DBROW2UINT64(*groupid, row[3]);
		ZBX_STR2UCHAR(script->host_access, row[4]);
		ret = SUCCEED;
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

static int	check_script_permissions(zbx_uint64_t groupid, zbx_uint64_t hostid)
{
	DB_RESULT		result;
	int			ret = SUCCEED;
	zbx_vector_uint64_t	groupids;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() groupid:" ZBX_FS_UI64 " hostid:" ZBX_FS_UI64, __func__, groupid, hostid);

	if (0 == groupid)
		goto exit;

	zbx_vector_uint64_create(&groupids);
	zbx_dc_get_nested_hostgroupids(&groupid, 1, &groupids);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select hostid"
			" from hosts_groups"
			" where hostid=" ZBX_FS_UI64
				" and",
			hostid);

	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "groupid", groupids.values,
			groupids.values_num);

	result = DBselect("%s", sql);

	zbx_free(sql);
	zbx_vector_uint64_destroy(&groupids);

	if (NULL == DBfetch(result))
		ret = FAIL;

	DBfree_result(result);
exit:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

static int	check_user_permissions(zbx_uint64_t userid, const DC_HOST *host, zbx_script_t *script)
{
	int		ret = SUCCEED;
	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() userid:" ZBX_FS_UI64 " hostid:" ZBX_FS_UI64 " scriptid:" ZBX_FS_UI64,
			__func__, userid, host->hostid, script->scriptid);

	result = DBselect(
		"select null"
			" from hosts_groups hg,rights r,users_groups ug"
		" where hg.groupid=r.id"
			" and r.groupid=ug.usrgrpid"
			" and hg.hostid=" ZBX_FS_UI64
			" and ug.userid=" ZBX_FS_UI64
		" group by hg.hostid"
		" having min(r.permission)>%d"
			" and max(r.permission)>=%d",
		host->hostid,
		userid,
		PERM_DENY,
		script->host_access);

	if (NULL == (row = DBfetch(result)))
		ret = FAIL;

	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

void	zbx_script_init(zbx_script_t *script)
{
	memset(script, 0, sizeof(zbx_script_t));
}

void	zbx_script_clean(zbx_script_t *script)
{
	zbx_free(script->port);
	zbx_free(script->username);
	zbx_free(script->publickey);
	zbx_free(script->privatekey);
	zbx_free(script->password);
	zbx_free(script->command);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_script_prepare                                               *
 *                                                                            *
 * Purpose: prepares user script                                              *
 *                                                                            *
 * Parameters: host          - [IN] the host the script will be executed on   *
 *             script        - [IN/OUT] the script to prepare                 *
 *             user          - [IN] the user executing script                 *
 *             error         - [OUT] the error message output buffer          *
 *             mas_error_len - [IN] the size of error message output buffer   *
 *                                                                            *
 * Return value:  SUCCEED - the script has been prepared successfully         *
 *                FAIL    - otherwise, error contains error message           *
 *                                                                            *
 * Comments: This function prepares script for execution by loading global    *
 *           script/expanding macros.                                         *
 *           Prepared scripts must be always freed with zbx_script_clean()    *
 *           function.                                                        *
 *                                                                            *
 ******************************************************************************/
int	zbx_script_prepare(zbx_script_t *script, const DC_HOST *host, const zbx_user_t *user, char *error,
		size_t max_error_len)
{
	int		ret = FAIL;
	zbx_uint64_t	groupid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	switch (script->type)
	{
		case ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT:
			dos2unix(script->command);	/* CR+LF (Windows) => LF (Unix) */
			break;
		case ZBX_SCRIPT_TYPE_SSH:
			substitute_simple_macros(NULL, NULL, NULL, NULL, &host->hostid, NULL, NULL, NULL, NULL,
					&script->publickey, MACRO_TYPE_COMMON, NULL, 0);
			substitute_simple_macros(NULL, NULL, NULL, NULL, &host->hostid, NULL, NULL, NULL, NULL,
					&script->privatekey, MACRO_TYPE_COMMON, NULL, 0);
			ZBX_FALLTHROUGH;
		case ZBX_SCRIPT_TYPE_TELNET:
			substitute_simple_macros(NULL, NULL, NULL, NULL, &host->hostid, NULL, NULL, NULL, NULL,
					&script->port, MACRO_TYPE_COMMON, NULL, 0);

			if ('\0' != *script->port && SUCCEED != (ret = is_ushort(script->port, NULL)))
			{
				zbx_snprintf(error, max_error_len, "Invalid port number \"%s\"", script->port);
				goto out;
			}

			substitute_simple_macros(NULL, NULL, NULL, NULL, &host->hostid, NULL, NULL, NULL, NULL,
					&script->username, MACRO_TYPE_COMMON, NULL, 0);
			substitute_simple_macros(NULL, NULL, NULL, NULL, &host->hostid, NULL, NULL, NULL, NULL,
					&script->password, MACRO_TYPE_COMMON, NULL, 0);
			break;
		case ZBX_SCRIPT_TYPE_GLOBAL_SCRIPT:
			if (SUCCEED != DBget_script_by_scriptid(script->scriptid, script, &groupid))
			{
				zbx_strlcpy(error, "Unknown script identifier.", max_error_len);
				goto out;
			}
			if (groupid > 0 && SUCCEED != check_script_permissions(groupid, host->hostid))
			{
				zbx_strlcpy(error, "Script does not have permission to be executed on the host.",
						max_error_len);
				goto out;
			}
			if (user != NULL && USER_TYPE_SUPER_ADMIN != user->type &&
				SUCCEED != check_user_permissions(user->userid, host, script))
			{
				zbx_strlcpy(error, "User does not have permission to execute this script on the host.",
						max_error_len);
				goto out;
			}

			substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, host, NULL, NULL, NULL, &script->command,
					MACRO_TYPE_SCRIPT, NULL, 0);

			/* DBget_script_by_scriptid() may overwrite script type with anything but global script... */
			if (ZBX_SCRIPT_TYPE_GLOBAL_SCRIPT == script->type)
			{
				THIS_SHOULD_NEVER_HAPPEN;
				goto out;
			}

			/* ...therefore this recursion is no more than two layers deep */
			if (FAIL == zbx_script_prepare(script, host, user, error, max_error_len))
				goto out;

			break;
		case ZBX_SCRIPT_TYPE_IPMI:
			break;
		default:
			zbx_snprintf(error, max_error_len, "Invalid command type \"%d\".", (int)script->type);
			goto out;
	}

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_script_execute                                               *
 *                                                                            *
 * Purpose: executing user scripts or remote commands                         *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - an error occurred                                    *
 *                TIMEOUT_ERROR - a timeout occurred                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_script_execute(const zbx_script_t *script, const DC_HOST *host, char **result, char *error,
		size_t max_error_len)
{
	int	ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	*error = '\0';

	switch (script->type)
	{
		case ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT:
			switch (script->execute_on)
			{
				case ZBX_SCRIPT_EXECUTE_ON_AGENT:
					ret = zbx_execute_script_on_agent(host, script->command, result, error,
							max_error_len);
					break;
				case ZBX_SCRIPT_EXECUTE_ON_SERVER:
				case ZBX_SCRIPT_EXECUTE_ON_PROXY:
					ret = zbx_execute(script->command, result, error, max_error_len,
							CONFIG_TRAPPER_TIMEOUT, ZBX_EXIT_CODE_CHECKS_ENABLED);
					break;
				default:
					zbx_snprintf(error, max_error_len, "Invalid 'Execute on' option \"%d\".",
							(int)script->execute_on);
			}
			break;
		case ZBX_SCRIPT_TYPE_IPMI:
#ifdef HAVE_OPENIPMI
			if (SUCCEED == (ret = zbx_ipmi_execute_command(host, script->command, error, max_error_len)))
			{
				if (NULL != result)
					*result = zbx_strdup(*result, "IPMI command successfully executed.");
			}
#else
			zbx_strlcpy(error, "Support for IPMI commands was not compiled in.", max_error_len);
#endif
			break;
		case ZBX_SCRIPT_TYPE_SSH:
#ifndef HAVE_SSH2
			zbx_strlcpy(error, "Support for SSH script was not compiled in.", max_error_len);
			break;
#endif
		case ZBX_SCRIPT_TYPE_TELNET:
			ret = zbx_execute_script_on_terminal(host, script, result, error, max_error_len);
			break;
		default:
			zbx_snprintf(error, max_error_len, "Invalid command type \"%d\".", (int)script->type);
	}

	if (SUCCEED != ret && NULL != result)
		*result = zbx_strdup(*result, "");

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_script_create_task                                           *
 *                                                                            *
 * Purpose: creates remote command task from a script                         *
 *                                                                            *
 * Return value:  the identifier of the created task or 0 in the case of      *
 *                error                                                       *
 *                                                                            *
 ******************************************************************************/
zbx_uint64_t	zbx_script_create_task(const zbx_script_t *script, const DC_HOST *host, zbx_uint64_t alertid, int now)
{
	zbx_tm_task_t	*task;
	unsigned short	port;
	zbx_uint64_t	taskid;

	if (NULL != script->port && '\0' != script->port[0])
		is_ushort(script->port, &port);
	else
		port = 0;

	taskid = DBget_maxid("task");

	task = zbx_tm_task_create(taskid, ZBX_TM_TASK_REMOTE_COMMAND, ZBX_TM_STATUS_NEW, now,
			ZBX_REMOTE_COMMAND_TTL, host->proxy_hostid);

	task->data = zbx_tm_remote_command_create(script->type, script->command, script->execute_on, port,
			script->authtype, script->username, script->password, script->publickey, script->privatekey,
			taskid, host->hostid, alertid);

	DBbegin();

	if (FAIL == zbx_tm_save_task(task))
		taskid = 0;

	DBcommit();

	zbx_tm_task_free(task);

	return taskid;
}
