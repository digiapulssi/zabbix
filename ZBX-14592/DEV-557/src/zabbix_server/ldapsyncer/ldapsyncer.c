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

#ifdef HAVE_LDAP

#include "log.h"
#include "db.h"
#include "zbxself.h"
#include "comms.h"
#include "zbxjson.h"
#include "./ldapsyncer.h"
#include <ldap.h>

#define ZBX_LDAPSYNCER_PERIOD		60	/* TODO: make period configurable in frontend */

#define ZBX_LDAP_USE_TLS_UNENCRYPTED	0
#define ZBX_LDAP_USE_TLS_STARTTLS	1
#define ZBX_LDAP_USE_TLS_LDAPS		2

extern unsigned char	process_type, program_type;
extern int		server_num, process_num;
extern int		CONFIG_TIMEOUT;

typedef struct
{
	char	*server_id;	/* keep 'server_id' as text string */
	char	*host;		/* LDAP server hostname or IP address */
	char	*bind_dn;	/* bind user */
	char	*bind_pw;	/* bind passowrd */
	int	port;		/* port number, often 389 */
	int	use_tls;	/* 0 - unencrypted, 1 - reserved for StartTLS, 2 - LDAPS */
	int	net_timeout;	/* network timeout, seconds */
	int	proc_timeout;	/* processing (API) timeout, seconds */
}
zbx_ldap_server_t;

typedef struct
{
	char	*group_base_dn;
	char	*group_filter;
	int	group_scope;		/* 0 - Base, 1 - One level, 2 - Subtree */
	char	*user_base_dn;
	char	*user_filter;
	int	user_scope;		/* 0 - Base, 1 - One level, 2 - Subtree */
	char	**groups;		/* array of strings with Zabbix user group names, the last element is NULL */
	char	*user_type_attr;
	int	user_type_default;	/* 1 - (default) Zabbix user; 2 - Zabbix admin; 3 - Zabbix super admin */
	char	*alias_attr;
	char	*name_attr;
	char	*surname_attr;
	char	*language_attr;
	char	*language_default;	/* example: "en_GB" */
	char	*theme_attr;
	char	*theme_default;		/* example: "default", "blue-theme" or "dark-theme" */
	char	*autologin_attr;
	int	autologin_default;	/* 0 - (default) auto-login disabled, 1 - auto-login enabled */
	char	*autologout_attr;
	int	autologout_default;
	char	*refresh_attr;
	int	refresh_default;
	char	*rows_per_page_attr;
	int	rows_per_page_default;
	char	*url_after_login_attr;
	char	*url_after_login_default;
	char	*media_type_attr;
	int	media_type_default;
	char	*send_to_attr;
	char	*send_to_default;
	char	*when_active_attr;
	char	*when_active_default;	/* example: "1-7,00:00-23:59" */
	char	*media_enabled_attr;
	int	media_enabled_default;	/* 0 - enabled, 1 - disabled */
	char	*use_if_severity_attr;
	int	use_if_severity_default;	/* 1 - Not classified, 2 - Information,  4 - Warning, 8 - Average, */
						/* 16 - High, 32 - Disaster (32) */
}
zbx_ldap_search_t;

typedef struct
{
	zbx_ldap_server_t	server;
	zbx_vector_ptr_t	searches;	/* container for storing pointers to 'zbx_ldap_search_t' structures */
}
zbx_ldap_source_t;

typedef struct
{
	char	*alias;
	char	*name;
	char	*surname;
	char	*language;
	char	*theme;
	int	user_type;
	int	autologin;
	int	autologout;
	int	refresh;
	int	rows_per_page;
	char	*url_after_login;
	int	media_type;
	char	*send_to;
	char	*when_active;
	int	media_enabled;
	int	use_if_severity;
	char	**groups;		/* array of strings with Zabbix user group names, the last element is NULL */
}
zbx_ldap_user_t;

/******************************************************************************
 *                                                                            *
 * Function: zbx_ldap_connect                                                 *
 *                                                                            *
 * Purpose: connect to LDAP server                                            *
 *                                                                            *
 * Return value: SUCCEED or FAIL                                              *
 *                                                                            *
 ******************************************************************************/
