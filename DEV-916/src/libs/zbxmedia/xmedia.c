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
					" order by type desc",
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
 * Function: zbx_remedy_query_events                                          *
 *                                                                            *
 * Purpose: retrieves status of Remedy incidents associated to the specified  *
 *          events                                                            *
 *                                                                            *
 * Parameters: userid     - [IN] the user querying events                     *
 *             eventids   - [IN] the events to query                          *
 *             tickets    - [OUT] the incident data                           *
 *             error      - [OUT] the error description                       *
 *                                                                            *
 * Return value: SUCCEED - the operation was completed successfully.          *
 *                         Per event query status can be determined by        *
 *                         inspecting ticketids contents.                     *
 *               FAIL - otherwise                                             *
 *                                                                            *
 * Comments: The caller must free the error description if it was set and     *
 *           tickets vector contents.                                         *
 *                                                                            *
 ******************************************************************************/
int	zbx_xmedia_query_events(zbx_uint64_t userid, zbx_vector_uint64_t *eventids, zbx_vector_ptr_t *tickets,
		char **error)
{
	const char		*__function_name = "zbx_xmedia_query_events";

	int			ret = FAIL;
	DB_MEDIATYPE		mediatype = {0};
	zbx_media_t		media = {0};

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (SUCCEED != mediatype_get(userid, &mediatype, &media))
	{
		*error = zbx_dsprintf(*error, "Failed to find apropriate media type");
		goto out;
	}

	switch (mediatype.type)
	{
		case MEDIA_TYPE_REMEDY:
			zbx_remedy_query_events(&mediatype, eventids, tickets, error);
			break;
		case MEDIA_TYPE_SERVICENOW:
			/* TODO: servicenow impl */
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
	}

	ret = SUCCEED;

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
 * Purpose: acknowledges events in Remedy service with specified message      *
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
	const char	*__function_name = "zbx_remedy_acknowledge_events";

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
			zbx_remedy_acknowledge_events(&mediatype, &media, userid, acknowledges, tickets, error);
			break;
		case MEDIA_TYPE_SERVICENOW:
			/* TODO: servicenow impl */
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;

	}

	ret = SUCCEED;

	mediatype_clear(&mediatype);
	media_clear(&media);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}
