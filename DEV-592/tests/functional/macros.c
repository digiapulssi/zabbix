#include "common.h"
#include "log.h"
#include "zbxserver.h"
#include "../../src/libs/zbxdbcache/valuecache.h"

#include "unresolved.h"

const char	*global_macro_name, *global_macro_context, *global_macro_value;
DC_ITEM		global_item;
DB_EVENT	global_event;
DC_FUNCTION	global_function;
history_value_t	global_value;

static void	prepare_dc_item(zbx_uint64_t hostid, const char *host_name)
{
	global_item.host.hostid = hostid;
	zbx_snprintf(global_item.host.name, sizeof(global_item.host.name), "%s", host_name);
}

static void	prepare_user_macro(const char *macro_name, const char *macro_context, const char *macro_value)
{
	global_macro_name = macro_name;
	global_macro_context = macro_context;
	global_macro_value = macro_value;
}

static void	prepare_event(int source, int object, int value, char *expression)
{
	global_event.source = source;
	global_event.object = object;
	global_event.value = value;

	if (NULL != expression)
		global_event.trigger.expression = expression;
}

static void	prepare_function(zbx_uint64_t itemid, char *function)
{
	global_function.itemid = itemid;
	global_function.function = function;
}

static void	prepare_value(int value_type, zbx_uint64_t value)
{
	if (ITEM_VALUE_TYPE_UINT64 == value_type)
	{
		global_value.ui64 = value;
	}
	else
	{
		printf("value type %d is not supported yet\n");
		exit(EXIT_FAILURE);
	}
}

static int		dbrow_cur, dbrow_total;
static DB_ROW		dbrow;
static DB_RESULT	result;

#define MAX_DBROWS	16
static void	prepare_dbrow(char *a1, char *a2, char *a3)
{
	char	*args[] = {a1, a2, a3, NULL};
	int	i;

	dbrow = zbx_calloc(NULL, MAX_DBROWS, sizeof(char *));

	for (i = 0; args[i] != NULL; i++)
	{
		if (MAX_DBROWS == i - 1)
		{
			printf("please increas maximum db rows (currently %d)\n", MAX_DBROWS);
			exit(EXIT_FAILURE);
		}

		dbrow[i] = args[i];
	}

	dbrow[i] = NULL;
}

static void	zbxtest_key_macro(const char *key, const char *expected)
{
	char	*data, error[128];
	int	ret;

	data = zbx_strdup(NULL, key);

	ret = substitute_key_macros(&data, &global_item.host.hostid, &global_item, NULL, MACRO_TYPE_ITEM_KEY,
			error, sizeof(error));

	ZBXTEST_RV("substitute_key_macros", ret, error);
	ZBXTEST_STRCMP("result item key", expected, data);

	zbx_free(data);
}

static void	zbxtest_simple_macro(int macro_type, const char *input, const char *expected)
{
	char	*result, error[128];
	int	ret;

	result = zbx_strdup(NULL, input);

	ret = substitute_simple_macros(NULL, &global_event, NULL, NULL, NULL, NULL, NULL, NULL, NULL,
			&result, macro_type, error, sizeof(error));

	ZBXTEST_RV("substitute_simple_macros", ret, error);

	ZBXTEST_STRCMP("trigger expression", expected, result);

	zbx_free(result);
}

void	DCconfig_get_hosts_by_itemids(DC_HOST *hosts, const zbx_uint64_t *itemids, int *errcodes, size_t num)
{
	*hosts = global_item.host;

	*errcodes = SUCCEED;
}

void	DCconfig_get_functions_by_functionids(DC_FUNCTION *functions, zbx_uint64_t *functionids, int *errcodes,
		size_t num)
{
	ZBXTEST_EXPR("number of functions", 1 == num);

	*functions = global_function;
	*errcodes = SUCCEED;
}

void	DCconfig_clean_functions(DC_FUNCTION *functions, int *errcodes, size_t num)
{
}