static int	zbx_ldap_connect(LDAP **ld, zbx_ldap_server_t *ldap_server, char **error)
{
	int		res, opt_protocol_version = LDAP_VERSION3, opt_deref = LDAP_DEREF_NEVER, ret = FAIL;
	struct timeval	tv;
	char		*uri = NULL;

	if (ZBX_LDAP_USE_TLS_UNENCRYPTED == ldap_server->use_tls)
	{
		uri = zbx_dsprintf(uri, "ldap://%s:%d", ldap_server->host, ldap_server->port);
	}
	else if (ZBX_LDAP_USE_TLS_LDAPS == ldap_server->use_tls)
	{
		uri = zbx_dsprintf(uri, "ldaps://%s:%d", ldap_server->host, ldap_server->port);
	}
	else if (ZBX_LDAP_USE_TLS_STARTTLS == ldap_server->use_tls)
	{
		*error = zbx_strdup(*error, "connection to LDAP with STARTTLS not implemented");
		goto out;
	}

	/* initialize LDAP data struture (without opening a connection) */

	if (LDAP_SUCCESS != (res = ldap_initialize(ld, uri)))
	{
		*error = zbx_dsprintf(*error, "ldap_initialize() failed for \"%s\": %d %s",
				uri, res, ldap_err2string(res));
		goto out;
	}

	/* TODO: (optionally) check LDAP_OPT_API_FEATURE_INFO, LDAP_OPT_API_INFO to detect library version mismatch */

	/* set LDAP protocol v3 */

	if (LDAP_OPT_SUCCESS != (res = ldap_set_option(*ld, LDAP_OPT_PROTOCOL_VERSION, &opt_protocol_version)))
	{
		*error = zbx_dsprintf(*error, "ldap_set_option(,LDAP_OPT_PROTOCOL_VERSION,) failed: %d %s",
				res, ldap_err2string(res));
		goto out;
	}

	/* do not use asynchronous connect */

	if (LDAP_OPT_SUCCESS != (res = ldap_set_option(*ld, LDAP_OPT_CONNECT_ASYNC, LDAP_OPT_OFF)))
	{
		*error = zbx_dsprintf(*error, "ldap_set_option(,LDAP_OPT_CONNECT_ASYNC,) failed: %d %s",
				res, ldap_err2string(res));
		goto out;
	}

	/* TODO: (optionally) set LDAP_OPT_DEBUG_LEVEL for using with DebugLevel=5 */

	/* do not dereference aliases (default) */

	if (LDAP_OPT_SUCCESS != (res = ldap_set_option(*ld, LDAP_OPT_DEREF, &opt_deref)))
	{
		*error = zbx_dsprintf(*error, "ldap_set_option(,LDAP_OPT_DEREF,) failed: %d %s",
				res, ldap_err2string(res));
		goto out;
	}

	/* set connection timeout */

	tv.tv_sec = ldap_server->net_timeout;
	tv.tv_usec = 0;

	if (LDAP_OPT_SUCCESS != (res = ldap_set_option(*ld, LDAP_OPT_NETWORK_TIMEOUT, &tv)))
	{
		*error = zbx_dsprintf(*error, "ldap_set_option(,LDAP_OPT_NETWORK_TIMEOUT,) failed: %d %s",
				res, ldap_err2string(res));
		goto out;
	}

	/* do not chase referrals */

	if (LDAP_OPT_SUCCESS != (res = ldap_set_option(*ld, LDAP_OPT_REFERRALS, LDAP_OPT_OFF)))
	{
		*error = zbx_dsprintf(*error, "ldap_set_option(,LDAP_OPT_REFERRALS,) failed: %d %s",
				res, ldap_err2string(res));
		goto out;
	}

	/* set timeout for synchronous API call */

	tv.tv_sec = ldap_server->proc_timeout;

	if (LDAP_OPT_SUCCESS != (res = ldap_set_option(*ld, LDAP_OPT_TIMEOUT, &tv)))
	{
		*error = zbx_dsprintf(*error, "ldap_set_option(,LDAP_OPT_TIMEOUT,) failed: %d %s",
				res, ldap_err2string(res));
		goto out;
	}

	/* simple bind with user/password */

	if (LDAP_SUCCESS != (res= ldap_simple_bind_s(*ld, ldap_server->bind_dn, ldap_server->bind_pw)))
	{
		*error = zbx_dsprintf(*error, "ldap_simple_bind_s() failed: %d %s", res, ldap_err2string(res));
		goto out;
	}

	ret = SUCCEED;
out:
	zbx_free(uri);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_ldap_free                                                    *
 *                                                                            *
 * Purpose: close connection, release LDAP resources                          *
 *                                                                            *
 ******************************************************************************/
static void	zbx_ldap_free(LDAP **ld)
{
	int	res;

	if (LDAP_SUCCESS == (res = ldap_unbind_ext_s(*ld, NULL, NULL)))
		*ld = NULL;
	else
		zabbix_log(LOG_LEVEL_WARNING, "ldap_unbind_ext_s() failed: %d %s", res, ldap_err2string(res));
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_ldap_server_destroy                                          *
 *                                                                            *
 * Purpose: release memory allocated for components of 'zbx_ldap_server_t'    *
 *                                                                            *
 ******************************************************************************/
static void	zbx_ldap_server_destroy(zbx_ldap_server_t *p)
{
	zbx_free(p->server_id);
	zbx_free(p->host);
	zbx_free(p->bind_dn);

	if (NULL != p->bind_pw)
	{
		zbx_guaranteed_memset(p->bind_pw, 0, strlen(p->bind_pw));
		zbx_free(p->bind_pw);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_ldap_search_destroy                                          *
 *                                                                            *
 * Purpose: release memory allocated for components of 'zbx_ldap_search_t'    *
 *                                                                            *
 ******************************************************************************/
static void	zbx_ldap_search_destroy(zbx_ldap_search_t *p)
{
	zbx_free(p->group_base_dn);
	zbx_free(p->group_filter);
	zbx_free(p->user_base_dn);
	zbx_free(p->user_filter);

/*	s = *p->groups;		TODO: unfinished.

	while (NULL != s++)
		zbx_free(s);

	zbx_free(p->groups);
*/
	zbx_free(p->user_type_attr);
	zbx_free(p->alias_attr);
	zbx_free(p->name_attr);
	zbx_free(p->surname_attr);
	zbx_free(p->language_attr);
	zbx_free(p->language_default);
	zbx_free(p->theme_attr);
	zbx_free(p->theme_default);
	zbx_free(p->autologin_attr);
	zbx_free(p->autologout_attr);
	zbx_free(p->refresh_attr);
	zbx_free(p->rows_per_page_attr);
	zbx_free(p->url_after_login_attr);
	zbx_free(p->url_after_login_default);
	zbx_free(p->media_type_attr);
	zbx_free(p->send_to_attr);
	zbx_free(p->send_to_default);
	zbx_free(p->when_active_attr);
	zbx_free(p->when_active_default);
	zbx_free(p->media_enabled_attr);
	zbx_free(p->use_if_severity_attr);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_ldap_source_destroy                                          *
 *                                                                            *
 * Purpose: release memory allocated for components of 'zbx_ldap_source_t'    *
 *                                                                            *
 ******************************************************************************/
static void	zbx_ldap_source_destroy(zbx_ldap_source_t *p)
{
	int	i;

	zbx_ldap_server_destroy(&p->server);

	for (i = 0; i < p->searches.values_num; i++)
	{
		zbx_ldap_search_destroy(p->searches.values[i]);
		zbx_free(p->searches.values[i]);
	}

	zbx_vector_ptr_destroy(&p->searches);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_ldap_user_destroy                                            *
 *                                                                            *
 * Purpose: release memory allocated for components of 'zbx_ldap_user_t'      *
 *                                                                            *
 ******************************************************************************/
static void	zbx_ldap_user_destroy(zbx_ldap_user_t *p)
{
	zbx_free(p->alias);
	zbx_free(p->name);
	zbx_free(p->surname);
	zbx_free(p->language);
	zbx_free(p->theme);
	zbx_free(p->url_after_login);
	zbx_free(p->send_to);
	zbx_free(p->when_active);
/*
	zbx_free(p->groups);               TODO : unfinished
*/
}

static void	zbx_ldap_get_user(LDAP *ld, LDAPMessage *entry, zbx_ldap_search_t *ldap_search, zbx_vector_ptr_t *users)
{
	char		*attr;
	BerElement	*ber = NULL;
	zbx_ldap_user_t	*user;

	user = zbx_calloc(NULL, 1, sizeof(zbx_ldap_user_t));
	zbx_vector_ptr_append(users, user);

	for (attr = ldap_first_attribute(ld, entry, &ber); NULL != attr; attr = ldap_next_attribute(ld, entry, ber))
	{
		char	**values = ldap_get_values(ld, entry, attr);

		if (NULL != values && NULL != *values)
		{
			if (0 == strcmp(ldap_search->user_type_attr, attr))
			{
				user->user_type = atoi(*values);
			}
			else if (0 == strcmp(ldap_search->alias_attr, attr))
			{
				user->alias = zbx_strdup(user->alias, *values);
			}
			else if (0 == strcmp(ldap_search->name_attr, attr))
			{
				user->name = zbx_strdup(user->name, *values);
			}
			else if (0 == strcmp(ldap_search->surname_attr, attr))
			{
				user->surname = zbx_strdup(user->surname, *values);
			}
			else if (0 == strcmp(ldap_search->language_attr, attr))
			{
				user->language = zbx_strdup(user->language, *values);
			}
			else if (0 == strcmp(ldap_search->theme_attr, attr))
			{
				user->theme = zbx_strdup(user->theme, *values);
			}
			else if (0 == strcmp(ldap_search->autologin_attr, attr))
			{
				user->autologin = atoi(*values);
			}
			else if (0 == strcmp(ldap_search->autologout_attr, attr))
			{
				user->autologout = atoi(*values);
			}
			else if (0 == strcmp(ldap_search->refresh_attr, attr))
			{
				user->refresh = atoi(*values);
			}
			else if (0 == strcmp(ldap_search->rows_per_page_attr, attr))
			{
				user->rows_per_page = atoi(*values);
			}
			else if (0 == strcmp(ldap_search->url_after_login_attr, attr))
			{
				user->url_after_login = zbx_strdup(user->url_after_login, *values);
			}
			else if (0 == strcmp(ldap_search->media_type_attr, attr))
			{
				user->media_type = atoi(*values);
			}
			else if (0 == strcmp(ldap_search->send_to_attr, attr))
			{
				user->send_to = zbx_strdup(user->send_to, *values);
			}
			else if (0 == strcmp(ldap_search->when_active_attr, attr))
			{
				user->when_active = zbx_strdup(user->when_active, *values);
			}
			else if (0 == strcmp(ldap_search->media_enabled_attr, attr))
			{
				user->media_enabled = atoi(*values);
			}
			else if (0 == strcmp(ldap_search->use_if_severity_attr, attr))
			{
				user->use_if_severity = atoi(*values);
			}
		}

		ldap_value_free(values);
		ldap_memfree(attr);
	}

	ber_free(ber, 0);
}

static void	zbx_remove_string(register char **strings, const char *string)
{
	register char	**p;
	size_t		size = strlen(string) + 1;

	for (p = strings; NULL != *p; p++)
	{
		if (0 != memcmp(*p, string, size))
			*strings++ = *p;
	}

	*strings = NULL;
}

static void	zbx_ldap_find_users(LDAP *ld, zbx_ldap_search_t *ldap_search, time_t tv_sec, zbx_vector_ptr_t *users)
{
	struct berval	*cookie = NULL;
	unsigned char	more_pages;
	struct timeval	timeout = {tv_sec, 0};
	char		*attrs[] =
	{
		ldap_search->user_type_attr,
		ldap_search->alias_attr,
		ldap_search->name_attr,
		ldap_search->surname_attr,
		ldap_search->language_attr,
		ldap_search->theme_attr,
		ldap_search->autologin_attr,
		ldap_search->autologout_attr,
		ldap_search->refresh_attr,
		ldap_search->rows_per_page_attr,
		ldap_search->url_after_login_attr,
		ldap_search->media_type_attr,
		ldap_search->send_to_attr,
		ldap_search->when_active_attr,
		ldap_search->media_enabled_attr,
		ldap_search->use_if_severity_attr,
		NULL
	};

	zbx_remove_string(attrs, "");

	do
	{
		LDAPControl	*ctrl = NULL, *ctrls[2], **ctrls_response = NULL;
		LDAPMessage	*lm = NULL, *entry;
		ber_int_t	count = 0;
		int		ldap_err, errcode;

		more_pages = 0;

		if (LDAP_SUCCESS != (ldap_err = ldap_create_page_control(ld, LDAP_MAXINT, cookie, 0, &ctrl)))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot create page control '%s'", ldap_err2string(ldap_err));
			goto clean;
		}

		ctrls[0] = ctrl;
		ctrls[1] = NULL;

		if (LDAP_SUCCESS != (ldap_err = ldap_search_ext_s(ld, ldap_search->user_base_dn,
				ldap_search->user_scope, ldap_search->user_filter, attrs, 0, ctrls, NULL,
				&timeout, LDAP_MAXINT, &lm)))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot perform search '%s'",ldap_err2string(ldap_err));
			goto clean;
		}

		if (LDAP_SUCCESS != (ldap_err = ldap_parse_result(ld, lm, &errcode, NULL, NULL, NULL, &ctrls_response,
				0)))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot parse result '%s'", ldap_err2string(ldap_err));
			goto clean;
		}

		ber_bvfree(cookie);
		cookie = NULL;

		if (LDAP_SUCCESS != (ldap_err = ldap_parse_page_control(ld, ctrls_response, &count, &cookie)))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot parse page control '%s'", ldap_err2string(ldap_err));
			goto clean;
		}

		for (entry = ldap_first_entry(ld, lm); NULL != entry; entry = ldap_next_entry(ld, entry))
			zbx_ldap_get_user(ld, entry, ldap_search, users);

		if (NULL != cookie && NULL != cookie->bv_val && 0 < cookie->bv_len)
			more_pages = 1;
clean:
		ldap_controls_free(ctrls_response);
		ldap_control_free(ctrl);
		ldap_msgfree(lm);
	}
	while (1 == more_pages);

	ber_bvfree(cookie);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_get_data_from_ldap                                           *
 *                                                                            *
 * Purpose: read user data from LDAP servers and fill into structures         *
 *                                                                            *
 ******************************************************************************/
static int	zbx_get_data_from_ldap(zbx_vector_ptr_t *sources, zbx_vector_ptr_t *users, char **error)
{
	const char	*__function_name = "zbx_get_data_from_ldap";
	int		i, j, ret = FAIL;
	char		*err = NULL;

	for (i = 0; i < sources->values_num; i++)	/* for each LDAP server */
	{
		LDAP			*ld = NULL;
		zbx_ldap_source_t	*ldap_source = sources->values[i];

		if (SUCCEED != zbx_ldap_connect(&ld, &ldap_source->server, &err))
		{
			zabbix_log(LOG_LEVEL_WARNING, "%s() cannot connect to LDAP server: %s",
					__function_name, *err);
			continue;
		}

		for (j = 0; j < ldap_source->searches.values_num; j++)
		{
			zbx_ldap_search_t	*ldap_search = ldap_source->searches.values[j];

			zbx_ldap_find_users(ld, ldap_search, ldap_source->server.proc_timeout, users);
		}

		if (NULL != ld)
			zbx_ldap_free(&ld);

		ret = SUCCEED;		/* at least one LDAP server was contacted */
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_get_sources_from_db                                          *
 *                                                                            *
 * Purpose: read all LDAP search info from DB and fill it into structures     *
 *                                                                            *
 ******************************************************************************/
static int	zbx_get_sources_from_db(zbx_vector_ptr_t *sources, char **error)
{
	DB_RESULT	result;
	DB_ROW		row;
	char		*ids = NULL;
	size_t		alloc = 0, offset = 0;
	int		ret = FAIL;

	/* read all enabled records from 'ldap_servers' table */

	result = DBselect(
			"select server_id,host,port,bind_dn,bind_pw,use_tls,net_timeout,proc_timeout"
			" from ldap_servers"
			" where status=0"
			" order by server_id");

	while (NULL != (row = DBfetch(result)))
	{
		zbx_ldap_source_t	*p;

		/* collect 'server_id' values for later reading from 'ldap_searches' table */
		zbx_snprintf_alloc(&ids, &alloc, &offset, "%s%s", NULL != ids ? "," : "", row[0]);

		/* build 'zbx_ldap_source_t' structure and insert it into 'sources' vector */

		p = zbx_malloc(NULL, sizeof(zbx_ldap_source_t));

		p->server.server_id = zbx_strdup(NULL, row[0]);
		p->server.host = zbx_strdup(NULL, row[1]);
		p->server.port = atoi(row[2]);
		p->server.bind_dn = zbx_strdup(NULL, row[3]);
		p->server.bind_pw = zbx_strdup(NULL, row[4]);
		p->server.use_tls = atoi(row[5]);
		p->server.net_timeout = atoi(row[6]);
		p->server.proc_timeout = atoi(row[7]);

		zbx_vector_ptr_create(&p->searches);
		zbx_vector_ptr_append(sources, p);
	}

	DBfree_result(result);

	if (0 == sources->values_num)	/* no LDAP servers configured or all disabled */
	{
		ret = SUCCEED;
		goto out;
	}

	/* read corresponding records from 'ldap_searches' table */

	result = DBselect(
			"select search_id,server_id,group_base_dn,group_filter,group_scope,user_base_dn,user_filter,"
			"user_scope,user_type_attr,user_type_default,alias_attr,name_attr,surname_attr,language_attr,"
			"language_default,theme_attr,theme_default,autologin_attr,autologin_default,autologout_attr,"
			"autologout_default,refresh_attr,refresh_default,rows_per_page_attr,rows_per_page_default,"
			"url_after_login_attr,url_after_login_default,media_type_attr,media_type_default,send_to_attr,"
			"send_to_default,when_active_attr,when_active_default,media_enabled_attr,media_enabled_default,"
			"use_if_severity_attr,use_if_severity_default"
			" from ldap_searches"
			" where status=0 and server_id in (%s)"
			" order by server_id, search_id", ids);

	while (NULL != (row = DBfetch(result)))
	{
		zbx_ldap_search_t	*p;
		int			i;

		/* find which element in 'sources' this 'ldap_searches' record belongs to */

		for (i = 0; i < sources->values_num; i++)
		{
			if (0 == strcmp(((zbx_ldap_source_t *)(sources->values[i]))->server.server_id, row[1]))
				break;
		}

		if (sources->values_num == i)
		{
			*error = zbx_strdup(*error, "database changed while reading LDAP data");
			DBfree_result(result);
			goto out;
		}

		/* build 'zbx_ldap_search_t' structure and insert it into searches vector for the given server */

		p = zbx_malloc(NULL, sizeof(zbx_ldap_search_t));

		p->group_base_dn = zbx_strdup(NULL, row[2]);
		p->group_filter = zbx_strdup(NULL, row[3]);
		p->group_scope = atoi(row[4]);
		p->user_base_dn = zbx_strdup(NULL, row[5]);
		p->user_filter = zbx_strdup(NULL, row[6]);
		p->user_scope = atoi(row[7]);
		p->groups = NULL;
		p->user_type_attr = zbx_strdup(NULL, row[8]);
		p->user_type_default = atoi(row[9]);
		p->alias_attr = zbx_strdup(NULL, row[10]);
		p->name_attr = zbx_strdup(NULL, row[11]);
		p->surname_attr = zbx_strdup(NULL, row[12]);
		p->language_attr = zbx_strdup(NULL, row[13]);
		p->language_default = zbx_strdup(NULL, row[14]);
		p->theme_attr = zbx_strdup(NULL, row[15]);
		p->theme_default = zbx_strdup(NULL, row[16]);
		p->autologin_attr = zbx_strdup(NULL, row[17]);
		p->autologin_default = atoi(row[18]);
		p->autologout_attr = zbx_strdup(NULL, row[19]);
		p->autologout_default = atoi(row[20]);
		p->refresh_attr = zbx_strdup(NULL, row[21]);
		p->refresh_default = atoi(row[22]);
		p->rows_per_page_attr = zbx_strdup(NULL, row[23]);
		p->rows_per_page_default = atoi(row[24]);
		p->url_after_login_attr = zbx_strdup(NULL, row[25]);
		p->url_after_login_default = zbx_strdup(NULL, row[26]);
		p->media_type_attr = zbx_strdup(NULL, row[27]);
		p->media_type_default = atoi(row[28]);
		p->send_to_attr = zbx_strdup(NULL, row[29]);
		p->send_to_default = zbx_strdup(NULL, row[30]);
		p->when_active_attr = zbx_strdup(NULL, row[31]);
		p->when_active_default = zbx_strdup(NULL, row[32]);
		p->media_enabled_attr = zbx_strdup(NULL, row[33]);
		p->media_enabled_default = atoi(row[34]);
		p->use_if_severity_attr = zbx_strdup(NULL, row[35]);
		p->use_if_severity_default = atoi(row[36]);

		zbx_vector_ptr_append(&((zbx_ldap_source_t *)(sources->values[i]))->searches, p);
	}

	DBfree_result(result);

	ret = SUCCEED;
out:
	zbx_free(ids);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: synchronize_from_ldap                                            *
 *                                                                            *
 * Purpose: synchronize users and groups from LDAP database                   *
 *                                                                            *
 * Return value: SUCCEED or FAIL                                              *
 *                                                                            *
 ******************************************************************************/
static int	zbx_synchronize_from_ldap(int *user_num)
{
	const char		*__function_name = "zbx_synchronize_from_ldap";
	LDAPMessage		*result;
	char			*error = NULL;
	int			i, res, ret = FAIL;
	zbx_vector_ptr_t	ldap_sources;		/* top-level container for storing pointers to */
							/* 'zbx_ldap_source_t' structures */
	zbx_vector_ptr_t	ldap_users;		/* top-level container for storing pointers to */
							/* 'zbx_ldap_user_t' structures */

	/* TODO: consider supporting a list of URIs - ldap_initialize() in zbx_ldap_connect() can take a list of them */
	/* TODO: consider supporting 'ldaps' (LDAP over TLS) protocol. */

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_ptr_create(&ldap_sources);
	zbx_vector_ptr_create(&ldap_users);

	if (SUCCEED != zbx_get_sources_from_db(&ldap_sources, &error))
	{
		zabbix_log(LOG_LEVEL_WARNING, "%s(): cannot get LDAP details from database: %s",
				__function_name, error);
		goto out;
	}

	if (0 == ldap_sources.values_num)
	{
		ret = SUCCEED;
		goto out;
	}

	if (SUCCEED != zbx_get_data_from_ldap(&ldap_sources, &ldap_users, &error))
	{
		zabbix_log(LOG_LEVEL_WARNING, "%s(): cannot get user data from LDAP: %s",
				__function_name, error);
		goto out;
	}

	/* TODO: synchronization */

	ret = SUCCEED;
out:
	zbx_free(error);


	for (i = 0; i < ldap_sources.values_num; i++)
		zbx_ldap_source_destroy(ldap_sources.values[i]);

	zbx_vector_ptr_clear_ext(&ldap_sources, zbx_ptr_free);
	zbx_vector_ptr_destroy(&ldap_sources);

	for (i = 0; i < ldap_users.values_num; i++)
		zbx_ldap_user_destroy(ldap_users.values[i]);

	zbx_vector_ptr_clear_ext(&ldap_users, zbx_ptr_free);
	zbx_vector_ptr_destroy(&ldap_users);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s error:'%s'", __function_name, zbx_result_string(ret),
			ZBX_NULL2EMPTY_STR(error));
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_ldap_server_test                                             *
 *                                                                            *
 * Purpose: connect to LDAP server and test bind operation, then disconnect   *
 *                                                                            *
 * Parameters: ldap_server - [IN] LDAP server data                            *
 *             error       - [OUT] error message (to be freed by caller)      *
 *                                                                            *
 * Return value: SUCCEED or FAIL                                              *
 *                                                                            *
 ******************************************************************************/
static int	zbx_ldap_server_test(zbx_ldap_server_t *ldap_server, char **error)
{
	LDAP	*ld = NULL;
	int	ret = FAIL;

	if (SUCCEED == zbx_ldap_connect(&ld, ldap_server, error))
		ret = SUCCEED;

	if (NULL != ld)
		zbx_ldap_free(&ld);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: ldap_json_deserialize_server                                     *
 *                                                                            *
 * Purpose: deserialize LDAP server data from JSON                            *
 *                                                                            *
 * Parameters: jp          - [IN] the json data                               *
 *             ldap_server - [OUT] result                                     *
 *             error       - [OUT] error message (to be freed by caller)      *
 *                                                                            *
 * Return value: SUCCEED or FAIL                                              *
 *                                                                            *
 * Comments:                                                                  *
 *   Example of LDAP server data:                                             *
 *      [{                                                                    *
 *          "host":"192.168.123.123",                                         *
 *          "port":389,                                                       *
 *          "use_tls":0,                                                      *
 *          "bind_dn":"cn=ldap_search,dc=example,dc=com",                     *
 *          "bind_pw":"********",                                             *
 *          "net_timeout":10,                                                 *
 *          "proc_timeout":10}'                                               *
 *      }]                                                                    *
 *                                                                            *
 *   Processing stops on the first error, some (even invalid) values may be   *
 *   written into 'ldap_server'. It is a responsibility of the caller to      *
 *   free 'ldap_server' resources even in case of error.                      *
 *                                                                            *
 ******************************************************************************/
static int	ldap_json_deserialize_server(struct zbx_json_parse *jp, zbx_ldap_server_t *ldap_server, char **error)
{
#define xstr(s)	str(s)
#define str(s)	#s

	size_t	host_alloc = 0, bind_dn_alloc = 0, bind_pw_alloc = 0;
	char	value[MAX_STRING_LEN];

	ldap_server->server_id = NULL;

	/* "host" */

	if (SUCCEED != zbx_json_value_by_name_dyn(jp, ZBX_PROTO_TAG_HOST, &ldap_server->host, &host_alloc))
	{
		*error = zbx_strdup(*error, "no \"" ZBX_PROTO_TAG_HOST "\" tag");
		return FAIL;
	}

	/* "port" */

	if (SUCCEED != zbx_json_value_by_name(jp, ZBX_PROTO_TAG_PORT, value, sizeof(value)))
	{
		*error = zbx_strdup(*error, "no \"" ZBX_PROTO_TAG_PORT "\" tag");
		return FAIL;
	}

	if ('\0' == *value)
	{
		*error = zbx_strdup(*error, "\"" ZBX_PROTO_TAG_PORT "\" tag value is empty");
		return FAIL;
	}

	if (0 > (ldap_server->port = atoi(value)) || 65535 < ldap_server->port)
	{
		*error = zbx_strdup(*error, "invalid \"" ZBX_PROTO_TAG_PORT "\" tag value ");
		return FAIL;
	}

	/* "use_tls" */

	if (SUCCEED != zbx_json_value_by_name(jp, ZBX_PROTO_TAG_LDAP_USE_TLS, value, sizeof(value)))
	{
		*error = zbx_strdup(*error, "no \"" ZBX_PROTO_TAG_LDAP_USE_TLS "\" tag");
		return FAIL;
	}

	if ('\0' == *value)
	{
		*error = zbx_strdup(*error, "\"" ZBX_PROTO_TAG_LDAP_USE_TLS "\" tag value is empty");
		return FAIL;
	}

	if (0 == strcmp(xstr(ZBX_LDAP_USE_TLS_UNENCRYPTED), value) ||
			0 == strcmp(xstr(ZBX_LDAP_USE_TLS_STARTTLS), value) ||
			0 == strcmp(xstr(ZBX_LDAP_USE_TLS_LDAPS), value))
	{
		ldap_server->use_tls = atoi(value);
	}
	else
	{
		*error = zbx_strdup(*error, "invalid \"" ZBX_PROTO_TAG_LDAP_USE_TLS "\" tag value ");
		return FAIL;
	}

	/* "bind_dn" */

	if (SUCCEED != zbx_json_value_by_name_dyn(jp, ZBX_PROTO_TAG_LDAP_BIND_DN, &ldap_server->bind_dn,
			&bind_dn_alloc))
	{
		*error = zbx_strdup(*error, "no \"" ZBX_PROTO_TAG_LDAP_BIND_DN "\" tag");
		return FAIL;
	}

	/* "bind_pw" */

	if (SUCCEED != zbx_json_value_by_name_dyn(jp, ZBX_PROTO_TAG_LDAP_BIND_PW, &ldap_server->bind_pw,
			&bind_pw_alloc))
	{
		*error = zbx_strdup(*error, "no \"" ZBX_PROTO_TAG_LDAP_BIND_PW "\" tag");
		return FAIL;
	}

	/* "net_timeout" */

	if (SUCCEED != zbx_json_value_by_name(jp, ZBX_PROTO_TAG_LDAP_NET_TIMEOUT, value, sizeof(value)))
	{
		*error = zbx_strdup(*error, "no \"" ZBX_PROTO_TAG_LDAP_NET_TIMEOUT "\" tag");
		return FAIL;
	}

	if ('\0' == *value)
	{
		*error = zbx_strdup(*error, "\"" ZBX_PROTO_TAG_LDAP_NET_TIMEOUT "\" tag value is empty");
		return FAIL;
	}

	if (0 >= (ldap_server->net_timeout = atoi(value)))
	{
		*error = zbx_strdup(*error, "invalid \"" ZBX_PROTO_TAG_LDAP_NET_TIMEOUT "\" tag value ");
		return FAIL;
	}

	/* "proc_timeout" */

	if (SUCCEED != zbx_json_value_by_name(jp, ZBX_PROTO_TAG_LDAP_PROC_TIMEOUT, value, sizeof(value)))
	{
		*error = zbx_strdup(*error, "no \"" ZBX_PROTO_TAG_LDAP_PROC_TIMEOUT "\" tag");
		return FAIL;
	}

	if ('\0' == *value)
	{
		*error = zbx_strdup(*error, "\"" ZBX_PROTO_TAG_LDAP_PROC_TIMEOUT "\" tag value is empty");
		return FAIL;
	}

	if (0 >= (ldap_server->proc_timeout = atoi(value)))
	{
		*error = zbx_strdup(*error, "invalid \"" ZBX_PROTO_TAG_LDAP_PROC_TIMEOUT "\" tag value ");
		return FAIL;
	}

	return SUCCEED;
}

#define ZBX_LDAP_UNKNOWN	-1
#define ZBX_LDAP_TEST_SERVER	0
#define ZBX_LDAP_TEST_SEARCH	1
#define ZBX_LDAP_TEST_SYNC	2
#define ZBX_LDAP_SYNC_NOW	3

/******************************************************************************
 *                                                                            *
 * Function: zbx_ldap_sync                                                    *
 *                                                                            *
 * Purpose: process LDAP test and synchronization requests from PHP frontend  *
 *                                                                            *
 * Parameters: sock - [IN] network socket for sending reply to frontend       *
 *             jp   - [IN] the json data                                      *
 *                                                                            *
 * Comments:                                                                  *
 *   Example of LDAP server test request:                                     *
 *    '{"request":"ldap_sync",                                                *
 *      "sid":"d6....",                                                       *
 *      "type":"test_server",                                                 *
 *      "data": [{                                                            *
 *          "host":"192.168.123.123",                                         *
 *          "port":389,                                                       *
 *          "use_tls":0,                                                      *
 *          "bind_dn":"cn=ldap_search,dc=example,dc=com",                     *
 *          "bind_pw":"********",                                             *
 *          "net_timeout":10,                                                 *
 *          "proc_timeout":10}'                                               *
 *       }]                                                                   *
 *     }'                                                                     *
 *                                                                            *
 * Return value: SUCCEED or FAIL                                              *
 *                                                                            *
 ******************************************************************************/
int	zbx_ldap_sync(zbx_socket_t *sock, struct zbx_json_parse *jp)
{
	const char		*__function_name = "zbx_ldap_sync";
	int			ret = FAIL, request_type = ZBX_LDAP_UNKNOWN;
	char			*error = NULL, type[MAX_STRING_LEN];
	struct zbx_json_parse	jp_data;
	const char		*p = NULL;
	zbx_ldap_server_t	ldap_server = { NULL, NULL, NULL, NULL, 0, 0, 0, 0 };

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	/* Start with "type" tag. "request" and "sid" tags were processed earlier. */

	if (SUCCEED == zbx_json_value_by_name(jp, ZBX_PROTO_TAG_TYPE, type, sizeof(type)))
	{
		if (0 == strcmp(type, ZBX_PROTO_VALUE_LDAP_TEST_SERVER))
			request_type = ZBX_LDAP_TEST_SERVER;
		else if (0 == strcmp(type, ZBX_PROTO_VALUE_LDAP_TEST_SEARCH))
			request_type = ZBX_LDAP_TEST_SEARCH;
		else if (0 == strcmp(type, ZBX_PROTO_VALUE_LDAP_TEST_SYNC))
			request_type = ZBX_LDAP_TEST_SYNC;
		else if (0 == strcmp(type, ZBX_PROTO_VALUE_LDAP_SYNC_NOW))
			request_type = ZBX_LDAP_SYNC_NOW;
	}

	if (ZBX_LDAP_UNKNOWN == request_type)
	{
		zbx_send_response_raw(sock, FAIL, "Unsupported request type.", CONFIG_TIMEOUT);
		goto out;
	}

	/* "data" tag */

	if (SUCCEED != zbx_json_brackets_by_name(jp, ZBX_PROTO_TAG_DATA, &jp_data))
	{
		zbx_send_response_raw(sock, FAIL, "no \"" ZBX_PROTO_TAG_DATA "\" tag", CONFIG_TIMEOUT);
		goto out;
	}

	while (NULL != (p = zbx_json_next(&jp_data, p)))
	{
		struct zbx_json_parse	jp_server;

		if (SUCCEED != (ret = zbx_json_brackets_open(p, &jp_server)))
		{
			zbx_send_response_raw(sock, FAIL, zbx_json_strerror(), CONFIG_TIMEOUT);
			goto out;
		}

		if (SUCCEED != ldap_json_deserialize_server(&jp_server, &ldap_server, &error))
		{
			zbx_send_response_raw(sock, FAIL, error, CONFIG_TIMEOUT);
			goto out;
		}

		if (ZBX_LDAP_TEST_SERVER == request_type)
		{
			if (SUCCEED == zbx_ldap_server_test(&ldap_server, &error))
				zbx_send_response_raw(sock, SUCCEED, "LDAP server test successful", CONFIG_TIMEOUT);
			else
				zbx_send_response_raw(sock, FAIL, error, CONFIG_TIMEOUT);

			goto out;
		}

		/* TODO: process "test_search", "test_sync" and "sync_now" requests */
	}
out:
	zbx_free(error);
	zbx_ldap_server_destroy(&ldap_server);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

ZBX_THREAD_ENTRY(ldap_syncer_thread, args)
{
	double	sec1, sec2;
	int	sleeptime;

	process_type = ((zbx_thread_args_t *)args)->process_type;
	server_num = ((zbx_thread_args_t *)args)->server_num;
	process_num = ((zbx_thread_args_t *)args)->process_num;

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(program_type),
			server_num, get_process_type_string(process_type), process_num);

	zbx_setproctitle("%s [connecting to the database]", get_process_type_string(process_type));
	DBconnect(ZBX_DB_CONNECT_NORMAL);

	sec1 = zbx_time();
	sec2 = sec1;

	sleeptime = ZBX_LDAPSYNCER_PERIOD - (int)sec1 % ZBX_LDAPSYNCER_PERIOD;

	zbx_setproctitle("%s [started, idle %d sec]", get_process_type_string(process_type), sleeptime);

	for (;;)
	{
		int	user_num = 0, nextsync;

		zbx_sleep_loop(sleeptime);

		zbx_handle_log();

		zbx_setproctitle("%s [synchronizing LDAP users]", get_process_type_string(process_type));

		sec1 = zbx_time();

		if (SUCCEED != zbx_synchronize_from_ldap(&user_num))
		{
			/* TODO: communicate error to frontend */
		}

		sec2 = zbx_time();

		nextsync = (int)sec1 - (int)sec1 % ZBX_LDAPSYNCER_PERIOD + ZBX_LDAPSYNCER_PERIOD;

		if (0 > (sleeptime = nextsync - (int)sec2))
			sleeptime = 0;

		zbx_setproctitle("%s [synchronized %d LDAP users(s) in " ZBX_FS_DBL " sec, idle %d sec]",
				get_process_type_string(process_type), user_num, sec2 - sec1, sleeptime);
	}
}
#endif
