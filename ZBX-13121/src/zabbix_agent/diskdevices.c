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

#ifndef _WINDOWS

#include "common.h"
#include "diskdevices.h"
#include "log.h"
#include "mutexs.h"
#include "ipc.h"

#define LOCK_DISKSTATS		zbx_mutex_lock(&diskstats_lock)
#define UNLOCK_DISKSTATS	zbx_mutex_unlock(&diskstats_lock)

typedef struct
{
	int				count;		/* number of disks to collect statistics for */
	int				max_diskdev;	/* number of "slots" for disk statistics */
	ZBX_SINGLE_DISKDEVICE_DATA	device[1];	/* more "slots" for disk statistics added dynamically */
}
ZBX_DISKDEVICES_DATA;

static ZBX_MUTEX		diskstats_lock = ZBX_MUTEX_NULL;
static int			*diskstat_shmid = NULL;
static int			my_diskstat_shmid = ZBX_NONEXISTENT_SHMID;
static ZBX_DISKDEVICES_DATA	*diskdevices = NULL;

/******************************************************************************
 *                                                                            *
 * Function: diskstat_shm_init                                                *
 *                                                                            *
 * Purpose: Allocate shared memory for collecting disk statistics             *
 *                                                                            *
 ******************************************************************************/
static void	diskstat_shm_init(void)
{
	/* initially allocate memory for collecting statistics for only 1 disk */
	if (-1 == (*diskstat_shmid = zbx_shm_create(sizeof(ZBX_DISKDEVICES_DATA))))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot allocate shared memory for disk statistics collector");
		exit(EXIT_FAILURE);
	}

	if ((void *)(-1) == (diskdevices = shmat(*diskstat_shmid, NULL, 0)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot attach shared memory for disk statistics collector: %s",
				zbx_strerror(errno));
		exit(EXIT_FAILURE);
	}

	diskdevices->count = 0;
	diskdevices->max_diskdev = 1;
	my_diskstat_shmid = *diskstat_shmid;

	zabbix_log(LOG_LEVEL_DEBUG, "diskstat_shm_init() allocated initial shared memory segment id:%d"
			" for disk statistics collector", *diskstat_shmid);
}

/******************************************************************************
 *                                                                            *
 * Function: diskstat_shm_reattach                                            *
 *                                                                            *
 * Purpose: If necessary, reattach to disk statistics shared memory segment.  *
 *                                                                            *
 ******************************************************************************/
static void	diskstat_shm_reattach(void)
{
	if (my_diskstat_shmid == *diskstat_shmid)
		return;

	if (NULL != diskdevices && -1 == shmdt(diskdevices))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot detach from disk statistics collector shared memory: %s",
				zbx_strerror(errno));
		exit(EXIT_FAILURE);
	}

	if ((void *)(-1) == (diskdevices = shmat(*diskstat_shmid, NULL, 0)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot attach shared memory for disk statistics collector: %s",
				zbx_strerror(errno));
		exit(EXIT_FAILURE);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "diskstat_shm_reattach() switched from shared memory segment %d to %d",
			my_diskstat_shmid, *diskstat_shmid);
	my_diskstat_shmid = *diskstat_shmid;
}

/******************************************************************************
 *                                                                            *
 * Function: diskstat_shm_extend                                              *
 *                                                                            *
 * Purpose: create a new, larger disk statistics shared memory segment and    *
 *          copy data from the old one.                                       *
 *                                                                            *
 ******************************************************************************/
