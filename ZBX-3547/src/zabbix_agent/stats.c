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
#include "stats.h"
#include "log.h"
#include "zbxconf.h"
#include "diskdevices.h"
#include "cfg.h"

#if defined(_WINDOWS)
#	include "service.h"
#else
#	include "daemon.h"
#	include "ipc.h"
#endif /* _WINDOWS */

ZBX_COLLECTOR_DATA	*collector = NULL;

#ifndef _WINDOWS
static int	shm_id;
#endif /* _WINDOWS */

/******************************************************************************
 *                                                                            *
 * Function: zbx_get_cpu_num                                                  *
 *                                                                            *
 * Purpose: returns the number of processors which are currently online       *
 *          (i.e., available).                                                *
 *                                                                            *
 * Return value: number of CPUs                                               *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	zbx_get_cpu_num()
{
#if defined(_WINDOWS)
	SYSTEM_INFO	sysInfo;

	GetSystemInfo(&sysInfo);

	return (int)(sysInfo.dwNumberOfProcessors);
#elif defined(HAVE_SYS_PSTAT_H)
	struct pst_dynamic psd;

	if (-1 == pstat_getdynamic(&psd, sizeof(struct pst_dynamic), 1, 0))
		goto return_one;

	return (int)(psd.psd_proc_cnt);
return_one:
#elif defined(_SC_NPROCESSORS_ONLN)
	/* Solaris 10 x86 */
	/* FreeBSD 7.0 x86 */
	int	ncpu;

	if (-1 == (ncpu = sysconf(_SC_NPROCESSORS_ONLN)))
		goto return_one;

	return ncpu;
return_one:
#elif defined(HAVE_FUNCTION_SYSCTL_HW_NCPU)
	/* NetBSD 3.1 x86; NetBSD 4.0 x86 */
	/* OpenBSD 4.2 x86 */
	/* FreeBSD 6.2 x86; FreeBSD 7.0 x86 */
	size_t	len;
	int	mib[] = {CTL_HW, HW_NCPU}, ncpu;

	len = sizeof(ncpu);

	if (0 != sysctl(mib, 2, &ncpu, &len, NULL, 0))
		goto return_one;

	return ncpu;
return_one:
#elif defined(HAVE_PROC_CPUINFO)
	FILE	*f = NULL;
	int	ncpu = 0;

	if (NULL == (file = fopen("/proc/cpuinfo", "r")))
		goto return_one;

	while (fgets(line, 1024, file) != NULL)
	{
		if (strstr(line, "processor") == NULL)
			continue;
		ncpu++;
	}
	zbx_fclose(file);

	if (ncpu == 0)
		goto return_one;

	return ncpu;
return_one:
#elif defined(HAVE_LIBPERFSTAT)
	/* AIX 6.1 */
	perfstat_cpu_total_t	ps_cpu_total;

	if (-1 == perfstat_cpu_total(NULL, &ps_cpu_total, sizeof(ps_cpu_total), 1))
		goto return_one;

	return (int)ps_cpu_total.ncpus;
return_one:
#endif

	zabbix_log(LOG_LEVEL_WARNING, "Can not determine number of CPUs, adjust to 1");
	return 1;
}

/******************************************************************************
 *                                                                            *
 * Function: init_collector_data                                              *
 *                                                                            *
 * Purpose: Allocate memory for collector                                     *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: Linux version allocates memory as shared.                        *
 *                                                                            *
 ******************************************************************************/
void	init_collector_data()
{
	int	cpu_count;
	size_t	sz, sz_cpu;
#ifndef _WINDOWS
	key_t	shm_key;
#endif

	cpu_count = zbx_get_cpu_num();
	sz = sizeof(ZBX_COLLECTOR_DATA);

#ifdef _WINDOWS
	sz_cpu = sizeof(PERF_COUNTERS*) * (cpu_count + 1);

	collector = zbx_malloc(collector, sz + sz_cpu);
	memset(collector, 0, sz + sz_cpu);

	collector->cpus.cpu_counter = (PERF_COUNTERS **)(collector + 1);
	collector->cpus.count = cpu_count;

	init_perf_collector(&collector->perfs);

#else /* not _WINDOWS */
	sz_cpu = sizeof(ZBX_SINGLE_CPU_STAT_DATA) * (cpu_count + 1);

	if (-1 == (shm_key = zbx_ftok(CONFIG_FILE, ZBX_IPC_COLLECTOR_ID)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "Cannot create IPC key for collector");
		exit(FAIL);
	}

	if (-1 == (shm_id = zbx_shmget(shm_key, sz + sz_cpu)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "Cannot allocate shared memory for collector");
		exit(FAIL);
	}

	if ((void *)(-1) == (collector = shmat(shm_id, NULL, 0)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "Cannot attach shared memory for collector [%s]", strerror(errno));
		exit(1);
	}

	collector->cpus.cpu = (ZBX_SINGLE_CPU_STAT_DATA *)(collector + 1);
	collector->cpus.count = cpu_count;
#ifdef _AIX
	memset(&collector->vmstat, 0, sizeof(collector->vmstat));
#endif /* _AIX */

#endif /* _WINDOWS */
}

/******************************************************************************
 *                                                                            *
 * Function: free_collector_data                                              *
 *                                                                            *
 * Purpose: Free memory allocated for collector                               *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: Linux version allocates memory as shared.                        *
 *                                                                            *
 ******************************************************************************/
void	free_collector_data()
{

#if defined (_WINDOWS)

	zbx_free(collector);

#else /* not _WINDOWS */

	if (NULL == collector)
		return;

	if (-1 == shmctl(shm_id, IPC_RMID, 0))
		zabbix_log(LOG_LEVEL_WARNING, "Cannot remove shared memory for collector [%s]", strerror(errno));

#endif /* _WINDOWS */

	collector = NULL;
}

/******************************************************************************
 *                                                                            *
 * Function: collector_thread                                                 *
 *                                                                            *
 * Purpose: Collect system information                                        *
 *                                                                            *
 * Parameters:  args - skipped                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
ZBX_THREAD_ENTRY(collector_thread, args)
{
#if defined(ZABBIX_DAEMON)
	set_child_signal_handler();
#endif	/* ZABBIX_DAEMON */

#ifdef _WINDOWS
	if (0 != init_cpu_collector(&(collector->cpus)))
		close_cpu_collector(&(collector->cpus));
#endif /* _WINDOWS */

	collector_diskdevice_add("");

	while (ZBX_IS_RUNNING())
	{
		zbx_setproctitle("collector [processing data]");

#ifdef _WINDOWS
		collect_perfstat();
#else
		if (CPU_COLLECTOR_STARTED(collector))
			collect_cpustat(&(collector->cpus));
#endif /* _WINDOWS */

		collect_stats_diskdevices(&(collector->diskdevices));
#ifdef _AIX
		collect_vmstat_data(&collector->vmstat);
#endif
		zbx_setproctitle("collector [sleeping for 1 seconds]");
		zbx_sleep(1);
	}

	if (CPU_COLLECTOR_STARTED(collector))
		close_cpu_collector(&(collector->cpus));
#ifdef _WINDOWS /* cpu_collector must be closed before closing perf_collector */
	close_perf_collector();
#endif /* _WINDOWS */

	zabbix_log(LOG_LEVEL_INFORMATION, "zabbix_agentd collector stopped");

	ZBX_DO_EXIT();

	zbx_thread_exit(0);
}
