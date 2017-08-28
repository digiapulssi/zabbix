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
#include "dbupgrade.h"

/*
 * 3.4 development database patches
 */

#ifndef HAVE_SQLITE3

static int	DBpatch_3050000(void)
{
	return SUCCEED;
}

/* Patches 3030199-3030209 are to remove references to table that is about to be renamed, this is required on IBM DB2 */

static int	DBpatch_3050001(void)
{
#ifdef HAVE_IBM_DB2
	return DBdrop_foreign_key("group_prototype", 2);
#else
	return SUCCEED;
#endif
}

static int	DBpatch_3050002(void)
{
#ifdef HAVE_IBM_DB2
	return DBdrop_foreign_key("group_discovery", 1);
#else
	return SUCCEED;
#endif
}

static int	DBpatch_3050003(void)
{
#ifdef HAVE_IBM_DB2
	return DBdrop_foreign_key("scripts", 2);
#else
	return SUCCEED;
#endif
}

static int	DBpatch_3050004(void)
{
#ifdef HAVE_IBM_DB2
	return DBdrop_foreign_key("opcommand_grp", 2);
#else
	return SUCCEED;
#endif
}

static int	DBpatch_3050005(void)
{
#ifdef HAVE_IBM_DB2
	return DBdrop_foreign_key("opgroup", 2);
#else
	return SUCCEED;
#endif
}

static int	DBpatch_3050006(void)
{
#ifdef HAVE_IBM_DB2
	return DBdrop_foreign_key("config", 2);
#else
	return SUCCEED;
#endif
}

static int	DBpatch_3050007(void)
{
#ifdef HAVE_IBM_DB2
	return DBdrop_foreign_key("hosts_groups", 2);
#else
	return SUCCEED;
#endif
}

static int	DBpatch_3050008(void)
{
#ifdef HAVE_IBM_DB2
	return DBdrop_foreign_key("rights", 2);
#else
	return SUCCEED;
#endif
}

static int	DBpatch_3050009(void)
{
#ifdef HAVE_IBM_DB2
	return DBdrop_foreign_key("maintenances_groups", 2);
#else
	return SUCCEED;
#endif
}

static int	DBpatch_3050010(void)
{
#ifdef HAVE_IBM_DB2
	return DBdrop_foreign_key("corr_condition_group", 2);
#else
	return SUCCEED;
#endif
}

static int	DBpatch_3050011(void)
{
#ifdef HAVE_IBM_DB2
	return DBdrop_foreign_key("widget_field", 2);
#else
	return SUCCEED;
#endif
}

/* groups is reserved keyword since MySQL 8.0 */

static int	DBpatch_3050012(void)
{
	return DBrename_table(ZBX_SQL_QUOTE("groups"), "hstgrp");
}

static int	DBpatch_3050013(void)
{
	return DBrename_index("hstgrp", "groups_1", "hstgrp_1", "name", 0);
}

/* Patches 3030212-3030222 are to restore references after renaming table on IBM DB2 */

static int	DBpatch_3050014(void)
{
#ifdef HAVE_IBM_DB2
	const ZBX_FIELD	field = {"groupid", NULL, "hstgrp", "groupid", 0, 0, 0, 0};

	return DBadd_foreign_key("group_prototype", 2, &field);
#else
	return SUCCEED;
#endif
}

