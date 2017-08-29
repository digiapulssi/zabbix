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
 * 4.0 development database patches
 */

#ifndef HAVE_SQLITE3

static int	DBpatch_4010000(void)
{
	DB_RESULT		result;
	zbx_vector_uint64_t	eventids;
	DB_ROW			row;
	int			ret = SUCCEED;

	zbx_vector_uint64_create(&eventids);

	result = DBselect(
			"select eventid"
			" from problem"
			" where object=0 and objectid not in ("
				"select triggerid"
				" from triggers"
			")");

	while (NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t	eventid;

		ZBX_STR2UINT64(eventid, row[0]);

		zbx_vector_uint64_append(&eventids, eventid);
	}
	DBfree_result(result);

	zbx_vector_uint64_sort(&eventids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	if (0 != eventids.values_num)
		ret = DBexecute_multiple_query("delete from events where", "eventid", &eventids);

	zbx_vector_uint64_destroy(&eventids);

	return ret;
}

#endif

DBPATCH_START(4010)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(4010000, 0, 1)

DBPATCH_END()
