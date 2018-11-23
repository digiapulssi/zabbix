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

#define ZBX_MOCK_DISABLED	0
#define ZBX_MOCK_ENABLED	1

#include <stdlib.h>
#include <stddef.h>
#include <stdbool.h>
#include <stdarg.h>
#include <setjmp.h>
#include <cmocka.h>
#include <cmocka.h>
#include <string.h>

void	*__real_malloc(size_t size);
void	*__real_realloc(void *ptr, size_t size);
void	__real_free(void *ptr);
char	*__real_strdup(const char *);

static int	mock_memtrack = ZBX_MOCK_DISABLED;

void	*__wrap_malloc(size_t size)
{
	if (ZBX_MOCK_DISABLED == mock_memtrack)
		return __real_malloc(size);
	else
		return test_malloc(size);
}

void	*__wrap_realloc(void *ptr, size_t size)
{
	if (ZBX_MOCK_DISABLED == mock_memtrack)
		return __real_realloc(ptr, size);
	else
		return test_realloc(ptr, size);
}

void	__wrap_free(void *ptr)
{
	if (ZBX_MOCK_DISABLED == mock_memtrack)
		__real_free(ptr);
	else
		test_free(ptr);
}

void	*__wrap_zbx_malloc2(const char *file, int line, void *ptr, size_t size)
{
	if (ZBX_MOCK_DISABLED == mock_memtrack)
	{
		if (NULL != ptr)
			fail_msg("Allocating already allocated memory.");

		return __real_malloc(size);
	}
	else
		return _test_malloc(size, file, line);
}

void	*__wrap_zbx_realloc2(const char *file, int line, void *ptr, size_t size)
{
	if (ZBX_MOCK_DISABLED == mock_memtrack)
		return __real_realloc(ptr, size);
	else
		return _test_realloc(ptr, size, file, line);
}


char	*__wrap_strdup(const char *src)
{
	if (ZBX_MOCK_DISABLED == mock_memtrack)
	{
		return __real_strdup(src);
	}
	else
	{
		char	*dst;
		size_t	len;

		len = strlen(src) + 1;
		dst = test_malloc(len);
		memcpy(dst, src, len);

		return dst;
	}
}

void	*zbx_mock_malloc(void *ptr, size_t size)
{
	if (NULL != ptr)
		fail_msg("Allocating already allocated memory.");

	return __real_malloc(size);
}

void	*zbx_mock_realloc(void *ptr, size_t size)
{
	return __real_realloc(ptr, size);
}

void	zbx_mock_free(void *ptr)
{
	__real_free(ptr);
}

void	zbx_mock_memtrack_enable()
{
	mock_memtrack = ZBX_MOCK_ENABLED;
}

void	zbx_mock_memtrack_disable()
{
	mock_memtrack = ZBX_MOCK_DISABLED;
}