static int	DBpatch_3050015(void)
{
#ifdef HAVE_IBM_DB2
	const ZBX_FIELD	field = {"groupid", NULL, "hstgrp", "groupid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("group_discovery", 1, &field);
#else
	return SUCCEED;
#endif
}

static int	DBpatch_3050016(void)
{
#ifdef HAVE_IBM_DB2
	const ZBX_FIELD	field = {"groupid", NULL, "hstgrp", "groupid", 0, 0, 0, 0};

	return DBadd_foreign_key("scripts", 2, &field);
#else
	return SUCCEED;
#endif
}

static int	DBpatch_3050017(void)
{
#ifdef HAVE_IBM_DB2
	const ZBX_FIELD	field = {"groupid", NULL, "hstgrp", "groupid", 0, 0, 0, 0};

	return DBadd_foreign_key("opcommand_grp", 2, &field);
#else
	return SUCCEED;
#endif
}

static int	DBpatch_3050018(void)
{
#ifdef HAVE_IBM_DB2
	const ZBX_FIELD	field = {"groupid", NULL, "hstgrp", "groupid", 0, 0, 0, 0};

	return DBadd_foreign_key("opgroup", 2, &field);
#else
	return SUCCEED;
#endif
}

static int	DBpatch_3050019(void)
{
#ifdef HAVE_IBM_DB2
	const ZBX_FIELD	field = {"discovery_groupid", NULL, "hstgrp", "groupid", 0, 0, 0, 0};

	return DBadd_foreign_key("config", 2, &field);
#else
	return SUCCEED;
#endif
}

static int	DBpatch_3050020(void)
{
#ifdef HAVE_IBM_DB2
	const ZBX_FIELD	field = {"groupid", NULL, "hstgrp", "groupid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("hosts_groups", 2, &field);
#else
	return SUCCEED;
#endif
}

static int	DBpatch_3050021(void)
{
#ifdef HAVE_IBM_DB2
	const ZBX_FIELD	field = {"id",	NULL, "hstgrp", "groupid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("rights", 2, &field);
#else
	return SUCCEED;
#endif
}

static int	DBpatch_3050022(void)
{
#ifdef HAVE_IBM_DB2
	const ZBX_FIELD	field = {"groupid", NULL, "hstgrp", "groupid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("maintenances_groups", 2, &field);
#else
	return SUCCEED;
#endif
}

static int	DBpatch_3050023(void)
{
#ifdef HAVE_IBM_DB2
	const ZBX_FIELD	field = {"groupid", NULL, "hstgrp", "groupid", 0, ZBX_TYPE_ID, ZBX_NOTNULL, 0};

	return DBadd_foreign_key("corr_condition_group", 2, &field);
#else
	return SUCCEED;
#endif
}

static int	DBpatch_3050024(void)
{
#ifdef HAVE_IBM_DB2
	const ZBX_FIELD	field = {"value_groupid", NULL, "hstgrp", "groupid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("widget_field", 2, &field);
#else
	return SUCCEED;
#endif
}

/* function is reserved keyword since MySQL 8.0 */

static int	DBpatch_3050025(void)
{
#ifdef HAVE_IBM_DB2
	return DBdrop_foreign_key("functions", 1);
#else
	return SUCCEED;
#endif
}

static int	DBpatch_3050026(void)
{
#ifdef HAVE_IBM_DB2
	return DBdrop_index("functions", "functions_2");
#else
	return SUCCEED;
#endif
}

static int	DBpatch_3050027(void)
{
	const ZBX_FIELD	field = {"func_name", "", NULL, NULL, 12, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBrename_field("functions", ZBX_SQL_QUOTE("function"), &field);
}

static int	DBpatch_3050028(void)
{
#ifdef HAVE_IBM_DB2
	return DBcreate_index("functions", "functions_2", "itemid,func_name,parameter", 0);
#else
	return SUCCEED;
#endif
}

static int	DBpatch_3050029(void)
{
#ifdef HAVE_IBM_DB2
	const ZBX_FIELD	field = {"itemid", NULL, "items", "itemid", 0, 0, 0, ZBX_FK_CASCADE_DELETE};

	return DBadd_foreign_key("functions", 1, &field);
#else
	return SUCCEED;
#endif
}

#endif

DBPATCH_START(3050)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(3050000, 0, 1)
DBPATCH_ADD(3050001, 0, 1)
DBPATCH_ADD(3050002, 0, 1)
DBPATCH_ADD(3050003, 0, 1)
DBPATCH_ADD(3050004, 0, 1)
DBPATCH_ADD(3050005, 0, 1)
DBPATCH_ADD(3050006, 0, 1)
DBPATCH_ADD(3050007, 0, 1)
DBPATCH_ADD(3050008, 0, 1)
DBPATCH_ADD(3050009, 0, 1)
DBPATCH_ADD(3050010, 0, 1)
DBPATCH_ADD(3050011, 0, 1)
DBPATCH_ADD(3050012, 0, 1)
DBPATCH_ADD(3050013, 0, 1)
DBPATCH_ADD(3050014, 0, 1)
DBPATCH_ADD(3050015, 0, 1)
DBPATCH_ADD(3050016, 0, 1)
DBPATCH_ADD(3050017, 0, 1)
DBPATCH_ADD(3050018, 0, 1)
DBPATCH_ADD(3050019, 0, 1)
DBPATCH_ADD(3050020, 0, 1)
DBPATCH_ADD(3050021, 0, 1)
DBPATCH_ADD(3050022, 0, 1)
DBPATCH_ADD(3050023, 0, 1)
DBPATCH_ADD(3050024, 0, 1)
DBPATCH_ADD(3050025, 0, 1)
DBPATCH_ADD(3050026, 0, 1)
DBPATCH_ADD(3050027, 0, 1)
DBPATCH_ADD(3050028, 0, 1)
DBPATCH_ADD(3050029, 0, 1)

DBPATCH_END()
