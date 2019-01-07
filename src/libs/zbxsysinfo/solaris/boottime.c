/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
#include "sysinfo.h"

#ifdef HAVE_ZONE_H
#	include "zone.h"
#	include "utmpx.h"
#endif

int	SYSTEM_BOOTTIME(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	int	ret = SYSINFO_RET_FAIL;

#ifdef HAVE_ZONE_H
	if (GLOBAL_ZONEID == getzoneid())
	{
#endif
		kstat_ctl_t	*kc;
		kstat_t		*kp;
		kstat_named_t	*kn;

		if (NULL == (kc = kstat_open()))
			return ret;

		if (NULL != (kp = kstat_lookup(kc, "unix", 0, "system_misc")))
		{
			if (-1 != kstat_read(kc, kp, 0))
			{
				if (NULL != (kn = (kstat_named_t *)kstat_data_lookup(kp, "boot_time")))
				{
					SET_UI64_RESULT(result, get_kstat_numeric_value(kn));
					ret = SYSINFO_RET_OK;
				}
			}
		}
		kstat_close(kc);
#ifdef HAVE_ZONE_H
	}
	else
	{
		struct utmpx	utmpx_local, *utmpx;

		utmpx_local.ut_type = BOOT_TIME;

		setutxent();

		if (NULL != (utmpx = getutxid(&utmpx_local)))
		{
			SET_UI64_RESULT(result, utmpx->ut_xtime);
			ret = SYSINFO_RET_OK;
		}

		endutxent();
	}
#endif
	if (SYSINFO_RET_OK != ret)
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain system boot time."));

	return ret;
}
