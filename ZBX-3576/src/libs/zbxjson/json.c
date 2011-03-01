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

#include "common.h"
#include "zbxjson.h"

/******************************************************************************
 *                                                                            *
 * Function: zbx_json_strerror                                                *
 *                                                                            *
 * Purpose: return string describing json error                               *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: pointer to the null terminated string                        *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
#define ZBX_JSON_MAX_STRERROR	255

static char	zbx_json_strerror_message[ZBX_JSON_MAX_STRERROR];

const char	*zbx_json_strerror()
{
	zbx_json_strerror_message[ZBX_JSON_MAX_STRERROR - 1] = '\0'; /* force terminate string */
	return (&zbx_json_strerror_message[0]);
}

#ifdef HAVE___VA_ARGS__
#	define zbx_set_json_strerror(fmt, ...) __zbx_zbx_set_json_strerror(ZBX_CONST_STRING(fmt), ##__VA_ARGS__)
#else
#	define zbx_set_json_strerror __zbx_zbx_set_json_strerror
#endif /* HAVE___VA_ARGS__ */
static void	__zbx_zbx_set_json_strerror(const char *fmt, ...)
{
	va_list args;

	va_start(args, fmt);

	zbx_vsnprintf(zbx_json_strerror_message, sizeof(zbx_json_strerror_message), fmt, args);

	va_end(args);
}

/******************************************************************************
 *                                                                            *
 * Function: __zbx_json_realloc                                               *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: -                                                            *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	__zbx_json_realloc(struct zbx_json *j, size_t need)
{
	int	realloc = 0;

	if (NULL == j->buffer)
	{
		if (need > sizeof(j->buf_stat))
		{
			j->buffer_allocated = need;
			j->buffer = zbx_malloc(j->buffer, j->buffer_allocated);
		}
		else
		{
			j->buffer_allocated = sizeof(j->buf_stat);
			j->buffer = j->buf_stat;
		}
		return;
	}

	while (need > j->buffer_allocated)
	{
		if (0 == j->buffer_allocated)
			j->buffer_allocated = 1024;
		else
			j->buffer_allocated *= 2;
		realloc = 1;
	}

	if (1 == realloc)
	{
		if (j->buffer == j->buf_stat)
		{
			j->buffer = NULL;
			j->buffer = zbx_malloc(j->buffer, j->buffer_allocated);
			memcpy(j->buffer, j->buf_stat, sizeof(j->buf_stat));
		}
		else
			j->buffer = zbx_realloc(j->buffer, j->buffer_allocated);
	}
}

void	zbx_json_init(struct zbx_json *j, size_t allocate)
{
	assert(j);

	j->buffer = NULL;
	j->buffer_allocated = 0;
	j->buffer_offset = 0;
	j->buffer_size = 0;
	j->status = ZBX_JSON_EMPTY;
	j->level = 0;
	__zbx_json_realloc(j, allocate);
	*j->buffer = '\0';

	zbx_json_addobject(j, NULL);
}

void	zbx_json_clean(struct zbx_json *j)
{
	assert(j);

	j->buffer_offset = 0;
	j->buffer_size = 0;
	j->status = ZBX_JSON_EMPTY;
	j->level = 0;
	*j->buffer = '\0';

	zbx_json_addobject(j, NULL);
}

void	zbx_json_free(struct zbx_json *j)
{
	assert(j);

	if (j->buffer != j->buf_stat)
		zbx_free(j->buffer);
}

static size_t	__zbx_json_stringsize(const char *string, zbx_json_type_t type)
{
	size_t		len = 0;
	const char	*sptr;
	char		buffer[] = {"null"};

	for (sptr = (NULL != string ? string : buffer); '\0' != *sptr; sptr++)
	{
		switch (*sptr)
		{
			case '"':  /* quotation mark */
			case '\\': /* reverse solidus */
			case '/':  /* solidus */
			case '\b': /* backspace */
			case '\f': /* formfeed */
			case '\n': /* newline */
			case '\r': /* carriage return */
			case '\t': /* horizontal tab */
				len += 2;
				break;
			default:
				if ((u_char)*sptr < 32)
					len += 6;
				else
					len++;
		}
	}

	if (NULL != string && ZBX_JSON_TYPE_STRING == type)
		len += 2; /* "" */

	return len;
}

