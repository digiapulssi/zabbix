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

#include "common.h"
#include "log.h"
#include "zbxalgo.h"
#include "zbxhistory.h"
#include "history.h"

extern char	*CONFIG_HISTORY_STORAGE_URL;
extern char	*CONFIG_HISTORY_STORAGE_OPTS;

zbx_history_iface_t	history_ifaces[ITEM_VALUE_TYPE_MAX];

/************************************************************************************
 *                                                                                  *
 * Function: zbx_history_init                                                       *
 *                                                                                  *
 * Purpose: initializes history storage                                             *
 *                                                                                  *
 * Comments: History interfaces are created for all values types based on           *
 *           configuration. Every value type can have different history storage     *
 *           backend.                                                               *
 *                                                                                  *
 ************************************************************************************/
int	zbx_history_init(char **error)
{
	int		i, ret;
	const char	*opts[] = {ZBX_HISTORY_TYPE_FLOAT_STR, ZBX_HISTORY_TYPE_CHAR_STR, ZBX_HISTORY_TYPE_LOG_STR,
			ZBX_HISTORY_TYPE_UNUM_STR, ZBX_HISTORY_TYPE_TEXT_STR};

	for (i = 0; i < ITEM_VALUE_TYPE_MAX; i++)
	{
		if (NULL == CONFIG_HISTORY_STORAGE_URL || NULL == strstr(CONFIG_HISTORY_STORAGE_OPTS, opts[i]))
			ret = zbx_history_sql_init(&history_ifaces[i], i, error);
		else
			ret = zbx_history_elastic_init(&history_ifaces[i], i, error);

		if (FAIL == ret)
			return FAIL;
	}

	return SUCCEED;
}

/************************************************************************************
 *                                                                                  *
 * Function: zbx_history_destroy                                                    *
 *                                                                                  *
 * Purpose: destroys history storage                                                *
 *                                                                                  *
 * Comments: All interfaces created by zbx_history_init() function are destroyed    *
 *           here.                                                                  *
 *                                                                                  *
 ************************************************************************************/
void	zbx_history_destroy()
{
	int	i;

	for (i = 0; i < ITEM_VALUE_TYPE_MAX; i++)
	{
		zbx_history_iface_t	*writer = &history_ifaces[i];

		writer->destroy(writer);
		zbx_free(writer);
	}
}

/************************************************************************************
 *                                                                                  *
 * Function: zbx_history_add_values                                                 *
 *                                                                                  *
 * Purpose: Sends values to the history storage                                     *
 *                                                                                  *
 * Parameters: history - [IN] the values to store                                   *
 *                                                                                  *
 * Comments: All interfaces created by zbx_history_init() function are destroyed    *
 *           here.                                                                  *
 *                                                                                  *
 ************************************************************************************/
void	zbx_history_add_values(const zbx_vector_ptr_t *history)
{
	int	i, flags = 0;

	for (i = 0; i < ITEM_VALUE_TYPE_MAX; i++)
	{
		zbx_history_iface_t	*writer = &history_ifaces[i];

		if (0 < writer->add_values(writer, history))
			flags |= (1 << i);
	}

	for (i = 0; i < ITEM_VALUE_TYPE_MAX; i++)
	{
		zbx_history_iface_t	*writer = &history_ifaces[i];

		if (0 != (flags & (1 << i)))
			writer->flush(writer);
	}
}

/************************************************************************************
 *                                                                                  *
 * Function: zbx_history_get_values                                                 *
 *                                                                                  *
 * Purpose: gets item values from history storage                                   *
 *                                                                                  *
 * Parameters:  itemid     - [IN] the itemid                                        *
 *              value_type - [IN] the item value type                               *
 *              start      - [IN] the period start timestamp                        *
 *              count      - [IN] the number of values to read                      *
 *              end        - [IN] the period end timestamp                          *
 *              values     - [OUT] the item history data values                     *
 *                                                                                  *
 * Return value: SUCCEED - the history data were read successfully                  *
 *               FAIL - otherwise                                                   *
 *                                                                                  *
 * Comments: This function reads <count> values from ]<start>,<end>] interval or    *
 *           all values from the specified interval if count is zero.               *
 *                                                                                  *
 ************************************************************************************/
int	zbx_history_get_values(zbx_uint64_t itemid, int value_type, int start, int count, int end,
		zbx_vector_history_record_t *values)
{
	zbx_history_iface_t	*writer = &history_ifaces[value_type];

	return writer->get_values(writer, itemid, start, count, end, values);
}

