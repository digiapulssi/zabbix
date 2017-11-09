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

#define ZBX_TIME_FORMAT	"%04d.%02d.%02d %02d:%02d:%02d"

#define ZBX_DATETIME_FORMAT	"%04d-%02d-%02d %02d:%02d:%02d"
#define ZBX_TIMEZONE_FORMAT	"%+03d:%02d"

#define ZBX_DATETIME_LEN	(4 + 1 + 2 + 1 + 2 + 1 + 2 + 1 + 2 + 1 + 2)
#define ZBX_TIMEZONE_LEN	(1 + 2 + 1 + 2)
#define ZBX_FULLTIME_LEN	(ZBX_DATETIME_LEN + ZBX_TIMEZONE_LEN)

/******************************************************************************
 *                                                                            *
 * Function: strtime_tz_sec                                                   *
 *                                                                            *
 * Purpose: gets timezone offset in seconds from date in RFC 3339 format      *
 *          (for example 2017-10-09 14:26:43+03:00)                           *
 *                                                                            *
 * Parameters: strtime - [IN] the time in RFC 3339 format                     *
 *             tz_sec  - [OUT] the timezone offset in seconds                 *
 *                                                                            *
 * Return value: SUCCEED - the timezone offset was parsed successfully        *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	strtime_tz_sec(const char *strtime, int *tz_sec)
{
	int	tz_hour, tz_min;

	if (strlen(strtime) < ZBX_FULLTIME_LEN)
		return FAIL;

	if (2 != sscanf(strtime + 19, "%d:%d", &tz_hour, &tz_min))
		return FAIL;

	if (tz_hour < 0)
		tz_min = -tz_min;

	*tz_sec = (tz_hour * 60 + tz_min) * 60;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: time_to_strtime                                                  *
 *                                                                            *
 * Purpose: converts time  from seconds since the Epoch to RFC 3339 format    *
 *          (for example 2017-10-09 14:26:43+03:00)                           *
 *                                                                            *
 * Parameters: timestamp - [IN] the number of seconds since the Epoch         *
 *             tz_sec    - [IN] the timezone offset in seconds                *
 *             buffer    - [OUT] the output buffer                            *
 *                               (at least ZBX_FULLTIME_LEN + 1 characters)   *
 *             size      - [IN] the output buffer size                        *
 *                                                                            *
 * Return value: SUCCEED - the was converted successfully                     *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	time_to_strtime(time_t timestamp, int tz_sec, char *buffer, char size)
{
	struct tm	*tm;
	int		tz_hour, tz_min;

	if (size < ZBX_FULLTIME_LEN + 1)
		return -1;

	timestamp += tz_sec;
	tz_hour = tz_sec / 60;
	tz_min = tz_hour % 60;
	tz_hour /= 60;

	tm = gmtime(&timestamp);

	zbx_snprintf(buffer, size, ZBX_DATETIME_FORMAT ZBX_TIMEZONE_FORMAT,
			tm->tm_year + 1900, tm->tm_mon + 1, tm->tm_mday,
			tm->tm_hour, tm->tm_min, tm->tm_sec, tz_hour, tz_min);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: time_to_strtime                                                  *
 *                                                                            *
 * Purpose: converts time from RFC 3339 format to seconds since the Epoch     *
 *                                                                            *
 * Parameters: strtime   - [IN] the time in RFC 3339 format                   *
 *             timestamp - [OUT] the number of seconds since the Epoch        *
 *                                                                            *
 * Return value: SUCCEED - the was converted successfully                     *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	strtime_to_time(const char *strtime, time_t *timestamp)
{
	struct tm	tm;
	int		tz_sec;
	time_t		time_gm;

	if (6 != sscanf(strtime, ZBX_DATETIME_FORMAT, &tm.tm_year, &tm.tm_mon, &tm.tm_mday,
			&tm.tm_hour, &tm.tm_min, &tm.tm_sec))
	{
		return FAIL;
	}

	if (FAIL == strtime_tz_sec(strtime, &tz_sec))
		return FAIL;

	tm.tm_year -= 1900;
	tm.tm_mon--;

	if (-1 == (time_gm = timegm(&tm)))
		return FAIL;

	*timestamp = time_gm - tz_sec;

	return SUCCEED;
}

void	zbx_mock_test_entry(void **state)
{
	int			ret, simple_interval, tz_sec;
	zbx_custom_interval_t	*custom_intervals;
	char			*error = NULL, buffer[32];

	ZBX_UNUSED(state);

	setenv("TZ", get_in_param_by_name("TZ"), 1);

	ret = zbx_interval_preproc(get_in_param_by_name("interval"), &simple_interval, &custom_intervals, &error);

	assert_int_equal(ret, atoi(get_out_param_by_name("return")));

	if (SUCCEED == ret)
	{
		int		i, num,nextcheck;
		char		*param = NULL;
		const char	*value;
		time_t		now;

		num = atoi(get_out_param_by_name("nextcheck_num"));
		assert_false(FAIL == strtime_to_time(get_in_param_by_name("now"), &now));

		for (i = 1; i <= num; i++)
		{
			nextcheck = calculate_item_nextcheck(atoi(get_in_param_by_name("seed")),
					atoi(get_in_param_by_name("type")), simple_interval, custom_intervals,
					now);
			param = zbx_dsprintf(param, "nextcheck#%d", i);
			value = get_out_param_by_name(param);

			assert_false(FAIL == strtime_tz_sec(value, &tz_sec));
			assert_false(FAIL == time_to_strtime(nextcheck, tz_sec, buffer, sizeof(buffer)));
			assert_string_equal(buffer, value);
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
