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

#include "common.h"
#include "log.h"

#include "preproc_history.h"

void	zbx_preproc_op_history_free(zbx_preproc_op_history_t *ophistory)
{
	zbx_variant_clear(&ophistory->value);
	zbx_free(ophistory);
}

const zbx_preproc_op_history_t	*zbx_preproc_history_get_value(zbx_vector_ptr_t *history, int type)
{
	int				i;
	zbx_preproc_op_history_t	*ophistory;

	for (i = 0; i < history->values_num; i++)
	{
		ophistory = (zbx_preproc_op_history_t *)history->values[i];

		if (ophistory->type == type)
			return ophistory;
	}

	return NULL;
}

void	zbx_preproc_history_set_value(zbx_vector_ptr_t *history, int type, const zbx_variant_t *data,
		const zbx_timespec_t *ts)
{
	int				i;
	zbx_preproc_op_history_t	*ophistory;

	for (i = 0; i < history->values_num; i++)
	{
		ophistory = (zbx_preproc_op_history_t *)history->values[i];

		if (ophistory->type == type)
			break;
	}

	if (i == history->values_num)
	{
		ophistory = zbx_malloc(NULL, sizeof(zbx_preproc_op_history_t));
		ophistory->type = type;
		zbx_vector_ptr_append(history, ophistory);
	}

	zbx_variant_set_variant(&ophistory->value, data);
	ophistory->ts = *ts;
}
