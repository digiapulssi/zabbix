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

#include "common.h"

#include "db.h"
#include "log.h"
#include "zbxmedia.h"
#include "zbxserver.h"

/******************************************************************************
 *                                                                            *
 * Function: mediatype_clear                                                  *
 *                                                                            *
 * Purpose: releases resource allocated to store mediatype properties         *
 *                                                                            *
 * Parameters: media   - [IN] the mediatype data                              *
 *                                                                            *
 ******************************************************************************/
static void	mediatype_clear(DB_MEDIATYPE *media)
{
	zbx_free(media->description);
	zbx_free(media->exec_path);
	zbx_free(media->gsm_modem);
	zbx_free(media->smtp_server);
	zbx_free(media->smtp_helo);
	zbx_free(media->smtp_email);
	zbx_free(media->username);
	zbx_free(media->passwd);
}

/******************************************************************************
 *                                                                            *
 * Function: media_clear                                                      *
 *                                                                            *
 * Purpose: clears user media data                                            *
 *                                                                            *
 * Parameters: media - [IN] the media data to clear                           *
 *                                                                            *
 ******************************************************************************/
void	media_clear(zbx_media_t *media)
{
	zbx_free(media->sendto);
}

/******************************************************************************
 *                                                                            *
 * Function: mediatype_get                                                    *
 *                                                                            *
 * Purpose: reads the first active external (servicenow/remedy) media type    *
 *          conifigured for the user, servicenow having higher priority       *
 *                                                                            *
 * Parameters: userid    - [IN] the user id                                   *
 *             mediatype - [OUT] the mediatype data                           *
 *             media     - [OUT] the user media data                          *
 *                                                                            *
 * Return value: SUCCEED - the media type was read successfully               *
 *               FAIL - otherwise                                             *
 *                                                                            *
 * Comments: This function allocates memory to store mediatype properties     *
 *           which must be freed later with mediatype_clear() function.       *
 *                                                                            *
 ******************************************************************************/
