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

void	zbx_mock_test_entry(void **state)
{
	int			ret, simple_interval;
	zbx_custom_interval_t	*custom_intervals;
	char			*error = NULL;

	ZBX_UNUSED(state);

	setenv("TZ", get_in_param_by_name("TZ"), 1);

	ret = zbx_interval_preproc(get_in_param_by_name("interval"), &simple_interval, &custom_intervals, &error);

	assert_int_equal(ret, atoi(get_out_param_by_name("return")));

	if (SUCCEED == ret)
	{
		int	i, num, now, nextcheck;
		char	*param = NULL;

		num = atoi(get_out_param_by_name("nextcheck_num"));
		now = atoi(get_in_param_by_name("now"));

		for (i = 1; i <= num; i++)
		{
			nextcheck = calculate_item_nextcheck(atoi(get_in_param_by_name("seed")),
					atoi(get_in_param_by_name("type")), simple_interval, custom_intervals,
					now);
			param = zbx_dsprintf(param, "nextcheck#%d", i);
			assert_int_equal(nextcheck, atoi(get_out_param_by_name(param)));
			now = nextcheck + 1;
		}

		zbx_custom_interval_free(custom_intervals);
		zbx_free(param);
	}
	else
	{
		assert_string_equal(error, get_out_param_by_name("error"));
		zbx_free(error);
	}
}
