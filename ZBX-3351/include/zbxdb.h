/*
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

#ifndef ZABBIX_ZBXDB_H
#define ZABBIX_ZBXDB_H

#include "common.h"

#define	ZBX_DB_OK	(0)
#define	ZBX_DB_FAIL	(-1)
#define	ZBX_DB_DOWN	(-2)

#define ZBX_MAX_SQL_SIZE	262144	/* 256KB */

#if defined(HAVE_IBM_DB2)

#	include <sqlcli1.h>

	typedef struct
	{
		SQLHANDLE	henv;
		SQLHANDLE	hdbc;
	}
	zbx_ibm_db2_handle_t;

	extern zbx_ibm_db2_handle_t	ibm_db2;

#	define DB_ROW		char **
#	define DB_RESULT	ZBX_IBM_DB2_RESULT *
#	define DBfree_result	IBM_DB2free_result

	typedef struct
	{
		SQLHANDLE	hstmt;
		SQLSMALLINT	nalloc;
		SQLSMALLINT	ncolumn;
		DB_ROW		values;
		DB_ROW		values_cli;
		SQLINTEGER	*values_len;
	}
	ZBX_IBM_DB2_RESULT;

	void	IBM_DB2free_result(DB_RESULT result);
	int	IBM_DB2server_status();
	int	zbx_ibm_db2_success(SQLRETURN ret);
	int	zbx_ibm_db2_success_ext(SQLRETURN ret);
	void	zbx_ibm_db2_log_errors(SQLSMALLINT htype, SQLHANDLE hndl);

#elif defined(HAVE_MYSQL)

#	include "mysql.h"
#	include "errmsg.h"
#	include "mysqld_error.h"

	extern MYSQL	*conn;

#	define DB_ROW		MYSQL_ROW
#	define DB_RESULT	MYSQL_RES *
#	define DBfree_result	mysql_free_result

#elif defined(HAVE_ORACLE)

#	include "oci.h"

	typedef struct
	{
		OCIEnv		*envhp;
		OCIError	*errhp;
		OCISvcCtx	*svchp;
		OCIServer	*srvhp;
	}
	zbx_oracle_db_handle_t;
	
	extern zbx_oracle_db_handle_t	oracle;

#	define DB_ROW		char **
#	define DB_RESULT	ZBX_OCI_DB_RESULT *
#	define DBfree_result	OCI_DBfree_result

	typedef struct
	{
		OCIStmt		*stmthp;
		int 		ncolumn;
		DB_ROW		values;
	}
	ZBX_OCI_DB_RESULT;

	void		OCI_DBfree_result(DB_RESULT result);
	ub4		OCI_DBserver_status();
	const char	*zbx_oci_error(sword status);

#elif defined(HAVE_POSTGRESQL)

#	include <libpq-fe.h>

	extern PGconn	*conn;

#	define DB_ROW		char **
#	define DB_RESULT	ZBX_PG_DB_RESULT *
#	define DBfree_result	PG_DBfree_result

	typedef struct
	{
		PGresult	*pg_result;
		int		row_num;
		int		fld_num;
		int		cursor;
		DB_ROW		values;
	}
	ZBX_PG_DB_RESULT;

	void	PG_DBfree_result(DB_RESULT result);

#elif defined(HAVE_SQLITE3)

#	include <sqlite3.h>

	extern sqlite3		*conn;

#	define DB_ROW		char **
#	define DB_RESULT	ZBX_SQ_DB_RESULT *
#	define DBfree_result	SQ_DBfree_result

	typedef struct
	{
		int		curow;
		char		**data;
		int		nrow;
		int		ncolumn;
		DB_ROW		values;
	}
	ZBX_SQ_DB_RESULT;

	void	SQ_DBfree_result(DB_RESULT result);

#	include "mutexs.h"

	extern PHP_MUTEX	sqlite_access;

#endif	/* HAVE_SQLITE3 */

#ifdef HAVE_SQLITE3
	/* We have to put double % here for sprintf */
#	define ZBX_SQL_MOD(x, y) #x "%%" #y
#else
#	define ZBX_SQL_MOD(x, y) "mod(" #x "," #y ")"
#endif

int	zbx_db_connect(char *host, char *user, char *password, char *dbname, char *dbschema, char *dbsocket, int port);
void	zbx_db_init(char *host, char *user, char *password, char *dbname, char *dbschema, char *dbsocket, int port);
void    zbx_db_close();

int	zbx_db_begin();
int	zbx_db_commit();
int	zbx_db_rollback();

int		zbx_db_vexecute(const char *fmt, va_list args);
DB_RESULT	zbx_db_vselect(const char *fmt, va_list args);
DB_RESULT	zbx_db_select_n(const char *query, int n);

DB_ROW		zbx_db_fetch(DB_RESULT result);
int		zbx_db_is_null(const char *field);

#endif
