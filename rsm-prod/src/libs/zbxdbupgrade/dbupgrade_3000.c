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
	OP_MESSAGE
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

typedef union
{
	opmessage_t	opmessage;
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
#define MAX_CONDITIONS	4

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
			if (ZBX_DB_OK > DBexecute(
					"insert into operations (operationid,actionid)"
					" values (" ZBX_FS_UI64 "," ZBX_FS_UI64 ")",
					operation->id, action->id))
			{
				return FAIL;
			}

			switch (operation->type)
			{
				case OP_MESSAGE:
					if (SUCCEED != db_insert_opmessage(operation->id, &operation->data.opmessage))
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

DBPATCH_END()
