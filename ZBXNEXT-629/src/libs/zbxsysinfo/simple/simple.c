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

#include "../common/net.h"
#include "ntp.h"

#include "simple.h"

ZBX_METRIC	parameters_simple[] =
	/* KEY                   FLAG            FUNCTION             ADD_PARAM  TEST_PARAM      */
	{
	{"net.tcp.service",	CF_USEUPARAM,	CHECK_SERVICE, 		0,	"ssh,127.0.0.1,22"},
	{"net.tcp.service.perf",CF_USEUPARAM,	CHECK_SERVICE_PERF, 	0,	"ssh,127.0.0.1,22"},
	{0}
	};

#ifdef HAVE_LDAP

static int    check_ldap(const char *host, unsigned short port, int timeout, int *value_int)
{
	LDAP		*ldap	= NULL;
	LDAPMessage	*res	= NULL;
	LDAPMessage	*msg	= NULL;
	BerElement	*ber	= NULL;

	char	*attrs[2] = { "namingContexts", NULL };
	char	*attr	 = NULL;
	char	**valRes = NULL;
	int	ldapErr = 0;

	*value_int = 0;

	alarm(timeout);

	if (NULL == (ldap = ldap_init(host, port)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "LDAP - initialization failed [%s:%hu]", host, port);
		goto lbl_ret;
	}

	if (LDAP_SUCCESS != (ldapErr = ldap_search_s(ldap, "", LDAP_SCOPE_BASE, "(objectClass=*)", attrs, 0, &res)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "LDAP - searching failed [%s] [%s]", host, ldap_err2string(ldapErr));
		goto lbl_ret;
	}

	if (NULL == (msg = ldap_first_entry(ldap, res)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "LDAP - empty sort result. [%s] [%s]", host, ldap_err2string(ldapErr));
		goto lbl_ret;
	}

	attr = ldap_first_attribute(ldap, msg, &ber);
	valRes = ldap_get_values(ldap, msg, attr);

	*value_int = 1;

lbl_ret:
	alarm(0);

	if (NULL != valRes)
		ldap_value_free(valRes);
	if (NULL != attr)
		ldap_memfree(attr);
	if (NULL != ber)
		ber_free(ber, 0);
	if (NULL != res)
		ldap_msgfree(res);
	if (NULL != ldap)
		ldap_unbind(ldap);

	return SYSINFO_RET_OK;
}

#endif	/* HAVE_LDAP */

static int	check_ssh(const char *host, unsigned short port, int timeout, int *value_int)
{
	int		ret;
	zbx_sock_t	s;
	char		send_buf[MAX_BUFFER_LEN];
	char		*recv_buf;
	char		*ssh_server, *ssh_proto;

	*value_int = 0;

	if (SUCCEED == (ret = zbx_tcp_connect(&s, CONFIG_SOURCE_IP, host, port, timeout)))
	{
		if (SUCCEED == (ret = zbx_tcp_recv(&s, &recv_buf)))
		{
			if (0 == strncmp(recv_buf, "SSH", 3))
			{
				ssh_server = ssh_proto = recv_buf + 4;
				ssh_server += strspn(ssh_proto, "0123456789-. ");
				ssh_server[-1] = '\0';

				zbx_snprintf(send_buf, sizeof(send_buf), "SSH-%s-%s\n", ssh_proto, "zabbix_agent");
				*value_int = 1;
			}
			else
				zbx_snprintf(send_buf, sizeof(send_buf), "0\n");

			ret = zbx_tcp_send_raw(&s, send_buf);
		}

		zbx_tcp_close(&s);
	}

	if (FAIL == ret)
		zabbix_log(LOG_LEVEL_DEBUG, "SSH check error: %s", zbx_tcp_strerror());

	return SYSINFO_RET_OK;
}

static int	check_telnet(const char *host, unsigned short port, int timeout, int *value_int)
{
	zbx_sock_t	s;
	char		buf[MAX_BUFFER_LEN];
	size_t		sz, offset;
	int		rc, ret = FAIL, flags;

	*value_int = 0;

	if (SUCCEED == zbx_tcp_connect(&s, CONFIG_SOURCE_IP, host, port, timeout))
	{
		flags = fcntl(s.socket, F_GETFL);
		if (0 == (flags & O_NONBLOCK))
			fcntl(s.socket, F_SETFL, flags | O_NONBLOCK);

		if (SUCCEED == telnet_test_login(s.socket))
			*value_int = 1;
		else
			zabbix_log(LOG_LEVEL_DEBUG,"Telnet check error: no login prompt");

		zbx_tcp_close(&s);
	}
	else
		zabbix_log(LOG_LEVEL_DEBUG, "Telnet check error: %s", zbx_tcp_strerror());

	return SYSINFO_RET_OK;
}

