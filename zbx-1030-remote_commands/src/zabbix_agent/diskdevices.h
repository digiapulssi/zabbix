/* 
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
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

#ifndef ZABBIX_DISKDEVICES_H
#define ZABBIX_DISKDEVICES_H

#include "sysinfo.h"

#define	MAX_DISKDEVICES	8

typedef struct c_single_diskdevice_data
{
	char		name[32];
	int		index;
	time_t		clock[MAX_COLLECTOR_HISTORY];
	zbx_uint64_t	r_sect[MAX_COLLECTOR_HISTORY];
	zbx_uint64_t	r_oper[MAX_COLLECTOR_HISTORY];
	zbx_uint64_t	r_byte[MAX_COLLECTOR_HISTORY];
	zbx_uint64_t	w_sect[MAX_COLLECTOR_HISTORY];
	zbx_uint64_t	w_oper[MAX_COLLECTOR_HISTORY];
	zbx_uint64_t	w_byte[MAX_COLLECTOR_HISTORY];
	double		r_sps[ZBX_AVGMAX];
	double		r_ops[ZBX_AVGMAX];
	double		r_bps[ZBX_AVGMAX];
	double		w_sps[ZBX_AVGMAX];
	double		w_ops[ZBX_AVGMAX];
	double		w_bps[ZBX_AVGMAX];
} ZBX_SINGLE_DISKDEVICE_DATA;

typedef struct c_diskdevices_data
{
	int				count;
	ZBX_SINGLE_DISKDEVICE_DATA	device[MAX_DISKDEVICES];
} ZBX_DISKDEVICES_DATA;

#define DISKDEVICE_COLLECTOR_STARTED(collector) collector

ZBX_SINGLE_DISKDEVICE_DATA	*collector_diskdevice_get(const char *devname);
ZBX_SINGLE_DISKDEVICE_DATA	*collector_diskdevice_add(const char *devname);
void				collect_stats_diskdevices(ZBX_DISKDEVICES_DATA *pdiskdevices);

#endif
