#include "zbxserver.h"
#include "../src/libs/zbxdbcache/valuecache.h"

int	zbx_vc_get_value_range(zbx_uint64_t itemid, int value_type, zbx_vector_history_record_t *values, int seconds,
		int count, int timestamp)
{
	history_value_t		value = {.ui64 = 1024};
	zbx_history_record_t	record = {.value = value};

	zbx_vector_history_record_append(values, record);

	return SUCCEED;
}

int	main(void)
{
	int	ret;
	char	value[100], *error = NULL;
	DC_ITEM	item = {.value_type = ITEM_VALUE_TYPE_UINT64};

	ret = evaluate_function(value, &item, "last", "", 123, &error);

	printf("ret:%d value:'%s' error:'%s'\n", ret, value, error);

	return 0;
}
