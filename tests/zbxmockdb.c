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

/* make sure that __wrap_*() prototypes match unwrapped counterparts */

#define zbx_db_vselect	__wrap_zbx_db_vselect
#define zbx_db_fetch	__wrap_zbx_db_fetch
#define DBfree_result	__wrap_DBfree_result
#include "zbxdb.h"
#undef zbx_db_vselect
#undef zbx_db_fetch
#undef DBfree_result

#define __zbx_DBexecute			__wrap___zbx_DBexecute
#define DBexecute_multiple_query	__wrap_DBexecute_multiple_query
#define DBbegin				__wrap_DBbegin
#define DBcommit			__wrap_DBcommit
#include "db.h"
#undef __zbx_DBexecute
#undef DBexecute_multiple_query
#undef DBbegin
#undef DBcommit

#define ZBX_MOCK_DB_RESULT_COLUMNS_MAX	128

struct zbx_db_result
{
	DB_ROW			row;
	char			*data_source;	/* for error messages */
	zbx_mock_handle_t	rows;
	int			row_to_fetch;	/* for error messages */
	int			columns;	/* to make sure that rows have identical number of columns */
};

/* <data_source> = <table name> , { "_" , <table name> } */
static char	*generate_data_source(const char *sql)
{
	int		found = 0;
	char		*data_source = NULL, *ptr_ds;
	const char	*ptr_sql = sql, *ptr_tmp;

	data_source = zbx_calloc(NULL, 64, sizeof(char *));
	ptr_ds = data_source;

	while ('\0' != *ptr_sql)
	{
		if (0 != found)
		{
			if (' ' == *ptr_sql || ';' == *ptr_sql)
			{
				found = 0;
				*(ptr_ds++) = ' ';
			}
			else
				*(ptr_ds++) = *ptr_sql;

			ptr_sql++;
		}
		else if (NULL != (ptr_tmp = strstr(ptr_sql, "from ")))
		{
			found = 1;
			ptr_sql = ptr_tmp + strlen("from ");
		}
		else if (NULL != (ptr_tmp = strstr(ptr_sql, "join ")))
		{
			found = 1;
			ptr_sql = ptr_tmp + strlen("join ");
		}
		else
			break;
	}

	if (ptr_ds == data_source)
		zbx_free(data_source);	/* failed to generate data_source */
	else
		*(ptr_ds - 1) = '\0';

	return data_source;
}

DB_RESULT	__wrap_zbx_db_vselect(const char *fmt, va_list args)
{
	char			*sql = NULL, *data_source = NULL;
	zbx_mock_error_t	error;
	zbx_mock_handle_t	rows;
	DB_RESULT		result = NULL;

	sql = zbx_dvsprintf(sql, fmt, args);

	if (NULL == (data_source = generate_data_source(sql)))
		fail_msg("Cannot generate data source string from SQL query: %s", sql);

	zbx_free(sql);

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_db_rows(data_source, &rows)))
		fail_msg("Cannot find data for data source \"%s\": %s", data_source, zbx_mock_error_string(error));

	result = zbx_malloc(result, sizeof(struct zbx_db_result));
	result->row = zbx_malloc(NULL, ZBX_MOCK_DB_RESULT_COLUMNS_MAX * sizeof(char *));
	result->data_source = data_source;
	result->rows = rows;
	result->row_to_fetch = 1;
	result->columns = -1;

	return result;
}

DB_ROW	__wrap_zbx_db_fetch(DB_RESULT result)
{
	zbx_mock_error_t	error;
	zbx_mock_handle_t	row, field;
	int			column = 0;

	if (NULL == result || ZBX_MOCK_END_OF_VECTOR == (error = zbx_mock_vector_element(result->rows, &row)))
		return NULL;

	if (ZBX_MOCK_SUCCESS != error)
	{
		fail_msg("Cannot fetch row %d for data source \"%s\": %s", result->row_to_fetch, result->data_source,
				zbx_mock_error_string(error));
	}

	while (ZBX_MOCK_SUCCESS == (error = zbx_mock_vector_element(row, &field)))
	{
		if (ZBX_MOCK_DB_RESULT_COLUMNS_MAX <= column)
			fail_msg("Too many columns for data source \"%s\".", result->data_source);

		if (ZBX_MOCK_SUCCESS != (error = zbx_mock_string(field, (const char **)&result->row[column])))
			break;

		column++;
	}

	if (ZBX_MOCK_END_OF_VECTOR != error)
	{
		fail_msg("Cannot get value of column %d, row %d for data source \"%s\": %s", column + 1,
				result->row_to_fetch, result->data_source, zbx_mock_error_string(error));
	}

	if (0 <= result->columns)
	{
		if (column < result->columns)
		{
			fail_msg("Too few columns in row %d for data source \"%s\".", result->row_to_fetch,
					result->data_source);
		}

		if (column > result->columns)
		{
			fail_msg("Too many columns in row %d for data source \"%s\".", result->row_to_fetch,
					result->data_source);
		}
	}
	else
		result->columns = column;

	while (column < ZBX_MOCK_DB_RESULT_COLUMNS_MAX)
		result->row[column++] = NULL;

	result->row_to_fetch++;
	return result->row;
}

void	__wrap_DBfree_result(DB_RESULT result)
{
	if (NULL != result)
	{
		zbx_free(result->row);
		zbx_free(result->data_source);
	}

	zbx_free(result);
}

int	__wrap___zbx_DBexecute(const char *fmt, ...)
{
	ZBX_UNUSED(fmt);

	return 0;
}

int	__wrap_DBexecute_multiple_query(const char *query, const char *field_name, zbx_vector_uint64_t *ids)
{
	ZBX_UNUSED(query);
	ZBX_UNUSED(field_name);
	ZBX_UNUSED(ids);

	return SUCCEED;
}

void	__wrap_DBbegin(void)
{
}

int	__wrap_DBcommit(void)
{
	return ZBX_DB_OK;
}