/************************************************************************************
 *                                                                                  *
 * Function: zbx_history_requires_trends                                            *
 *                                                                                  *
 * Purpose: checks if the value type requires trends data calculations              *
 *                                                                                  *
 * Parameters: value_type - [IN] the value type                                     *
 *                                                                                  *
 * Return value: SUCCEED - trends must be calculated for this value type            *
 *               FAIL - otherwise                                                   *
 *                                                                                  *
 * Comments: This function is used to check if the trends must be calculated for    *
 *           the specified value type based on the history storage used.            *
 *                                                                                  *
 ************************************************************************************/
int	zbx_history_requires_trends(int value_type)
{
	zbx_history_iface_t	*writer = &history_ifaces[value_type];

	return 0 != writer->requires_trends ? SUCCEED : FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: history_logfree                                                  *
 *                                                                            *
 * Purpose: frees history log and all resources allocated for it              *
 *                                                                            *
 * Parameters: log   - [IN] the history log to free                           *
 *                                                                            *
 ******************************************************************************/
static void	history_logfree(zbx_log_value_t *log)
{
	zbx_free(log->source);
	zbx_free(log->value);
	zbx_free(log);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_history_record_vector_destroy                                *
 *                                                                            *
 * Purpose: destroys value vector and frees resources allocated for it        *
 *                                                                            *
 * Parameters: vector    - [IN] the value vector                              *
 *                                                                            *
 * Comments: Use this function to destroy value vectors created by            *
 *           zbx_vc_get_values_by_* functions.                                *
 *                                                                            *
 ******************************************************************************/
void	zbx_history_record_vector_destroy(zbx_vector_history_record_t *vector, int value_type)
{
	if (NULL != vector->values)
	{
		zbx_history_record_vector_clean(vector, value_type);
		zbx_vector_history_record_destroy(vector);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_history_record_clear                                         *
 *                                                                            *
 * Purpose: frees resources allocated by a cached value                       *
 *                                                                            *
 * Parameters: value      - [IN] the cached value to clear                    *
 *             value_type - [IN] the history value type                       *
 *                                                                            *
 ******************************************************************************/
void	zbx_history_record_clear(zbx_history_record_t *value, int value_type)
{
	switch (value_type)
	{
		case ITEM_VALUE_TYPE_STR:
		case ITEM_VALUE_TYPE_TEXT:
			zbx_free(value->value.str);
			break;
		case ITEM_VALUE_TYPE_LOG:
			history_logfree(value->value.log);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_vc_history_value2str                                         *
 *                                                                            *
 * Purpose: converts history value to string format                           *
 *                                                                            *
 * Parameters: buffer     - [OUT] the output buffer                           *
 *             size       - [IN] the output buffer size                       *
 *             value      - [IN] the value to convert                         *
 *             value_type - [IN] the history value type                       *
 *                                                                            *
 ******************************************************************************/
void	zbx_vc_history_value2str(char *buffer, size_t size, history_value_t *value, int value_type)
{
	switch (value_type)
	{
		case ITEM_VALUE_TYPE_FLOAT:
			zbx_snprintf(buffer, size, ZBX_FS_DBL, value->dbl);
			break;
		case ITEM_VALUE_TYPE_UINT64:
			zbx_snprintf(buffer, size, ZBX_FS_UI64, value->ui64);
			break;
		case ITEM_VALUE_TYPE_STR:
		case ITEM_VALUE_TYPE_TEXT:
			zbx_strlcpy_utf8(buffer, value->str, size);
			break;
		case ITEM_VALUE_TYPE_LOG:
			zbx_strlcpy_utf8(buffer, value->log->value, size);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_history_record_vector_clean                                  *
 *                                                                            *
 * Purpose: releases resources allocated to store history records             *
 *                                                                            *
 * Parameters: vector      - [IN] the history record vector                   *
 *             value_type  - [IN] the type of vector values                   *
 *                                                                            *
 ******************************************************************************/
void	zbx_history_record_vector_clean(zbx_vector_history_record_t *vector, int value_type)
{
	int	i;

	switch (value_type)
	{
		case ITEM_VALUE_TYPE_STR:
		case ITEM_VALUE_TYPE_TEXT:
			for (i = 0; i < vector->values_num; i++)
				zbx_free(vector->values[i].value.str);

			break;
		case ITEM_VALUE_TYPE_LOG:
			for (i = 0; i < vector->values_num; i++)
				history_logfree(vector->values[i].value.log);
	}

	zbx_vector_history_record_clear(vector);
}
