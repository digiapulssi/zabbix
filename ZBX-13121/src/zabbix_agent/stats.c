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
#include "stats.h"
#include "log.h"
#include "zbxconf.h"
#include "zbxself.h"

#ifndef _WINDOWS
#	include "diskdevices.h"
#endif
#include "cfg.h"
#include "mutexs.h"

#ifdef _WINDOWS
#	include "service.h"
#	include "perfstat.h"
#else
#	include "daemon.h"
#	include "ipc.h"
#endif

ZBX_COLLECTOR_DATA	*collector = NULL;

extern ZBX_THREAD_LOCAL unsigned char	process_type;
extern ZBX_THREAD_LOCAL int		server_num, process_num;

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
 ******************************************************************************/
static int	zbx_get_cpu_num(void)
{
#if defined(_WINDOWS)
	/* Define a function pointer type for the GetActiveProcessorCount API */
	typedef DWORD (WINAPI *GETACTIVEPC) (WORD);

	GETACTIVEPC	get_act;
	SYSTEM_INFO	sysInfo;

	/* The rationale for checking dynamically if the GetActiveProcessorCount is implemented */
	/* in kernel32.lib, is because the function is implemented only on 64 bit versions of Windows */
	/* from Windows 7 onward. Windows Vista 64 bit doesn't have it and also Windows XP does */
	/* not. We can't resolve this using conditional compilation unless we release multiple agents */
	/* targeting different sets of Windows APIs. */
	get_act = (GETACTIVEPC)GetProcAddress(GetModuleHandle(TEXT("kernel32.dll")), "GetActiveProcessorCount");

	if (NULL != get_act)
	{
		return (int)get_act(ALL_PROCESSOR_GROUPS);
	}
	else
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Cannot find address of GetActiveProcessorCount function");

		GetNativeSystemInfo(&sysInfo);

		return (int)sysInfo.dwNumberOfProcessors;
	}
#elif defined(HAVE_SYS_PSTAT_H)
	struct pst_dynamic	psd;

	if (-1 == pstat_getdynamic(&psd, sizeof(struct pst_dynamic), 1, 0))
		goto return_one;

	return (int)psd.psd_proc_cnt;
#elif defined(_SC_NPROCESSORS_CONF)
	/* FreeBSD 7.0 x86 */
	/* Solaris 10 x86 */
	int	ncpu;

	if (-1 == (ncpu = sysconf(_SC_NPROCESSORS_CONF)))
		goto return_one;

	return ncpu;
#elif defined(HAVE_FUNCTION_SYSCTL_HW_NCPU)
	/* FreeBSD 6.2 x86; FreeBSD 7.0 x86 */
	/* NetBSD 3.1 x86; NetBSD 4.0 x86 */
	/* OpenBSD 4.2 x86 */
	size_t	len;
	int	mib[] = {CTL_HW, HW_NCPU}, ncpu;

	len = sizeof(ncpu);

	if (0 != sysctl(mib, 2, &ncpu, &len, NULL, 0))
		goto return_one;

	return ncpu;
#elif defined(HAVE_PROC_CPUINFO)
	FILE	*f = NULL;
	int	ncpu = 0;

	if (NULL == (file = fopen("/proc/cpuinfo", "r")))
		goto return_one;

	while (NULL != fgets(line, 1024, file))
	{
		if (NULL == strstr(line, "processor"))
			continue;
		ncpu++;
	}
	zbx_fclose(file);

	if (0 == ncpu)
		goto return_one;

	return ncpu;
#elif defined(HAVE_LIBPERFSTAT)
	/* AIX 6.1 */
	perfstat_partition_config_t	part_cfg;
	int				rc;

	rc = perfstat_partition_config(NULL, &part_cfg, sizeof(perfstat_partition_config_t), 1);

	if (1 != rc)
		goto return_one;

	return (int)part_cfg.lcpus;
#endif

#ifndef _WINDOWS
return_one:
	zabbix_log(LOG_LEVEL_WARNING, "cannot determine number of CPUs, assuming 1");
	return 1;
#endif
}

/******************************************************************************
 *                                                                            *
 * Function: init_collector_data                                              *
 *                                                                            *
 * Purpose: Allocate memory for collector                                     *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: Unix version allocates memory as shared.                         *
 *                                                                            *
 ******************************************************************************/
