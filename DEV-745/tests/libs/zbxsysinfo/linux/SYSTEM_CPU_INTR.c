#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "common.h"
#include "module.h"
#include "sysinfo.h"

static void	read_yaml_data(zbx_mock_handle_t *handle, zbx_uint64_t *interr, int *ret)
{
	zbx_mock_error_t	error;
	const char		*str;

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_out_parameter("interrupts_since_boot", handle)))
		fail_msg("Cannot get interruptions since boot: %s", zbx_mock_error_string(error));

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_string(*handle, &str)))
		fail_msg("Cannot read interruptions since boot: %s", zbx_mock_error_string(error));

	if (FAIL == is_uint64(str, interr))
		fail_msg("\"%s\" is not a valid numeric unsigned value", str);

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_out_parameter("ret", handle)))
		fail_msg("Cannot get return code: %s", zbx_mock_error_string(error));

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_string(*handle, &str)))
		fail_msg("Cannot read return code: %s", zbx_mock_error_string(error));

	if (SYSINFO_RET_OK != (*ret = atoi(str)) && *ret != SYSINFO_RET_FAIL)
		fail_msg("Incorrect return code '%s'", str);
}

void	zbx_mock_test_entry(void **state)
{
	zbx_mock_handle_t	handle;
	AGENT_RESULT		result;
	AGENT_REQUEST		request;
	zbx_uint64_t		interr;
	const char		*itemkey = "system.cpu.intr";
	int			ret, ret_actual;

	ZBX_UNUSED(state);

	read_yaml_data(&handle, &interr, &ret);

	init_result(&result);
	init_request(&request);

	if (SUCCEED != parse_item_key(itemkey, &request))
		fail_msg("Invalid item key format '%s'", itemkey);

	if (ret != (ret_actual = SYSTEM_CPU_INTR(&request, &result)))
		fail_msg("unexpected return code:%d", ret_actual);

	if (ret == SYSINFO_RET_OK)
	{
		if (NULL == GET_UI64_RESULT(&result))
			fail_msg("result does not contain numeric unsigned value");
		if (interr != result.ui64)
			fail_msg("expected:" ZBX_FS_UI64 " actual:" ZBX_FS_UI64, interr, result.ui64);
	}
	else if (NULL == GET_MSG_RESULT(&result))
		fail_msg("result does not contain failure message");

	free_request(&request);
	free_result(&result);
}