static int	mediatype_get(zbx_uint64_t userid, DB_MEDIATYPE *mediatype, zbx_media_t *media)
{
	const char	*__function_name = "mediatype_get";
	DB_RESULT	result;
	DB_ROW		row;
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	result = DBselect("select mt.smtp_server,mt.smtp_helo,mt.smtp_email,mt.username,mt.passwd,mt.mediatypeid,"
				"mt.exec_path,mt.type,m.sendto"
				" from media_type mt,media m"
				" where type in (%d,%d)"
					" and status=%d"
					" and mt.mediatypeid=m.mediatypeid"
					" and m.userid=" ZBX_FS_UI64
					" and m.active=%d"
					" order by type desc,mediatypeid asc",
				MEDIA_TYPE_REMEDY, MEDIA_TYPE_SERVICENOW, MEDIA_TYPE_STATUS_ACTIVE, userid,
				MEDIA_STATUS_ACTIVE);

	if (NULL != (row = DBfetch(result)))
	{
		mediatype->description = NULL;
		mediatype->gsm_modem = NULL;
		mediatype->smtp_server = zbx_strdup(mediatype->smtp_server, row[0]);
		mediatype->smtp_helo = zbx_strdup(mediatype->smtp_helo, row[1]);
		mediatype->smtp_email = zbx_strdup(mediatype->smtp_email, row[2]);
		mediatype->username = zbx_strdup(mediatype->username, row[3]);
		mediatype->passwd = zbx_strdup(mediatype->passwd, row[4]);
		ZBX_STR2UINT64(mediatype->mediatypeid, row[5]);
		mediatype->exec_path = zbx_strdup(mediatype->exec_path, row[6]);
		mediatype->type = atoi(row[7]);
		media->sendto = zbx_strdup(media->sendto, row[8]);

		ret = SUCCEED;
	}

	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/*
 * Public API
 */

/******************************************************************************
 *                                                                            *
 * Function: zbx_free_ticket                                                  *
 *                                                                            *
 * Purpose: frees the ticket data                                             *
 *                                                                            *
 * Parameters: ticket   - [IN] the ticket to free                             *
 *                                                                            *
 ******************************************************************************/
void	zbx_free_ticket(zbx_ticket_t *ticket)
{
	zbx_free(ticket->ticketid);
	zbx_free(ticket->status);
	zbx_free(ticket->error);
	zbx_free(ticket->assignee);
	zbx_free(ticket->url);
	zbx_free(ticket);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_free_acknowledge                                             *
 *                                                                            *
 * Purpose: frees the acknowledgment data                                     *
 *                                                                            *
 * Parameters: ack   - [IN] the acknowledgment to free                        *
 *                                                                            *
 ******************************************************************************/
void	zbx_free_acknowledge(zbx_acknowledge_t *ack)
{
	zbx_free(ack->subject);
	zbx_free(ack->message);
	zbx_free(ack);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_xmedia_query_events                                          *
 *                                                                            *
 * Purpose: retrieves status of incidents associated to the specified events  *
 *                                                                            *
 * Parameters: userid     - [IN] the user querying events                     *
 *             eventids   - [IN] the events to query                          *
 *             tickets    - [OUT] the incident data                           *
 *             error      - [OUT] the error description                       *
 *                                                                            *
 * Return value: SUCCEED - the operation was completed successfully.          *
 *                         Per event query status can be determined by        *
 *                         inspecting tickets contents.                       *
 *               FAIL   - otherwise                                           *
 *                                                                            *
 * Comments: The caller must free the error description if it was set and     *
 *           tickets vector contents.                                         *
 *                                                                            *
 ******************************************************************************/
int	zbx_xmedia_query_events(zbx_uint64_t userid, zbx_vector_uint64_t *eventids, zbx_vector_ptr_t *tickets,
		char **error)
{
	const char		*__function_name = "zbx_xmedia_query_events";

	int			ret;
	DB_MEDIATYPE		mediatype = {0};
	zbx_media_t		media = {0};

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (SUCCEED != (ret = mediatype_get(userid, &mediatype, &media)))
	{
		*error = zbx_dsprintf(*error, "Failed to find appropriate media type");
		goto out;
	}

	switch (mediatype.type)
	{
		case MEDIA_TYPE_REMEDY:
			zbx_remedy_query_events(&mediatype, eventids, tickets);
			break;
		case MEDIA_TYPE_SERVICENOW:
			zbx_servicenow_query_events(&mediatype, eventids, tickets);
			break;
		default:
			*error = zbx_dsprintf(*error, "Unsupported external media type %d", mediatype.type);
			ret = FAIL;
	}

	mediatype_clear(&mediatype);
	media_clear(&media);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_xmedia_acknowledge_events                                    *
 *                                                                            *
 * Purpose: acknowledges events in external service with specified message    *
 *          subjects and contents                                             *
 *                                                                            *
 * Parameters: userid        - [IN] the user acknowledging events             *
 *             acknowledges  - [IN] the event acknowledgment data             *
 *             tickets       - [OUT] the incident data                        *
 *             error         - [OUT] the error description                    *
 *                                                                            *
 * Return value: SUCCEED - the events were acknowledged successfully          *
 *               FAIL - otherwise                                             *
 *                                                                            *
 * Comments: The caller must free the error description if it was set and     *
 *           tickets vector contents.                                         *
 *                                                                            *
 ******************************************************************************/
int	zbx_xmedia_acknowledge_events(zbx_uint64_t userid, zbx_vector_ptr_t *acknowledges, zbx_vector_ptr_t *tickets,
		char **error)
{
	const char	*__function_name = "zbx_xmedia_acknowledge_events";

	int		ret = FAIL;
	DB_MEDIATYPE	mediatype = {0};
	zbx_media_t	media = {0};

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (SUCCEED != mediatype_get(userid, &mediatype, &media))
	{
		*error = zbx_dsprintf(*error, "Failed to find apropriate media type");
		goto out;
	}

	switch (mediatype.type)
	{
		case MEDIA_TYPE_REMEDY:
			zbx_remedy_acknowledge_events(&mediatype, &media, userid, acknowledges, tickets);
			ret = SUCCEED;
			break;
		case MEDIA_TYPE_SERVICENOW:
			zbx_servicenow_acknowledge_events(&mediatype, userid, acknowledges, tickets);
			ret = SUCCEED;
			break;
		default:
			*error = zbx_dsprintf(*error, "Unsupported external media type %d", mediatype.type);
			ret = FAIL;

	}

	mediatype_clear(&mediatype);
	media_clear(&media);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_xmedia_get_incident_by_eventid                               *
 *                                                                            *
 * Purpose: gets id of the ticket directly linked to the specified event      *
 *                                                                            *
 * Parameters: eventid      - [IN] event id                                   *
 *             mediatypeid  - [IN] media type id                              *
 *                                                                            *
 * Return value: the ticket id or NULL if no ticket was directly linked to    *
 *               the specified event                                          *
 *                                                                            *
 * Comments: The returned ticked id must be freed later by the caller.        *
 *                                                                            *
 ******************************************************************************/
char	*zbx_xmedia_get_incident_by_eventid(zbx_uint64_t eventid, zbx_uint64_t mediatypeid)
{
	const char	*__function_name = "zbx_xmedia_get_incident_by_eventid";
	DB_RESULT	result;
	DB_ROW		row;
	char		*ticketid = NULL;

	/* first check if the event is linked to an incident */
	result = DBselect("select externalid from ticket"
			" where eventid=" ZBX_FS_UI64
				" and mediatypeid=" ZBX_FS_UI64
			" order by clock desc,ticketid desc", eventid, mediatypeid);

	if (NULL != (row = DBfetch(result)))
		ticketid = zbx_strdup(NULL, row[0]);

	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, (NULL != ticketid ? ticketid : "FAIL"));

	return ticketid;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_xmedia_get_incident_by_triggerid                             *
 *                                                                            *
 * Purpose: gets id of the last ticket linked to an event generated by the    *
 *          specified trigger                                                 *
 *                                                                            *
 * Parameters: triggerid      - [IN] trigger id                               *
 *             mediatypeid    - [IN] media type id                            *
 *                                                                            *
 * Return value: the ticket id or NULL if no ticket was found                 *
 *                                                                            *
 * Comments: The returned ticked id must be freed later by the caller.        *
 *                                                                            *
 ******************************************************************************/
char	*zbx_xmedia_get_incident_by_triggerid(zbx_uint64_t triggerid, zbx_uint64_t mediatypeid)
{
	const char	*__function_name = "zbx_xmedia_get_incident_by_triggerid";
	DB_RESULT	result;
	DB_ROW		row;
	char		*sql = NULL, *ticketid = NULL;
	size_t		sql_alloc = 0, sql_offset = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() " ZBX_FS_UI64, __function_name, triggerid);

	/* find the latest ticket id which was created for the specified trigger */
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select externalid,clock from ticket"
				" where triggerid=" ZBX_FS_UI64
					" and mediatypeid=" ZBX_FS_UI64
				" order by clock desc,ticketid desc",
				triggerid, mediatypeid);

	result = DBselectN(sql, 1);

	if (NULL != (row = DBfetch(result)))
		ticketid = zbx_strdup(NULL, row[0]);

	DBfree_result(result);

	zbx_free(sql);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, (NULL != ticketid ? ticketid : "FAIL"));

	return ticketid;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_xmedia_register_incident                                     *
 *                                                                            *
 * Purpose: registers external incident created in response to Zabbix event   *
 *                                                                            *
 * Parameters: ticketnumber   - [IN] the ticket number                        *
 *             eventid        - [IN] the linked event id                      *
 *             triggerid      - [IN] the event trigger id                     *
 *             action         - [IN] the performed action                     *
 *                                                                            *
 ******************************************************************************/
void	zbx_xmedia_register_incident(const char *incident, zbx_uint64_t eventid, zbx_uint64_t triggerid,
		zbx_uint64_t mediatypeid, int action)
{
	zbx_uint64_t	ticketid;
	char 		*ticketnumber_esc;
	int		is_new;

	ticketid = DBget_maxid_num("ticket", 1);
	ticketnumber_esc = DBdyn_escape_string(incident);
	is_new = (ZBX_XMEDIA_ACTION_CREATE == action ? 1 : 0);

	DBexecute("insert into ticket (ticketid,externalid,eventid,triggerid,mediatypeid,clock,new) values"
					" (" ZBX_FS_UI64 ",'%s'," ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64 ",%d,%d)",
					ticketid, ticketnumber_esc, eventid, triggerid, mediatypeid, time(NULL),
					is_new);
	zbx_free(ticketnumber_esc);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_xmedia_acknowledge_event                                     *
 *                                                                            *
 * Purpose: acknowledges event with appropriate message                       *
 *                                                                            *
 * Parameters: eventid   - [IN] the event to acknowledge                      *
 *             userid    - [IN] the user the alert is assigned to             *
 *             incident  - [IN] the number of corresponding incident          *
 *             action    - [IN] the performed action, see ZBX_XMEDIA_ACTION_? *
 *                                                                            *
 * Return value: SUCCEED - the event was acknowledged                         *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_xmedia_acknowledge_event(zbx_uint64_t eventid, zbx_uint64_t userid, const char *incident, int action)
{
	const char	*__function_name = "zbx_xmedia_acknowledge_event";

	int		ret = FAIL;
	char		*sql = NULL, *message, *message_esc;
	size_t		sql_offset = 0, sql_alloc = 0;
	zbx_uint64_t	ackid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	switch (action)
	{
		case ZBX_XMEDIA_ACTION_CREATE:
			message = zbx_dsprintf(NULL, "Created a new incident %s", incident);
			break;
		case ZBX_XMEDIA_ACTION_REOPEN:
			message = zbx_dsprintf(NULL, "Reopened resolved incident %s", incident);
			break;
		case ZBX_XMEDIA_ACTION_UPDATE:
			message = zbx_dsprintf(NULL, "Updated incident %s", incident);
			break;
		default:
			goto out;
	}

	ackid = DBget_maxid("acknowledges");
	message_esc = DBdyn_escape_string(message);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "insert into acknowledges"
			" (acknowledgeid,userid,eventid,clock,message) values"
			" (" ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64 ",%d,'%s');\n",
			ackid, userid, eventid, time(NULL), message_esc);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update events set acknowledged=1"
			" where eventid=" ZBX_FS_UI64, eventid);

	if (ZBX_DB_OK <= DBexecute("%s", sql))
		ret = SUCCEED;

	zbx_free(sql);
	zbx_free(message_esc);
	zbx_free(message);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_xmedia_get_ticket_creation_time                                  *
 *                                                                            *
 * Purpose: retrieves the creation time of the specified ticket               *
 *                                                                            *
 * Parameters: externalid    - [IN] the ticket external id                    *
 *                                                                            *
 * Return value: the ticket creation time in seconds or 0 if the ticket was   *
 *               not found                                                    *
 *                                                                            *
 ******************************************************************************/
int	zbx_xmedia_get_ticket_creation_time(const char *externalid)
{
	int		clock = 0;
	DB_RESULT	result;
	DB_ROW		row;
	char		*incident_number;

	incident_number = DBdyn_escape_string(externalid);

	/* read the incident creation time */
	result = DBselect("select clock from ticket where externalid='%s' and new=1",
			incident_number);

	zbx_free(incident_number);

	if (NULL != (row = DBfetch(result)))
		clock = atoi(row[0]);

	DBfree_result(result);

	return clock;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_xmedia_get_last_ticketid                                     *
 *                                                                            *
 * Purpose: retrieves either the ticket directly linked to the specified      *
 *          event or the last ticket created in response to the event         *
 *          source trigger                                                    *
 *                                                                            *
 * Parameters: eventid     - [IN] the event                                   *
 *             mediatypeid - [IN] media type id                               *
 *             incident    - [OUT] the linked incident number                 *
 *                                                                            *
 * Return value: SUCCEED - the incident was retrieved successfully            *
 *               FAIL - otherwise                                             *
 *                                                                            *
 * Comments: This function allocates memory to store incident number          *
 *           which must be freed later.                                       *
 *                                                                            *
 ******************************************************************************/
int	zbx_xmedia_get_last_ticketid(zbx_uint64_t eventid, zbx_uint64_t mediatypeid, char **incident)
{
	DB_RESULT	result;
	DB_ROW		row;

	if (NULL == (*incident = zbx_xmedia_get_incident_by_eventid(eventid, mediatypeid)))
	{
		zbx_uint64_t	triggerid;

		/* get the event source trigger id */
		result = DBselect("select objectid from events"
				" where source=%d"
					" and object=%d"
					" and eventid=" ZBX_FS_UI64,
					EVENT_SOURCE_TRIGGERS, EVENT_OBJECT_TRIGGER, eventid);

		if (NULL != (row = DBfetch(result)))
		{
			ZBX_STR2UINT64(triggerid, row[0]);

			*incident = zbx_xmedia_get_incident_by_triggerid(triggerid, mediatypeid);
		}

		DBfree_result(result);
	}

	return NULL != *incident ? SUCCEED : FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_get_trigger_severity_name                                    *
 *                                                                            *
 * Purpose: gets trigger severity name                                        *
 *                                                                            *
 * Parameters: severity - [IN] the trigger severity                           *
 *             name     - [OUT] the trigger severity name                     *
 *                                                                            *
 * Return value: SUCCEED - the trigger severity name was returned             *
 *               FAIL    - otherwise.                                         *
 *                                                                            *
 ******************************************************************************/
int	zbx_get_trigger_severity_name(unsigned char severity, char **name)
{
	zbx_config_t	cfg;

	if (TRIGGER_SEVERITY_COUNT <= severity)
		return FAIL;

	zbx_config_get(&cfg, ZBX_CONFIG_FLAGS_SEVERITY_NAME);
	*name = zbx_strdup(*name, cfg.severity_name[severity]);
	zbx_config_clean(&cfg);

	return SUCCEED;
}