int	init_collector_data(char **error)
{
	const char	*__function_name = "init_collector_data";
	int		cpu_count, ret = FAIL;
	size_t		sz, sz_cpu;
#ifndef _WINDOWS
	int		shm_id;
#endif

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	cpu_count = zbx_get_cpu_num();
	sz = ZBX_SIZE_T_ALIGN8(sizeof(ZBX_COLLECTOR_DATA));

#ifdef _WINDOWS
	sz_cpu = sizeof(zbx_perf_counter_data_t *) * (cpu_count + 1);

	collector = zbx_malloc(collector, sz + sz_cpu);
	memset(collector, 0, sz + sz_cpu);

	collector->cpus.cpu_counter = (zbx_perf_counter_data_t **)((char *)collector + sz);
	collector->cpus.count = cpu_count;
#else
	sz_cpu = sizeof(ZBX_SINGLE_CPU_STAT_DATA) * (cpu_count + 1);

	if (-1 == (shm_id = zbx_shm_create(sz + sz_cpu)))
	{
		*error = zbx_strdup(*error, "cannot allocate shared memory for collector");
		goto out;
	}

	if ((void *)(-1) == (collector = shmat(shm_id, NULL, 0)))
	{
		*error = zbx_dsprintf(*error, "cannot attach shared memory for collector: %s", zbx_strerror(errno));
		goto out;
	}

	/* Immediately mark the new shared memory for destruction after attaching to it */
	if (-1 == zbx_shm_destroy(shm_id))
	{
		*error = zbx_strdup(*error, "cannot mark the new shared memory for destruction.");
		goto out;
	}

	collector->cpus.cpu = (ZBX_SINGLE_CPU_STAT_DATA *)((char *)collector + sz);
	collector->cpus.count = cpu_count;
#ifdef ZBX_PROCSTAT_COLLECTOR
	zbx_procstat_init();
#endif
	if (SUCCEED != zbx_diskstat_init(&collector->diskstat_shmid, error))
		goto out;
#endif

#ifdef _AIX
	memset(&collector->vmstat, 0, sizeof(collector->vmstat));
#endif
	ret = SUCCEED;
#ifndef _WINDOWS
out:
#endif
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: free_collector_data                                              *
 *                                                                            *
 * Purpose: Free memory allocated for collector                               *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: Unix version allocated memory as shared.                         *
 *                                                                            *
 ******************************************************************************/
void	free_collector_data(void)
{
#ifdef _WINDOWS
	zbx_free(collector);
#else
	if (NULL == collector)
		return;

#ifdef ZBX_PROCSTAT_COLLECTOR
	zbx_procstat_destroy();
#endif
	zbx_diskstat_destroy();
#endif
	collector = NULL;
}

/******************************************************************************
 *                                                                            *
 * Function: collector_thread                                                 *
 *                                                                            *
 * Purpose: Collect system information                                        *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 ******************************************************************************/
ZBX_THREAD_ENTRY(collector_thread, args)
{
	assert(args);

	process_type = ((zbx_thread_args_t *)args)->process_type;
	server_num = ((zbx_thread_args_t *)args)->server_num;
	process_num = ((zbx_thread_args_t *)args)->process_num;

	zabbix_log(LOG_LEVEL_INFORMATION, "agent #%d started [collector]", server_num);

	zbx_free(args);

	while (ZBX_IS_RUNNING())
	{
		zbx_handle_log();

		zbx_setproctitle("collector [processing data]");
#ifdef _WINDOWS
		collect_perfstat();
#else
		if (0 != CPU_COLLECTOR_STARTED(collector))
			collect_cpustat(&(collector->cpus));

		collect_stats_diskdevices();
#ifdef ZBX_PROCSTAT_COLLECTOR
		zbx_procstat_collect();
#endif

#endif
#ifdef _AIX
		if (1 == collector->vmstat.enabled)
			collect_vmstat_data(&collector->vmstat);
#endif
		zbx_setproctitle("collector [idle 1 sec]");
		zbx_sleep(1);

#if !defined(_WINDOWS) && defined(HAVE_RESOLV_H)
		zbx_update_resolver_conf();	/* handle /etc/resolv.conf update */
#endif
	}

#ifdef _WINDOWS
	if (0 != CPU_COLLECTOR_STARTED(collector))
		free_cpu_collector(&(collector->cpus));

	ZBX_DO_EXIT();

	zbx_thread_exit(EXIT_SUCCESS);
#endif
}