static int	check_service(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result, int perf)
{
	unsigned short	port = 0;
	char		service[16], ip[64], str_port[8];
	int		value_int = 0, ret = SYSINFO_RET_FAIL;
	double		check_time;

	assert(result);

	init_result(result);

	check_time = zbx_time();

	if (num_param(param) > 3)
		return ret;

	if (0 != get_param(param, 1, service, sizeof(service)))
		return ret;

	if (0 != get_param(param, 2, ip, sizeof(ip)) || '\0' == *ip)
		strscpy(ip, "127.0.0.1");

	if (0 != get_param(param, 3, str_port, sizeof(str_port)))
		*str_port = '\0';

	if ('\0' != *str_port && FAIL == is_ushort(str_port, &port))
		return ret;

	if (0 == strcmp(service, "ssh"))
	{
		if ('\0' == *str_port)
			port = ZBX_DEFAULT_SSH_PORT;
		ret = check_ssh(ip, port, CONFIG_TIMEOUT, &value_int);
	}
	else if (0 == strcmp(service, "ntp") || 0 == strcmp(service, "service.ntp" /* obsolete */))
	{
		if ('\0' == *str_port)
			port = ZBX_DEFAULT_NTP_PORT;
		ret = check_ntp(ip, port, CONFIG_TIMEOUT, &value_int);
	}
#ifdef HAVE_LDAP
	else if (0 == strcmp(service, "ldap"))
	{
		if ('\0' == *str_port)
			port = ZBX_DEFAULT_LDAP_PORT;
		ret = check_ldap(ip, port, CONFIG_TIMEOUT, &value_int);
	}
#endif	/* HAVE_LDAP */
	else if (0 == strcmp(service, "smtp"))
	{
		if ('\0' == *str_port)
			port = ZBX_DEFAULT_SMTP_PORT;
		ret = tcp_expect(ip, port, CONFIG_TIMEOUT, NULL, "220", "QUIT\n", &value_int);
	}
	else if (0 == strcmp(service, "ftp"))
	{
		if ('\0' == *str_port)
			port = ZBX_DEFAULT_FTP_PORT;
		ret = tcp_expect(ip, port, CONFIG_TIMEOUT, NULL, "220", "QUIT\n", &value_int);
	}
	else if (0 == strcmp(service, "http"))
	{
		if ('\0' == *str_port)
			port = ZBX_DEFAULT_HTTP_PORT;
		ret = tcp_expect(ip, port, CONFIG_TIMEOUT, NULL, NULL, NULL, &value_int);
	}
	else if (0 == strcmp(service, "pop"))
	{
		if ('\0' == *str_port)
			port = ZBX_DEFAULT_POP_PORT;
		ret = tcp_expect(ip, port, CONFIG_TIMEOUT, NULL, "+OK", "QUIT\n", &value_int);
	}
	else if (0 == strcmp(service, "nntp"))
	{
		if ('\0' == *str_port)
			port = ZBX_DEFAULT_NNTP_PORT;
		ret = tcp_expect(ip, port, CONFIG_TIMEOUT, NULL, "200", "QUIT\n", &value_int);
	}
	else if (0 == strcmp(service, "imap"))
	{
		if ('\0' == *str_port)
			port = ZBX_DEFAULT_IMAP_PORT;
		ret = tcp_expect(ip, port, CONFIG_TIMEOUT, NULL, "* OK", "a1 LOGOUT\n", &value_int);
	}
	else if (0 == strcmp(service, "tcp"))
	{
		if ('\0' == *str_port)
			return ret;
		ret = tcp_expect(ip, port, CONFIG_TIMEOUT, NULL, NULL, NULL, &value_int);
	}
	else if (0 == strcmp(service, "telnet"))
	{
		if ('\0' == *str_port)
			port = ZBX_DEFAULT_TELNET_PORT;
		ret = check_telnet(ip, port, CONFIG_TIMEOUT, &value_int);
	}
	else
		return SYSINFO_RET_FAIL;

	if (SYSINFO_RET_OK == ret)
	{
		if (0 != perf)
		{
			if (0 != value_int)
			{
				check_time = zbx_time() - check_time;
				check_time = MAX(check_time, 0.0001);
				SET_DBL_RESULT(result, check_time);
			}
			else
				SET_DBL_RESULT(result, 0.0);
		}
		else
			SET_UI64_RESULT(result, value_int);
	}

	return ret;
}

/* Examples:
 *
 *   net.tcp.service[ssh]
 *   net.tcp.service[smtp,127.0.0.1]
 *   net.tcp.service[ssh,127.0.0.1,22]
 *
 *   net.tcp.service.perf[ssh]
 *   net.tcp.service.perf[smtp,127.0.0.1]
 *   net.tcp.service.perf[ssh,127.0.0.1,22]
 *
 * The old name for these checks is check_service[*].
 */

int	CHECK_SERVICE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	return check_service(cmd, param, flags, result, 0);
}

int	CHECK_SERVICE_PERF(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	return check_service(cmd, param, flags, result, 1);
}
