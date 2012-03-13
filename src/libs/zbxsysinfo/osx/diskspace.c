/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "common.h"
#include "sysinfo.h"
#include "zbxjson.h"

static int	get_fs_size_stat(const char *fs, zbx_uint64_t *total,
		zbx_uint64_t *used, double *pused,
		zbx_uint64_t *free, double *pfree)
{
#ifdef HAVE_SYS_STATVFS_H
#	define ZBX_STATFS	statvfs
#	define ZBX_BSIZE	f_frsize
#else
#	define ZBX_STATFS	statfs
#	define ZBX_BSIZE	f_bsize
#endif
	struct ZBX_STATFS	s;

	if (0 != ZBX_STATFS(fs, &s))
		return SYSINFO_RET_FAIL;

	if (NULL != total)
		*total = (zbx_uint64_t)s.f_blocks * s.ZBX_BSIZE;

	if (NULL != used)
		*used = (zbx_uint64_t)(s.f_blocks - s.f_bfree) * s.ZBX_BSIZE;

	if (NULL != pused)
	{
		if (0 != s.f_blocks - s.f_bfree + s.f_bavail)
			*pused = 100.0 - (100.0 * s.f_bavail) / (s.f_blocks - s.f_bfree + s.f_bavail);
		else
			*pused = 0;
	}

	if (NULL != free)
		*free = (zbx_uint64_t)s.f_bavail * s.ZBX_BSIZE;

	if (NULL != pfree)
	{
		if (0 != s.f_blocks - s.f_bfree + s.f_bavail)
			*pfree = (100.0 * s.f_bavail) / (s.f_blocks - s.f_bfree + s.f_bavail);
		else
			*pfree = 0;
	}

	return SYSINFO_RET_OK;
}

static int	VFS_FS_TOTAL(const char *fs, AGENT_RESULT *result)
{
	zbx_uint64_t	total;

	if (SYSINFO_RET_OK != get_fs_size_stat(fs, &total, NULL, NULL, NULL, NULL))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, total);

	return SYSINFO_RET_OK;

}

static int	VFS_FS_USED(const char *fs, AGENT_RESULT *result)
{
	zbx_uint64_t	used;

	if (SYSINFO_RET_OK != get_fs_size_stat(fs, NULL, &used, NULL, NULL, NULL))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, used);

	return SYSINFO_RET_OK;
}

static int	VFS_FS_PUSED(const char *fs, AGENT_RESULT *result)
{
	double	pused;

	if (SYSINFO_RET_OK != get_fs_size_stat(fs, NULL, NULL, &pused, NULL, NULL))
		return SYSINFO_RET_FAIL;

	SET_DBL_RESULT(result, pused);

	return SYSINFO_RET_OK;
}

static int	VFS_FS_FREE(const char *fs, AGENT_RESULT *result)
{
	zbx_uint64_t	free;

	if (SYSINFO_RET_OK != get_fs_size_stat(fs, NULL, NULL, NULL, &free, NULL))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, free);

	return SYSINFO_RET_OK;
}

static int	VFS_FS_PFREE(const char *fs, AGENT_RESULT *result)
{
	double	pfree;

	if (SYSINFO_RET_OK != get_fs_size_stat(fs, NULL, NULL, NULL, NULL, &pfree))
		return SYSINFO_RET_FAIL;

	SET_DBL_RESULT(result, pfree);

	return SYSINFO_RET_OK;
}

int	VFS_FS_SIZE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	const MODE_FUNCTION	fl[] =
	{
		{"total",	VFS_FS_TOTAL},
		{"used",	VFS_FS_USED},
		{"pused",	VFS_FS_PUSED},
		{"free",	VFS_FS_FREE},
		{"pfree",	VFS_FS_PFREE},
		{NULL,		0}
	};

	char	fsname[MAX_STRING_LEN], mode[8];
	int	i;

	if (2 < num_param(param))
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, fsname, sizeof(fsname)))
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 2, mode, sizeof(mode)) || '\0' == *mode)
		strscpy(mode, "total");

	for (i = 0; NULL != fl[i].mode; i++)
		if (0 == strcmp(mode, fl[i].mode))
			return (fl[i].function)(fsname, result);

	return SYSINFO_RET_FAIL;
}

int	VFS_FS_DISCOVERY(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	int		i, rc, ret = SYSINFO_RET_FAIL;
	struct statfs	*mntbuf;
	struct zbx_json	j;

	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);

	zbx_json_addarray(&j, ZBX_PROTO_TAG_DATA);

	if (0 != (rc = getmntinfo(&mntbuf, MNT_WAIT)))
	{
		for (i = 0; i < rc; i++)
		{
			zbx_json_addobject(&j, NULL);
			zbx_json_addstring(&j, "{#FSNAME}", mntbuf[i].f_mntonname, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&j, "{#FSTYPE}", mntbuf[i].f_fstypename, ZBX_JSON_TYPE_STRING);
			zbx_json_close(&j);
		}

		ret = SYSINFO_RET_OK;
	}

	zbx_json_close(&j);

	SET_STR_RESULT(result, zbx_strdup(NULL, j.buffer));

	zbx_json_free(&j);

	return ret;
}
