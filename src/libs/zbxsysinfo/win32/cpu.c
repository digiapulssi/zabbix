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

#include "config.h"

#include "common.h"
#include "sysinfo.h"

int     OLD_CPU(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	/* SKIP REALIZATION */

	return SYSINFO_RET_FAIL;
}

int	SYSTEM_CPU_UTIL(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{

#define CPU_FNCLIST struct cpu_fnclist_s
CPU_FNCLIST
{
	char *type;
	char *mode;
	int (*function)();
};

#ifdef TODO
#error Realize function!!!
#endif /* todo */

	return SYSINFO_RET_FAIL;
}



int	SYSTEM_CPU_LOAD(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{

#ifdef TODO
#error Realize function!!!
#endif /* todo */

	return SYSINFO_RET_FAIL;
}

int     SYSTEM_CPU_SWITCHES(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#ifdef TODO
#error Realize function!!!
#endif /* todo */

	return SYSINFO_RET_FAIL;

}

int     SYSTEM_CPU_INTR(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#ifdef TODO
#error Realize function!!!
#endif /* todo */

	return SYSINFO_RET_FAIL;

}

