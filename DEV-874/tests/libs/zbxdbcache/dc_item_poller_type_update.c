/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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

/*
** Since there is a lot of permutations of parameters for DCitem_poller_type_update() to test we
** organize test data in YAML in a different way. Namely, there are only two test cases - direct
** and by proxy. Each test case consists of test parameter sets. Each test set has parameters like
** item type, item key, poller etc. These are used as arguments to call DCitem_poller_type_update().
** There is also "ref" parameter in test case used to identify set in case of set failure.
**/

#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockassert.h"
#include "zbxmockutil.h"

#include "common.h"
#include "mutexs.h"
#define ZBX_DBCONFIG_IMPL
#include "dbcache.h"
#include "dbconfig.h"

/* defines from dbconfig.c */
#define ZBX_ITEM_COLLECTED		0x01
#define ZBX_HOST_UNREACHABLE		0x02

/* YAML fields for test set */
#define PARAM_MONITORED	("access")
#define PARAM_TYPE	("type")
#define PARAM_KEY	("key")
#define PARAM_POLLER	("poller")
#define PARAM_FLAGS	("flags")
#define PARAM_RESULT	("result")
#define PARAM_REF	("ref")

typedef struct
{
	enum { DIRECT, PROXY }	monitored;
	zbx_item_type_t		type;
	const char		*key;
	unsigned char		poller_type;
	unsigned char		flags;
	unsigned char		result_poller_type;
	zbx_uint32_t		test_number;
}
test_config_t;

typedef struct
{
	zbx_uint64_t	val;
	const char	*str;
}
str_map_t;

void	DCitem_poller_type_update_test(ZBX_DC_ITEM *dc_item, const ZBX_DC_HOST *dc_host, int flags);

static void init_test(void)
{
	while (0 == CONFIG_PINGER_FORKS)
		CONFIG_PINGER_FORKS = rand();

	while (0 == CONFIG_POLLER_FORKS)
		CONFIG_POLLER_FORKS = rand();

	while (0 == CONFIG_IPMIPOLLER_FORKS)
		CONFIG_IPMIPOLLER_FORKS = rand();

	while (0 == CONFIG_JAVAPOLLER_FORKS)
		CONFIG_JAVAPOLLER_FORKS = rand();
}

#define _ZBX_MKMAP(c) { c,#c }

static zbx_item_type_t str2itemtype(const char *str)
{
	str_map_t map[] =
	{
		_ZBX_MKMAP(ITEM_TYPE_ZABBIX),		_ZBX_MKMAP(ITEM_TYPE_SNMPv1),
		_ZBX_MKMAP(ITEM_TYPE_TRAPPER),		_ZBX_MKMAP(ITEM_TYPE_SIMPLE),
		_ZBX_MKMAP(ITEM_TYPE_SNMPv2c),		_ZBX_MKMAP(ITEM_TYPE_INTERNAL),
		_ZBX_MKMAP(ITEM_TYPE_SNMPv3),		_ZBX_MKMAP(ITEM_TYPE_ZABBIX_ACTIVE),
		_ZBX_MKMAP(ITEM_TYPE_AGGREGATE),	_ZBX_MKMAP(ITEM_TYPE_HTTPTEST),
		_ZBX_MKMAP(ITEM_TYPE_EXTERNAL),		_ZBX_MKMAP(ITEM_TYPE_DB_MONITOR),
		_ZBX_MKMAP(ITEM_TYPE_IPMI),		_ZBX_MKMAP(ITEM_TYPE_SSH),
		_ZBX_MKMAP(ITEM_TYPE_TELNET),		_ZBX_MKMAP(ITEM_TYPE_CALCULATED),
		_ZBX_MKMAP(ITEM_TYPE_JMX),		_ZBX_MKMAP(ITEM_TYPE_SNMPTRAP),
		_ZBX_MKMAP(ITEM_TYPE_DEPENDENT),
		{ 0 }
	};

	for (str_map_t *e = &map[0]; NULL != e->str; e++)
		if (0 == strcmp(e->str, str))
			return e->val;

	fail_msg("Cannot find string %s", str);

	return 0;
}

