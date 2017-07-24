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

#ifndef ZABBIX_ACTIONS_H
#define ZABBIX_ACTIONS_H

#include "common.h"
#include "db.h"

#define ZBX_ACTION_RECOVERY_NONE	0
#define ZBX_ACTION_RECOVERY_OPERATIONS	1

int	check_action_condition(const DB_EVENT *event, DB_CONDITION *condition);
void	process_actions(const DB_EVENT *events, size_t events_num, zbx_vector_uint64_pair_t *closed_events);
void	get_db_actions_info(zbx_vector_uint64_t *actionids, zbx_vector_ptr_t *actions);
void	free_db_action(DB_ACTION *action);

#endif