static void	diskstat_shm_extend(void)
{
	const char		*__function_name = "diskstat_shm_extend";
	size_t			old_shm_size, new_shm_size;
	int			old_shmid, new_shmid, old_max, new_max;
	ZBX_DISKDEVICES_DATA	*new_diskdevices;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	/* calculate the size of the new shared memory segment */
	old_max = diskdevices->max_diskdev;

	if (old_max < 4)
		new_max = old_max + 1;
	else if (old_max < 256)
		new_max = old_max * 2;
	else
		new_max = old_max + 256;

	old_shm_size = sizeof(ZBX_DISKDEVICES_DATA) + sizeof(ZBX_SINGLE_DISKDEVICE_DATA) * (old_max - 1);
	new_shm_size = sizeof(ZBX_DISKDEVICES_DATA) + sizeof(ZBX_SINGLE_DISKDEVICE_DATA) * (new_max - 1);

	if (-1 == (new_shmid = zbx_shm_create(new_shm_size)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot allocate shared memory for extending disk statistics collector");
		exit(EXIT_FAILURE);
	}

	if ((void *)(-1) == (new_diskdevices = shmat(new_shmid, NULL, 0)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot attach shared memory for extending disk statistics collector: %s",
				zbx_strerror(errno));
		exit(EXIT_FAILURE);
	}

	/* copy data from the old segment */
	memcpy(new_diskdevices, diskdevices, old_shm_size);
	new_diskdevices->max_diskdev = new_max;

	/* delete the old segment */
	if (-1 == shmdt(diskdevices))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot detach from disk statistics collector shared memory");
		exit(EXIT_FAILURE);
	}

	if (-1 == zbx_shm_destroy(*diskstat_shmid))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot destroy old disk statistics collector shared memory");
		exit(EXIT_FAILURE);
	}

	/* switch to the new segment */
	old_shmid = *diskstat_shmid;
	*diskstat_shmid = new_shmid;
	my_diskstat_shmid = *diskstat_shmid;
	diskdevices = new_diskdevices;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() extended diskstat shared memory: old_max:%d new_max:%d old_size:%d"
			" new_size:%d old_shmid:%d new_shmid:%d", __function_name, old_max, new_max, old_shm_size,
			new_shm_size, old_shmid, new_shmid);
}

