/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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
#include "sysinfo.h"

#include "comms.h"
#include "log.h"
#include "cfg.h"

#include "net.h"

/*
 * 0 - NOT OK
 * 1 - OK
 * */
int	tcp_expect(const char *host, unsigned short port, int timeout, const char *request,
		const char *expect, const char *sendtoclose, int *value_int)
{
	zbx_sock_t	s;
	char		*buf;
	int		net, val = SUCCEED;

	assert(value_int);

	*value_int = 0;

	if (SUCCEED == (net = zbx_tcp_connect(&s, CONFIG_SOURCE_IP, host, port, timeout)))
	{
		if (NULL != request)
		{
			net = zbx_tcp_send_raw(&s, request);
		}

		if (NULL != expect && SUCCEED == net)
		{
			if (SUCCEED == (net = zbx_tcp_recv(&s, &buf)))
			{
				if (0 != strncmp(buf, expect, strlen(expect)))
				{
					val = FAIL;
				}
			}
		}

		if (NULL != sendtoclose && SUCCEED == net && SUCCEED == val)
		{
			zbx_tcp_send_raw(&s, sendtoclose);
		}

		if (SUCCEED == net && SUCCEED == val)
		{
			*value_int = 1;
		}

		zbx_tcp_close(&s);
	}

	if (FAIL == net)
		zabbix_log(LOG_LEVEL_DEBUG, "TCP expect network error: %s", zbx_tcp_strerror());

	if (FAIL == val)
		zabbix_log(LOG_LEVEL_DEBUG, "TCP expect content error: expected [%s] received [%s]", expect, buf);

	return SYSINFO_RET_OK;
}

int	NET_TCP_PORT(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	unsigned short	port;
	int		value_int, ret;
	char		ip[64], port_str[8];

	if (num_param(param) > 2)
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, ip, sizeof(ip)))
		*ip = '\0';

	if ('\0' == *ip)
		strscpy(ip, "127.0.0.1");

	if (0 != get_param(param, 2, port_str, sizeof(port_str)))
		*port_str = '\0';

	if (SUCCEED != is_ushort(port_str, &port))
		return SYSINFO_RET_FAIL;

	if (SYSINFO_RET_OK == (ret = tcp_expect(ip, port, CONFIG_TIMEOUT, NULL, NULL, NULL, &value_int)))
	{
		SET_UI64_RESULT(result, value_int);
	}

	return ret;
}

#ifndef _WINDOWS
#ifdef HAVE_RES_QUERY

#ifndef C_IN
#	define C_IN	ns_c_in
#endif	/* C_IN */

#ifndef T_ANY
#	define T_ANY	ns_t_any
#endif
#ifndef T_A
#	define T_A	ns_t_a
#endif
#ifndef T_NS
#	define T_NS	ns_t_ns
#endif	/* T_NS */
#ifndef T_MD
#	define T_MD	ns_t_md
#endif	/* T_MD */
#ifndef T_MF
#	define T_MF	ns_t_mf
#endif	/* T_MF */
#ifndef T_CNAME
#	define T_CNAME	ns_t_cname
#endif	/* T_CNAME */
#ifndef T_SOA
#	define T_SOA	ns_t_soa
#endif	/* T_SOA */
#ifndef T_MB
#	define T_MB	ns_t_mb
#endif	/* T_MB */
#ifndef T_MG
#	define T_MG	ns_t_mg
#endif	/* T_MG */
#ifndef T_MR
#	define T_MR	ns_t_mr
#endif	/* T_MR */
#ifndef T_NULL
#	define T_NULL	ns_t_null
#endif	/* T_NULL */
#ifndef T_WKS
#	define T_WKS	ns_t_wks
#endif	/* T_WKS */
#ifndef T_PTR
#	define T_PTR	ns_t_ptr
#endif	/* T_PTR */
#ifndef T_HINFO
#	define T_HINFO	ns_t_hinfo
#endif	/* T_HINFO */
#ifndef T_MINFO
#	define T_MINFO	ns_t_minfo
#endif	/* T_MINFO */
#ifndef T_MX
#	define T_MX	ns_t_mx
#endif	/* T_MX */
#ifndef T_TXT
#	define T_TXT	ns_t_txt
#endif	/* T_TXT */

