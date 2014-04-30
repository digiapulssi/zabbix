/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

#define ZBX_DEV_PFX	"/dev/"

typedef struct
{
	zbx_uint64_t	nread;
	zbx_uint64_t	nwritten;
	zbx_uint64_t	reads;
	zbx_uint64_t	writes;
}
zbx_perfstat_t;

int	get_diskstat(const char *devname, zbx_uint64_t *dstat)
{
	return FAIL;
}

static int	get_perfstat_io(const char *devname, zbx_perfstat_t *zp)
{
#if defined(HAVE_LIBPERFSTAT)
	if ('\0' != *devname)
	{
		perfstat_id_t	name;
		perfstat_disk_t	data;

		strscpy(name.name, devname);

		if (0 < perfstat_disk(&name, &data, sizeof(data), 1))
		{
			zp->nread = data.rblks * data.bsize;
			zp->nwritten = data.wblks * data.bsize;
			zp->reads = data.xrate;
			zp->writes = data.xfers - data.xrate;

			return SYSINFO_RET_OK;
		}
		else
			return SYSINFO_RET_FAIL;
	}
	else
	{
		perfstat_disk_total_t	data;

		if (0 < perfstat_disk_total(NULL, &data, sizeof(data), 1))
		{
			zp->nread = data.rblks * 512;
			zp->nwritten = data.wblks * 512;
			zp->reads = data.xrate;
			zp->writes = data.xfers - data.xrate;

			return SYSINFO_RET_OK;
		}
		else
			return SYSINFO_RET_FAIL;
	}
#else
	return SYSINFO_RET_FAIL;
#endif
}

static int	VFS_DEV_READ_BYTES(const char *devname, AGENT_RESULT *result)
{
	zbx_perfstat_t	zp;

	if (SYSINFO_RET_OK != get_perfstat_io(devname, &zp))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, zp.nread);

	return SYSINFO_RET_OK;
}

static int	VFS_DEV_READ_OPERATIONS(const char *devname, AGENT_RESULT *result)
{
	zbx_perfstat_t	zp;

	if (SYSINFO_RET_OK != get_perfstat_io(devname, &zp))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, zp.reads);

	return SYSINFO_RET_OK;
}

static int	VFS_DEV_WRITE_BYTES(const char *devname, AGENT_RESULT *result)
{
	zbx_perfstat_t	zp;

	if (SYSINFO_RET_OK != get_perfstat_io(devname, &zp))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, zp.nwritten);

	return SYSINFO_RET_OK;
}

static int	VFS_DEV_WRITE_OPERATIONS(const char *devname, AGENT_RESULT *result)
{
	zbx_perfstat_t	zp;

	if (SYSINFO_RET_OK != get_perfstat_io(devname, &zp))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, zp.writes);

	return SYSINFO_RET_OK;
}

int	VFS_DEV_READ(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	const char	*devname, *type;
	int		ret = SYSINFO_RET_FAIL;

	if (2 < request->nparam)
		return ret;

	devname = get_rparam(request, 0);

	if (NULL == devname || 0 == strcmp("all", devname))
		devname = "";
	else if (0 == strncmp(ZBX_DEV_PFX, devname, sizeof(ZBX_DEV_PFX) - 1))
		devname += sizeof(ZBX_DEV_PFX) - 1;

	type = get_rparam(request, 1);

	if (NULL == type || '\0' == *type || 0 == strcmp(type, "operations"))
		ret = VFS_DEV_READ_OPERATIONS(devname, result);
	else if (0 == strcmp(type, "bytes"))
		ret = VFS_DEV_READ_BYTES(devname, result);
	else
		ret = SYSINFO_RET_FAIL;

	return ret;
}

int	VFS_DEV_WRITE(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	const char	*devname, *type;
	int		ret = SYSINFO_RET_FAIL;

	if (2 < request->nparam)
		return ret;

	devname = get_rparam(request, 0);

	if (NULL == devname || 0 == strcmp("all", devname))
		devname = "";
	else if (0 == strncmp(ZBX_DEV_PFX, devname, sizeof(ZBX_DEV_PFX) - 1))
		devname += sizeof(ZBX_DEV_PFX) - 1;

	type = get_rparam(request, 1);

	if (NULL == type || '\0' == *type || 0 == strcmp(type, "operations"))
		ret = VFS_DEV_WRITE_OPERATIONS(devname, result);
	else if (0 == strcmp(type, "bytes"))
		ret = VFS_DEV_WRITE_BYTES(devname, result);
	else
		ret = SYSINFO_RET_FAIL;

	return ret;
}