static void	apply_diskstat(ZBX_SINGLE_DISKDEVICE_DATA *device, time_t now, zbx_uint64_t *dstat)
{
	register int	i;
	time_t		clock[ZBX_AVG_COUNT], sec;
	int		index[ZBX_AVG_COUNT];

	assert(device);

	device->index++;

	if (MAX_COLLECTOR_HISTORY == device->index)
		device->index = 0;

	device->clock[device->index] = now;
	device->r_sect[device->index] = dstat[ZBX_DSTAT_R_SECT];
	device->r_oper[device->index] = dstat[ZBX_DSTAT_R_OPER];
	device->r_byte[device->index] = dstat[ZBX_DSTAT_R_BYTE];
	device->w_sect[device->index] = dstat[ZBX_DSTAT_W_SECT];
	device->w_oper[device->index] = dstat[ZBX_DSTAT_W_OPER];
	device->w_byte[device->index] = dstat[ZBX_DSTAT_W_BYTE];

	clock[ZBX_AVG1] = clock[ZBX_AVG5] = clock[ZBX_AVG15] = now + 1;
	index[ZBX_AVG1] = index[ZBX_AVG5] = index[ZBX_AVG15] = -1;

	for (i = 0; i < MAX_COLLECTOR_HISTORY; i++)
	{
		if (0 == device->clock[i])
			continue;

#define DISKSTAT(t)\
		if ((device->clock[i] >= (now - (t * 60))) && (clock[ZBX_AVG ## t] > device->clock[i]))\
		{\
			clock[ZBX_AVG ## t] = device->clock[i];\
			index[ZBX_AVG ## t] = i;\
		}

		DISKSTAT(1);
		DISKSTAT(5);
		DISKSTAT(15);
	}

#define SAVE_DISKSTAT(t)\
	if (-1 == index[ZBX_AVG ## t] || 0 == now - device->clock[index[ZBX_AVG ## t]])\
	{\
		device->r_sps[ZBX_AVG ## t] = 0;\
		device->r_ops[ZBX_AVG ## t] = 0;\
		device->r_bps[ZBX_AVG ## t] = 0;\
		device->w_sps[ZBX_AVG ## t] = 0;\
		device->w_ops[ZBX_AVG ## t] = 0;\
		device->w_bps[ZBX_AVG ## t] = 0;\
	}\
	else\
	{\
		sec = now - device->clock[index[ZBX_AVG ## t]];\
		device->r_sps[ZBX_AVG ## t] = (dstat[ZBX_DSTAT_R_SECT] - device->r_sect[index[ZBX_AVG ## t]]) / (double)sec;\
		device->r_ops[ZBX_AVG ## t] = (dstat[ZBX_DSTAT_R_OPER] - device->r_oper[index[ZBX_AVG ## t]]) / (double)sec;\
		device->r_bps[ZBX_AVG ## t] = (dstat[ZBX_DSTAT_R_BYTE] - device->r_byte[index[ZBX_AVG ## t]]) / (double)sec;\
		device->w_sps[ZBX_AVG ## t] = (dstat[ZBX_DSTAT_W_SECT] - device->w_sect[index[ZBX_AVG ## t]]) / (double)sec;\
		device->w_ops[ZBX_AVG ## t] = (dstat[ZBX_DSTAT_W_OPER] - device->w_oper[index[ZBX_AVG ## t]]) / (double)sec;\
		device->w_bps[ZBX_AVG ## t] = (dstat[ZBX_DSTAT_W_BYTE] - device->w_byte[index[ZBX_AVG ## t]]) / (double)sec;\
	}

	SAVE_DISKSTAT(1);
	SAVE_DISKSTAT(5);
	SAVE_DISKSTAT(15);
}

static void	process_diskstat(ZBX_SINGLE_DISKDEVICE_DATA *device)
{
	time_t		now;
	zbx_uint64_t	dstat[ZBX_DSTAT_MAX];

	now = time(NULL);
	if (FAIL == get_diskstat(device->name, dstat))
		return;

	apply_diskstat(device, now, dstat);
}

void	collect_stats_diskdevices(void)
{
	int	i;

	LOCK_DISKSTATS;

	if (ZBX_NONEXISTENT_SHMID == *diskstat_shmid)
		goto unlock;

	diskstat_shm_reattach();

	for (i = 0; i < diskdevices->count; i++)
		process_diskstat(&diskdevices->device[i]);
unlock:
	UNLOCK_DISKSTATS;
}

ZBX_SINGLE_DISKDEVICE_DATA	*collector_diskdevice_get(const char *devname)
{
	const char			*__function_name = "collector_diskdevice_get";
	int				i;
	ZBX_SINGLE_DISKDEVICE_DATA	*device = NULL;

	assert(devname);

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() devname:'%s'", __function_name, devname);

	LOCK_DISKSTATS;

	if (ZBX_NONEXISTENT_SHMID == *diskstat_shmid)
		diskstat_shm_init();
	else
		diskstat_shm_reattach();

	for (i = 0; i < diskdevices->count; i++)
	{
		if (0 == strcmp(devname, diskdevices->device[i].name))
		{
			device = &diskdevices->device[i];
			zabbix_log(LOG_LEVEL_DEBUG, "%s() device '%s' found", __function_name, devname);
			break;
		}
	}

	UNLOCK_DISKSTATS;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%p", __function_name, device);

	return device;
}

ZBX_SINGLE_DISKDEVICE_DATA	*collector_diskdevice_add(const char *devname)
{
	const char			*__function_name = "collector_diskdevice_add";
	ZBX_SINGLE_DISKDEVICE_DATA	*device = NULL;

	assert(devname);

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() devname:'%s'", __function_name, devname);

	LOCK_DISKSTATS;

	if (ZBX_NONEXISTENT_SHMID == *diskstat_shmid)
		diskstat_shm_init();
	else
		diskstat_shm_reattach();

	if (diskdevices->count == MAX_DISKDEVICES)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() collector is full", __function_name);
		goto end;
	}

	if (diskdevices->count == diskdevices->max_diskdev)
		diskstat_shm_extend();

	device = &(diskdevices->device[diskdevices->count]);
	zbx_strlcpy(device->name, devname, sizeof(device->name));
	device->index = -1;
	(diskdevices->count)++;

	process_diskstat(device);
end:
	UNLOCK_DISKSTATS;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%p", __function_name, device);

	return device;
}

int	zbx_diskstat_init(int *shmid, char **error)
{
	diskstat_shmid = shmid;
	*diskstat_shmid = ZBX_NONEXISTENT_SHMID;
	return zbx_mutex_create(&diskstats_lock, ZBX_MUTEX_DISKSTATS, error);
}

void	zbx_diskstat_destroy(void)
{
	if (ZBX_NONEXISTENT_SHMID != *diskstat_shmid)
	{
		if (-1 == shmctl(*diskstat_shmid, IPC_RMID, NULL))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot remove shared memory for disk statistics collector: %s",
					zbx_strerror(errno));
		}

		diskdevices = NULL;
		*diskstat_shmid = ZBX_NONEXISTENT_SHMID;
	}

	zbx_mutex_destroy(&diskstats_lock);
}

#endif	/* _WINDOWS */