static char	*decode_type(int q_type)
{
	static char	buf[16];

	switch (q_type)
	{
		case T_A:	return "A";	/* "address"; */
		case T_NS:	return "NS";	/* "name server"; */
		case T_MD:	return "MD";	/* "mail forwarder"; */
		case T_MF:	return "MF";	/* "mail forwarder"; */
		case T_CNAME:	return "CNAME";	/* "canonical name"; */
		case T_SOA:	return "SOA";	/* "start of authority"; */
		case T_MB:	return "MB";	/* "mailbox"; */
		case T_MG:	return "MG";	/* "mail group member"; */
		case T_MR:	return "MR";	/* "mail rename"; */
		case T_NULL:	return "NULL";	/* "null"; */
		case T_WKS:	return "WKS";	/* "well-known service"; */
		case T_PTR:	return "PTR";	/* "domain name pointer"; */
		case T_HINFO:	return "HINFO";	/* "host information"; */
		case T_MINFO:	return "MINFO";	/* "mailbox information"; */
		case T_MX:	return "MX";	/* "mail exchanger"; */
		case T_TXT:	return "TXT";	/* "text"; */
		default:
			zbx_snprintf(buf, sizeof(buf), "T_%d", q_type);
			return buf;
	}
}

static char	*get_name(unsigned char *msg, unsigned char *msg_end, unsigned char **msg_ptr)
{
	int		res;
	static char	buffer[MAX_STRING_LEN];

	if ((res = dn_expand(msg, msg_end, *msg_ptr, buffer, sizeof(buffer))) < 0)
		return NULL;

	*msg_ptr += res;

	return buffer;
}

#endif /* HAVE_RES_QUERY */
#endif /* not _WINDOWS */

int	NET_DNS(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	dns_query(cmd, param, flags, result, 1);
}
int	NET_DNS_RECORD(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	dns_query(cmd, param, flags, result, 0);
}

