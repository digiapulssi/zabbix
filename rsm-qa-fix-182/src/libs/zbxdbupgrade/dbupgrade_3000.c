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
#include "db.h"
#include "zbxdbupgrade.h"
#include "dbupgrade.h"
#include "log.h"

/*
 * 3.0 maintenance database patches
 */

#ifndef HAVE_SQLITE3

#define zbx_db_dyn_escape_string(src)	zbx_db_dyn_escape_string(src, ZBX_SIZE_T_MAX, ZBX_SIZE_T_MAX, ESCAPE_SEQUENCE_ON)

extern unsigned char	program_type;

static int	DBpatch_3000000(void)
{
	return SUCCEED;
}

static int	DBpatch_3000100(void)
{
	zabbix_log(LOG_LEVEL_CRIT, "There is no automatic database upgrade for Phase 1");

	return FAIL;
}

static int	add_hostgroup(const char *name, zbx_uint64_t groupid)
{
	if (0 != (program_type & ZBX_PROGRAM_TYPE_PROXY))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute(
			"insert into groups (groupid,name,internal,flags)"
			" values ('" ZBX_FS_UI64 "','%s','0','0')", groupid, name))
	{
		return FAIL;
	}

	if (ZBX_DB_OK > DBexecute(
			"update ids"
			" set nextid=(select max(groupid) from groups)"
			" where table_name='groups'"
				" and field_name='groupid'"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_3000101(void)
{
	return add_hostgroup("TLD Probe results", 190);
}

static int	DBpatch_3000102(void)
{
	return add_hostgroup("gTLD Probe results", 200);
}

static int	DBpatch_3000103(void)
{
	return add_hostgroup("ccTLD Probe results", 210);
}

static int	DBpatch_3000104(void)
{
	return add_hostgroup("testTLD Probe results", 220);
}

static int	DBpatch_3000105(void)
{
	return add_hostgroup("otherTLD Probe results", 230);
}

static int	add_right(zbx_uint64_t rightid, zbx_uint64_t usergroupid, zbx_uint64_t groupid)
{
	if (0 != (program_type & ZBX_PROGRAM_TYPE_PROXY))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute(
			"insert into rights (rightid,groupid,permission,id)"
			" values ('" ZBX_FS_UI64 "','" ZBX_FS_UI64 "','2','" ZBX_FS_UI64 "')",
			rightid, usergroupid, groupid))
	{
		return FAIL;
	}

	if (ZBX_DB_OK > DBexecute(
			"update ids"
			" set nextid=(select max(rightid) from rights)"
			" where table_name='rights'"
				" and field_name='rightid'"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_3000106(void)
{
	return add_right(116, 110, 190);
}

static int	DBpatch_3000107(void)
{
	return add_right(106, 100, 200);
}

static int	DBpatch_3000108(void)
{
	return add_right(117, 110, 120);
}

static int	DBpatch_3000109(void)
{
	return add_right(107, 100, 120);
}

static int	add_hosts_to_group(const char *tld_type, zbx_uint64_t groupid)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	hostgroupid;

	if (0 != (program_type & ZBX_PROGRAM_TYPE_PROXY))
		return SUCCEED;

	result = DBselect("select max(hostgroupid) from hosts_groups");

	if (NULL == (row = DBfetch(result)))
	{
		DBfree_result(result);
		return FAIL;
	}

	ZBX_STR2UINT64(hostgroupid, row[0]);

	result = DBselect(
			"select h.host"
			" from hosts h,groups g,hosts_groups hg"
			" where h.hostid=hg.hostid"
				" and hg.groupid=g.groupid"
				" and g.name='%s'", tld_type);

	while (NULL != (row = DBfetch(result)))
	{
		DB_RESULT	result2;
		DB_ROW		row2;

		result2 = DBselect(
				"select h.hostid"
				" from hosts h,groups g,hosts_groups hg"
				" where h.hostid=hg.hostid"
					" and hg.groupid=g.groupid"
					" and h.proxy_hostid is not null"
					" and g.name='TLD %s'", row[0]);

		while (NULL != (row2 = DBfetch(result2)))
		{
			zbx_uint64_t	hostid;

			ZBX_STR2UINT64(hostid, row2[0]);

			if (ZBX_DB_OK > DBexecute("insert into hosts_groups (hostgroupid,hostid,groupid) values"
					" ('" ZBX_FS_UI64 "','" ZBX_FS_UI64 "','" ZBX_FS_UI64 "')",
					++hostgroupid, hostid, groupid))
			{
				DBfree_result(result2);
				DBfree_result(result);

				return FAIL;
			}

		}

		DBfree_result(result2);
	}

	DBfree_result(result);

	if (ZBX_DB_OK > DBexecute(
			"update ids"
			" set nextid=(select max(hostgroupid) from hosts_groups)"
			" where table_name='hosts_groups'"
				" and field_name='hostgroupid'"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_3000110(void)
{
	return add_hosts_to_group("gTLD", 200);
}

static int	DBpatch_3000111(void)
{
	return add_hosts_to_group("ccTLD", 210);
}

static int	DBpatch_3000112(void)
{
	return add_hosts_to_group("testTLD", 220);
}

static int	DBpatch_3000113(void)
{
	return add_hosts_to_group("otherTLD", 230);
}

static int	DBpatch_3000114(void)
{
	return add_hosts_to_group("TLDs", 190);
}

static int	DBpatch_update_trigger_expression(const char *old, const char *new)
{
	char	*old_esc, *new_esc;
	int	ret = SUCCEED;

	old_esc = zbx_db_dyn_escape_string(old);
	new_esc = zbx_db_dyn_escape_string(new);

	if (ZBX_DB_OK > DBexecute("update triggers set expression='%s' where expression='%s'", new_esc, old_esc))
		ret = FAIL;

	zbx_free(old_esc);
	zbx_free(new_esc);

	return ret;
}

static int	DBpatch_3000115(void)
{
	return DBpatch_update_trigger_expression(
			"{100102}<{$RSM.IP4.PROBE.ONLINE}",
			"{100102}<{$RSM.IP4.MIN.PROBE.ONLINE}");
}

static int	DBpatch_3000116(void)
{
	return DBpatch_update_trigger_expression(
			"{100103}<{$RSM.IP6.PROBE.ONLINE}",
			"{100103}<{$RSM.IP6.MIN.PROBE.ONLINE}");
}

static int	DBpatch_3000117(void)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		ret = SUCCEED;

	if (0 != (program_type & ZBX_PROGRAM_TYPE_PROXY))
		return SUCCEED;

	result = DBselect(
			"select h.hostid"
			" from hosts h,applications a"
			" where h.status in (0,1)"	/* HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED */
				" and a.hostid=h.hostid"
				" and a.name='Probe status'");

	if (NULL == result)
		return FAIL;

	while (NULL != (row = DBfetch(result)) && SUCCEED == ret)
	{
		zbx_uint64_t		hostid;
		zbx_vector_uint64_t	templateids;

		ZBX_STR2UINT64(hostid, row[0]);			/* hostid of probe host */
		zbx_vector_uint64_create(&templateids);
		zbx_vector_uint64_reserve(&templateids, 1);
		zbx_vector_uint64_append(&templateids, 10058);	/* hostid of "Template App Zabbix Proxy" */

		ret = DBcopy_template_elements(hostid, &templateids);

		zbx_vector_uint64_destroy(&templateids);
	}

	DBfree_result(result);

	return ret;
}

static int	DBpatch_3000118(void)
{
	return add_right(109, 110, 130);
}

static int	DBpatch_3000119(void)
{
	return add_right(119, 110, 110);
}

static int	DBpatch_3000120(void)
{
	return DBpatch_3000117();
}

typedef enum
{
	OP_MESSAGE,
	OP_COMMAND
}
operation_type_t;

typedef enum
{
	OP_MESSAGE_USR
}
opmessage_type_t;

typedef union
{
	zbx_uint64_t	userid;
}
opmessage_data_t;

typedef struct
{
	zbx_uint64_t		id;
	opmessage_type_t	type;
	opmessage_data_t	data;
}
recipient_t;

typedef struct
{
#define MAX_RECIPIENTS	1

	int		default_msg;
	const char	*subject;
	const char	*message;
	zbx_uint64_t	mediatypeid;
	recipient_t	recipients[MAX_RECIPIENTS + 1];

#undef MAX_RECIPIENTS
}
opmessage_t;

typedef enum
{
	OP_COMMAND_CURRENT_HOST
}
opcommand_type_t;

typedef struct
{
	zbx_uint64_t		id;
	opcommand_type_t	type;
}
target_t;

typedef struct
{
#define MAX_TARGETS	1

	int		type;
	int		execute_on;
	const char	*port;
	int		authtype;
	const char	*username;
	const char	*password;
	const char	*publickey;
	const char	*privatekey;
	const char	*command;
	target_t	targets[MAX_TARGETS + 1];

#undef MAX_TARGETS
}
opcommand_t;

typedef union
{
	opmessage_t	opmessage;
	opcommand_t	opcommand;
}
operation_data_t;

typedef struct
{
	zbx_uint64_t		id;
	operation_type_t	type;
	operation_data_t	data;
}
operation_t;

typedef struct
{
	zbx_uint64_t	id;
	int		conditiontype;
	int		operator;
	const char	*value;
}
condition_t;

typedef struct
{
#define MAX_OPERATIONS	1
#define MAX_CONDITIONS	5

	zbx_uint64_t	id;
	const char	*name;
	int		esc_period;
	const char	*def_shortdata;
	const char	*def_longdata;
	int		recovery_msg;
	const char	*r_shortdata;
	const char	*r_longdata;
	operation_t	operations[MAX_OPERATIONS + 1];
	condition_t	conditions[MAX_CONDITIONS + 1];

#undef MAX_OPERATIONS
#undef MAX_CONDITIONS
}
action_t;

static int	db_insert_action(const action_t *action)
{
	char	*name_esc = NULL, *def_shortdata_esc = NULL, *def_longdata_esc = NULL, *r_shortdata_esc = NULL,
		*r_longdata_esc = NULL;
	int	ret;

	name_esc = zbx_db_dyn_escape_string(action->name);
	def_shortdata_esc = zbx_db_dyn_escape_string(action->def_shortdata);
	def_longdata_esc = zbx_db_dyn_escape_string(action->def_longdata);
	r_shortdata_esc = zbx_db_dyn_escape_string(action->r_shortdata);
	r_longdata_esc = zbx_db_dyn_escape_string(action->r_longdata);

	ret = DBexecute(
			"insert into actions (actionid,name,esc_period,def_shortdata,def_longdata,"
				"recovery_msg,r_shortdata,r_longdata)"
			" values (" ZBX_FS_UI64 ",'%s',%d,'%s','%s',%d,'%s','%s')",
			action->id, name_esc, action->esc_period, def_shortdata_esc, def_longdata_esc,
			action->recovery_msg, r_shortdata_esc, r_longdata_esc);

	zbx_free(name_esc);
	zbx_free(def_shortdata_esc);
	zbx_free(def_longdata_esc);
	zbx_free(r_shortdata_esc);
	zbx_free(r_longdata_esc);

	return ZBX_DB_OK > ret ? FAIL : SUCCEED;
}

static int	db_insert_opmessage(zbx_uint64_t operationid, const opmessage_t *opmessage)
{
	const recipient_t	*recipient;
	char			*subject_esc = NULL, *message_esc = NULL;
	int			ret;

	subject_esc = zbx_db_dyn_escape_string(opmessage->subject);
	message_esc = zbx_db_dyn_escape_string(opmessage->message);

	ret = DBexecute(
			"insert into opmessage (operationid,default_msg,subject,message,mediatypeid)"
			" values (" ZBX_FS_UI64 ",%d,'%s','%s'," ZBX_FS_UI64 ")",
			operationid, opmessage->default_msg, subject_esc, message_esc, opmessage->mediatypeid);

	zbx_free(subject_esc);
	zbx_free(message_esc);

	if (ZBX_DB_OK > ret)
		return FAIL;

	for (recipient = opmessage->recipients; 0 != recipient->id; recipient++)
	{
		switch (recipient->type)
		{
			case OP_MESSAGE_USR:
				if (ZBX_DB_OK > DBexecute(
						"insert into opmessage_usr (opmessage_usrid,operationid,userid)"
						" values (" ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64 ")",
						recipient->id, operationid, recipient->data.userid))
				{
					return FAIL;
				}
				break;
			default:
				THIS_SHOULD_NEVER_HAPPEN;
				return FAIL;
		}
	}

	return SUCCEED;
}

static int	db_insert_opcommand(zbx_uint64_t operationid, const opcommand_t *opcommand)
{
	const target_t	*target;
	char		*port_esc = NULL, *username_esc = NULL, *password_esc = NULL, *publickey_esc = NULL,
			*privatekey_esc = NULL, *command_esc = NULL;
	int		ret;

	if (0 != opcommand->type)	/* ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT */
	{
		THIS_SHOULD_NEVER_HAPPEN;
		return FAIL;
	}

	port_esc = zbx_db_dyn_escape_string(opcommand->port);
	username_esc = zbx_db_dyn_escape_string(opcommand->username);
	password_esc = zbx_db_dyn_escape_string(opcommand->password);
	publickey_esc = zbx_db_dyn_escape_string(opcommand->publickey);
	privatekey_esc = zbx_db_dyn_escape_string(opcommand->privatekey);
	command_esc = zbx_db_dyn_escape_string(opcommand->command);

	ret = DBexecute(
			"insert into opcommand (operationid,type,scriptid,execute_on,port,authtype,"
				"username,password,publickey,privatekey,command)"
			" values (" ZBX_FS_UI64 ",%d,null,%d,'%s',"
				"%d,'%s','%s','%s','%s','%s')",
			operationid, opcommand->type, opcommand->execute_on, port_esc, opcommand->authtype,
				username_esc, password_esc, publickey_esc, privatekey_esc, command_esc);

	zbx_free(port_esc);
	zbx_free(username_esc);
	zbx_free(password_esc);
	zbx_free(publickey_esc);
	zbx_free(privatekey_esc);
	zbx_free(command_esc);

	if (ZBX_DB_OK > ret)
		return FAIL;

	for (target = opcommand->targets; 0 != target->id; target++)
	{
		switch (target->type)
		{
			case OP_COMMAND_CURRENT_HOST:
				if (ZBX_DB_OK > DBexecute(
						"insert into opcommand_hst (opcommand_hstid,operationid,hostid)"
						" values (" ZBX_FS_UI64 "," ZBX_FS_UI64 ",null)",
						target->id, operationid))
				{
					return FAIL;
				}
				break;
			default:
				THIS_SHOULD_NEVER_HAPPEN;
				return FAIL;
		}
	}

	return SUCCEED;
}

static int	db_insert_condition(zbx_uint64_t actionid, const condition_t *condition)
{
	char	*value_esc = NULL;
	int	ret;

	value_esc = zbx_db_dyn_escape_string(condition->value);

	ret = DBexecute(
			"insert into conditions (conditionid,actionid,conditiontype,operator,value)"
			" values (" ZBX_FS_UI64 "," ZBX_FS_UI64 ",%d,%d,'%s')",
			condition->id, actionid, condition->conditiontype, condition->operator, value_esc);

	zbx_free(value_esc);

	return ZBX_DB_OK > ret ? FAIL : SUCCEED;
}

static int	add_actions(const action_t *actions)
{
	const action_t		*action;
	const operation_t	*operation;
	const condition_t	*condition;

	for (action = actions; 0 != action->id; action++)
	{
		if (SUCCEED != db_insert_action(action))
			return FAIL;

		for (operation = action->operations; 0 != operation->id; operation++)
		{
			int	operationtype;

			switch (operation->type)
			{
				case OP_MESSAGE:
					operationtype = 0;	/* OPERATION_TYPE_MESSAGE */
					break;
				case OP_COMMAND:
					operationtype = 1;	/* OPERATION_TYPE_COMMAND */
					break;
				default:
					THIS_SHOULD_NEVER_HAPPEN;
					return FAIL;
			}

			if (ZBX_DB_OK > DBexecute(
					"insert into operations (operationid,actionid,operationtype)"
					" values (" ZBX_FS_UI64 "," ZBX_FS_UI64 ",%d)",
					operation->id, action->id, operationtype))
			{
				return FAIL;
			}

			switch (operation->type)
			{
				case OP_MESSAGE:
					if (SUCCEED != db_insert_opmessage(operation->id, &operation->data.opmessage))
						return FAIL;
					break;
				case OP_COMMAND:
					if (SUCCEED != db_insert_opcommand(operation->id, &operation->data.opcommand))
						return FAIL;
					break;
				default:
					THIS_SHOULD_NEVER_HAPPEN;
					return FAIL;
			}
		}

		for (condition = action->conditions; 0 != condition->id; condition++)
		{
			if (SUCCEED != db_insert_condition(action->id, condition))
				return FAIL;
		}
	}

	if (ZBX_DB_OK > DBexecute(
			"delete from ids"
			" where table_name in ('actions','operations','opmessage','opmessage_usr','conditions')"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static const action_t	actions[] = {
	{110,	"Probes-Mon",		3600,
		"probes#{TRIGGER.STATUS}#{HOST.NAME1}#{ITEM.NAME1}#{ITEM.VALUE1}",	"{EVENT.DATE} {EVENT.TIME} UTC",
		1,
		"probes#{TRIGGER.STATUS}#{HOST.NAME1}#{ITEM.NAME1}#{ITEM.VALUE1}",	"{EVENT.DATE} {EVENT.TIME} UTC",
		{
			{110,	OP_MESSAGE,	{.opmessage = {1,	"",	"",	10,	{
					{110,	OP_MESSAGE_USR,	{.userid = 100}},
					{0}
			}}}},
			{0}
		},
		{
			{110,	16,	7,	""},
			{111,	5,	0,	"1"},
			{112,	4,	5,	"2"},
			{113,	0,	0,	"130"},
			{0}
		}
	},
	{120,	"Central-Server",	3600,
		"system#{TRIGGER.STATUS}#{HOST.NAME1}#{ITEM.NAME1}#{ITEM.VALUE1}",	"{EVENT.DATE} {EVENT.TIME} UTC",
		1,
		"system#{TRIGGER.STATUS}#{HOST.NAME1}#{ITEM.NAME1}#{ITEM.VALUE1}",	"{EVENT.DATE} {EVENT.TIME} UTC",
		{
			{120,	OP_MESSAGE,	{.opmessage = {1,	"",	"",	10,	{
					{120,	OP_MESSAGE_USR,	{.userid = 100}},
					{0}
			}}}},
			{0}
		},
		{
			{120,	16,	7,	""},
			{121,	5,	0,	"1"},
			{122,	4,	5,	"2"},
			{123,	0,	0,	"110"},
			{0}
		}
	},
	{130,	"TLDs",			3600,
		"tld#{TRIGGER.STATUS}#{HOST.NAME1}#{ITEM.NAME1}#{ITEM.VALUE1}",		"{EVENT.DATE} {EVENT.TIME} UTC",
		1,
		"tld#{TRIGGER.STATUS}#{HOST.NAME1}#{ITEM.NAME1}#{ITEM.VALUE1}",		"{EVENT.DATE} {EVENT.TIME} UTC",
		{
			{130,	OP_MESSAGE,	{.opmessage = {1,	"",	"",	10,	{
					{130,	OP_MESSAGE_USR,	{.userid = 100}},
					{0}
			}}}},
			{0}
		},
		{
			{130,	16,	7,	""},
			{131,	5,	0,	"1"},
			{132,	0,	0,	"140"},
			{0}
		}
	},
	{0}
};

static int	DBpatch_3000121(void)
{
	if (0 != (program_type & ZBX_PROGRAM_TYPE_PROXY))
		return SUCCEED;

	return add_actions(actions);
}

static int	DBpatch_3000122(void)
{
	char	*params_esc = NULL;
	int	ret;

	if (0 != (program_type & ZBX_PROGRAM_TYPE_PROXY))
		return SUCCEED;

	params_esc = zbx_db_dyn_escape_string("{ALERT.SENDTO}\n{ALERT.SUBJECT}\n{ALERT.MESSAGE}\n");

	ret = DBexecute("update media_type set exec_params='%s' where mediatypeid=10", params_esc);

	zbx_free(params_esc);

	return ZBX_DB_OK > ret ? FAIL : SUCCEED;
}

static int	DBpatch_3000123(void)
{
	if (0 != (program_type & ZBX_PROGRAM_TYPE_PROXY))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("update hosts_groups set groupid=110 where hostgroupid=1001"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_3000124(void)
{
	if (0 != (program_type & ZBX_PROGRAM_TYPE_PROXY))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("update rights set id=130 where rightid=106"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_3000125(void)
{
	char	*key_esc = NULL;
	int	ret;

	if (0 != (program_type & ZBX_PROGRAM_TYPE_PROXY))
		return SUCCEED;

	key_esc = zbx_db_dyn_escape_string("zabbix[proxy,{$RSM.PROXY_NAME},lastaccess]");

	ret = DBexecute("update items set name='Availability of probe' where key_='%s'", key_esc);

	zbx_free(key_esc);

	return ZBX_DB_OK > ret ? FAIL : SUCCEED;
}

static int	DBpatch_3000126(void)
{
	if (0 != (program_type & ZBX_PROGRAM_TYPE_PROXY))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute(
			"delete from hosts_groups"
			" where hostid in (select * from ("
				"select hostid from hosts_groups"
				" where groupid=120) as probes)"	/* groupid of "Probes" host group */
			" and groupid<>120"))				/* groupid of "Probes" host group */
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_3000127(void)
{
	if (0 != (program_type & ZBX_PROGRAM_TYPE_PROXY))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute(
			"delete from hosts_groups"
			" where hostid in (select * from ("
				"select hostid from hosts_groups"
				" where groupid=140) as tlds)"		/* groupid of "TLDs" host group */
			" and groupid not in (140,150,160,170,180)"))	/* groupids of host groups: "TLDs", "gTLD", */
	{								/* "ccTLD", "testTLD" and "otherTLD" */
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_3000128(void)
{
	if (0 != (program_type & ZBX_PROGRAM_TYPE_PROXY))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("update functions set parameter='10s' where function='fuzzytime'"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_3000129(void)
{
	char	*desc_esc = NULL;
	int	ret;

	if (0 != (program_type & ZBX_PROGRAM_TYPE_PROXY))
		return SUCCEED;

	desc_esc = zbx_db_dyn_escape_string("System time on {HOST.HOST} is out of sync with Zabbix Server");

	ret = DBexecute("update triggers set description='%s' where triggerid=13509 or templateid=13509", desc_esc);

	zbx_free(desc_esc);

	return ZBX_DB_OK > ret ? FAIL : SUCCEED;
}

static int	DBpatch_3000130(void)
{
	const ZBX_TABLE table =
			{"lastvalue", "itemid", 0,
				{
					{"itemid", NULL, NULL, NULL, 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0},
					{"clock", "0", NULL, NULL, 0, ZBX_TYPE_INT, ZBX_NOTNULL, 0},
					{"value", "0.0000", NULL, NULL, 0, ZBX_TYPE_FLOAT, ZBX_NOTNULL, 0},
					{0}
				},
				NULL
			};

	return DBcreate_table(&table);
}

static int	DBpatch_3000131(void)
{
	const ZBX_FIELD	field = {"itemid", NULL, "items", "itemid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("lastvalue", 1, &field);
}

static int	DBpatch_3000132(void)
{
	return DBpatch_3000122();
}

static int	DBpatch_3000133(void)
{
	if (ZBX_DB_OK > DBexecute(
			"update actions"
			" set r_longdata='{EVENT.RECOVERY.DATE} {EVENT.RECOVERY.TIME} UTC'"
			" where actionid in (100,110,120,130)"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	move_interface(zbx_uint64_t interfaceid)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	nextid;

	if (NULL == (result = DBselect("select max(interfaceid)+1 from interface")))
		return FAIL;

	if (NULL == (row = DBfetch(result)))
	{
		THIS_SHOULD_NEVER_HAPPEN;	/* there is "Zabbix Server" host, 'interface' table can't be empty */
		DBfree_result(result);
		return FAIL;
	}

	ZBX_STR2UINT64(nextid, row[0]);
	DBfree_result(result);

	if (ZBX_DB_OK > DBexecute(
			"insert into interface (interfaceid,hostid,main,type,useip,ip,dns,port,bulk)"
			" select " ZBX_FS_UI64 ",hostid,main,type,useip,ip,dns,port,bulk from interface"
			" where interfaceid=" ZBX_FS_UI64,
			nextid, interfaceid))
	{
		return FAIL;
	}

	if (ZBX_DB_OK > DBexecute(
			"update items set interfaceid=" ZBX_FS_UI64
			" where interfaceid=" ZBX_FS_UI64,
			nextid, interfaceid))
	{
		return FAIL;
	}

	if (ZBX_DB_OK > DBexecute(
			"update interface_discovery set interfaceid=" ZBX_FS_UI64
			" where interfaceid=" ZBX_FS_UI64,
			nextid, interfaceid))
	{
		return FAIL;
	}

	if (ZBX_DB_OK > DBexecute(
			"update interface_discovery set parent_interfaceid=" ZBX_FS_UI64
			" where parent_interfaceid=" ZBX_FS_UI64,
			nextid, interfaceid))
	{
		return FAIL;
	}

	if (ZBX_DB_OK > DBexecute("delete from interface where interfaceid=" ZBX_FS_UI64, interfaceid))
		return FAIL;

	return SUCCEED;
}

static int	reserve_interfaceid(zbx_uint64_t interfaceid)
{
	DB_RESULT	result;
	DB_ROW		row;

	if (NULL == (result = DBselect("select null from interface where interfaceid=" ZBX_FS_UI64, interfaceid)))
		return FAIL;

	row = DBfetch(result);
	DBfree_result(result);
	return NULL == row ? SUCCEED : move_interface(interfaceid);
}

static int	DBpatch_3000134(void)
{
#define DEFAULT_INTERFACE_INSERT								\
	"insert into interface (interfaceid,hostid,main,type,useip,ip,dns,port,bulk)"		\
	" values ('" ZBX_FS_UI64 "','" ZBX_FS_UI64 "','1','1','1','127.0.0.1','','10050','1')"

	if (0 != (program_type & ZBX_PROGRAM_TYPE_PROXY))
		return SUCCEED;

	if (SUCCEED != reserve_interfaceid(2) || ZBX_DB_OK > DBexecute(DEFAULT_INTERFACE_INSERT, 2, 100000))
		return FAIL;

	if (SUCCEED != reserve_interfaceid(3) || ZBX_DB_OK > DBexecute(DEFAULT_INTERFACE_INSERT, 3, 100001))
		return FAIL;

	if (ZBX_DB_OK > DBexecute("delete from ids where table_name='interface'"))
		return FAIL;

	return SUCCEED;

#undef DEFAULT_INTERFACE_INSERT
}

static int	DBpatch_3000135(void)
{
#define RESERVE_GLOBALMACROID								\
		"update globalmacro"							\
		" set globalmacroid=(select nextid from ("				\
			"select max(globalmacroid)+1 as nextid from globalmacro) as tmp)"	\
		" where globalmacroid=" ZBX_FS_UI64

	if (0 != (program_type & ZBX_PROGRAM_TYPE_PROXY))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute(RESERVE_GLOBALMACROID, 56))
		return FAIL;

	if (ZBX_DB_OK > DBexecute(
			"insert into globalmacro (globalmacroid,macro,value)"
			" values (56,'{$MAX_CPU_LOAD}','50')"))
	{
		return FAIL;
	}

	if (ZBX_DB_OK > DBexecute(RESERVE_GLOBALMACROID, 57))
		return FAIL;

	if (ZBX_DB_OK > DBexecute(
			"insert into globalmacro (globalmacroid,macro,value)"
			" values (57,'{$MAX_RUN_PROCESSES}','1500')"))
	{
		return FAIL;
	}

	if (ZBX_DB_OK > DBexecute("delete from ids where table_name='globalmacro'"))
		return FAIL;

	return SUCCEED;

#undef RESERVE_GLOBALMACROID
}

static int	DBpatch_3000136(void)
{
#define RESERVE_HOSTMACROID								\
		"update hostmacro"							\
		" set hostmacroid=(select nextid from ("				\
			"select max(hostmacroid)+1 as nextid from hostmacro) as tmp)"	\
		" where hostmacroid=" ZBX_FS_UI64

	if (0 != (program_type & ZBX_PROGRAM_TYPE_PROXY))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute(RESERVE_HOSTMACROID, 1))
		return FAIL;

	if (ZBX_DB_OK > DBexecute(
			"insert into hostmacro (hostmacroid,hostid,macro,value)"
			" values (1,10057,'{$MAX_CPU_LOAD}','5')"))		/* hostid of "Zabbix Server" host */
	{
		return FAIL;
	}

	if (ZBX_DB_OK > DBexecute(RESERVE_HOSTMACROID, 2))
		return FAIL;

	if (ZBX_DB_OK > DBexecute(
			"insert into hostmacro (hostmacroid,hostid,macro,value)"
			" values (2,10057,'{$MAX_RUN_PROCESSES}','30')"))	/* hostid of "Zabbix Server" host */
	{
		return FAIL;
	}

	if (ZBX_DB_OK > DBexecute("delete from ids where table_name='hostmacro'"))
		return FAIL;

	return SUCCEED;

#undef RESERVE_HOSTMACROID
}

static int	DBpatch_3000137(void)
{
	if (0 != (program_type & ZBX_PROGRAM_TYPE_PROXY))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("update functions"
			" set function='avg',parameter='#2'"	/* "Processor load is too high on {HOST.NAME}" trigger */
			" where triggerid=10010"		/* triggerids on "Template OS Linux" template */
			" or triggerid=13541"))			/* and "Zabbix Server" host */
	{
		return FAIL;
	}

	if (ZBX_DB_OK > DBexecute("update triggers"
			" set expression='{12586}>{$MAX_CPU_LOAD}'"
			" where triggerid=10010"))	/* triggerid of "Processor load is too high on {HOST.NAME}" */
							/* trigger from "Template OS Linux" template */
	{
		return FAIL;
	}

	if (ZBX_DB_OK > DBexecute("update triggers"
			" set expression='{12970}>{$MAX_CPU_LOAD}'"
			" where triggerid=13541"))	/* triggerid of "Processor load is too high on {HOST.NAME}" */
							/* trigger from "Zabbix Server" host */
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_3000138(void)
{
	if (0 != (program_type & ZBX_PROGRAM_TYPE_PROXY))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("update triggers"
			" set expression='{12555}>{$MAX_RUN_PROCESSES}'"
			" where triggerid=10011"))	/* triggerid of "Too many processes running on {HOST.NAME}" */
							/* trigger from "Template OS Linux" template */
	{
		return FAIL;
	}

	if (ZBX_DB_OK > DBexecute("update triggers"
			" set expression='{12968}>{$MAX_RUN_PROCESSES}'"
			" where triggerid=13539"))	/* triggerid of "Too many processes running on {HOST.NAME}" */
							/* trigger from "Zabbix Server" host */
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_3000139(void)
{
	return SUCCEED;
}

static int	DBpatch_3000140(void)
{
	if (0 != (program_type & ZBX_PROGRAM_TYPE_PROXY))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("update config set refresh_unsupported=60"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_3000141(void)
{
	if (0 != (program_type & ZBX_PROGRAM_TYPE_PROXY))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("insert into rsm_status_map (id,name) values ('7','Activated'),('8','Deactivated')"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_3000200(void)
{
	return SUCCEED;
}

static int	DBpatch_3000211(void)
{
	/* this is just a direct paste from data.tmpl, with each line quoted and properly indented */
	static const char	*const data[] = {
		"ROW   |13000    |130       |-1   |Internal error                                                                                                  |",
		"ROW   |13001    |130       |-200 |No reply from RDDS43 server (obsolete)                                                                          |",
		"ROW   |13002    |130       |-201 |Whois server returned no NS                                                                                     |",
		"ROW   |13003    |130       |-202 |No Unix timestamp (obsolete)                                                                                    |",
		"ROW   |13004    |130       |-203 |Invalid Unix timestamp (obsolete)                                                                               |",
		"ROW   |13005    |130       |-204 |No reply from RDDS80 server (obsolete)                                                                          |",
		"ROW   |13006    |130       |-205 |Cannot resolve a Whois host name (obsolete)                                                                     |",
		"ROW   |13007    |130       |-206 |no HTTP status code                                                                                             |",
		"ROW   |13008    |130       |-207 |invalid HTTP status code (obsolete)                                                                             |",
		"ROW   |13009    |130       |-222 |RDDS43 - No reply from local resolver                                                                           |",
		"ROW   |13011    |130       |-224 |RDDS43 - Expecting NOERROR RCODE but got SERVFAIL when resolving hostname                                       |",
		"ROW   |13012    |130       |-225 |RDDS43 - Expecting NOERROR RCODE but got NXDOMAIN when resolving hostname                                       |",
		"ROW   |13013    |130       |-226 |RDDS43 - Expecting NOERROR RCODE but got unexpected error when resolving hostname                               |",
		"ROW   |13014    |130       |-227 |RDDS43 - Timeout                                                                                                |",
		"ROW   |13015    |130       |-228 |RDDS43 - Error opening connection to server                                                                     |",
		"ROW   |13016    |130       |-229 |RDDS43 - Empty response                                                                                         |",
		"ROW   |13017    |130       |-250 |RDDS80 - No reply from local resolver                                                                           |",
		"ROW   |13019    |130       |-252 |RDDS80 - Expecting NOERROR RCODE but got SERVFAIL when resolving hostname                                       |",
		"ROW   |13020    |130       |-253 |RDDS80 - Expecting NOERROR RCODE but got NXDOMAIN when resolving hostname                                       |",
		"ROW   |13021    |130       |-254 |RDDS80 - Expecting NOERROR RCODE but got unexpected error when resolving hostname                               |",
		"ROW   |13022    |130       |-255 |RDDS80 - Timeout                                                                                                |",
		"ROW   |13023    |130       |-256 |RDDS80 - Error opening connection to server                                                                     |",
		"ROW   |13024    |130       |-257 |RDDS80 - Error in HTTP protocol                                                                                 |",
		"ROW   |13025    |130       |-258 |RDDS80 - Error in HTTPS protocol                                                                                |",
		"-- Error code for every assigned HTTP status code (with the exception of HTTP/200)                                                                 ",
		"-- as per: http://www.iana.org/assignments/http-status-codes/http-status-codes.xhtml                                                               ",
		"ROW   |13026    |130       |-300 |RDDS80 - Expecting HTTP status code 200 but got 100                                                             |",
		"ROW   |13027    |130       |-301 |RDDS80 - Expecting HTTP status code 200 but got 101                                                             |",
		"ROW   |13028    |130       |-302 |RDDS80 - Expecting HTTP status code 200 but got 102                                                             |",
		"ROW   |13029    |130       |-303 |RDDS80 - Expecting HTTP status code 200 but got 103                                                             |",
		"ROW   |13030    |130       |-304 |RDDS80 - Expecting HTTP status code 200 but got 201                                                             |",
		"ROW   |13031    |130       |-305 |RDDS80 - Expecting HTTP status code 200 but got 202                                                             |",
		"ROW   |13032    |130       |-306 |RDDS80 - Expecting HTTP status code 200 but got 203                                                             |",
		"ROW   |13033    |130       |-307 |RDDS80 - Expecting HTTP status code 200 but got 204                                                             |",
		"ROW   |13034    |130       |-308 |RDDS80 - Expecting HTTP status code 200 but got 205                                                             |",
		"ROW   |13035    |130       |-309 |RDDS80 - Expecting HTTP status code 200 but got 206                                                             |",
		"ROW   |13036    |130       |-310 |RDDS80 - Expecting HTTP status code 200 but got 207                                                             |",
		"ROW   |13037    |130       |-311 |RDDS80 - Expecting HTTP status code 200 but got 208                                                             |",
		"ROW   |13038    |130       |-312 |RDDS80 - Expecting HTTP status code 200 but got 226                                                             |",
		"ROW   |13039    |130       |-313 |RDDS80 - Expecting HTTP status code 200 but got 300                                                             |",
		"ROW   |13040    |130       |-314 |RDDS80 - Expecting HTTP status code 200 but got 301                                                             |",
		"ROW   |13041    |130       |-315 |RDDS80 - Expecting HTTP status code 200 but got 302                                                             |",
		"ROW   |13042    |130       |-316 |RDDS80 - Expecting HTTP status code 200 but got 303                                                             |",
		"ROW   |13043    |130       |-317 |RDDS80 - Expecting HTTP status code 200 but got 304                                                             |",
		"ROW   |13044    |130       |-318 |RDDS80 - Expecting HTTP status code 200 but got 305                                                             |",
		"ROW   |13045    |130       |-319 |RDDS80 - Expecting HTTP status code 200 but got 306                                                             |",
		"ROW   |13046    |130       |-320 |RDDS80 - Expecting HTTP status code 200 but got 307                                                             |",
		"ROW   |13047    |130       |-321 |RDDS80 - Expecting HTTP status code 200 but got 308                                                             |",
		"ROW   |13048    |130       |-322 |RDDS80 - Expecting HTTP status code 200 but got 400                                                             |",
		"ROW   |13049    |130       |-323 |RDDS80 - Expecting HTTP status code 200 but got 401                                                             |",
		"ROW   |13050    |130       |-324 |RDDS80 - Expecting HTTP status code 200 but got 402                                                             |",
		"ROW   |13051    |130       |-325 |RDDS80 - Expecting HTTP status code 200 but got 403                                                             |",
		"ROW   |13052    |130       |-326 |RDDS80 - Expecting HTTP status code 200 but got 404                                                             |",
		"ROW   |13053    |130       |-327 |RDDS80 - Expecting HTTP status code 200 but got 405                                                             |",
		"ROW   |13054    |130       |-328 |RDDS80 - Expecting HTTP status code 200 but got 406                                                             |",
		"ROW   |13055    |130       |-329 |RDDS80 - Expecting HTTP status code 200 but got 407                                                             |",
		"ROW   |13056    |130       |-330 |RDDS80 - Expecting HTTP status code 200 but got 408                                                             |",
		"ROW   |13057    |130       |-331 |RDDS80 - Expecting HTTP status code 200 but got 409                                                             |",
		"ROW   |13058    |130       |-332 |RDDS80 - Expecting HTTP status code 200 but got 410                                                             |",
		"ROW   |13059    |130       |-333 |RDDS80 - Expecting HTTP status code 200 but got 411                                                             |",
		"ROW   |13060    |130       |-334 |RDDS80 - Expecting HTTP status code 200 but got 412                                                             |",
		"ROW   |13061    |130       |-335 |RDDS80 - Expecting HTTP status code 200 but got 413                                                             |",
		"ROW   |13062    |130       |-336 |RDDS80 - Expecting HTTP status code 200 but got 414                                                             |",
		"ROW   |13063    |130       |-337 |RDDS80 - Expecting HTTP status code 200 but got 415                                                             |",
		"ROW   |13064    |130       |-338 |RDDS80 - Expecting HTTP status code 200 but got 416                                                             |",
		"ROW   |13065    |130       |-339 |RDDS80 - Expecting HTTP status code 200 but got 417                                                             |",
		"ROW   |13066    |130       |-340 |RDDS80 - Expecting HTTP status code 200 but got 421                                                             |",
		"ROW   |13067    |130       |-341 |RDDS80 - Expecting HTTP status code 200 but got 422                                                             |",
		"ROW   |13068    |130       |-342 |RDDS80 - Expecting HTTP status code 200 but got 423                                                             |",
		"ROW   |13069    |130       |-343 |RDDS80 - Expecting HTTP status code 200 but got 424                                                             |",
		"ROW   |13070    |130       |-344 |RDDS80 - Expecting HTTP status code 200 but got 426                                                             |",
		"ROW   |13071    |130       |-345 |RDDS80 - Expecting HTTP status code 200 but got 428                                                             |",
		"ROW   |13072    |130       |-346 |RDDS80 - Expecting HTTP status code 200 but got 429                                                             |",
		"ROW   |13073    |130       |-347 |RDDS80 - Expecting HTTP status code 200 but got 431                                                             |",
		"ROW   |13074    |130       |-348 |RDDS80 - Expecting HTTP status code 200 but got 451                                                             |",
		"ROW   |13075    |130       |-349 |RDDS80 - Expecting HTTP status code 200 but got 500                                                             |",
		"ROW   |13076    |130       |-350 |RDDS80 - Expecting HTTP status code 200 but got 501                                                             |",
		"ROW   |13077    |130       |-351 |RDDS80 - Expecting HTTP status code 200 but got 502                                                             |",
		"ROW   |13078    |130       |-352 |RDDS80 - Expecting HTTP status code 200 but got 503                                                             |",
		"ROW   |13079    |130       |-353 |RDDS80 - Expecting HTTP status code 200 but got 504                                                             |",
		"ROW   |13080    |130       |-354 |RDDS80 - Expecting HTTP status code 200 but got 505                                                             |",
		"ROW   |13081    |130       |-355 |RDDS80 - Expecting HTTP status code 200 but got 506                                                             |",
		"ROW   |13082    |130       |-356 |RDDS80 - Expecting HTTP status code 200 but got 507                                                             |",
		"ROW   |13083    |130       |-357 |RDDS80 - Expecting HTTP status code 200 but got 508                                                             |",
		"ROW   |13084    |130       |-358 |RDDS80 - Expecting HTTP status code 200 but got 510                                                             |",
		"ROW   |13085    |130       |-359 |RDDS80 - Expecting HTTP status code 200 but got 511                                                             |",
		"ROW   |13086    |130       |-360 |RDDS80 - Expecting HTTP status code 200 but got unexpected status code                                          |",
		NULL
	};
	int			i;

	if (0 != (program_type & ZBX_PROGRAM_TYPE_PROXY))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("delete from mappings where valuemapid=130"))	/* valuemapid of "RSM RDDS rtt" */
		return FAIL;

	for (i = 0; NULL != data[i]; i++)
	{
		zbx_uint64_t	mappingid, valuemapid;
		char		*value = NULL, *newvalue = NULL, *value_esc, *newvalue_esc;

		if (0 == strncmp(data[i], "--", ZBX_CONST_STRLEN("--")))
			continue;

		if (4 != sscanf(data[i], "ROW |" ZBX_FS_UI64 " |" ZBX_FS_UI64 " |%m[^|]|%m[^|]|",
				&mappingid, &valuemapid, &value, &newvalue))
		{
			zabbix_log(LOG_LEVEL_CRIT, "failed to parse the following line:\n%s", data[i]);
			zbx_free(value);
			zbx_free(newvalue);
			return FAIL;
		}

		zbx_rtrim(value, ZBX_WHITESPACE);
		zbx_rtrim(newvalue, ZBX_WHITESPACE);

		/* NOTE: to keep it simple assume that data does not contain sequences "&pipe;", "&eol;" or "&bsn;" */

		value_esc = zbx_db_dyn_escape_string(value);
		newvalue_esc = zbx_db_dyn_escape_string(newvalue);
		zbx_free(value);
		zbx_free(newvalue);

		if (ZBX_DB_OK > DBexecute("insert into mappings (mappingid,valuemapid,value,newvalue)"
				" values (" ZBX_FS_UI64 "," ZBX_FS_UI64 ",'%s','%s')",
				mappingid, valuemapid, value_esc, newvalue_esc))
		{
			zbx_free(value_esc);
			zbx_free(newvalue_esc);
			return FAIL;
		}

		zbx_free(value_esc);
		zbx_free(newvalue_esc);
	}

	return SUCCEED;
}

static int	DBpatch_3000202(void)
{
	/* this is just a direct paste from data.tmpl, with each line quoted and properly indented */
	static const char	*const data[] = {
		"ROW   |12000    |120       |-1   |Internal error                                                                                                  |",
		"ROW   |12001    |120       |-200 |DNS UDP - No reply from name server                                                                             |",
		"ROW   |12002    |120       |-201 |Invalid reply from Name Server (obsolete)                                                                       |",
		"ROW   |12003    |120       |-202 |No UNIX timestamp (obsolete)                                                                                    |",
		"ROW   |12004    |120       |-203 |Invalid UNIX timestamp (obsolete)                                                                               |",
		"ROW   |12005    |120       |-204 |DNSSEC error (obsolete)                                                                                         |",
		"ROW   |12006    |120       |-205 |No reply from resolver (obsolete)                                                                               |",
		"ROW   |12007    |120       |-206 |Keyset is not valid (obsolete)                                                                                  |",
		"ROW   |12008    |120       |-207 |DNS UDP - Expecting DNS CLASS IN but got CHAOS                                                                  |",
		"ROW   |12009    |120       |-208 |DNS UDP - Expecting DNS CLASS IN but got HESIOD                                                                 |",
		"ROW   |12010    |120       |-209 |DNS UDP - Expecting DNS CLASS IN but got something different than IN, CHAOS or HESIOD                           |",
		"ROW   |12011    |120       |-210 |DNS UDP - Header section incomplete                                                                             |",
		"ROW   |12012    |120       |-211 |DNS UDP - Question section incomplete                                                                           |",
		"ROW   |12013    |120       |-212 |DNS UDP - Answer section incomplete                                                                             |",
		"ROW   |12014    |120       |-213 |DNS UDP - Authority section incomplete                                                                          |",
		"ROW   |12015    |120       |-214 |DNS UDP - Additional section incomplete                                                                         |",
		"ROW   |12016    |120       |-215 |DNS UDP - Malformed DNS response                                                                                |",
		"ROW   |12017    |120       |-250 |DNS UDP - Querying for a non existent domain - AA flag not present in response                                  |",
		"ROW   |12018    |120       |-251 |DNS UDP - Querying for a non existent domain - Domain name being queried not present in question section        |",
		"-- Error code for every assigned, non private DNS RCODE (with the exception of RCODE/NXDOMAIN)                                                     ",
		"-- as per: https://www.iana.org/assignments/dns-parameters/dns-parameters.xhtml                                                                    ",
		"ROW   |12019    |120       |-252 |DNS UDP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got NOERROR                         |",
		"ROW   |12020    |120       |-253 |DNS UDP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got FORMERR                         |",
		"ROW   |12021    |120       |-254 |DNS UDP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got SERVFAIL                        |",
		"ROW   |12022    |120       |-255 |DNS UDP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got NOTIMP                          |",
		"ROW   |12023    |120       |-256 |DNS UDP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got REFUSED                         |",
		"ROW   |12024    |120       |-257 |DNS UDP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got YXDOMAIN                        |",
		"ROW   |12025    |120       |-258 |DNS UDP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got YXRRSET                         |",
		"ROW   |12026    |120       |-259 |DNS UDP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got NXRRSET                         |",
		"ROW   |12027    |120       |-260 |DNS UDP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got NOTAUTH                         |",
		"ROW   |12028    |120       |-261 |DNS UDP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got NOTZONE                         |",
		"ROW   |12029    |120       |-262 |DNS UDP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got BADVERS or BADSIG               |",
		"ROW   |12030    |120       |-263 |DNS UDP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got BADKEY                          |",
		"ROW   |12031    |120       |-264 |DNS UDP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got BADTIME                         |",
		"ROW   |12032    |120       |-265 |DNS UDP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got BADMODE                         |",
		"ROW   |12033    |120       |-266 |DNS UDP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got BADNAME                         |",
		"ROW   |12034    |120       |-267 |DNS UDP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got BADALG                          |",
		"ROW   |12035    |120       |-268 |DNS UDP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got BADTRUNC                        |",
		"ROW   |12036    |120       |-269 |DNS UDP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got BADCOOKIE                       |",
		"ROW   |12037    |120       |-270 |DNS UDP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got unexpected                      |",
		"ROW   |12038    |120       |-400 |DNS UDP - No reply from local resolver                                                                          |",
		"ROW   |12039    |120       |-401 |DNS UDP - No AD bit from local resolver                                                                         |",
		"ROW   |12040    |120       |-402 |DNS UDP - Expecting NOERROR RCODE but got SERVFAIL from local resolver                                          |",
		"ROW   |12041    |120       |-403 |DNS UDP - Expecting NOERROR RCODE but got NXDOMAIN from local resolver                                          |",
		"ROW   |12042    |120       |-404 |DNS UDP - Expecting NOERROR RCODE but got unexpecting from local resolver                                       |",
		"ROW   |12043    |120       |-405 |DNS UDP - Unknown cryptographic algorithm                                                                       |",
		"ROW   |12044    |120       |-406 |DNS UDP - Cryptographic algorithm not implemented                                                               |",
		"ROW   |12045    |120       |-407 |DNS UDP - No RRSIGs where found in any section, and the TLD has the DNSSEC flag enabled                         |",
		"ROW   |12046    |120       |-410 |DNS UDP - The signature does not cover this RRset                                                               |",
		"ROW   |12047    |120       |-414 |DNS UDP - The RRSIG found is not signed by a DNSKEY from the KEYSET of the TLD                                  |",
		"ROW   |12048    |120       |-415 |DNS UDP - Bogus DNSSEC signature                                                                                |",
		"ROW   |12049    |120       |-416 |DNS UDP - DNSSEC signature has expired                                                                          |",
		"ROW   |12050    |120       |-417 |DNS UDP - DNSSEC signature not incepted yet                                                                     |",
		"ROW   |12051    |120       |-418 |DNS UDP - DNSSEC signature has expiration date earlier than inception date                                      |",
		"ROW   |12052    |120       |-419 |DNS UDP - Error in NSEC3 denial of existence proof                                                              |",
		"ROW   |12053    |120       |-421 |DNS UDP - Iterations count for NSEC3 record higher than maximum                                                 |",
		"ROW   |12054    |120       |-422 |DNS UDP - RR not covered by the given NSEC RRs                                                                  |",
		"ROW   |12055    |120       |-423 |DNS UDP - Wildcard not covered by the given NSEC RRs                                                            |",
		"ROW   |12056    |120       |-425 |DNS UDP - The RRSIG has too few RDATA fields                                                                    |",
		"ROW   |12057    |120       |-426 |DNS UDP - The DNSKEY has too few RDATA fields                                                                   |",
		"ROW   |12058    |120       |-427 |DNS UDP - Malformed DNSSEC response                                                                             |",
		"ROW   |12059    |120       |-428 |DNS UDP - The TLD is configured as DNSSEC-enabled, but no DNSKEY was found in the apex                          |",
		"ROW   |12060    |120       |-600 |DNS TCP - Timeout reply from name server                                                                        |",
		"ROW   |12061    |120       |-601 |DNS TCP - Error opening connection to name server                                                               |",
		"ROW   |12062    |120       |-607 |DNS TCP - Expecting DNS CLASS IN but got CHAOS                                                                  |",
		"ROW   |12063    |120       |-608 |DNS TCP - Expecting DNS CLASS IN but got HESIOD                                                                 |",
		"ROW   |12064    |120       |-609 |DNS TCP - Expecting DNS CLASS IN but got something different than IN, CHAOS or HESIOD                           |",
		"ROW   |12065    |120       |-610 |DNS TCP - Header section incomplete                                                                             |",
		"ROW   |12066    |120       |-611 |DNS TCP - Question section incomplete                                                                           |",
		"ROW   |12067    |120       |-612 |DNS TCP - Answer section incomplete                                                                             |",
		"ROW   |12068    |120       |-613 |DNS TCP - Authority section incomplete                                                                          |",
		"ROW   |12069    |120       |-614 |DNS TCP - Additional section incomplete                                                                         |",
		"ROW   |12070    |120       |-615 |DNS TCP - Malformed DNS response                                                                                |",
		"ROW   |12071    |120       |-650 |DNS TCP - Querying for a non existent domain - AA flag not present in response                                  |",
		"ROW   |12072    |120       |-651 |DNS TCP - Querying for a non existent domain - Domain name being queried not present in question section        |",
		"-- Error code for every assigned, non private DNS RCODE (with the exception of RCODE/NXDOMAIN)                                                     ",
		"-- as per: https://www.iana.org/assignments/dns-parameters/dns-parameters.xhtml                                                                    ",
		"ROW   |12073    |120       |-652 |DNS TCP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got NOERROR                         |",
		"ROW   |12074    |120       |-653 |DNS TCP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got FORMERR                         |",
		"ROW   |12075    |120       |-654 |DNS TCP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got SERVFAIL                        |",
		"ROW   |12076    |120       |-655 |DNS TCP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got NOTIMP                          |",
		"ROW   |12077    |120       |-656 |DNS TCP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got REFUSED                         |",
		"ROW   |12078    |120       |-657 |DNS TCP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got YXDOMAIN                        |",
		"ROW   |12079    |120       |-658 |DNS TCP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got YXRRSET                         |",
		"ROW   |12080    |120       |-659 |DNS TCP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got NXRRSET                         |",
		"ROW   |12081    |120       |-660 |DNS TCP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got NOTAUTH                         |",
		"ROW   |12082    |120       |-661 |DNS TCP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got NOTZONE                         |",
		"ROW   |12083    |120       |-662 |DNS TCP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got BADVERS or BADSIG               |",
		"ROW   |12084    |120       |-663 |DNS TCP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got BADKEY                          |",
		"ROW   |12085    |120       |-664 |DNS TCP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got BADTIME                         |",
		"ROW   |12086    |120       |-665 |DNS TCP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got BADMODE                         |",
		"ROW   |12087    |120       |-666 |DNS TCP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got BADNAME                         |",
		"ROW   |12088    |120       |-667 |DNS TCP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got BADALG                          |",
		"ROW   |12089    |120       |-668 |DNS TCP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got BADTRUNC                        |",
		"ROW   |12090    |120       |-669 |DNS TCP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got BADCOOKIE                       |",
		"ROW   |12091    |120       |-670 |DNS TCP - Querying for a non existent domain - Expecting NXDOMAIN RCODE but got unexpected                      |",
		"ROW   |12092    |120       |-800 |DNS TCP - No reply from local resolver                                                                          |",
		"ROW   |12093    |120       |-801 |DNS TCP - No AD bit from local resolver                                                                         |",
		"ROW   |12094    |120       |-802 |DNS TCP - Expecting NOERROR RCODE but got SERVFAIL from local resolver                                          |",
		"ROW   |12095    |120       |-803 |DNS TCP - Expecting NOERROR RCODE but got NXDOMAIN from local resolver                                          |",
		"ROW   |12096    |120       |-804 |DNS TCP - Expecting NOERROR RCODE but got unexpecting from local resolver                                       |",
		"ROW   |12097    |120       |-805 |DNS TCP - Unknown cryptographic algorithm                                                                       |",
		"ROW   |12098    |120       |-806 |DNS TCP - Cryptographic algorithm not implemented                                                               |",
		"ROW   |12099    |120       |-807 |DNS TCP - No RRSIGs where found in any section, and the TLD has the DNSSEC flag enabled                         |",
		"ROW   |12100    |120       |-810 |DNS TCP - The signature does not cover this RRset                                                               |",
		"ROW   |12101    |120       |-814 |DNS TCP - The RRSIG found is not signed by a DNSKEY from the KEYSET of the TLD                                  |",
		"ROW   |12102    |120       |-815 |DNS TCP - Bogus DNSSEC signature                                                                                |",
		"ROW   |12103    |120       |-816 |DNS TCP - DNSSEC signature has expired                                                                          |",
		"ROW   |12104    |120       |-817 |DNS TCP - DNSSEC signature not incepted yet                                                                     |",
		"ROW   |12105    |120       |-818 |DNS TCP - DNSSEC signature has expiration date earlier than inception date                                      |",
		"ROW   |12106    |120       |-819 |DNS TCP - Error in NSEC3 denial of existence proof                                                              |",
		"ROW   |12107    |120       |-821 |DNS TCP - Iterations count for NSEC3 record higher than maximum                                                 |",
		"ROW   |12108    |120       |-822 |DNS TCP - RR not covered by the given NSEC RRs                                                                  |",
		"ROW   |12109    |120       |-823 |DNS TCP - Wildcard not covered by the given NSEC RRs                                                            |",
		"ROW   |12110    |120       |-825 |DNS TCP - The RRSIG has too few RDATA fields                                                                    |",
		"ROW   |12111    |120       |-826 |DNS TCP - The DNSKEY has too few RDATA fields                                                                   |",
		"ROW   |12112    |120       |-827 |DNS TCP - Malformed DNSSEC response                                                                             |",
		"ROW   |12113    |120       |-828 |DNS TCP - The TLD is configured as DNSSEC-enabled, but no DNSKEY was found in the apex                          |",
		NULL
	};
	int			i;

	if (0 != (program_type & ZBX_PROGRAM_TYPE_PROXY))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("delete from mappings where valuemapid=120"))	/* valuemapid of "RSM DNS rtt" */
		return FAIL;

	for (i = 0; NULL != data[i]; i++)
	{
		zbx_uint64_t	mappingid, valuemapid;
		char		*value = NULL, *newvalue = NULL, *value_esc, *newvalue_esc;

		if (0 == strncmp(data[i], "--", ZBX_CONST_STRLEN("--")))
			continue;

		if (4 != sscanf(data[i], "ROW |" ZBX_FS_UI64 " |" ZBX_FS_UI64 " |%m[^|]|%m[^|]|",
				&mappingid, &valuemapid, &value, &newvalue))
		{
			zabbix_log(LOG_LEVEL_CRIT, "failed to parse the following line:\n%s", data[i]);
			zbx_free(value);
			zbx_free(newvalue);
			return FAIL;
		}

		zbx_rtrim(value, ZBX_WHITESPACE);
		zbx_rtrim(newvalue, ZBX_WHITESPACE);

		/* NOTE: to keep it simple assume that data does not contain sequences "&pipe;", "&eol;" or "&bsn;" */

		value_esc = zbx_db_dyn_escape_string(value);
		newvalue_esc = zbx_db_dyn_escape_string(newvalue);
		zbx_free(value);
		zbx_free(newvalue);

		if (ZBX_DB_OK > DBexecute("insert into mappings (mappingid,valuemapid,value,newvalue)"
				" values (" ZBX_FS_UI64 "," ZBX_FS_UI64 ",'%s','%s')",
				mappingid, valuemapid, value_esc, newvalue_esc))
		{
			zbx_free(value_esc);
			zbx_free(newvalue_esc);
			return FAIL;
		}

		zbx_free(value_esc);
		zbx_free(newvalue_esc);
	}

	return SUCCEED;
}

static int	DBpatch_3000203(void)
{
	if (0 != (program_type & ZBX_PROGRAM_TYPE_PROXY))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("insert into mappings (mappingid,valuemapid,value,newvalue) values"
			" (11002,110,'2','Up-inconclusive-no-data'),"
			" (11003,110,'3','Up-inconclusive-no-probes')"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_3000204(void)
{
	if (0 != (program_type & ZBX_PROGRAM_TYPE_PROXY))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("insert into rsm_status_map (id,name) values"
			" (9,'Up-inconclusive-no-data'),"
			" (10,'Up-inconclusive-no-probes')"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_3000205(void)
{
#define RESERVE_GLOBALMACROID									\
		"update globalmacro"								\
		" set globalmacroid=(select nextid from ("					\
			"select max(globalmacroid)+1 as nextid from globalmacro) as tmp)"	\
		" where globalmacroid=" ZBX_FS_UI64

	if (0 != (program_type & ZBX_PROGRAM_TYPE_PROXY))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute(RESERVE_GLOBALMACROID, 66))
		return FAIL;

	if (ZBX_DB_OK > DBexecute(
			"insert into globalmacro (globalmacroid,macro,value)"
			" values (66,'{$PROBE.INTERNAL.ERROR.INTERVAL}','5m')"))
	{
		return FAIL;
	}

	if (ZBX_DB_OK > DBexecute("delete from ids where table_name='globalmacro'"))
		return FAIL;

	return SUCCEED;

#undef RESERVE_GLOBALMACROID
}

static int	DBpatch_3000206(void)
{
	if (0 != (program_type & ZBX_PROGRAM_TYPE_PROXY))
		return SUCCEED;

	/* create "Template Probe Errors" */
	if (ZBX_DB_OK > DBexecute(
			"insert into hosts (hostid,proxy_hostid,host,status,"
				"ipmi_authtype,ipmi_privilege,ipmi_username,ipmi_password,name,flags,templateid,"
				"description,tls_connect,tls_accept,tls_issuer,tls_subject,tls_psk_identity,tls_psk)"
			" values ('99990',NULL,'Template Probe Errors','3',"
				"'0','2','','','Template Probe Errors','0',NULL,"
				"'','1','1','','','','')"))
	{
		return FAIL;
	}

	/* add it to "Templates" host group */
	if (ZBX_DB_OK > DBexecute("insert into hosts_groups (hostgroupid,hostid,groupid) values ('999','99990','1')"))
		return FAIL;

	/* add "Internal errors" application */
	if (ZBX_DB_OK > DBexecute(
			"insert into applications (applicationid,hostid,name,flags)"
			" values ('999','99990','Internal errors','0')"))
	{
		return FAIL;
	}

	/* add "Internal error rate" item */
	if (ZBX_DB_OK > DBexecute(
			"insert into items (itemid,type,snmp_community,snmp_oid,hostid,name,key_,delay,history,trends,"
				"status,value_type,trapper_hosts,units,multiplier,delta,"
				"snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,"
				"formula,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,"
				"authtype,username,password,publickey,privatekey,flags,interfaceid,port,description,"
				"inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,snmpv3_contextname,evaltype)"
			" values ('99990','3','','','99990','Internal error rate','rsm.errors','60','90','365',"
				"'0','0','','','0','1',"
				"'','0','','',"
				"'1','',NULL,NULL,'','','','0',"
				"'0','','','','','0',NULL,'','',"
				"'0','30','0','0','','0')"))
	{
		return FAIL;
	}

	/* put item into application */
	if (ZBX_DB_OK > DBexecute(
			"insert into items_applications (itemappid,applicationid,itemid)"
			" values ('99990','999','99990')"))
	{
		return FAIL;
	}

	/* add triggers... */
	if (ZBX_DB_OK > DBexecute(
			"insert into triggers (triggerid,expression,description,url,status,priority,comments,templateid,type,flags)"
			" values"
				" ('99990','{99990}>0','Internal errors happening for {$PROBE.INTERNAL.ERROR.INTERVAL}','','0','4','',NULL,'0','0'),"
				" ('99991','{99991}>0','Internal errors happening','','0','2','',NULL,'0','0')"))
	{
		return FAIL;
	}

	/* ...and trigger functions */
	if (ZBX_DB_OK > DBexecute(
			"insert into functions (functionid,itemid,triggerid,function,parameter)"
			" values"
				" ('99990','99990','99990','min','{$PROBE.INTERNAL.ERROR.INTERVAL}'),"
				" ('99991','99990','99991','last','')"))
	{
		return FAIL;
	}

	/* add dependency */

#define RESERVE_TRIGGERDEPID									\
		"update trigger_depends"							\
		" set triggerdepid=(select nextid from ("					\
			"select max(triggerdepid)+1 as nextid from trigger_depends) as tmp)"	\
		" where triggerdepid=" ZBX_FS_UI64

	if (ZBX_DB_OK > DBexecute(RESERVE_TRIGGERDEPID, 1))
		return FAIL;

	if (ZBX_DB_OK > DBexecute(
			"insert into trigger_depends (triggerdepid,triggerid_down,triggerid_up)"
			" values ('1','99991','99990')"))
	{
		return FAIL;
	}

	if (ZBX_DB_OK > DBexecute("delete from ids where table_name='trigger_depends'"))
		return FAIL;

#undef RESERVE_TRIGGERDEPID

	return SUCCEED;
}

static int	DBpatch_3000210(void)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		ret = SUCCEED;

	if (0 != (program_type & ZBX_PROGRAM_TYPE_PROXY))
		return SUCCEED;

	result = DBselect("select h.hostid from hosts h,hosts_groups hg where h.hostid=hg.hostid and hg.groupid=120");

	if (NULL == result)
		return FAIL;

	while (NULL != (row = DBfetch(result)) && SUCCEED == ret)
	{
		zbx_uint64_t		hostid;
		zbx_vector_uint64_t	templateids;

		ZBX_STR2UINT64(hostid, row[0]);			/* hostid of probe host */
		zbx_vector_uint64_create(&templateids);
		zbx_vector_uint64_reserve(&templateids, 1);
		zbx_vector_uint64_append(&templateids, 99990);	/* hostid of "Template Probe Errors" */

		ret = DBcopy_template_elements(hostid, &templateids);

		zbx_vector_uint64_destroy(&templateids);
	}

	DBfree_result(result);

	return ret;
}

static const action_t	two_more_actions[] = {
	{115,	"Probes",		3600,
		"probes#{TRIGGER.STATUS}#{HOST.NAME1}#{ITEM.NAME1}#{ITEM.VALUE1}",	"{EVENT.DATE} {EVENT.TIME} UTC",
		1,
		"probes#{TRIGGER.STATUS}#{HOST.NAME1}#{ITEM.NAME1}#{ITEM.VALUE1}",	"{EVENT.RECOVERY.DATE} {EVENT.RECOVERY.TIME} UTC",
		{
			{115,	OP_MESSAGE,	{.opmessage = {1,	"",	"",	10,
				{
					{115,	OP_MESSAGE_USR,	{.userid = 100}},
					{0}
				}
			}}},
			{0}
		},
		{
			{115,	16,	7,	""},
			{116,	5,	0,	"1"},
			{117,	4,	5,	"2"},
			{118,	0,	0,	"120"},
			{0}
		}
	},
	{140,	"Probes-Knockout",	3600,
		"probes#{TRIGGER.STATUS}#{HOST.NAME1}#{ITEM.NAME1}#{ITEM.VALUE1}",	"{EVENT.DATE} {EVENT.TIME} UTC",
		1,
		"probes#{TRIGGER.STATUS}#{HOST.NAME1}#{ITEM.NAME1}#{ITEM.VALUE1}",	"{EVENT.RECOVERY.DATE} {EVENT.RECOVERY.TIME} UTC",
		{
			{140,	OP_COMMAND,	{.opcommand = {0,	1,	"",	0,	"",	"",	"",	"",
				"/opt/zabbix/scripts/probe-manual.pl --probe \'{HOST.HOST}\' --set 0",
				{
					{140,	OP_COMMAND_CURRENT_HOST},
					{0}
				}
			}}},
			{0}
		},
		{
			{140,	16,	7,	""},
			{141,	5,	0,	"1"},
			{142,	4,	5,	"2"},
			{143,	0,	0,	"120"},
			{144,	2,	0,	"99990"},
			{0}
		}
	},
	{0}
};

static int	DBpatch_3000208(void)
{
	if (0 != (program_type & ZBX_PROGRAM_TYPE_PROXY))
		return SUCCEED;

	return add_actions(two_more_actions);
}

static int	DBpatch_3000212(void)
{
	if (0 != (program_type & ZBX_PROGRAM_TYPE_PROXY))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute("insert into valuemaps (valuemapid,name) values ('135','RSM RDAP rtt')"))
		return FAIL;

	return SUCCEED;
}

static int	DBpatch_3000213(void)
{
	if (0 != (program_type & ZBX_PROGRAM_TYPE_PROXY))
		return SUCCEED;

	if (ZBX_DB_OK > DBexecute(
			"insert into hosts ("
				"hostid,proxy_hostid,host,status,ipmi_authtype,ipmi_privilege,"
				"ipmi_username,ipmi_password,name,flags,templateid,description,"
				"tls_connect,tls_accept,tls_issuer,tls_subject,tls_psk_identity,tls_psk"
			")"
			" values ("
				"'99980',NULL,'Template RDAP','3','0','2',"
				"'','','Template RDAP','0',NULL,'',"
				"'1','1','','','',''"
			")"))
	{
		return FAIL;
	}

	if (ZBX_DB_OK > DBexecute(
			"insert into applications (applicationid,hostid,name,flags) values ('998','99980','RDAP','0')"))
	{
		return FAIL;
	}

	if (ZBX_DB_OK > DBexecute(
			"insert into items ("
				"itemid,type,snmp_community,snmp_oid,hostid,name,"
				"key_,"
				"delay,history,trends,status,value_type,trapper_hosts,units,multiplier,delta,"
				"snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,"
				"formula,logtimefmt,templateid,valuemapid,delay_flex,params,ipmi_sensor,data_type,"
				"authtype,username,password,publickey,privatekey,flags,interfaceid,port,"
				"description,"
				"inventory_link,lifetime,snmpv3_authprotocol,snmpv3_privprotocol,snmpv3_contextname,evaltype"
			")"
			" values ("
				"'99980','3','','','99980','RDAP availability',"
				"'rdap["
					"{$RSM.TLD},"
					"{$RDAP.TEST.DOMAIN},"
					"{$RDAP.BASE.URL},"
					"{$RSM.RDDS.MAXREDIRS},"
					"{$RSM.RDDS.RTT.HIGH},"
					"{$RSM.TLD.RDDS.ENABLED},"
					"{$RSM.RDDS.ENABLED},"
					"{$RSM.IP4.ENABLED},"
					"{$RSM.IP6.ENABLED},"
					"{$RSM.RESOLVER}"
					"]',"
				"'300','7','365','0','3','','','0','0',"
				"'','0','','',"
				"'1','',NULL,'1','','','','0',"
				"'0','','','','','0',NULL,'',"
				"'Status of Registration Data Access Protocol service',"
				"'0','0','0','0','','0'"
			"),"
			"("
				"'99981','2','','','99980','RDAP IP',"
				"'rdap.ip',"
				"'0','7','365','0','1','','','0','0',"
				"'','0','','',"
				"'1','',NULL,NULL,'','','','0',"
				"'0','','','','','0',NULL,'',"
				"'IP address of Registration Data Access Protocol service provider domain used to perform the test',"
				"'0','0','0','0','','0'"
			"),"
			"("
				"'99982','2','','','99980','RDAP RTT',"
				"'rdap.rtt',"
				"'0','7','365','0','0','','ms','0','0',"
				"'','0','','',"
				"'1','',NULL,'135','','','','0',"
				"'0','','','','','0',NULL,'',"
				"'Round-Trip Time of Registration Data Access Protocol service test',"
				"'0','0','0','0','','0'"
			")"))
	{
		return FAIL;
	}

	if (ZBX_DB_OK > DBexecute("insert into hosts_groups (hostgroupid,hostid,groupid) values ('998','99980','1')"))
		return FAIL;

	if (ZBX_DB_OK > DBexecute(
			"insert into items_applications (itemappid,applicationid,itemid)"
			" values ('99980','998','99980'),('99981','998','99981'),('99982','998','99982')"))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	DBpatch_3000214(void)
{
	/* this is just a direct paste from data.tmpl, with each line quoted and properly indented */
	static const char	*const data[] = {
		"ROW   |13500    |135       |-1   |Internal error                                                                                                  |",
		"ROW   |13501    |135       |-100 |The TLD is not listed in the Bootstrap Service Registry for Domain Name Space                                   |",
		"ROW   |13502    |135       |-101 |The RDAP base URL obtained from Bootstrap Service Registry for Domain Name Space does not use HTTPS             |",
		"ROW   |13503    |135       |-200 |RDAP - No reply from local resolver                                                                             |",
		"ROW   |13504    |135       |-201 |RDAP - No AD bit from local resolver                                                                            |",
		"ROW   |13505    |135       |-202 |RDAP - Expecting NOERROR RCODE but got SERVFAIL when resolving hostname                                         |",
		"ROW   |13506    |135       |-203 |RDAP - Expecting NOERROR RCODE but got NXDOMAIN when resolving hostname                                         |",
		"ROW   |13507    |135       |-204 |RDAP - Expecting NOERROR RCODE but got unexpected error when resolving hostname                                 |",
		"ROW   |13508    |135       |-205 |RDAP - Timeout                                                                                                  |",
		"ROW   |13509    |135       |-206 |RDAP - Error opening connection to server                                                                       |",
		"ROW   |13510    |135       |-207 |RDAP - Invalid JSON format in response                                                                          |",
		"ROW   |13511    |135       |-208 |RDAP - ldhName member not found in response                                                                     |",
		"ROW   |13512    |135       |-209 |RDAP - ldhName member doesn't match query in response                                                           |",
		"ROW   |13513    |135       |-213 |RDAP - Error in HTTP protocol                                                                                   |",
		"ROW   |13514    |135       |-214 |RDAP - Error in HTTPS protocol                                                                                  |",
		"-- Error code for every assigned HTTP status code (with the exception of HTTP/200)                                                                 ",
		"-- as per: http://www.iana.org/assignments/http-status-codes/http-status-codes.xhtml                                                               ",
		"ROW   |13515    |135       |-250 |RDAP - Expecting HTTP status code 200 but got 100                                                               |",
		"ROW   |13516    |135       |-251 |RDAP - Expecting HTTP status code 200 but got 101                                                               |",
		"ROW   |13517    |135       |-252 |RDAP - Expecting HTTP status code 200 but got 102                                                               |",
		"ROW   |13518    |135       |-253 |RDAP - Expecting HTTP status code 200 but got 103                                                               |",
		"ROW   |13519    |135       |-254 |RDAP - Expecting HTTP status code 200 but got 201                                                               |",
		"ROW   |13520    |135       |-255 |RDAP - Expecting HTTP status code 200 but got 202                                                               |",
		"ROW   |13521    |135       |-256 |RDAP - Expecting HTTP status code 200 but got 203                                                               |",
		"ROW   |13522    |135       |-257 |RDAP - Expecting HTTP status code 200 but got 204                                                               |",
		"ROW   |13523    |135       |-258 |RDAP - Expecting HTTP status code 200 but got 205                                                               |",
		"ROW   |13524    |135       |-259 |RDAP - Expecting HTTP status code 200 but got 206                                                               |",
		"ROW   |13525    |135       |-260 |RDAP - Expecting HTTP status code 200 but got 207                                                               |",
		"ROW   |13526    |135       |-261 |RDAP - Expecting HTTP status code 200 but got 208                                                               |",
		"ROW   |13527    |135       |-262 |RDAP - Expecting HTTP status code 200 but got 226                                                               |",
		"ROW   |13528    |135       |-263 |RDAP - Expecting HTTP status code 200 but got 300                                                               |",
		"ROW   |13529    |135       |-264 |RDAP - Expecting HTTP status code 200 but got 301                                                               |",
		"ROW   |13530    |135       |-265 |RDAP - Expecting HTTP status code 200 but got 302                                                               |",
		"ROW   |13531    |135       |-266 |RDAP - Expecting HTTP status code 200 but got 303                                                               |",
		"ROW   |13532    |135       |-267 |RDAP - Expecting HTTP status code 200 but got 304                                                               |",
		"ROW   |13533    |135       |-268 |RDAP - Expecting HTTP status code 200 but got 305                                                               |",
		"ROW   |13534    |135       |-269 |RDAP - Expecting HTTP status code 200 but got 306                                                               |",
		"ROW   |13535    |135       |-270 |RDAP - Expecting HTTP status code 200 but got 307                                                               |",
		"ROW   |13536    |135       |-271 |RDAP - Expecting HTTP status code 200 but got 308                                                               |",
		"ROW   |13537    |135       |-272 |RDAP - Expecting HTTP status code 200 but got 400                                                               |",
		"ROW   |13538    |135       |-273 |RDAP - Expecting HTTP status code 200 but got 401                                                               |",
		"ROW   |13539    |135       |-274 |RDAP - Expecting HTTP status code 200 but got 402                                                               |",
		"ROW   |13540    |135       |-275 |RDAP - Expecting HTTP status code 200 but got 403                                                               |",
		"ROW   |13541    |135       |-276 |RDAP - Expecting HTTP status code 200 but got 404                                                               |",
		"ROW   |13542    |135       |-277 |RDAP - Expecting HTTP status code 200 but got 405                                                               |",
		"ROW   |13543    |135       |-278 |RDAP - Expecting HTTP status code 200 but got 406                                                               |",
		"ROW   |13544    |135       |-279 |RDAP - Expecting HTTP status code 200 but got 407                                                               |",
		"ROW   |13545    |135       |-280 |RDAP - Expecting HTTP status code 200 but got 408                                                               |",
		"ROW   |13546    |135       |-281 |RDAP - Expecting HTTP status code 200 but got 409                                                               |",
		"ROW   |13547    |135       |-282 |RDAP - Expecting HTTP status code 200 but got 410                                                               |",
		"ROW   |13548    |135       |-283 |RDAP - Expecting HTTP status code 200 but got 411                                                               |",
		"ROW   |13549    |135       |-284 |RDAP - Expecting HTTP status code 200 but got 412                                                               |",
		"ROW   |13550    |135       |-285 |RDAP - Expecting HTTP status code 200 but got 413                                                               |",
		"ROW   |13551    |135       |-286 |RDAP - Expecting HTTP status code 200 but got 414                                                               |",
		"ROW   |13552    |135       |-287 |RDAP - Expecting HTTP status code 200 but got 415                                                               |",
		"ROW   |13553    |135       |-288 |RDAP - Expecting HTTP status code 200 but got 416                                                               |",
		"ROW   |13554    |135       |-289 |RDAP - Expecting HTTP status code 200 but got 417                                                               |",
		"ROW   |13555    |135       |-290 |RDAP - Expecting HTTP status code 200 but got 421                                                               |",
		"ROW   |13556    |135       |-291 |RDAP - Expecting HTTP status code 200 but got 422                                                               |",
		"ROW   |13557    |135       |-292 |RDAP - Expecting HTTP status code 200 but got 423                                                               |",
		"ROW   |13558    |135       |-293 |RDAP - Expecting HTTP status code 200 but got 424                                                               |",
		"ROW   |13559    |135       |-294 |RDAP - Expecting HTTP status code 200 but got 426                                                               |",
		"ROW   |13560    |135       |-295 |RDAP - Expecting HTTP status code 200 but got 428                                                               |",
		"ROW   |13561    |135       |-296 |RDAP - Expecting HTTP status code 200 but got 429                                                               |",
		"ROW   |13562    |135       |-297 |RDAP - Expecting HTTP status code 200 but got 431                                                               |",
		"ROW   |13563    |135       |-298 |RDAP - Expecting HTTP status code 200 but got 451                                                               |",
		"ROW   |13564    |135       |-299 |RDAP - Expecting HTTP status code 200 but got 500                                                               |",
		"ROW   |13565    |135       |-300 |RDAP - Expecting HTTP status code 200 but got 501                                                               |",
		"ROW   |13566    |135       |-301 |RDAP - Expecting HTTP status code 200 but got 502                                                               |",
		"ROW   |13567    |135       |-302 |RDAP - Expecting HTTP status code 200 but got 503                                                               |",
		"ROW   |13568    |135       |-303 |RDAP - Expecting HTTP status code 200 but got 504                                                               |",
		"ROW   |13569    |135       |-304 |RDAP - Expecting HTTP status code 200 but got 505                                                               |",
		"ROW   |13570    |135       |-305 |RDAP - Expecting HTTP status code 200 but got 506                                                               |",
		"ROW   |13571    |135       |-306 |RDAP - Expecting HTTP status code 200 but got 507                                                               |",
		"ROW   |13572    |135       |-307 |RDAP - Expecting HTTP status code 200 but got 508                                                               |",
		"ROW   |13573    |135       |-308 |RDAP - Expecting HTTP status code 200 but got 510                                                               |",
		"ROW   |13574    |135       |-309 |RDAP - Expecting HTTP status code 200 but got 511                                                               |",
		"ROW   |13575    |135       |-310 |RDAP - Expecting HTTP status code 200 but got unexpected status code                                            |",
		NULL
	};
	int			i;

	if (0 != (program_type & ZBX_PROGRAM_TYPE_PROXY))
		return SUCCEED;

	for (i = 0; NULL != data[i]; i++)
	{
		zbx_uint64_t	mappingid, valuemapid;
		char		*value = NULL, *newvalue = NULL, *value_esc, *newvalue_esc;

		if (0 == strncmp(data[i], "--", ZBX_CONST_STRLEN("--")))
			continue;

		if (4 != sscanf(data[i], "ROW |" ZBX_FS_UI64 " |" ZBX_FS_UI64 " |%m[^|]|%m[^|]|",
				&mappingid, &valuemapid, &value, &newvalue))
		{
			zabbix_log(LOG_LEVEL_CRIT, "failed to parse the following line:\n%s", data[i]);
			zbx_free(value);
			zbx_free(newvalue);
			return FAIL;
		}

		zbx_rtrim(value, ZBX_WHITESPACE);
		zbx_rtrim(newvalue, ZBX_WHITESPACE);

		/* NOTE: to keep it simple assume that data does not contain sequences "&pipe;", "&eol;" or "&bsn;" */

		value_esc = zbx_db_dyn_escape_string(value);
		newvalue_esc = zbx_db_dyn_escape_string(newvalue);
		zbx_free(value);
		zbx_free(newvalue);

		if (ZBX_DB_OK > DBexecute("insert into mappings (mappingid,valuemapid,value,newvalue)"
				" values (" ZBX_FS_UI64 "," ZBX_FS_UI64 ",'%s','%s')",
				mappingid, valuemapid, value_esc, newvalue_esc))
		{
			zbx_free(value_esc);
			zbx_free(newvalue_esc);
			return FAIL;
		}

		zbx_free(value_esc);
		zbx_free(newvalue_esc);
	}

	return SUCCEED;
}

#endif

DBPATCH_START(3000)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(3000000, 0, 1)
DBPATCH_ADD(3000100, 0, 1)	/* Phase 1 */
DBPATCH_ADD(3000101, 0, 1)	/* new group "TLD Probe results" */
DBPATCH_ADD(3000102, 0, 1)	/* new group "gTLD Probe results" */
DBPATCH_ADD(3000103, 0, 1)	/* new group "ccTLD Probe results" */
DBPATCH_ADD(3000104, 0, 1)	/* new group "testTLD Probe results" */
DBPATCH_ADD(3000105, 0, 1)	/* new group "otherTLD Probe results" */
DBPATCH_ADD(3000106, 0, 1)	/* read permissions on "TLD Probe results" host group for "Technical services users" */
DBPATCH_ADD(3000107, 0, 1)	/* read permissions on "gTLD Probe results" host group for "EBERO users" */
DBPATCH_ADD(3000108, 0, 1)	/* read permissions on "Probes" host group for "Technical services users" */
DBPATCH_ADD(3000109, 0, 1)	/* read permissions on "Probes" host group for "EBERO users" */
DBPATCH_ADD(3000110, 0, 1)	/* add "gTLD" hosts to "gTLD Probe results" host group */
DBPATCH_ADD(3000111, 0, 1)	/* add "ccTLD" hosts to "ccTLD Probe results" host group */
DBPATCH_ADD(3000112, 0, 1)	/* add "testTLD" hosts to "testTLD Probe results" host group */
DBPATCH_ADD(3000113, 0, 1)	/* add "otherTLD" hosts to "otherTLD Probe results" host group */
DBPATCH_ADD(3000114, 0, 1)	/* add all TLD hosts to "TLD Probe results" host group */
DBPATCH_ADD(3000115, 0, 0)	/* fix trigger expression for minimum online IPv4 enabled probe number */
DBPATCH_ADD(3000116, 0, 0)	/* fix trigger expression for minimum online IPv6 enabled probe number */
DBPATCH_ADD(3000117, 0, 0)	/* link "Template App Zabbix Proxy" to all probe hosts */
DBPATCH_ADD(3000118, 0, 0)	/* read permissions on "Probes - Mon" host group for "Technical services users" */
DBPATCH_ADD(3000119, 0, 0)	/* read permissions on "Mon" host group for "Technical services users" */
DBPATCH_ADD(3000120, 0, 0)	/* link "Template App Zabbix Proxy" to all probe hosts (again) */
DBPATCH_ADD(3000121, 0, 0)	/* new actions: "Probes-Mon", "Central-Server", "TLDs" */
DBPATCH_ADD(3000122, 0, 0)	/* parameters for "Script" media type */
DBPATCH_ADD(3000123, 0, 0)	/* move "Probe statuses" host from "Probes - Mon" group to "Mon" group */
DBPATCH_ADD(3000124, 0, 1)	/* change read permissions on "Probes" host group to "Probes - Mon" host group for "EBERO users" */
DBPATCH_ADD(3000125, 0, 0)	/* drop "$2" and fixed capitalization in "zabbix[proxy,{$RSM.PROXY_NAME},lastaccess]" item name */
DBPATCH_ADD(3000126, 0, 0)	/* remove "<probe>" hosts from "<probe>" host group */
DBPATCH_ADD(3000127, 0, 0)	/* remove "<TLD>" hosts from "TLD <TLD>" host group */
DBPATCH_ADD(3000128, 0, 0)	/* adjust allowed system time difference between Zabbix Server and other hosts */
DBPATCH_ADD(3000129, 0, 0)	/* rename corresponding trigger */
DBPATCH_ADD(3000130, 0, 1)	/* create lastvalue table */
DBPATCH_ADD(3000131, 0, 1)	/* add itemid constraint to lastvalue */
DBPATCH_ADD(3000132, 0, 0)	/* delete carriage returns in parameters of "Script" media type */
DBPATCH_ADD(3000133, 0, 1)	/* use recovery event (instead of problem event) date and time in recovery message */
DBPATCH_ADD(3000134, 0, 0)	/* add missing interfaces for "Global macro history" and "Probe statuses" hosts */
DBPATCH_ADD(3000135, 0, 0)	/* add global "{$MAX_CPU_LOAD}" and "{$MAX_RUN_PROCESSES}" macros */
DBPATCH_ADD(3000136, 0, 0)	/* add "{$MAX_CPU_LOAD}" and "{$MAX_RUN_PROCESSES}" macros on "Zabbix Server" host */
DBPATCH_ADD(3000137, 0, 0)	/* update "Processor load is too high on {HOST.NAME}" trigger in "Template OS Linux" template and "Zabbix Server" host */
DBPATCH_ADD(3000138, 0, 0)	/* update "Too many processes running on {HOST.NAME}" trigger in "Template OS Linux" template and "Zabbix Server" host */
DBPATCH_ADD(3000139, 0, 0)	/* unsuccessful attempt to unlink and link updated "Template OS Linux" template to all hosts it is currently linked to (except "Zabbix Server") */
DBPATCH_ADD(3000140, 0, 0)	/* lowered "Refresh unsupported items" interval to 60 seconds */
DBPATCH_ADD(3000141, 0, 0)	/* added "Activated" and "Deactivated" false positive statuses to "statusMaps.csv" */
DBPATCH_ADD(3000200, 0, 0)	/* Phase 2 */
DBPATCH_ADD(3000202, 0, 0)	/* update "RSM DNS rtt" value mapping with new DNS test error codes */
DBPATCH_ADD(3000203, 0, 0)	/* add "Up-inconclusive-no-data" and "Up-inconclusive-no-probes" to "RSM Service Availability" value mapping */
DBPATCH_ADD(3000204, 0, 0)	/* add "Up-inconclusive-no-data" and "Up-inconclusive-no-probes" to data export "statusMaps" catalog */
DBPATCH_ADD(3000205, 0, 0)	/* add {$PROBE.INTERNAL.ERROR.INTERVAL} global macro */
DBPATCH_ADD(3000206, 0, 0)	/* create "Template Probe Errors" template with "Internal error rate" item and triggers */
DBPATCH_ADD(3000208, 0, 0)	/* new actions: "Probes", "Probes-Knockout" */
DBPATCH_ADD(3000210, 0, 0)	/* link "Template Probe Errors" template to all probe hosts */
DBPATCH_ADD(3000211, 0, 0)	/* update "RSM RDDS rtt" value mapping with new RDDS43 and RDDS80 test error codes */
DBPATCH_ADD(3000212, 0, 0)	/* add "RSM RDAP rtt" value mapping (without error codes) */
DBPATCH_ADD(3000213, 0, 0)	/* create "Template RDAP" template with rdap[...], rdap.ip and rdap.rtt items, RDAP application; place template into "Templates" host group */
DBPATCH_ADD(3000214, 0, 0)	/* add RDAP error codes into "RSM RDAP rtt" value mapping */

DBPATCH_END()
