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

void	zbx_mock_test_entry(void **state)
{
	zbx_mock_error_t	error;
	AGENT_REQUEST		request;
	AGENT_RESULT		result;
	const char		*expected_string, *actual_string;
	int			expected_result, actual_result;

	ZBX_UNUSED(state);

	if (ZBX_MOCK_NO_PARAMETER == (error = get_out_parameter("json", &expected_string)))
	{
		if (ZBX_MOCK_NO_PARAMETER == (error = get_out_parameter("error", &expected_string)))
			fail_msg("Invalid test case data: expected \"json\" or \"error\" out parameter");

		expected_result = SYSINFO_RET_FAIL;
	}
	else
		expected_result = SYSINFO_RET_OK;

	/* VFS_FS_DISCOVERY() does not use request */
	actual_result = VFS_FS_DISCOVERY(&request, &result);

	if (actual_result != expected_result)
	{
		fail_msg("Unexpected return code from VFS_FS_DISCOVERY(): expected %d, got %d", expected_result,
				actual_result);
	}

	if (SYSINFO_RET_OK == actual_result)
	actual_string = *GET_STR_RESULT(&result);
	else if (SYSINFO_RET_FAIL == actual_result)
		actual_string = *GET_MSG_RESULT(&result);
	else
		fail_msg("Unsupported  return code from VFS_FS_DISCOVERY(): %d", actual_result);

	if (0 != strcmp(expected_string, actual_string))
		fail_msg("Unexpected result string: expected \"%s\", got \"%s\"", expected_string, actual_string);
}
