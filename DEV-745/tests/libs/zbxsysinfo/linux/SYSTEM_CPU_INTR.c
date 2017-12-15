#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "common.h"
#include "module.h"
#include "sysinfo.h"

void	zbx_mock_test_entry(void **state)
{
	zbx_mock_error_t	error;
	zbx_mock_handle_t	handle;
	AGENT_RESULT		result;
	AGENT_REQUEST		request;
	const char		*str;
	const char		*itemkey = "system.cpu.intr";

	ZBX_UNUSED(state);

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_out_parameter("interrupts_since_boot", &handle)))
		fail_msg("Cannot get interruptions since boot: %s", zbx_mock_error_string(error));

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_string(handle, &str)))
		fail_msg("Cannot read interruptions since boot: %s", zbx_mock_error_string(error));

	init_result(&result);
	init_request(&request);

	if (SUCCEED != parse_item_key(itemkey, &request))
		fail_msg("Invalid item key format '%s'", itemkey);

	if (SYSINFO_RET_OK == SYSTEM_CPU_INTR(&request, &result))
	{
		zbx_uint64_t	total_interr;

		if (FAIL == is_uint64(str, &total_interr))
			fail_msg("\"%s\" is not a valid numeric unsigned value", str);

		if (NULL == GET_UI64_RESULT(&result))
			fail_msg("result does not contain numeric unsigned value");

		if (total_interr != result.ui64)
			fail_msg("expected:" ZBX_FS_UI64 " actual:" ZBX_FS_UI64, total_interr, result.ui64);
	}
	else
		fail_msg("test failed '%s'", result.msg);

	free_request(&request);
	free_result(&result);
}
