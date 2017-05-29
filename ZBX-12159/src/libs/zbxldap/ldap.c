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

#include <ldap.h>

#ifdef HAVE_LBER_H
#	include <lber.h>
#endif

#include "log.h"
#include "module.h"
#include "zbxldap.h"

static LDAP		*(*zbx_ldap_init)(const char *host, int port) = NULL;
static int		(*zbx_ldap_search_s)(LDAP *ld, char *base, int scope, char *filter, char *attrs[],
					int attrsonly, LDAPMessage **res) = NULL;
static LDAPMessage	*(*zbx_ldap_first_entry)(LDAP *ld, LDAPMessage *result) = NULL;
static char		*(*zbx_ldap_first_attribute)(LDAP *ld, LDAPMessage *entry, BerElement **berptr) = NULL;
static char		**(*zbx_ldap_get_values)(LDAP *ld, LDAPMessage *entry, char *attr) = NULL;
static void		(*zbx_ldap_value_free)(char **vals) = NULL;
static void		(*zbx_ldap_memfree)(void *p) = NULL;
static void		(*zbx_ber_free)(BerElement *ber, int freebuf) = NULL;
static int		(*zbx_ldap_msgfree)(LDAPMessage *msg) = NULL;
static int		(*zbx_ldap_unbind)(LDAP *ld) = NULL;
static char		*(*zbx_ldap_err2string)(int err) = NULL;

int	zbx_check_ldap(const char *host, unsigned short port, int timeout, int *value_int)
{
	LDAP		*ldap	= NULL;
	LDAPMessage	*res	= NULL;
	LDAPMessage	*msg	= NULL;
	BerElement	*ber	= NULL;

	char	*attrs[2] = { "namingContexts", NULL };
	char	*attr	 = NULL;
	char	**valRes = NULL;
	int	ldapErr = 0;

	zbx_alarm_on(timeout);

	*value_int = 0;

	if (NULL == (ldap = zbx_ldap_init(host, port)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "LDAP - initialization failed [%s:%hu]", host, port);
		goto lbl_ret;
	}

	if (LDAP_SUCCESS != (ldapErr = zbx_ldap_search_s(ldap, "", LDAP_SCOPE_BASE, "(objectClass=*)", attrs, 0, &res)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "LDAP - searching failed [%s] [%s]", host, zbx_ldap_err2string(ldapErr));
		goto lbl_ret;
	}

	if (NULL == (msg = zbx_ldap_first_entry(ldap, res)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "LDAP - empty sort result. [%s] [%s]", host, zbx_ldap_err2string(ldapErr));
		goto lbl_ret;
	}

	if (NULL == (attr = zbx_ldap_first_attribute(ldap, msg, &ber)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "LDAP - empty first entry result. [%s] [%s]", host,
				zbx_ldap_err2string(ldapErr));
		goto lbl_ret;
	}

	valRes = zbx_ldap_get_values(ldap, msg, attr);

	*value_int = 1;
lbl_ret:
	zbx_alarm_off();

	if (NULL != valRes)
		zbx_ldap_value_free(valRes);
	if (NULL != attr)
		zbx_ldap_memfree(attr);
	if (NULL != ber)
		zbx_ber_free(ber, 0);
	if (NULL != res)
		zbx_ldap_msgfree(res);
	if (NULL != ldap)
		zbx_ldap_unbind(ldap);

	return SYSINFO_RET_OK;
}

typedef void	*zbx_lib_ptr_t;
typedef void	*zbx_sym_ptr_t;

typedef struct
{
	const char	*name;
	zbx_sym_ptr_t	*address;
}
zbx_lib_sym_t;

static zbx_lib_ptr_t	ldap_library = NULL;
static zbx_lib_ptr_t	lber_library = NULL;

static const zbx_lib_sym_t	ldap_symbols[] = {
	{"ldap_init",			(zbx_sym_ptr_t *)&zbx_ldap_init},
	{"ldap_search_s",		(zbx_sym_ptr_t *)&zbx_ldap_search_s},
	{"ldap_first_entry",		(zbx_sym_ptr_t *)&zbx_ldap_first_entry},
	{"ldap_first_attribute",	(zbx_sym_ptr_t *)&zbx_ldap_first_attribute},
	{"ldap_get_values",		(zbx_sym_ptr_t *)&zbx_ldap_get_values},
	{"ldap_value_free",		(zbx_sym_ptr_t *)&zbx_ldap_value_free},
	{"ldap_memfree",		(zbx_sym_ptr_t *)&zbx_ldap_memfree},
	{"ldap_msgfree",		(zbx_sym_ptr_t *)&zbx_ldap_msgfree},
	{"ldap_unbind",			(zbx_sym_ptr_t *)&zbx_ldap_unbind},
	{"ldap_err2string",		(zbx_sym_ptr_t *)&zbx_ldap_err2string},
	{NULL}
};

static const zbx_lib_sym_t	lber_symbols[] = {
	{"ber_free",	(zbx_sym_ptr_t *)&zbx_ber_free},
	{NULL}
};

static int	zbx_dlopen(const char *path, zbx_lib_ptr_t *library, const zbx_lib_sym_t *symbols, char **error)
{
	const zbx_lib_sym_t	*symbol;

	if (NULL == (*library = dlopen(path, RTLD_NOW)))
	{
		*error = zbx_dsprintf(*error, "cannot open \"%s\" library: %s", path, dlerror());
		return FAIL;
	}

	for (symbol = symbols; NULL != symbol->name; symbol++)
	{
		if (NULL == (*symbol->address = dlsym(*library, symbol->name)))
		{
			*error = zbx_dsprintf(*error, "cannot find \"%s\" symbol in \"%s\" library: %s", symbol->name,
					path, dlerror());
			return FAIL;
		}
	}

	return SUCCEED;
}

int	zbx_load_ldap(char **error)
{
	if (SUCCEED != zbx_dlopen("libldap.so", &ldap_library, ldap_symbols, error) ||
			SUCCEED != zbx_dlopen("liblber.so", &lber_library, lber_symbols, error))
	{
		zbx_unload_ldap();
		return FAIL;
	}

	return SUCCEED;
}

void	zbx_unload_ldap(void)
{
	if (NULL != lber_library)
		dlclose(lber_library);

	if (NULL != ldap_library)
		dlclose(ldap_library);
}

#endif	/* HAVE_LDAP */
