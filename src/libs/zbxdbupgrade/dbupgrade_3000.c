/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
DBPATCH_ADD(3000115, 0, 0)	/* fixed trigger expression for minimum online IPv4 enabled probe number */
DBPATCH_ADD(3000116, 0, 0)	/* fixed trigger expression for minimum online IPv6 enabled probe number */
DBPATCH_ADD(3000117, 0, 0)	/* linked "Template App Zabbix Proxy" to all probe hosts */
DBPATCH_ADD(3000118, 0, 0)	/* read permissions on "Probes - Mon" host group for "Technical services users" */
DBPATCH_ADD(3000119, 0, 0)	/* read permissions on "Mon" host group for "Technical services users" */
DBPATCH_ADD(3000120, 0, 0)	/* linked "Template App Zabbix Proxy" to all probe hosts (again) */
DBPATCH_ADD(3000121, 0, 0)	/* new actions: "Probes-Mon", "Central-Server", "TLDs" */

DBPATCH_END()
