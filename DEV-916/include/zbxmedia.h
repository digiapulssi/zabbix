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

#ifndef ZABBIX_ZBXMEDIA_H
#define ZABBIX_ZBXMEDIA_H

#include "sysinc.h" /* using "config.h" would be better, but it causes warnings when compiled with Net-SNMP */
#include "zbxalgo.h"
#include "db.h"

extern char	*CONFIG_SOURCE_IP;

struct DB_ALERT;
struct DB_MEDIATYPE;

int	send_email(const char *smtp_server, unsigned short smtp_port, const char *smtp_helo,
		const char *smtp_email, const char *mailto, const char *mailsubject, const char *mailbody,
		unsigned char smtp_security, unsigned char smtp_verify_peer, unsigned char smtp_verify_host,
		unsigned char smtp_authentication, const char *username, const char *password, int timeout,
		char *error, size_t max_error_len);
int	send_ez_texting(const char *username, const char *password, const char *sendto,
		const char *message, const char *limit, char *error, int max_error_len);
#ifdef HAVE_JABBER
int	send_jabber(const char *username, const char *password, const char *sendto,
		const char *subject, const char *message, char *error, int max_error_len);
#endif
int	send_sms(const char *device, const char *number, const char *message, char *error, int max_error_len);

typedef struct
{
	/* the source event id */
	zbx_uint64_t	eventid;
	/* the associated ticketid (Remedy incident number) */
	char		*ticketid;
	/* the ticket status */
	char		*status;
	/* contains error message or NULL otherwise */
	char		*error;
	/* the assignee */
	char		*assignee;
	/* the ticket URL in the external service */
	char		*url;
	/* the allowed or performed action - create/reopen/update */
	int		action;
	/* the ticket creation time, set only for zbx_remedy_query_events() request */
	int		clock;

}
zbx_ticket_t;

typedef struct
{
	/* the event id */
	zbx_uint64_t	eventid;
	/* the acknowledgment message subject */
	char		*subject;
	/* the acknowledgment message contents */
	char		*message;
}
zbx_acknowledge_t;

typedef struct
{
	char	*sendto;
}
zbx_media_t;

void	zbx_free_ticket(zbx_ticket_t *ticket);
void	zbx_free_acknowledge(zbx_acknowledge_t *ticket);
void	zbx_media_clear(zbx_media_t *media);

int	zbx_remedy_process_alert(zbx_uint64_t eventid, zbx_uint64_t userid, const char *sendto, const char *subject,
		const char *message, const struct DB_MEDIATYPE *mediatype, char **error);
void	zbx_remedy_query_events(const DB_MEDIATYPE *mediatype, zbx_vector_uint64_t *eventids, zbx_vector_ptr_t *tickets);
void	zbx_remedy_acknowledge_events(const DB_MEDIATYPE *mediatype, const zbx_media_t *media, zbx_uint64_t userid,
		zbx_vector_ptr_t *acknowledges, zbx_vector_ptr_t *tickets);

int	zbx_servicenow_process_alert(zbx_uint64_t eventid, zbx_uint64_t userid, const char *subject,
		const char *message, const struct DB_MEDIATYPE *mediatype, char **error);
void	zbx_servicenow_query_events(const DB_MEDIATYPE *mediatype, zbx_vector_uint64_t *eventids,
		zbx_vector_ptr_t *tickets);
void	zbx_servicenow_acknowledge_events(const DB_MEDIATYPE *mediatype, zbx_uint64_t userid,
		zbx_vector_ptr_t *acknowledges, zbx_vector_ptr_t *tickets);


/* defines current state of event processing - automated (alerts) or manual (frontend) */
#define ZBX_XMEDIA_PROCESS_MANUAL	0
#define ZBX_XMEDIA_PROCESS_AUTOMATED	1

/* incident action invoked when processing event */
#define ZBX_XMEDIA_ACTION_NONE		0
#define ZBX_XMEDIA_ACTION_CREATE	1
#define ZBX_XMEDIA_ACTION_REOPEN	2
#define ZBX_XMEDIA_ACTION_UPDATE	3
#define ZBX_XMEDIA_ACTION_RESOLVE	4

int	zbx_xmedia_query_events(zbx_uint64_t userid, zbx_vector_uint64_t *eventids, zbx_vector_ptr_t *tickets,
		char **error);
int	zbx_xmedia_acknowledge_events(zbx_uint64_t userid, zbx_vector_ptr_t *acknowledges, zbx_vector_ptr_t *tickets,
		char **error);
char	*zbx_xmedia_get_incident_by_eventid(zbx_uint64_t eventid, zbx_uint64_t mediatypeid);
char	*zbx_xmedia_get_incident_by_triggerid(zbx_uint64_t triggerid, zbx_uint64_t mediatypeid);
void	zbx_xmedia_register_incident(const char *incident, zbx_uint64_t eventid, zbx_uint64_t triggerid,
		zbx_uint64_t mediatypeid, int action);
int	zbx_xmedia_acknowledge_event(zbx_uint64_t eventid, zbx_uint64_t userid, const char *ticketnumber,
		int status);
int	zbx_xmedia_get_ticket_creation_time(const char *externalid);
int	zbx_xmedia_get_last_ticketid(zbx_uint64_t eventid, zbx_uint64_t mediatypeid, char **externalid);
int	zbx_get_trigger_severity_name(unsigned char severity, char **name);

#endif