static char	*__zbx_json_insstring(char *p, const char *string, zbx_json_type_t type)
{
	const char	*sptr;
	char		buffer[] = {"null"};

	if (NULL != string && ZBX_JSON_TYPE_STRING == type)
		*p++ = '"';

	for (sptr = (NULL != string ? string : buffer); '\0' != *sptr; sptr++)
	{
		switch (*sptr)
		{
			case '"':		/* quotation mark */
				*p++ = '\\';
				*p++ = '"';
				break;
			case '\\':		/* reverse solidus */
				*p++ = '\\';
				*p++ = '\\';
				break;
			case '/':		/* solidus */
				*p++ = '\\';
				*p++ = '/';
				break;
			case '\b':		/* backspace */
				*p++ = '\\';
				*p++ = 'b';
				break;
			case '\f':		/* formfeed */
				*p++ = '\\';
				*p++ = 'f';
				break;
			case '\n':		/* newline */
				*p++ = '\\';
				*p++ = 'n';
				break;
			case '\r':		/* carriage return */
				*p++ = '\\';
				*p++ = 'r';
				break;
			case '\t':		/* horizontal tab */
				*p++ = '\\';
				*p++ = 't';
				break;
			default:
				if ((u_char)*sptr < 32)
				{
					*p++ = '\\';
					*p++ = 'u';
					*p++ = '0';
					*p++ = '0';
					*p++ = zbx_num2hex((*sptr >> 4) & 0xf);
					*p++ = zbx_num2hex(*sptr & 0xf);
				}
				else
					*p++ = *sptr;
		}
	}

	if (NULL != string && ZBX_JSON_TYPE_STRING == type)
		*p++ = '"';

	return p;
}

static void	__zbx_json_addobject(struct zbx_json *j, const char *name, int object)
{
	size_t	len = 2; /* brackets */
	char	*p, *psrc, *pdst;
	int	i;

	assert(j);

	if (ZBX_JSON_COMMA == j->status)
		len++; /* , */

	if (0 != j->level)
		len++;
	len += j->level;

	if (NULL != name)
	{
		len += __zbx_json_stringsize(name, ZBX_JSON_TYPE_STRING);
		len += 1; /* : */
	}

	__zbx_json_realloc(j, j->buffer_size + len + 1/*'\0'*/);

	psrc = j->buffer + j->buffer_offset;
	pdst = j->buffer + j->buffer_offset + len;

	memmove(pdst, psrc, j->buffer_size - j->buffer_offset + 1/*'\0'*/);

	p = psrc;

	if (ZBX_JSON_COMMA == j->status)
		*p++ = ',';

	if (0 != j->level)
		*p++ = '\n';
	for (i = 0; i < j->level; i++)
		*p++ = '\t';

	if (NULL != name)
	{
		p = __zbx_json_insstring(p, name, ZBX_JSON_TYPE_STRING);
		*p++ = ':';
	}

	*p++ = object ? '{' : '[';
	*p = object ? '}' : ']';

	j->buffer_offset = p - j->buffer;
	j->buffer_size += len;
	j->level++;
	j->status = ZBX_JSON_EMPTY;
}

void	zbx_json_addobject(struct zbx_json *j, const char *name)
{
	__zbx_json_addobject(j, name, 1);
}

void	zbx_json_addarray(struct zbx_json *j, const char *name)
{
	__zbx_json_addobject(j, name, 0);
}

