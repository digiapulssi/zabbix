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

#include "common.h"
#include "sysinfo.h"

#include "system.h"

#ifdef _WINDOWS
	#include "perfmon.h"
#endif /* _WINDOWS */

int	SYSTEM_LOCALTIME(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char		type[16], buf[32];
	struct tm	*tm;
	int		gmtoff, offset, ms;
	unsigned short	h, m;
#if defined(_WINDOWS)
        struct _timeb	tv;
#else /* not _WINDOWS */
	struct timeval	tv;
	struct timezone	tz;
#endif /* _WINDOWS */

	assert(result);

	init_result(result);

	if (num_param(param) > 3)
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, type, sizeof(type)))
		return SYSINFO_RET_FAIL;

	if ('\0' == *type || 0 == strcmp(type, "utc"))
	{
		SET_UI64_RESULT(result, time(NULL));
	}
	else if (0 == strcmp(type, "local"))
	{
#if defined(_WINDOWS)
	        _ftime(&tv);

		tm = localtime(&tv.time);

		ms = tv.millitm;
#else /* not _WINDOWS */
		gettimeofday(&tv, &tz);

		tm = localtime(&tv.tv_sec);

		ms = (int)(tv.tv_usec / 1000);
#endif /* _WINDOWS */

		offset = zbx_snprintf(buf, sizeof(buf), "%d-%d-%d,%d:%d:%d.%d,",
				1900 + tm->tm_year, 1 + tm->tm_mon, tm->tm_mday,
				tm->tm_hour, tm->tm_min, tm->tm_sec, ms);

		/* Timezone offset */
#if defined(HAVE_TM_TM_GMTOFF)
		gmtoff = tm->tm_gmtoff;
#else
		gmtoff = -timezone;
#endif /* HAVE_TM_TM_GMTOFF */

		if (tm->tm_isdst > 0)	/* Daylight saving time */
			gmtoff += 3600;	/* Assume add one hour */

		h = (unsigned short)(abs(gmtoff) / 3600);
		m = (unsigned short)((abs(gmtoff) - h * 3600) / 60);

		if (gmtoff >= 0)
			offset += zbx_snprintf(buf + offset, sizeof(buf) - offset, "+");
		else
			offset += zbx_snprintf(buf + offset, sizeof(buf) - offset, "-");
		offset += zbx_snprintf(buf + offset, sizeof(buf) - offset, "%d:%d", (int)h, (int)m);

		SET_STR_RESULT(result, strdup(buf));
	}
	else
		return SYSINFO_RET_FAIL;

	return SYSINFO_RET_OK;
}

int	SYSTEM_UNUM(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#if defined(_WINDOWS)
	char	counter_path[64];

	zbx_snprintf(counter_path, sizeof(counter_path), "\\%d\\%d", PCI_TERMINAL_SERVICES, PCI_TOTAL_SESSIONS);

	return PERF_MONITOR(cmd, counter_path, flags, result);
#else
	assert(result);

	init_result(result);

	return EXECUTE_INT(cmd, "who|wc -l", flags, result);
#endif /* _WINDOWS */
}

