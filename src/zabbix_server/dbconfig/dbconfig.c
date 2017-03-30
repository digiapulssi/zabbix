/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
#include "daemon.h"
#include "zbxself.h"
#include "log.h"
#include "dbconfig.h"
#include "dbcache.h"

extern int		CONFIG_CONFSYNCER_FREQUENCY;
extern unsigned char	process_type, program_type;
extern int		server_num, process_num;

void	zbx_dbconfig_sigusr_handler(int flags)
{
	if (ZBX_RTC_CONFIG_CACHE_RELOAD == ZBX_RTC_GET_MSG(flags))
	{
		if (0 < zbx_sleep_get_remainder())
		{
			zabbix_log(LOG_LEVEL_WARNING, "forced reloading of the configuration cache");
			zbx_wakeup();
		}
		else
			zabbix_log(LOG_LEVEL_WARNING, "configuration cache reloading is already in progress");
	}
}

/******************************************************************************
 *                                                                            *
 * Function: main_dbconfig_loop                                               *
 *                                                                            *
 * Purpose: periodically synchronises database data with memory cache         *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments: never returns                                                    *
 *                                                                            *
 ******************************************************************************/
ZBX_THREAD_ENTRY(dbconfig_thread, args)
{
	double	sec = 0.0;

	process_type = ((zbx_thread_args_t *)args)->process_type;
	server_num = ((zbx_thread_args_t *)args)->server_num;
	process_num = ((zbx_thread_args_t *)args)->process_num;

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(program_type),
			server_num, get_process_type_string(process_type), process_num);

	if (0 != CONFIG_CONFSYNCER_FREQUENCY)
	{
		zbx_setproctitle("%s [waiting %d sec for processes]", get_process_type_string(process_type),
				CONFIG_CONFSYNCER_FREQUENCY);
	}
	else
		zbx_setproctitle("%s [waiting for processes]", get_process_type_string(process_type));

	zbx_set_sigusr_handler(zbx_dbconfig_sigusr_handler);

	/* the initial configuration sync is done by server before worker processes are forked */
	if (0 != CONFIG_CONFSYNCER_FREQUENCY)
		zbx_sleep_loop(CONFIG_CONFSYNCER_FREQUENCY);
	else
		pause();

	zbx_setproctitle("%s [connecting to the database]", get_process_type_string(process_type));

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	for (;;)
	{
		zbx_handle_log();

		zbx_setproctitle("%s [synced configuration in " ZBX_FS_DBL " sec, syncing configuration]",
				get_process_type_string(process_type), sec);

		sec = zbx_time();
		DCsync_configuration();
		DCupdate_hosts_availability();
		sec = zbx_time() - sec;

		if (0 != CONFIG_CONFSYNCER_FREQUENCY)
		{
			zbx_setproctitle("%s [synced configuration in " ZBX_FS_DBL " sec, idle %d sec]",
					get_process_type_string(process_type), sec, CONFIG_CONFSYNCER_FREQUENCY);

			zbx_sleep_loop(CONFIG_CONFSYNCER_FREQUENCY);
		}
		else
		{
			zbx_setproctitle("%s [synced configuration in " ZBX_FS_DBL " sec, idling]",
					get_process_type_string(process_type), sec);

			pause();
		}
	}
}
