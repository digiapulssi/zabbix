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

#ifndef ZABBIX_PREPROC_HISTORY_H
#define ZABBIX_PREPROC_HISTORY_H

#include "common.h"
#include "dbcache.h"

typedef struct
{
	unsigned char	type;
	zbx_variant_t	value;
	zbx_timespec_t	ts;
}
zbx_preproc_op_history_t;

typedef struct
{
	zbx_uint64_t		itemid;
	zbx_vector_ptr_t	history;
}
zbx_preproc_history_t;

void	zbx_preproc_op_history_free(zbx_preproc_op_history_t *ophistory);
const zbx_preproc_op_history_t	*zbx_preproc_history_get_value(zbx_vector_ptr_t *history, int type);
void	zbx_preproc_history_set_value(zbx_vector_ptr_t *history, int type, const zbx_variant_t *data,
		const zbx_timespec_t *ts);

#endif
