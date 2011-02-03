/*
** ZABBIX
** Copyright (C) 2000-2011 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

#ifndef ZABBIX_EVENTLOG_H
#define ZABBIX_EVENTLOG_H

int process_eventlog(
	const char	*source,
	long		*lastlogsize,
	unsigned long	*out_timestamp,
	char		**out_source,
	unsigned short	*out_severity,
	char		**out_message,
	unsigned long	*out_eventid,
	unsigned char	skip_old_data);

#endif /* ZABBIX_EVENTLOG_H */