static unsigned char str2pollertype(const char *str)
{
	str_map_t map[] =
	{
		_ZBX_MKMAP(ZBX_NO_POLLER),			_ZBX_MKMAP(ZBX_POLLER_TYPE_NORMAL),
		_ZBX_MKMAP(ZBX_POLLER_TYPE_UNREACHABLE),	_ZBX_MKMAP(ZBX_POLLER_TYPE_IPMI),
		_ZBX_MKMAP(ZBX_POLLER_TYPE_PINGER),		_ZBX_MKMAP(ZBX_POLLER_TYPE_JAVA),
		{ 0 }
	};

	for (str_map_t *e = &map[0]; NULL != e->str; e++)
		if (0 == strcmp(e->str, str))
			return (unsigned char)e->val;

	fail_msg("Cannot find string %s", str);

	return 0;
}

static int str2flags(const char *str)
{
	int flags = 0;

	str_map_t map[] =
	{
		{ 0, "0" },
		_ZBX_MKMAP(ZBX_ITEM_COLLECTED),
		_ZBX_MKMAP(ZBX_HOST_UNREACHABLE),
		0
	};

	for (str_map_t *e = &map[0]; NULL != e->str; e++)
		if (0 == strcmp(e->str, str))
			return e->val;

	if (NULL != strstr(str, "ZBX_ITEM_COLLECTED"))
		flags |= ZBX_ITEM_COLLECTED;

	if (NULL != strstr(str, "ZBX_HOST_UNREACHABLE"))
		flags |= ZBX_HOST_UNREACHABLE;

	return flags;
}

static const char	*read_string(const zbx_mock_handle_t *handle, const char *read_str)
{
	const char *str;
	zbx_mock_handle_t string_handle;

	zbx_mock_assert_int_eq("Failed to access object member", ZBX_MOCK_SUCCESS,
			zbx_mock_object_member(*handle, read_str, &string_handle));

	zbx_mock_assert_int_eq("Failed to extract string", ZBX_MOCK_SUCCESS,
			zbx_mock_string(string_handle, &str));

	return str;
}

static void	read_test(const zbx_mock_handle_t *handle, test_config_t *config)
{
	const char *str;

	str = read_string(handle, PARAM_MONITORED);
	config->monitored = 0 == strcmp(str, "DIRECT") ? DIRECT : PROXY;

	str = read_string(handle, PARAM_TYPE);
	config->type = str2itemtype(str);

	str = read_string(handle, PARAM_KEY);
	config->key = str;

	str = read_string(handle, PARAM_POLLER);
	config->poller_type = str2pollertype(str);

	/* only ZBX_HOST_UNREACHABLE and ZBX_ITEM_COLLECTED flags are used */
	str = read_string(handle, PARAM_FLAGS);
	config->flags = str2flags(str);

	str = read_string(handle, PARAM_RESULT);
	config->result_poller_type = str2pollertype(str);

	/* test number is for reference only */
	str = read_string(handle, PARAM_REF);
	config->test_number = (zbx_uint32_t)strtol(str, NULL, 10);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_mock_test_entry                                              *
 *                                                                            *
 ******************************************************************************/
void	zbx_mock_test_entry(void **state)
{
	zbx_mock_error_t mock_error;
	zbx_mock_handle_t handle, elem_handle, string_handle;
	test_config_t config;
	ZBX_DC_ITEM item;
	ZBX_DC_HOST host;
	char buffer[MAX_STRING_LEN];

	ZBX_UNUSED(state);

	mock_error = zbx_mock_in_parameter("sets", &handle);
	if (ZBX_MOCK_SUCCESS != mock_error)
		fail_msg("Invalid input path, %d", mock_error);

	init_test();

	while (ZBX_MOCK_SUCCESS == (mock_error = zbx_mock_vector_element(handle, &elem_handle)))
	{
		read_test(&elem_handle, &config);

		memset((void*)&host, 0, sizeof(host));
		memset((void*)&item, 0, sizeof(item));

		item.type		= config.type;
		item.key		= config.key;
		item.poller_type	= config.poller_type;

		if (PROXY == config.monitored)
			while (0 == host.proxy_hostid)
				host.proxy_hostid = rand();

		zbx_snprintf(buffer, sizeof(buffer), "host is monitored %s and is %sreachable, item type is %d, "
				"item key is %s, poller type is %d, flags %d, ref %d",
				PROXY == config.monitored ? "by proxy" : "directly",
				config.flags & ZBX_HOST_UNREACHABLE ? "un" : "",
				(int)config.type, config.key, (int)config.poller_type, (int)config.flags,
				(int)config.test_number);

		DCitem_poller_type_update_test(&item, &host, config.flags);

		zbx_mock_assert_int_eq(buffer, config.result_poller_type, item.poller_type);
	}
}
