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


#ifndef ZABBIX_DAEMON_H
#define ZABBIX_DAEMON_H

#if defined(WIN32)
#	error "This module allowed only for Linux OS"
#endif


#include "threads.h"

#define	MAXFD	64

void    child_signal_handler(int sig);

void	init_daemon(void);

void	init_parent_process(void);

/* ask for application closing status - NOT needed for linux forks */
#define ZBX_IS_RUNNING (1)

/* tall all threads what application must be closed  - NOT needed for linux forks */
#define ZBX_DO_EXIT()

#endif /* ZABBIX_DAEMON_H */
