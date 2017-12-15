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
	const char		*str;

	ZBX_UNUSED(state);

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_out_parameter("interrupts_since_boot", &handle)))
		fail_msg("Cannot zbx_mock_in_parameter: %s", zbx_mock_error_string(error));

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_string(handle, &str)))
		fail_msg("Cannot zbx_mock_string: %s", zbx_mock_error_string(error));

	init_result(&result);

	if (SYSINFO_RET_OK == SYSTEM_CPU_INTR(NULL, &result))
	{
		zbx_uint64_t	total_interr;

		ZBX_STR2UINT64(total_interr, str);

		if (total_interr != result.ui64)
			fail_msg("expected:" ZBX_FS_UI64 " actual:" ZBX_FS_UI64, total_interr, result.ui64);
	}
	else
		fail_msg("test failed '%s'", result.msg);

	free_result(&result);
}