int	SYSTEM_UNAME(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#if defined(_WINDOWS)
	DWORD	dwSize;
		/* NOTE: The buffer size should be large enough to contain MAX_COMPUTERNAME_LENGTH + 1 characters.*/
	TCHAR	computerName[MAX_COMPUTERNAME_LENGTH + 1], osVersion[256], *cpuType, wide_buffer[MAX_STRING_LEN];
	SYSTEM_INFO
		sysInfo;
	OSVERSIONINFO
		versionInfo;

	dwSize = MAX_COMPUTERNAME_LENGTH;
	if (0 == GetComputerName(computerName, &dwSize))
		*computerName = '\0';

	versionInfo.dwOSVersionInfoSize = sizeof(OSVERSIONINFO);
	GetVersionEx(&versionInfo);
	switch (versionInfo.dwPlatformId) {
	case VER_PLATFORM_WIN32_WINDOWS:
		switch (versionInfo.dwMinorVersion) {
		case 0:
			zbx_wsnprintf(osVersion, sizeof(osVersion), TEXT("Windows 95-%s"), versionInfo.szCSDVersion);
			break;
		case 10:
			zbx_wsnprintf(osVersion, sizeof(osVersion), TEXT("Windows 98-%s"), versionInfo.szCSDVersion);
			break;
		case 90:
			zbx_wsnprintf(osVersion, sizeof(osVersion), TEXT("Windows Me-%s"), versionInfo.szCSDVersion);
			break;
		default:
			zbx_wsnprintf(osVersion, sizeof(osVersion), TEXT("Windows [Unknown Version]"));
		}
		break;
	case VER_PLATFORM_WIN32_NT:
		switch (versionInfo.dwMajorVersion) {
		case 4:
			zbx_wsnprintf(osVersion, sizeof(osVersion), TEXT("Windows NT 4.0 %s"), versionInfo.szCSDVersion);
			break;
		case 5:
			switch (versionInfo.dwMinorVersion) {
			case 1:
				zbx_wsnprintf(osVersion, sizeof(osVersion), TEXT("Windows XP %s"), versionInfo.szCSDVersion);
				break;
			case 2:
				zbx_wsnprintf(osVersion, sizeof(osVersion), TEXT("Windows Server 2003 %s"), versionInfo.szCSDVersion);
				break;
			default:
				zbx_wsnprintf(osVersion, sizeof(osVersion), TEXT("Windows [Unknown Version]"));
			}
			break;
		case 6:
			zbx_wsnprintf(osVersion, sizeof(osVersion), TEXT("Windows Server 2008 %s"), versionInfo.szCSDVersion);
			break;
		default:
			zbx_wsnprintf(osVersion, sizeof(osVersion), TEXT("Windows [Unknown Version]"));
			break;
		}
	}

	GetSystemInfo(&sysInfo);
	switch(sysInfo.wProcessorArchitecture)
	{
		case PROCESSOR_ARCHITECTURE_INTEL:
			cpuType=TEXT("Intel IA-32");
			break;
		case PROCESSOR_ARCHITECTURE_MIPS:
			cpuType=TEXT("MIPS");
			break;
		case PROCESSOR_ARCHITECTURE_ALPHA:
			cpuType=TEXT("Alpha");
			break;
		case PROCESSOR_ARCHITECTURE_PPC:
			cpuType=TEXT("PowerPC");
			break;
		case PROCESSOR_ARCHITECTURE_IA64:
			cpuType=TEXT("Intel IA-64");
			break;
		case PROCESSOR_ARCHITECTURE_IA32_ON_WIN64:
			cpuType=TEXT("IA-32 on IA-64");
			break;
		case PROCESSOR_ARCHITECTURE_AMD64:
			cpuType=TEXT("AMD-64");
			break;
		default:
			cpuType=TEXT("unknown");
			break;
	}

	zbx_wsnprintf(wide_buffer, sizeof(wide_buffer),
		TEXT("Windows %s %d.%d.%d %s %s"),
		computerName,
		versionInfo.dwMajorVersion,
		versionInfo.dwMinorVersion,
		versionInfo.dwBuildNumber,
		osVersion,
		cpuType);

	SET_STR_RESULT(result, zbx_unicode_to_utf8(wide_buffer));

	return SYSINFO_RET_OK;
#else
	assert(result);

	init_result(result);

	return EXECUTE_STR(cmd, "uname -a", flags, result);
#endif /* _WINDOWS */
}

int	SYSTEM_HOSTNAME(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#if defined(_WINDOWS)
	/* NOTE: The buffer size should be large enough to contain MAX_COMPUTERNAME_LENGTH + 1 characters.*/
	TCHAR	wide_buffer[MAX_COMPUTERNAME_LENGTH + 1];
	DWORD	dwSize = MAX_COMPUTERNAME_LENGTH;

	if (0 == GetComputerName(wide_buffer, &dwSize))
		*wide_buffer = '\0';

	SET_STR_RESULT(result, zbx_unicode_to_utf8(wide_buffer))

	return SYSINFO_RET_OK;
#else
	assert(result);

	init_result(result);

	return EXECUTE_STR(cmd, "hostname", flags, result);
#endif /* _WINDOWS */
}