void	zbx_json_addstring(struct zbx_json *j, const char *name, const char *string, zbx_json_type_t type)
{
	size_t	len = 0;
	char	*p, *psrc, *pdst;
	int	i;

	assert(j);

	if (ZBX_JSON_COMMA == j->status)
		len++; /* , */

	if (NULL != name)
	{
		len += 1 + j->level;
		len += __zbx_json_stringsize(name, ZBX_JSON_TYPE_STRING);
		len += 1; /* : */
	}
	len += __zbx_json_stringsize(string, type);

	__zbx_json_realloc(j, j->buffer_size + len + 1/*'\0'*/);

	psrc = j->buffer + j->buffer_offset;
	pdst = j->buffer + j->buffer_offset + len;

	memmove(pdst, psrc, j->buffer_size - j->buffer_offset + 1/*'\0'*/);

	p = psrc;

	if (ZBX_JSON_COMMA == j->status)
		*p++ = ',';

	if (NULL != name)
	{
		*p++ = '\n';
		for (i = 0; i < j->level; i++)
			*p++ = '\t';
		p = __zbx_json_insstring(p, name, ZBX_JSON_TYPE_STRING);
		*p++ = ':';
	}
	p = __zbx_json_insstring(p, string, type);

	j->buffer_offset = p - j->buffer;
	j->buffer_size += len;
	j->status = ZBX_JSON_COMMA;
}

void	zbx_json_adduint64(struct zbx_json *j, const char *name, zbx_uint64_t value)
{
	char	buffer[21];	/* strlen(MAX_UINT64) == 20 */

	zbx_snprintf(buffer, sizeof(buffer), ZBX_FS_UI64, value);
	zbx_json_addstring(j, name, buffer, ZBX_JSON_TYPE_INT);
}

