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
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

#include "common.h"
#include "pid.h"
#include "log.h"
#include "threads.h"
static FILE	*fpid = NULL;
static int	fdpid = -1;

int	create_pid_file(const char *pidfile)
{
	int		fd;
	struct stat	buf;
	struct flock	fl;

	fl.l_type = F_WRLCK;
	fl.l_whence = SEEK_SET;
	fl.l_start = 0;
	fl.l_len = 0;
	fl.l_pid = getpid();

	/* check if pid file already exists */
	if (0 == stat(pidfile, &buf))
	{
		if (-1 == (fd = open(pidfile, O_WRONLY | O_APPEND)))
		{
			zbx_error("cannot open PID file [%s]: %s", pidfile, zbx_strerror(errno));
			zabbix_log(LOG_LEVEL_CRIT, "cannot open PID file [%s]: %s", pidfile, zbx_strerror(errno));
			return FAIL;
		}

		if (-1 == fcntl(fd, F_SETLK, &fl) && EAGAIN == errno)
		{
			close(fd);
			zbx_error("File [%s] exists and is locked. Is this process already running?", pidfile);
			zabbix_log(LOG_LEVEL_CRIT, "File [%s] exists and is locked. Is this process already running?", pidfile);
			return FAIL;
		}

		close(fd);
	}

	/* open pid file */
	if (NULL == (fpid = fopen(pidfile, "w")))
	{
		zbx_error("cannot create PID file [%s]: %s", pidfile, zbx_strerror(errno));
		zabbix_log(LOG_LEVEL_CRIT, "cannot create PID file [%s]: %s", pidfile, zbx_strerror(errno));
		return FAIL;
	}

	/* lock file */
	if (-1 != (fdpid = fileno(fpid)))
	{
		fcntl(fdpid, F_SETLK, &fl);
		fcntl(fdpid, F_SETFD, FD_CLOEXEC);
	}

	/* write pid to file */
	fprintf(fpid, "%d", (int)getpid());
	fflush(fpid);

	return SUCCEED;
}

int	read_pid_file(const char *pidfile, pid_t *pid, char *error, size_t max_error_len)
{
	int	fd, ret = FAIL;
	char	buf[MAX_ID_LEN];

	if (-1 == (fd = open(pidfile, O_RDONLY)))
	{
		zbx_snprintf(error, max_error_len, "cannot open PID file [%s]: %s", pidfile, zbx_strerror(errno));
		return ret;
	}

	if (-1 != read(fd, buf, sizeof(buf)))
	{
		if (1 == sscanf(buf, "%d", (int *)pid))
			ret = SUCCEED;
	}
	close(fd);

	return ret;
}

void	drop_pid_file(const char *pidfile)
{
	struct flock	fl;

	fl.l_type = F_UNLCK;
	fl.l_whence = SEEK_SET;
	fl.l_start = 0;
	fl.l_len = 0;
	fl.l_pid = zbx_get_thread_id();

	/* unlock file */
	if (-1 != fdpid)
		fcntl(fdpid, F_SETLK, &fl);

	/* close pid file */
	zbx_fclose(fpid);

	unlink(pidfile);
}
