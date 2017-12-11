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

#include "zbxmocktest.h"
#include "zbxmockdata.h"

#include "common.h"
#include "sysinfo.h"
#include "db.h"
#include "log.h"

#define	ZBX_MOCK_NOT_AN_INTEGER	10

static zbx_mock_error_t	get_out_parameter(const char *name, const char **value)
{
	zbx_mock_handle_t	handle;
	zbx_mock_error_t	error;

	if (ZBX_MOCK_NO_PARAMETER != (error = zbx_mock_out_parameter(name, &handle)) && ZBX_MOCK_SUCCESS != error)
		fail_msg("Cannot get \"%s\": %s", name, zbx_mock_error_string(error));
	else if (ZBX_MOCK_SUCCESS == error && ZBX_MOCK_SUCCESS != (error = zbx_mock_string(handle, value)))
		fail_msg("Cannot get \"%s\": %s", name, zbx_mock_error_string(error));

	return error;
}

static zbx_mock_error_t	int_out_paramater(const char *name, int *value)
{
	zbx_mock_handle_t	handle;
	zbx_mock_error_t	error;
	char			*string;

	if (ZBX_MOCK_NO_PARAMETER == (error = zbx_mock_out_parameter(name, &handle)))
		return ZBX_MOCK_SUCCESS;

	if (ZBX_MOCK_SUCCESS != error)
	{
		fail_msg("Cannot get \"%s\": %s", name, zbx_mock_error_string(error));
		return error;
	}

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_string(handle, (const char **)&string)))
	{
		fail_msg("Cannot extract string from \"%s\": %s", name, zbx_mock_error_string(error));
		return error;
	}

	if (SUCCEED == is_uint_n_range(string, ZBX_SIZE_T_MAX, value, sizeof(int), INT_MIN, INT_MAX))
		return ZBX_MOCK_SUCCESS;

	fail_msg("Not a valid integer: \"%s\"", string);
	return ZBX_MOCK_NOT_AN_INTEGER;
}

static zbx_mock_error_t	uint64_out_paramater(const char *name, zbx_uint64_t *value)
{
	zbx_mock_handle_t	handle;
	zbx_mock_error_t	error;
	char			*string;

	if (ZBX_MOCK_NO_PARAMETER == (error = zbx_mock_out_parameter(name, &handle)))
		return ZBX_MOCK_SUCCESS;

	if (ZBX_MOCK_SUCCESS != error)
	{
		fail_msg("Cannot get \"%s\": %s", name, zbx_mock_error_string(error));
		return error;
	}

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_string(handle, (const char **)&string)))
	{
		fail_msg("Cannot extract string from \"%s\": %s", name, zbx_mock_error_string(error));
		return error;
	}

	if (SUCCEED == str2uint64(string, "", value))
		return ZBX_MOCK_SUCCESS;

	fail_msg("Not a valid uint64: \"%s\"", string);
	return ZBX_MOCK_NOT_AN_INTEGER;
}

void	zbx_mock_test_entry(void **state)
{
	zbx_mock_error_t	error;
	zbx_mock_handle_t	expected_error_h;
	AGENT_REQUEST		request;
	AGENT_RESULT		result;
	const char		*expected_error = NULL, *actual_error;
	zbx_uint64_t		expected_result, actual_result;
	int			expected_ret = SYSINFO_RET_OK, actual_ret;

	ZBX_UNUSED(state);

	if (ZBX_MOCK_SUCCESS != (error = uint64_out_paramater("value", &expected_result)))
	{
		fail_msg("Didn't get expected_result from test case data: %s", zbx_mock_error_string(error));
	}

	if (ZBX_MOCK_SUCCESS == zbx_mock_out_parameter("error", &expected_error_h))
		expected_ret = SYSINFO_RET_FAIL;

	init_metrics();

	/* KERNEL_MAXFILES() does not use request */
	actual_ret = KERNEL_MAXFILES(&request, &result);

	if (actual_ret != expected_ret)
	{
		fail_msg("Unexpected return code from KERNEL_MAXFILES(): expected %d, got %d", expected_ret, actual_ret);
	}

	switch (actual_ret)
	{
		case SYSINFO_RET_OK:
			actual_result = *GET_UI64_RESULT(&result);
			if (actual_result != expected_result)
				fail_msg("Unexpected result from KERNEL_MAXFILES(): expected " ZBX_FS_UI64
						", got " ZBX_FS_UI64, expected_result, actual_result);
			break;
		case SYSINFO_RET_FAIL:
			if (ZBX_MOCK_SUCCESS != (error = zbx_mock_out_parameter("error", &expected_error_h)) ||
				ZBX_MOCK_SUCCESS != (error = zbx_mock_string(expected_error_h, &expected_error)))
			{
				fail_msg("Cannot get expected error message: %s", zbx_mock_error_string(error));
				break;
			}

			actual_error = *GET_MSG_RESULT(&result);
			if (0 != strcmp(actual_error, expected_error))
				fail_msg("Unexpected error string: expected \"%s\", got \"%s\"",
						expected_error, actual_error);
			break;
		default:
			fail_msg("Unsupported return code from KERNEL_MAXFILES(): %d", actual_ret);
	}
}

/*
extern char	*__real_zbx_strdup2(const char *filename, int line, char *old, const char *str);
char	*__wrap_zbx_strdup2(const char *filename, int line, char *old, const char *str)
{
	zabbix_log(LOG_LEVEL_INFORMATION, "[file:%s,line:%d] MOCKED zbx_strdup(%p,%p) WILL PROCEED.",
			filename, line, old, str);

	return __real_zbx_strdup2(filename, line, old, str);
}
*/

