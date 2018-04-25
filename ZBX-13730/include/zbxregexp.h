/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
#ifndef ZABBIX_ZBXREGEXP_H
#define ZABBIX_ZBXREGEXP_H

#include "zbxalgo.h"

#define ZBX_REGEXP_NO_MATCH	0
#define ZBX_REGEXP_MATCH	1
#define ZBX_REGEXP_ERROR        -1
#define ZBX_REGEXP_RUNAWAY	-8

typedef struct
{
	char		*name;
	char		*expression;
	int		expression_type;
	char		exp_delimiter;
	unsigned char	case_sensitive;
}
zbx_expression_t;

/* maps to ovector of pcre_exec() */
typedef struct
{
	int rm_so;
	int rm_eo;
}
zbx_regmatch_t;

/* regular expressions */
pcre 	*zbx_regexp_compile(const char *pattern, int flags, const char **error);
int	zbx_regexp_exec(const char *string, const pcre *regex_compiled, int flags, size_t count,
		zbx_regmatch_t *matches);
void	zbx_regexp_free(pcre *regexp);
int	zbx_regexp_match_precompiled(const char *string, const pcre *regexp);
char	*zbx_regexp_match(const char *string, const char *pattern, int *len, int *result);
char	*zbx_iregexp_match(const char *string, const char *pattern, int *len, int *result);
int	zbx_regexp_sub(const char *string, const char *pattern, const char *output_template, char **out);
int	zbx_mregexp_sub(const char *string, const char *pattern, const char *output_template, char **out);
int	zbx_iregexp_sub(const char *string, const char *pattern, const char *output_template, char **out);

void	zbx_regexp_clean_expressions(zbx_vector_ptr_t *expressions);

void	add_regexp_ex(zbx_vector_ptr_t *regexps, const char *name, const char *expression, int expression_type,
		char exp_delimiter, int case_sensitive);
int	regexp_match_ex(const zbx_vector_ptr_t *regexps, const char *string, const char *pattern, int case_sensitive);
int	regexp_sub_ex(const zbx_vector_ptr_t *regexps, const char *string, const char *pattern, int case_sensitive,
		const char *output_template, char **output);

#endif /* ZABBIX_ZBXREGEXP_H */