int	dns_query(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result, int shortAnswer)
{
#if defined(HAVE_RES_QUERY) || defined(_WINDOWS)

	int		res, retrans, retry;
	char		ip[MAX_STRING_LEN];
	char		zone[MAX_STRING_LEN];
	char		retransStr[MAX_STRING_LEN];
	char		retryStr[MAX_STRING_LEN];

#ifdef _WINDOWS
	WSADATA 	wsaData;
	PDNS_RECORD	pDnsRecord;
	PIP4_ARRAY	pSrvList = NULL;
#else /* not _WINDOWS */
	typedef struct resolv_querytype_s {
		char	*name;
		int	type;
	} resolv_querytype_t;

	static resolv_querytype_t qt[] = {
		{"ANY", T_ANY},
		{"A", T_A},
		{"NS", T_NS},
		{"MD", T_MD},
		{"MF", T_MF},
		{"CNAME", T_CNAME},
		{"SOA", T_SOA},
		{"MB", T_MB},
		{"MG", T_MG},
		{"MR", T_MR},
		{"NULL", T_NULL},
		{"WKS", T_WKS},
		{"PTR", T_PTR},
		{"HINFO", T_HINFO},
		{"MINFO", T_MINFO},
		{"MX", T_MX},
		{"TXT", T_TXT},
		{NULL}
	};

	typedef union {
		HEADER		h;
#if defined(NS_PACKETSZ)
		unsigned char	buffer[NS_PACKETSZ];
#elif defined(PACKETSZ)
		unsigned char	buffer[PACKETSZ];
#else
		unsigned char	buffer[512];
#endif
	} answer_t;

	char		tmp[MAX_STRING_LEN], *name,
			buffer[MAX_STRING_LEN];
	unsigned char	*msg_end, *msg_ptr, *p;
	int		num_answers, num_query, q_type, q_class, q_ttl, q_len,
			value, offset, c, i, type, n;
	answer_t	answer;
	struct in_addr	inaddr, dns_serv;
	struct protoent	*pr;
	struct servent	*s;
	HEADER 		*hp;

#if PACKETSZ > 1024
	char 		buf[PACKETSZ];
#else
	char 		buf[1024];
#endif

#endif /* ifdef _WINDOWS */


	if (num_param(param) > 5)
		return SYSINFO_RET_FAIL;

	if(0 != get_param(param, 1, ip, MAX_STRING_LEN))
		ip[0] = '\0';

	if(0 != get_param(param, 2, zone, MAX_STRING_LEN) || '\0' == zone[0])
		strscpy(zone, "localhost");

	if (get_param(param, 3, tmp, sizeof(tmp)) != 0 || *tmp == ' ')
		type = T_SOA;
	else
	{
		for (i = 0; qt[i].name != NULL; i++)
		{
			if (0 == strcasecmp(qt[i].name, tmp))
			{
				type = qt[i].type;
				break;
			}
		}

		if (qt[i].name == NULL)
			return SYSINFO_RET_FAIL;
	}

	if(0 != get_param(param, 4, retransStr, MAX_STRING_LEN) || '\0' == retransStr[0])
		retrans = 1;
	else
		retrans = atoi(retransStr);

	if(0 != get_param(param, 5, retryStr, MAX_STRING_LEN) || '\0' == retryStr[0])
		retry = 2;
	else
		retry = atoi(retryStr);

	if('\0' != ip[0])
	{
		if(1 != inet_aton(ip, &dns_serv))
		{
			SET_UI64_RESULT(result,0);
			return SYSINFO_RET_FAIL;
		}
#ifdef _WINDOWS
		pSrvList = (PIP4_ARRAY) LocalAlloc(LPTR,sizeof(IP4_ARRAY));
		if(!pSrvList)
			return SYSINFO_RET_FAIL;
		pSrvList->AddrCount = 1;
		pSrvList->AddrArray[0] = inet_addr(dns_serv);
#else
		_res.nsaddr_list[0].sin_addr = dns_serv;
		_res.nsaddr_list[0].sin_family = AF_INET;
		_res.nsaddr_list[0].sin_port = htons(NS_DEFAULTPORT);
		_res.nscount = 1;
#endif
	}

#ifdef _WINDOWS
//	if (!(res = WSAStartup(MAKEWORD(2, 2), &wsaData)))
//		return SYSINFO_RET_FAIL;
	if (1 == shortAnswer)
		SET_UI64_RESULT(result, DnsQuery(zone, DNS_TYPE_A, DNS_QUERY_BYPASS_CACHE, pSrvList, &pDnsRecord, NULL) ? 0 : 1);
	else
		return SYSINFO_RET_FAIL;
//		SET_UI64_RESULT(result, NULL == gethostbyname(zone) ? 0 : 1);
//		SET_TEXT_RESULT(result, strdup(buffer));

//    status = DnsQuery(zone, DNS_TYPE_A, DNS_QUERY_BYPASS_CACHE, pSrvList, &pDnsRecord, NULL);


#else

	if (!(_res.options & RES_INIT))
		res_init();

	res = res_mkquery(QUERY, zone, C_IN, type, NULL, 0, NULL, buf, sizeof(buf));
	if (res <= 0)
		return SYSINFO_RET_FAIL;


	_res.retrans = retrans;
	_res.retry = retry;
	res = res_send(buf, res, answer.buffer, sizeof(answer.buffer));

	hp = (HEADER *) answer.buffer;
	if (1 == shortAnswer)
	{
		SET_UI64_RESULT(result, (NOERROR != hp->rcode || 0 == ntohs(hp->ancount) || -1 == res) ? 0 : 1);
		return SYSINFO_RET_OK;
	}
	if (NOERROR != hp->rcode || 0 == ntohs(hp->ancount) || -1 == res)
		return SYSINFO_RET_FAIL;

	*buffer = '\0';
	offset = 0;

	msg_end = answer.buffer + res;

	num_answers = ntohs(answer.h.ancount);
	num_query = ntohs(answer.h.qdcount);

	msg_ptr = answer.buffer + HFIXEDSZ;

	/* skipping query records*/
	for (; num_query > 0 && msg_ptr < msg_end; num_query--)
		msg_ptr += dn_skipname(msg_ptr, msg_end) + QFIXEDSZ;

	for (; num_answers > 0 && msg_ptr < msg_end; num_answers--)
	{
		if (NULL == (name = get_name(answer.buffer, msg_end, &msg_ptr)))
			return SYSINFO_RET_FAIL;

		offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, "%-20s", name);

		GETSHORT(q_type, msg_ptr);
		GETSHORT(q_class, msg_ptr);
		GETLONG(q_ttl, msg_ptr);
		GETSHORT(q_len, msg_ptr);

		switch (q_type)
		{
			case T_A:
				switch (q_class)
				{
					case C_IN:
					case C_HS:
						bcopy(msg_ptr, &inaddr, INADDRSZ);
						offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %-8s %s", decode_type(q_type), inet_ntoa(inaddr));
						break;
					default:
						;
				}
				msg_ptr += q_len;
				break;
			case T_NS:
			case T_CNAME:
			case T_MB:
			case T_MG:
			case T_MR:
			case T_PTR:
				if (NULL == (name = get_name(answer.buffer, msg_end, &msg_ptr)))
					return SYSINFO_RET_FAIL;
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %-8s %s", decode_type(q_type), name);
				break;
			case T_MD:
			case T_MF:
			case T_MX:
				GETSHORT(value, msg_ptr);
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %-8s %d", decode_type(q_type), value);

				if (NULL == (name = get_name(answer.buffer, msg_end, &msg_ptr)))
					return SYSINFO_RET_FAIL;
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %s", name);
				break;
			case T_SOA:
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %-8s", decode_type(q_type));

				if (NULL == (name = get_name(answer.buffer, msg_end, &msg_ptr)))
					return SYSINFO_RET_FAIL;
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %s", name);

				if (NULL == (name = get_name(answer.buffer, msg_end, &msg_ptr)))
					return SYSINFO_RET_FAIL;
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %s", name);

				GETLONG(value, msg_ptr);
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %d", value);

				GETLONG(value, msg_ptr);
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %d", value);

				GETLONG(value, msg_ptr);
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %d", value);

				GETLONG(value, msg_ptr);
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %d", value);

				GETLONG(value, msg_ptr);
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %d", value);
				break;
			case T_NULL:
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %-8s len:%d", decode_type(q_type), q_len);
				msg_ptr += q_len;
				break;
			case T_WKS:
				if (q_len < INT32SZ + 1)
					return SYSINFO_RET_FAIL;

				p = msg_ptr + q_len;

				bcopy(msg_ptr, &inaddr, INADDRSZ);
				msg_ptr += INT32SZ;

				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %-8s %s", decode_type(q_type), inet_ntoa(inaddr));

				if (NULL != (pr = getprotobynumber(*msg_ptr)))
					offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %s", pr->p_name);
				else
					offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %d", (int)*msg_ptr);

				msg_ptr++;
				n = 0;

				while (msg_ptr < p)
				{
					c = *msg_ptr++;
					do {
						if (c & 0200)
						{
							s = getservbyport((int)htons(n), pr ? pr->p_name : NULL);
							if (s != NULL)
								offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %s", s->s_name);
							else
								offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " #%d", n);
						}
						c <<= 1;
					} while (++n & 07);
				}
				break;
			case T_HINFO:
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %-8s", decode_type(q_type));

				p = msg_ptr + q_len;
				c = *msg_ptr++;

				if (c != 0)
				{
					offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %.*s", c, msg_ptr);
					msg_ptr += c;
				}

				if (msg_ptr < p) {
					c = *msg_ptr++;

					if (c != 0)
					{
						offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %.*s", c, msg_ptr);
						msg_ptr += c;
					}
				}
				break;
			case T_MINFO:
				if (NULL == (name = get_name(answer.buffer, msg_end, &msg_ptr)))
					return SYSINFO_RET_FAIL;
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %-8s %s", decode_type(q_type), name);

				if (NULL == (name = get_name(answer.buffer, msg_end, &msg_ptr)))
					return SYSINFO_RET_FAIL;
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %s", name);
				break;
			case T_TXT:
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, " %-8s \"", decode_type(q_type));

				p = msg_ptr + q_len;
				while (msg_ptr < p)
				{
					for (c = *msg_ptr++; c > 0 && msg_ptr < p; c--)
						offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, "%c", *msg_ptr++);
				}
				offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, "\"");
				break;
			default:
				msg_ptr += q_len;
		}
		offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, "\n");
	}
	if (offset != 0)
		buffer[--offset] = '\0';

	/*zabbix_log(LOG_LEVEL_CRIT, "== [%zd] %s", strlen(buffer), buffer);*/

	SET_TEXT_RESULT(result, strdup(buffer));

#endif
	return SYSINFO_RET_OK;
#else /* Both HAVE_RES_QUERY and _WINDOWS not defined */
	return SYSINFO_RET_FAIL;
#endif /* defined(HAVE_RES_QUERY) || defined(_WINDOWS) */
}