int	zbx_json_close(struct zbx_json *j)
{
	if (1 == j->level)
	{
		zbx_set_json_strerror("Can't close top level object");
		return FAIL;
	}

	j->level--;
	j->buffer_offset++;
	j->status = ZBX_JSON_COMMA;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: __zbx_json_type                                                  *
 *                                                                            *
 * Purpose: return type of pointed value                                      *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: type of pointed value                                        *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static zbx_json_type_t	__zbx_json_type(const char *p)
{
	if ('"' == *p)
		return ZBX_JSON_TYPE_STRING;
	if (('0' <= *p && *p <= '9') || '-' == *p)
		return ZBX_JSON_TYPE_INT;
	if ('[' == *p)
		return ZBX_JSON_TYPE_ARRAY;
	if ('{' == *p)
		return ZBX_JSON_TYPE_OBJECT;
	if ('n' == p[0] && 'u' == p[1] && 'l' == p[2] && 'l' == p[3])
		return ZBX_JSON_TYPE_NULL;

	zbx_set_json_strerror("Invalid type of JSON value \"%.64s\"", p);

	return ZBX_JSON_TYPE_UNKNOWN;
}

/******************************************************************************
 *                                                                            *
 * Function: __zbx_json_rbracket                                              *
 *                                                                            *
 * Purpose: return position of right bracket                                  *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: position of right bracket                                    *
 *               NULL - an error occurred                                     *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static const char	*__zbx_json_rbracket(const char *p)
{
	int	level = 0;
	int	state = 0; /* 0 - outside string; 1 - inside string */
	char	lbracket, rbracket;

	assert(p);

	lbracket = *p;

	if ('{' != lbracket && '[' != lbracket)
		return NULL;

	rbracket = ('{' == lbracket ? '}' : ']');

	while ('\0' != *p)
	{
		switch (*p)
		{
			case '"':
				state = (0 == state ? 1 : 0);
				break;
			case '\\':
				if (1 == state)
					if ('\0' == *++p)
						return NULL;
				break;
			case '[':
			case '{':
				if (0 == state)
					level++;
				break;
			case ']':
			case '}':
				if (0 == state)
				{
					level--;
					if (0 == level)
						return (rbracket == *p ? p : NULL);
				}
				break;
		}
		p++;
	}

	return NULL;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_json_open                                                    *
 *                                                                            *
 * Purpose: open json buffer and check for brackets                           *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: SUCCESS - processed successfully                             *
 *               FAIL - an error occurred                                     *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	zbx_json_open(char *buffer, struct zbx_json_parse *jp)
{
	char	*o, *i;
	int	state = 0; /* 0 - outside string; 1 - inside string */

	jp->start = NULL;
	jp->end = NULL;

	while ('\0' != *buffer && NULL != strchr(ZBX_WHITESPACE, *buffer))
		buffer++;

	if ('{' == *buffer)
		jp->start = buffer;
	else
		goto error;

	o = buffer;
	i = buffer;
	do
	{
		switch (*i)
		{
			case '"':
				state = (0 == state ? 1 : 0);
				break;
			case '\\':
				*o++ = *i++;
				break;
		}

		if (1 == state || NULL == strchr(ZBX_WHITESPACE, *i) || '\0' == *i)
			*o++ = *i;
	}
	while ('\0' != *i++);

	if (NULL == (jp->end = __zbx_json_rbracket(buffer)))
		goto error;

	if ('\0' != jp->end[1])
		goto error;

	return SUCCEED;
error:
	zbx_set_json_strerror("Can't open JSON object \"%.64s\"", buffer);

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_json_next                                                    *
 *                                                                            *
 * Purpose: locate next pair or element                                       *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: NULL - no more values                                        *
 *               NOT NULL - pointer to pair or element                        *
 *      {"name",...    or  "array":["name", ... ,1,null]                      *
 * p =   ^                                         ^                          *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
const char	*zbx_json_next(struct zbx_json_parse *jp, const char *p)
{
	int	level = 0;
	int	state = 0; /* 0 - outside string; 1 - inside string */

	if (1 == jp->end - jp->start) /* empty object or array */
		return NULL;

	if (NULL == p)
		return jp->start + 1;

	while (p <= jp->end)
	{
		switch (*p)
		{
			case '"':
				state = (0 == state) ? 1 : 0;
				break;
			case '\\':
				if (1 == state)
					p++;
				break;
			case '[':
			case '{':
				if (0 == state)
					level++;
				break;
			case ']':
			case '}':
				if (0 == state)
				{
					if (0 == level)
						return NULL;
					level--;
				}
				break;
			case ',':
				if (0 == state && 0 == level)
					return ++p;
				break;
		}
		p++;
	}

	return NULL;
}

const char	*zbx_json_next_value(struct zbx_json_parse *jp, const char *p, char *string, size_t len)
{
	if (NULL == (p = zbx_json_next(jp, p)))
		return NULL;

	if (NULL == zbx_json_decodevalue(p, string, len))
		return NULL;

	return p;
}

static const char	*zbx_json_decodestring(const char *p, char *string, size_t len)
{
	int	state = 0; /* 0 - init; 1 - inside string */
	char	*o = string;
	u_char	c;

	if ('"' != *p)
		return NULL;

	while ('\0' != *p)	/* this should never happen */
	{
		if (*p == '"')
		{
			if (state == 1)
			{
				*o = '\0';
				return ++p;
			}
			state = 1;
		}
		else if (state == 1 && (size_t)(o - string) < len - 1/*'\0'*/)
		{
			if (*p == '\\')
			{
				switch (*++p)
				{
					case '"':
					case '\\':
					case '/':
						*o++ = *p;
						break;
					case 'b':
						*o++ = '\b';
						break;
					case 'f':
						*o++ = '\f';
						break;
					case 'n':
						*o++ = '\n';
						break;
					case 'r':
						*o++ = '\r';
						break;
					case 't':
						*o++ = '\t';
						break;
					case 'u':
						p += 2; /* "00" */
						c = zbx_hex2num(*p++) << 4;
						c += zbx_hex2num(*p);
						*o++ = (char)c;
						break;
					default:
						THIS_SHOULD_NEVER_HAPPEN;
				}
			}
			else
				*o++ = *p;
		}

		p++;
	}

	return NULL;
}

static const char	*zbx_json_decodeint(const char *p, char *string, size_t len)
{
	char	*o = string;

	while ('\0' != *p)	/* this should never happen */
	{
		if ((*p < '0' || *p > '9') && *p != '-')
		{
			*o = '\0';
			return p;
		}
		else if ((size_t)(o - string) < len - 1/*'\0'*/)
		{
			*o++ = *p;
		}

		p++;
	}

	return NULL;
}

const char	*zbx_json_decodevalue(const char *p, char *string, size_t len)
{
	switch (__zbx_json_type(p))
	{
		case ZBX_JSON_TYPE_STRING:
			return zbx_json_decodestring(p, string, len);
		case ZBX_JSON_TYPE_INT:
			return zbx_json_decodeint(p, string, len);
		default:
			return NULL;
	}
}

const char	*zbx_json_pair_next(struct zbx_json_parse *jp, const char *p, char *name, size_t len)
{
	if (NULL == (p = zbx_json_next(jp, p)))
		return NULL;

	if (ZBX_JSON_TYPE_STRING != __zbx_json_type(p))
		goto error;

	if (NULL == (p = zbx_json_decodestring(p, name, len)))
		goto error;

	if (':' != *p)
		goto error;

	return ++p;
error:
	return NULL;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_json_pair_by_name                                            *
 *                                                                            *
 * Purpose: find pair by name and return pointer to value                     *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: pointer to value                                             *
 *        {"name":["a","b",...]}                                              *
 *                ^ - returned pointer                                        *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
const char	*zbx_json_pair_by_name(struct zbx_json_parse *jp, const char *name)
{
	char		buffer[MAX_STRING_LEN];
	const char	*p = NULL;

	while (NULL != (p = zbx_json_pair_next(jp, p, buffer, sizeof(buffer))))
		if (0 == strcmp(name, buffer))
			return p;

	zbx_set_json_strerror("Can't find pair with name \"%s\"", name);

	return NULL;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_json_value_by_name                                           *
 *                                                                            *
 * Purpose: return value by pair name                                         *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: pointer to value                                             *
 *        {"name":["a","b",...]}                                              *
 *                ^ - returned pointer                                        *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	zbx_json_value_by_name(struct zbx_json_parse *jp, const char *name, char *string, size_t len)
{
	const char	*p = NULL;

	if (NULL == (p = zbx_json_pair_by_name(jp, name)))
		return FAIL;

	if (NULL == zbx_json_decodevalue(p, string, len))
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_json_brackets_open                                           *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: SUCCESS - processed successfully                             *
 *               FAIL - an error occurred                                     *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	zbx_json_brackets_open(const char *p, struct zbx_json_parse *jp)
{
	if (NULL == (jp->end = __zbx_json_rbracket(p)))
	{
		zbx_set_json_strerror("Can't open JSON object or array \"%.64s\"", p);
		return FAIL;
	}

	jp->start = p;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_json_brackets_by_name                                        *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: SUCCESS - processed successfully                             *
 *               FAIL - an error occurred                                     *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	zbx_json_brackets_by_name(struct zbx_json_parse *jp, const char *name, struct zbx_json_parse *out)
{
	const char	*p = NULL;

	if (NULL == (p = zbx_json_pair_by_name(jp, name)))
		return FAIL;

	if (FAIL == zbx_json_brackets_open(p, out))
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_json_object_is_empty                                         *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: SUCCESS - if object is empty                                 *
 *               FAIL - if object contains data                               *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	zbx_json_object_is_empty(struct zbx_json_parse *jp)
{
	return jp->end - jp->start > 1 ? FAIL : SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_json_count                                                   *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: number of elements in zbx_json_parse object                  *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	zbx_json_count(struct zbx_json_parse *jp)
{
	int		num = 0;
	const char	*p = NULL;

	while (NULL != (p = zbx_json_next(jp, p)))
		num++;

	return num;
}
