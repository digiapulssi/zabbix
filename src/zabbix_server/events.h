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

#ifndef ZABBIX_EVENTS_H
#define ZABBIX_EVENTS_H

#define ZBX_EVENTS_SKIP_CORRELATION	0
#define ZBX_EVENTS_PROCESS_CORRELATION	1

void	zbx_initialize_events(void);
void	zbx_uninitialize_events(void);
int	add_event(unsigned char source, unsigned char object, zbx_uint64_t objectid,
		const zbx_timespec_t *timespec, int value, const char *trigger_description,
		const char *trigger_expression, const char *trigger_recovery_expression, unsigned char trigger_priority,
		unsigned char trigger_type, const zbx_vector_ptr_t *trigger_tags,
		unsigned char trigger_correlation_mode, const char *trigger_correlation_tag,
		unsigned char trigger_value);

int	close_event(zbx_uint64_t eventid, unsigned char source, unsigned char object, zbx_uint64_t objectid,
		const zbx_timespec_t *ts, zbx_uint64_t userid, zbx_uint64_t correlationid, zbx_uint64_t c_eventid,
		const char *trigger_description, const char *trigger_expression,
		const char *trigger_recovery_expression, unsigned char trigger_priority, unsigned char trigger_type,
		const zbx_vector_ptr_t *trigger_tags, unsigned char trigger_correlation_mode,
		const char *trigger_correlation_tag, unsigned char trigger_value);

int	process_events(void);
int	process_trigger_events(zbx_vector_ptr_t *trigger_diff, zbx_vector_uint64_t *triggerids_lock, int mode);
int	flush_correlated_events(void);

void	get_db_eventid_r_eventid_pairs(zbx_vector_uint64_t *eventids, zbx_vector_uint64_pair_t *event_pairs,
		zbx_vector_uint64_t *r_eventids);
void	get_db_events_info(zbx_vector_uint64_t *eventids, zbx_vector_ptr_t *events);
void	free_db_event(DB_EVENT *event);

#endif
