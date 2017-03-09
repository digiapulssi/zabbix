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

#include "common.h"

#ifdef HAVE_LDAP

#include "log.h"
#include "db.h"
#include "zbxself.h"
#include "./ldapsyncer.h"

#include <ldap.h>

#define ZBX_LDAPSYNCER_PERIOD		600	/* TODO: make period configurable */

extern unsigned char	process_type, program_type;
extern int		server_num, process_num;

/******************************************************************************
 *                                                                            *
 * Function: synchronize_from_ldap                                            *
 *                                                                            *
 * Purpose: synchronize users and groups from LDAP database                   *
 *                                                                            *
 * Return value: The number of synchronized users                             *
 *                                                                            *
 ******************************************************************************/
static int      synchronize_from_ldap(void)
{
	return 0;			/* TODO: return real value */
}

ZBX_THREAD_ENTRY(ldap_syncer_thread, args)
{
	double	sec1, sec2;
	int	user_num = 0, sleeptime, nextsync;

	process_type = ((zbx_thread_args_t *)args)->process_type;
	server_num = ((zbx_thread_args_t *)args)->server_num;
	process_num = ((zbx_thread_args_t *)args)->process_num;

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(program_type),
			server_num, get_process_type_string(process_type), process_num);

	zbx_setproctitle("%s [connecting to the database]", get_process_type_string(process_type));
	DBconnect(ZBX_DB_CONNECT_NORMAL);

	sec1 = zbx_time();
	sec2 = sec1;

	sleeptime = ZBX_LDAPSYNCER_PERIOD - (int)sec1 % ZBX_LDAPSYNCER_PERIOD;

	zbx_setproctitle("%s [started, idle %d sec]", get_process_type_string(process_type), sleeptime);

	for (;;)
	{
		zbx_sleep_loop(sleeptime);

		zbx_handle_log();

		zbx_setproctitle("%s [synchronizing LDAP users]", get_process_type_string(process_type));

		sec1 = zbx_time();
		user_num = synchronize_from_ldap();
		sec2 = zbx_time();

		nextsync = (int)sec1 - (int)sec1 % ZBX_LDAPSYNCER_PERIOD + ZBX_LDAPSYNCER_PERIOD;

		if (0 > (sleeptime = nextsync - (int)sec2))
			sleeptime = 0;

		zbx_setproctitle("%s [synchronized %d LDAP users(s) in " ZBX_FS_DBL " sec, idle %d sec]",
		get_process_type_string(process_type), user_num, sec2 - sec1, sleeptime);
	}
}
#endif
