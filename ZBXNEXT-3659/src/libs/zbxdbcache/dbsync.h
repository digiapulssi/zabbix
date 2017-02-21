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

#ifndef ZABBIX_DBSYNC_H
#define ZABBIX_DBSYNC_H

#include "common.h"

/* no changes */
#define ZBX_DBSYNC_ROW_NONE	0
/*  a new object must be added to configuration cache */
#define ZBX_DBSYNC_ROW_ADD	1
/* a cached object must be updated in configuration cache */
#define ZBX_DBSYNC_ROW_UPDATE	2
/* a cached object must be removed from configuration cache */
#define ZBX_DBSYNC_ROW_REMOVE	3

typedef struct
{
	/* a row tag, describing the changes (see ZBX_DBSYNC_ROW_* defines) */
	unsigned char	tag;

	/* the identifier of the object represented by the row */
	zbx_uint64_t	rowid;

	/* the column values, NULL if the tag is ZBX_DBSYNC_ROW_REMOVE */
	char		**row;
}
zbx_dbsync_row_t;

typedef struct
{
	/* the number of columns in diff */
	int			columns_num;

	/* the changed rows */
	zbx_vector_ptr_t	rows;
}
zbx_dbsync_t;

typedef struct
{
	zbx_uint64_t	add_num;
	zbx_uint64_t	update_num;
	zbx_uint64_t	remove_num;
}
zbx_dbsync_stats_t;

void zbx_dbsync_init(zbx_dbsync_t *diff);
void zbx_dbsync_clear(zbx_dbsync_t *diff);
void	zbx_dbsync_get_stats(const zbx_dbsync_t *sync, zbx_dbsync_stats_t *stats);

int	zbx_dbsync_compare_config(ZBX_DC_CONFIG *cache, zbx_dbsync_t *sync);
int	zbx_dbsync_compare_hosts(ZBX_DC_CONFIG *cache, zbx_dbsync_t *sync);
int	zbx_dbsync_compare_host_inventory(ZBX_DC_CONFIG *cache, zbx_dbsync_t *sync);

#endif /* BUILD_SRC_LIBS_ZBXDBCACHE_DBSYNC_H_ */