void    DCget_user_macro(const zbx_uint64_t *hostids, int hostids_num, const char *macro, char **replace_to)
{
	const char      *__function_name = "DCget_user_macro";
	char            *macro_name = NULL, *macro_context = NULL;
	int		ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In mocked %s() hostid:" ZBX_FS_UI64 " hostids_num:%d macro:'%s'",
			__function_name, *hostids, hostids_num, macro);

	ret = zbx_user_macro_parse_dyn(macro, &macro_name, &macro_context, NULL);

	ZBXTEST_RV("zbx_user_macro_parse_dyn", ret, zbx_result_string(ret));

	if (SUCCEED != ret)
		goto out;

	zabbix_log(LOG_LEVEL_DEBUG, "mocked %s() macro_name:'%s' macro_context:'%s'", __function_name, macro_name,
			ZBX_NULL2STR(macro_context));

	ZBXTEST_EXPR("hostid match", *hostids == global_item.host.hostid);
	ZBXTEST_EXPR("number of hosts match", 1 == hostids_num);
	ZBXTEST_STRCMP("user macro", global_macro_name, macro_name);
	ZBXTEST_STRCMP("user macro context", global_macro_context, macro_context);

	*replace_to = zbx_strdup(NULL, global_macro_value);

	zbx_free(macro_context);
	zbx_free(macro_name);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of mocked %s()", __function_name);
}

int	zbx_vc_get_value(zbx_uint64_t itemid, int value_type, const zbx_timespec_t *ts, zbx_history_record_t *value)
{
	value->value = global_value;

	return SUCCEED;
}

DB_RESULT       zbx_db_vselect(const char *fmt, va_list args)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In mocked zbx_db_vselect()");

	dbrow_cur = 0;
	dbrow_total = 1;

	zabbix_log(LOG_LEVEL_DEBUG, "End of mocked zbx_db_fetch()");

	return result;
}

DB_ROW  zbx_db_fetch(DB_RESULT result)
{
	DB_ROW	row;

	zabbix_log(LOG_LEVEL_DEBUG, "In mocked zbx_db_fetch()");

	if (dbrow_cur != dbrow_total)
	{
		row = dbrow;
		dbrow_cur++;
	}
	else
		row = NULL;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of mocked zbx_db_fetch() row:%s", (NULL == row ? "NULL" : "non-NULL"));

	return row;
}

void    DBfree_result(DB_RESULT result)
{
	zbx_free(dbrow);
}

int	main(int argc, char *const argv[])
{
	char	*error = NULL;
	size_t	error_size = 128;
	int	ret;

	if (SUCCEED != parse_opts(argc, argv))
	{
		printf("FAIL\n");
		goto out;
	}

	CONFIG_CONF_CACHE_SIZE = 1024 * 128;

	error = zbx_malloc(NULL, error_size);

	ret = init_configuration_cache(&error);

	ZBXTEST_RV("init_configuration_cache", ret, error);

	prepare_dc_item(3, "srv");
	prepare_user_macro("{$M}", "C", "v");
	zbxtest_key_macro("k[{$M:C},two,{HOST.NAME}]", "k[v,two,srv]");

	prepare_user_macro("{$MACRO}", NULL, "vvvvvvvvvvvvv");
	zbxtest_key_macro("k[{$MACRO}]", "k[vvvvvvvvvvvvv]");

	prepare_dc_item(1, "ninja");
	prepare_event(EVENT_SOURCE_TRIGGERS, EVENT_OBJECT_TRIGGER, TRIGGER_VALUE_PROBLEM, "{12321}=0");
	prepare_function(123, "last");
	zbxtest_simple_macro(MACRO_TYPE_TRIGGER_EXPRESSION, "If foo {TRIGGER.VALUE}", "If foo 1");
	zbxtest_simple_macro(MACRO_TYPE_TRIGGER_URL, "http://{HOST.NAME}.com", "http://ninja.com");

	prepare_value(ITEM_VALUE_TYPE_UINT64, 117);
	prepare_dbrow("3", "", "");	/* DBitem_lastvalue():value_type,valuemapid,units from table items */
	zbxtest_simple_macro(MACRO_TYPE_MESSAGE_NORMAL, "last value:{ITEM.LASTVALUE1}", "last value:117");
out:
	zbx_free(error);

	return 0;
}
