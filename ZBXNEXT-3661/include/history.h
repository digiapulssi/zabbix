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

#ifndef ZABBIX_HISTORY_H
#define ZABBIX_HISTORY_H

#define	HISTORY_API_VERSION	"v1"

#define	ZBX_HISTORY_TYPE_UNUM_STR	"unum"
#define	ZBX_HISTORY_TYPE_FLOAT_STR	"float"
#define	ZBX_HISTORY_TYPE_CHAR_STR	"char"
#define	ZBX_HISTORY_TYPE_TEXT_STR	"text"
#define	ZBX_HISTORY_TYPE_LOG_STR		"log"

int	zbx_init_history_service(const char *url, const char *types);

void	zbx_history_add_values(zbx_vector_ptr_t *history, unsigned char value_type);

void	zbx_history_get_values(zbx_uint64_t itemid, int value_type, int start, int count, int end,
		zbx_vector_history_record_t *values);

void	zbx_trends_send_values(zbx_vector_ptr_t *trends, unsigned char value_type);

int	zbx_history_check_type(int value_type);

#endif
