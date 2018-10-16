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

/* LIBXML2 is used */
#ifdef HAVE_LIBXML2
#	include <libxml/parser.h>
#	include <libxml/tree.h>
#	include <libxml/xpath.h>
#endif

#include "db.h"
#include "log.h"
#include "zbxmedia.h"
#include "zbxserver.h"

#include "../../zabbix_server/vmware/vmware.h"

#if defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL)

#define ZBX_XML_HEADER_CONTENTTYPE		"Content-Type:text/xml; charset=utf-8"
#define	ZBX_XML_HEADER_SOAPACTION_CREATE	"SOAPAction:urn:HPD_Incident_Interface_Create_Monitor_WS/" \
						"HelpDesk_Submit_Service"
#define	ZBX_XML_HEADER_SOAPACTION_QUERY		"SOAPAction:urn:HPD_IncidentInterface_WS/HelpDesk_Query_Service"
#define	ZBX_XML_HEADER_SOAPACTION_MODIFY	"SOAPAction:urn:HPD_IncidentInterface_WS/HelpDesk_Modify_Service"


#define ZBX_SOAP_URL		"&webService=HPD_IncidentInterface_WS"
#define ZBX_SOAP_URL_CREATE	"&webService=HPD_Incident_Interface_Create_Monitor_WS"

#define ZBX_SOAP_XML_HEADER		"<?xml version=\"1.0\" encoding=\"UTF-8\"?>"

#define ZBX_SOAP_ENVELOPE_CREATE_OPEN	"<soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\""\
					" xmlns:urn=\"urn:HPD_Incident_Interface_Create_Monitor_WS\">"
#define ZBX_SOAP_ENVELOPE_CREATE_CLOSE	"</soapenv:Envelope>"

#define ZBX_SOAP_ENVELOPE_OPEN	"<soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\""\
					" xmlns:urn=\"urn:HPD_IncidentInterface_WS\">"
#define ZBX_SOAP_ENVELOPE_CLOSE	"</soapenv:Envelope>"


#define ZBX_SOAP_HEADER		"<soapenv:Header>"\
					"<urn:AuthenticationInfo>"\
					"<urn:userName>%s</urn:userName>"\
					"<urn:password>%s</urn:password>"\
					"</urn:AuthenticationInfo>"\
				"</soapenv:Header>"

#define ZBX_SOAP_BODY_OPEN	"<soapenv:Body>"
#define ZBX_SOAP_BODY_CLOSE	"</soapenv:Body>"

#define ZBX_HELPDESK_QUERY_SERVICE_OPEN		"<urn:HelpDesk_Query_Service>"
#define ZBX_HELPDESK_QUERY_SERVICE_CLOSE	"</urn:HelpDesk_Query_Service>"

#define ZBX_HELPDESK_MODIFY_SERVICE_OPEN	"<urn:HelpDesk_Modify_Service>"
#define ZBX_HELPDESK_MODIFY_SERVICE_CLOSE	"</urn:HelpDesk_Modify_Service>"

#define ZBX_REMEDY_FIELD_INCIDENT_NUMBER	"Incident_Number"
#define ZBX_REMEDY_FIELD_STATUS			"Status"
#define ZBX_REMEDY_FIELD_ACTION			"Action"
#define ZBX_REMEDY_FIELD_ASSIGNEE		"Assignee"

#define ZBX_REMEDY_ERROR_INVALID_INCIDENT	"ERROR (302)"

#define ZBX_REMEDY_STATUS_NEW			"New"
#define ZBX_REMEDY_STATUS_ASSIGNED		"Assigned"
#define ZBX_REMEDY_STATUS_RESOLVED		"Resolved"
#define ZBX_REMEDY_STATUS_CLOSED		"Closed"
#define ZBX_REMEDY_STATUS_CANCELLED		"Cancelled"
#define ZBX_REMEDY_STATUS_WORK_INFO_SUMMARY	"Work_Info_Summary"

#define ZBX_REMEDY_ACTION_CREATE	"CREATE"
#define ZBX_REMEDY_ACTION_MODIFY	"MODIFY"

#define ZBX_REMEDY_CI_ID_FIELD		"tag"
#define ZBX_REMEDY_SERVICECLASS_FIELD	"serialno_b"

/* Service CI values for network and server services */
#define ZBX_REMEDY_SERVICECI_NETWORK		"Networks & Telecomms"
#define ZBX_REMEDY_SERVICECI_RID_NETWORK	"REGAA5V0BLLZRAMO2G4KO1499OT4JQ"
#define ZBX_REMEDY_SERVICECI_SERVER		"Server & Storage"
#define ZBX_REMEDY_SERVICECI_RID_SERVER		"OI-f9bee1dac03044f894ed43937bdc52dc"

extern int	CONFIG_REMEDY_SERVICE_TIMEOUT;

typedef struct
{
	char	*name;
	char	*value;
}
zbx_remedy_field_t;

typedef struct
{
	char	*data;
	size_t	alloc;
	size_t	offset;
}
ZBX_HTTPPAGE;

static ZBX_HTTPPAGE	page;

static size_t	WRITEFUNCTION2(void *ptr, size_t size, size_t nmemb, void *userdata)
{
	size_t	r_size = size * nmemb;

	ZBX_UNUSED(userdata);

	zbx_strncpy_alloc(&page.data, &page.alloc, &page.offset, ptr, r_size);

	return r_size;
}

static size_t	HEADERFUNCTION2(void *ptr, size_t size, size_t nmemb, void *userdata)
{
	ZBX_UNUSED(ptr);
	ZBX_UNUSED(userdata);

	return size * nmemb;
}

/******************************************************************************
 *                                                                            *
 * Function: xml_read_remedy_fields                                           *
 *                                                                            *
 * Purpose: reads the specified list of fields from Remedy Query Service      *
 *          response                                                          *
 *                                                                            *
 * Parameters: data       - [IN] the response data                            *
 *             fields     - [IN/OUT] the array of fields to read              *
 *             fields_num - [IN] the number of items in fields array          *
 *             error      - [OUT] the error message                           *
 *                                                                            *
 * Return value: The number of fields read                                    *
 *                                                                            *
 * Comments: This function allocates the values in fields array which must    *
 *           be freed afterwards with remedy_fields_clean_values() function.  *
 *                                                                            *
 ******************************************************************************/
static int	xml_read_remedy_fields(const char *data, zbx_remedy_field_t *fields, int fields_num, char **error)
{
	xmlDoc		*doc;
	xmlXPathContext	*xpathCtx;
	xmlXPathObject	*xpathObj;
	xmlNodeSetPtr	nodeset;
	xmlChar		*val;
	int		i, ret = FAIL;

	if (NULL == data)
	{
		*error = zbx_strdup(*error, "no data received");
		goto out;
	}

	if (NULL == (doc = xmlReadMemory(data, strlen(data), "noname.xml", NULL, 0)))
	{
		xmlErrorPtr	pErr;

		if (NULL != (pErr = xmlGetLastError()))
			*error = zbx_dsprintf(*error, "cannot parse xml value: %s", pErr->message);
		else
			*error = zbx_strdup(*error, "cannot parse xml value");

		goto out;
	}

	xpathCtx = xmlXPathNewContext(doc);

	for (i = 0; i < fields_num; i++)
	{
		char	xmlPath[4096];

		zbx_snprintf(xmlPath, sizeof(xmlPath), "//*[local-name()='HelpDesk_Query_ServiceResponse']"
				"/*[local-name()='%s']", fields[i].name);

		zbx_free(fields[i].value);

		if (NULL == (xpathObj = xmlXPathEvalExpression((const xmlChar *)xmlPath, xpathCtx)))
			continue;

		if (0 == xmlXPathNodeSetIsEmpty(xpathObj->nodesetval))
		{
			nodeset = xpathObj->nodesetval;

			if (NULL != (val = xmlNodeListGetString(doc, nodeset->nodeTab[0]->xmlChildrenNode, 1)))
			{
				fields[i].value = zbx_strdup(NULL, (char *)val);
				xmlFree(val);
			}
		}
		xmlXPathFreeObject(xpathObj);
	}

	xmlXPathFreeContext(xpathCtx);
	xmlFreeDoc(doc);
	xmlCleanupParser();

	ret = SUCCEED;
out:
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: remedy_fields_clean_values                                       *
 *                                                                            *
 * Purpose: releases field values allocated by xml_read_remedy_fields()       *
 *          function                                                          *
 *                                                                            *
 * Parameters: fields     - [IN/OUT] the fields array to clean                *
 *             fields_num - [IN] the number of items in fields array          *
 *                                                                            *
 ******************************************************************************/
static void	remedy_fields_clean_values(zbx_remedy_field_t *fields, int fields_num)
{
	int	i;

	for (i = 0; i < fields_num; i++)
		zbx_free(fields[i].value);
}


/******************************************************************************
 *                                                                            *
 * Function: remedy_fields_set_value                                          *
 *                                                                            *
 * Purpose: sets the specified field value in fields array                    *
 *                                                                            *
 * Parameters: fields     - [IN/OUT] the fields array                         *
 *             fields_num - [IN] the number of items in fields array          *
 *             name       - [IN] the field name                               *
 *             value      - [IN] the field value                              *
 *                                                                            *
 ******************************************************************************/
static void	remedy_fields_set_value(zbx_remedy_field_t *fields, int fields_num, const char *name, const char *value)
{
	int	i;

	for (i = 0; i < fields_num; i++)
	{
		if (0 == strcmp(fields[i].name, name))
		{
			if (NULL != value)
			{
				/* zbx_strdup() frees old value if it's not NULL */
				fields[i].value = zbx_strdup(fields[i].value, value);
			}
			else
				zbx_free(fields[i].value);
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Function: remedy_fields_get_value                                          *
 *                                                                            *
 * Purpose: gets the specified field value from fields array                  *
 *                                                                            *
 * Parameters: fields     - [IN/OUT] the fields array                         *
 *             fields_num - [IN] the number of items in fields array          *
 *             name       - [IN] the field name                               *
 *                                                                            *
 * Return value: the value of requested field or NULL if the field was not    *
 *               found.                                                       *
 *                                                                            *
 ******************************************************************************/
static const char	*remedy_fields_get_value(zbx_remedy_field_t *fields, int fields_num, const char *name)
{
	int	i;

	for (i = 0; i < fields_num; i++)
	{
		if (0 == strcmp(fields[i].name, name))
			return fields[i].value;
	}
	return NULL;
}

/******************************************************************************
 *                                                                            *
 * Function: libxml_handle_error                                              *
 *                                                                            *
 * Purpose: libxml2 callback function for error handle                        *
 *                                                                            *
 * Parameters: user_data - [IN/OUT] the user context                          *
 *             err       - [IN] the libxml2 error message                     *
 *                                                                            *
 ******************************************************************************/
static void	libxml_handle_error(void *user_data, xmlErrorPtr err)
{
	ZBX_UNUSED(user_data);
	ZBX_UNUSED(err);
}

/******************************************************************************
 *                                                                            *
 * Function: remedy_init_connection                                           *
 *                                                                            *
 * Purpose: initializes connection to the Remedy service                      *
 *                                                                            *
 * Parameters: easyhandle - [OUT] the CURL easy handle                        *
 *             headers    - [OUT] the CURL headers                            *
 *             url        - [IN] the Remedy service URL                       *
 *             proxy      - [IN] the http(s) proxy URL, pass empty string to  *
 *                               disable proxy                                *
 *             error      - [OUT] the error description                       *
 *                                                                            *
 * Return value: SUCCEED - the connection was initialized successfully        *
 *               FAIL - connection initialization failed, error contains      *
 *                      allocated string with error description               *
 *                                                                            *
 * Comments: The caller must free the error description if it was set.        *
 *                                                                            *
 ******************************************************************************/
static int	remedy_init_connection(CURL **easyhandle, const struct curl_slist *headers, const char *url,
		const char *proxy, char **error)
{
	int	opt, timeout = CONFIG_REMEDY_SERVICE_TIMEOUT, ret = FAIL, err;

	xmlSetStructuredErrorFunc(NULL, &libxml_handle_error);

	if (NULL == (*easyhandle = curl_easy_init()))
	{
		*error = zbx_strdup(NULL, "Cannot init cURL library");
		goto out;
	}

	if (CURLE_OK != (err = curl_easy_setopt(*easyhandle, opt = CURLOPT_HTTPHEADER, headers)))
	{
		*error = zbx_dsprintf(*error, "Cannot set cURL option [%d]: %s", opt, curl_easy_strerror(err));
		goto out;
	}

	if (CURLE_OK != (err = curl_easy_setopt(*easyhandle, opt = CURLOPT_PROXY, proxy)) ||
			CURLE_OK != (err = curl_easy_setopt(*easyhandle, opt = CURLOPT_COOKIEFILE, "")) ||
			CURLE_OK != (err = curl_easy_setopt(*easyhandle, opt = CURLOPT_FOLLOWLOCATION, 1L)) ||
			CURLE_OK != (err = curl_easy_setopt(*easyhandle, opt = CURLOPT_WRITEFUNCTION, WRITEFUNCTION2)) ||
			CURLE_OK != (err = curl_easy_setopt(*easyhandle, opt = CURLOPT_HEADERFUNCTION, HEADERFUNCTION2)) ||
			CURLE_OK != (err = curl_easy_setopt(*easyhandle, opt = CURLOPT_SSL_VERIFYPEER, 0L)) ||
			CURLE_OK != (err = curl_easy_setopt(*easyhandle, opt = CURLOPT_POST, 1L)) ||
			CURLE_OK != (err = curl_easy_setopt(*easyhandle, opt = CURLOPT_URL, url)) ||
			CURLE_OK != (err = curl_easy_setopt(*easyhandle, opt = CURLOPT_TIMEOUT, (long)timeout)) ||
			CURLE_OK != (err = curl_easy_setopt(*easyhandle, opt = CURLOPT_SSL_VERIFYHOST, 0L)))
	{
		*error = zbx_dsprintf(*error, "Cannot set cURL option [%d]: %s", opt, curl_easy_strerror(err));
		goto out;
	}

	page.offset = 0;
	ret = SUCCEED;
out:
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: remedy_create_ticket                                             *
 *                                                                            *
 * Purpose: creates new ticket in Remedy service                              *
 *                                                                            *
 * Parameters: url        - [IN] the Remedy service URL                       *
 *             proxy      - [IN] the http(s) proxy URL, pass empty string to  *
 *                               disable proxy                                *
 *             user       - [IN] the Remedy user name                         *
 *             password   - [IN] the Remedy user password                     *
 *             ...        - [IN] various ticket parameters                    *
 *             externalid - [OUT] the number of created incident in Remedy    *
 *             error      - [OUT] the error description                       *
 *                                                                            *
 * Return value: SUCCEED - the ticket was created successfully                *
 *               FAIL - ticket creation failed, error contains                *
 *                      allocated string with error description               *
 *                                                                            *
 * Comments: The caller must free the incident number and error description.  *
 *                                                                            *
 ******************************************************************************/
static int	remedy_create_ticket(const char *url, const char *proxy, const char *user, const char *password,
		const char *loginid, const char *service_name, const char *service_id, const char *ci,
		const char *ci_id, const char *summary, const char *notes, const char *impact, const char *urgency,
		const char *company, const char *serviceclass, char **externalid, char **error)
{
#	define ZBX_POST_REMEDY_CREATE_SERVICE								\
		ZBX_SOAP_ENVELOPE_CREATE_OPEN								\
		ZBX_SOAP_HEADER										\
		ZBX_SOAP_BODY_OPEN									\
		"<urn:HelpDesk_Submit_Service>"								\
			"<urn:Assigned_Group>Control center</urn:Assigned_Group>"			\
			"<urn:First_Name/>"								\
			"<urn:Impact>%s</urn:Impact>"							\
			"<urn:Last_Name/>"								\
			"<urn:Reported_Source>Systems Management</urn:Reported_Source>"			\
			"<urn:Service_Type>Infrastructure Event</urn:Service_Type>"			\
			"<urn:Status>New</urn:Status>"							\
			"<urn:Action>%s</urn:Action>"							\
			"<urn:Summary>%s</urn:Summary>"							\
			"<urn:Notes>%s</urn:Notes>"							\
			"<urn:Urgency>%s</urn:Urgency>"							\
			"<urn:ServiceCI>%s</urn:ServiceCI>"						\
			"<urn:ServiceCI_ReconID>%s</urn:ServiceCI_ReconID>"				\
			"<urn:HPD_CI>%s</urn:HPD_CI>"							\
			"<urn:HPD_CI_ReconID>%s</urn:HPD_CI_ReconID>"					\
			"<urn:Login_ID>%s</urn:Login_ID>"						\
			"<urn:Customer_Company>%s</urn:Customer_Company>"				\
			"<urn:CSC_INC></urn:CSC_INC>"							\
			"<urn:Service_Class>%s</urn:Service_Class>"					\
		"</urn:HelpDesk_Submit_Service>"							\
		ZBX_SOAP_BODY_CLOSE									\
		ZBX_SOAP_ENVELOPE_CREATE_CLOSE

	const char		*__function_name = "remedy_create_ticket";
	CURL			*easyhandle = NULL;
	struct curl_slist	*headers = NULL;
	int			ret = FAIL, err, opt;
	char			*xml = NULL, *summary_esc = NULL, *notes_esc = NULL, *ci_esc = NULL,
				*service_url = NULL, *impact_esc, *urgency_esc, *company_esc, *service_name_esc,
				*service_id_esc, *user_esc = NULL, *password_esc = NULL, *ci_id_esc = NULL,
				*loginid_esc = NULL, *serviceclass_esc = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	service_url = zbx_dsprintf(service_url, "%s" ZBX_SOAP_URL_CREATE, url);

	headers = curl_slist_append(headers, ZBX_XML_HEADER_CONTENTTYPE);
	headers = curl_slist_append(headers, ZBX_XML_HEADER_SOAPACTION_CREATE);

	if (FAIL == remedy_init_connection(&easyhandle, headers, service_url, proxy, error))
		goto out;

	user_esc = xml_escape_dyn(user);
	password_esc = xml_escape_dyn(password);
	summary_esc = xml_escape_dyn(summary);
	notes_esc = xml_escape_dyn(notes);
	ci_esc = xml_escape_dyn(ci);
	ci_id_esc = xml_escape_dyn(ci_id);
	impact_esc = xml_escape_dyn(impact);
	urgency_esc = xml_escape_dyn(urgency);
	service_name_esc = xml_escape_dyn(service_name);
	service_id_esc = xml_escape_dyn(service_id);
	company_esc = xml_escape_dyn(company);
	loginid_esc = xml_escape_dyn(loginid);
	serviceclass_esc = xml_escape_dyn(serviceclass);

	xml = zbx_dsprintf(xml, ZBX_POST_REMEDY_CREATE_SERVICE, user_esc, password_esc, impact_esc,
			ZBX_REMEDY_ACTION_CREATE, summary_esc, notes_esc, urgency_esc, service_name_esc,
			service_id_esc, ci_esc, ci_id_esc, loginid_esc, company_esc, serviceclass_esc);

	zabbix_log(LOG_LEVEL_TRACE, "Soap post: %s", xml);

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_POSTFIELDS, xml)))
	{
		*error = zbx_dsprintf(*error, "Cannot set cURL option [%d]: %s", opt, curl_easy_strerror(err));
		goto out;
	}

	if (CURLE_OK != (err = curl_easy_perform(easyhandle)))
	{
		*error = zbx_strdup(*error, curl_easy_strerror(err));
		goto out;
	}

	if (NULL != (*error = zbx_xml_read_value(page.data, ZBX_XPATH_LN1("faultstring"))))
		goto out;

	if (NULL == (*externalid = zbx_xml_read_value(page.data,
			ZBX_XPATH_LN2("HelpDesk_Submit_ServiceResponse", "Incident_Number"))))
	{
		*error = zbx_dsprintf(*error, "Cannot retrieve incident number from Remedy response");
		goto out;
	}

	ret = SUCCEED;
out:
	curl_easy_cleanup(easyhandle);
	curl_slist_free_all(headers);

	zbx_free(xml);
	zbx_free(serviceclass_esc);
	zbx_free(loginid_esc);
	zbx_free(company_esc);
	zbx_free(service_id_esc);
	zbx_free(service_name_esc);
	zbx_free(urgency_esc);
	zbx_free(impact_esc);
	zbx_free(ci_id_esc);
	zbx_free(ci_esc);
	zbx_free(notes_esc);
	zbx_free(summary_esc);
	zbx_free(password_esc);
	zbx_free(user_esc);
	zbx_free(service_url);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s '%s'", __function_name, zbx_result_string(ret),
			SUCCEED == ret ? *externalid : *error);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: remedy_query_ticket                                              *
 *                                                                            *
 * Purpose: reads the specified list of ticket fields from Remedy service     *
 *                                                                            *
 * Parameters: url        - [IN] the Remedy service URL                       *
 *             proxy      - [IN] the http(s) proxy URL, pass empty string to  *
 *                               disable proxy                                *
 *             user       - [IN] the Remedy user name                         *
 *             password   - [IN] the Remedy user password                     *
 *             externalid - [NI] the Remedy ticket id                         *
 *             fields     - [IN/OUT] the array of fields to read.             *
 *                          To ensure that old data is not carried over the   *
 *                          fields[*].value members must be set to NULL.      *
 *             fields_num - [IN] the number of items in fields array          *
 *             error      - [OUT] the error description                       *
 *                                                                            *
 * Return value: SUCCEED - the request was made successfully                  *
 *               FAIL - the operation failed, error contains                  *
 *                      allocated string with error description               *
 *                                                                            *
 * Comments: This function allocates the values in fields array which must    *
 *           be freed afterwards with remedy_fields_clean_values() function.  *
 *                                                                            *
 *           The caller must free the error description if it was set.        *
 *                                                                            *
 *           If the requested incident number was not found the function      *
 *           sill returns SUCCEED, but the Incident_Number field in the       *
 *           fields array will be left NULL. If the incident was found the    *
 *           requested fields will be set from the response except            *
 *           the Incident_Number field, which will be copied from request.    *
 *                                                                            *
 ******************************************************************************/
static int	remedy_query_ticket(const char *url, const char *proxy, const char *user, const char *password,
		const char *externalid, zbx_remedy_field_t *fields, int fields_num, char **error)
{
#	define ZBX_POST_REMEDY_QUERY_SERVICE								\
		ZBX_SOAP_ENVELOPE_OPEN									\
		ZBX_SOAP_HEADER										\
		ZBX_SOAP_BODY_OPEN									\
		ZBX_HELPDESK_QUERY_SERVICE_OPEN								\
			"<urn:Incident_Number>%s</urn:Incident_Number>"					\
		ZBX_HELPDESK_QUERY_SERVICE_CLOSE							\
		ZBX_SOAP_BODY_CLOSE									\
		ZBX_SOAP_ENVELOPE_CLOSE

	const char		*__function_name = "remedy_query_ticket";
	CURL			*easyhandle = NULL;
	struct curl_slist	*headers = NULL;
	int			ret = FAIL, opt, err;
	char			*xml = NULL, *service_url = NULL, *user_esc = NULL, *password_esc = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() externalid:%s", __function_name, externalid);

	service_url = zbx_dsprintf(service_url, "%s" ZBX_SOAP_URL, url);

	user_esc = xml_escape_dyn(user);
	password_esc = xml_escape_dyn(password);

	headers = curl_slist_append(headers, ZBX_XML_HEADER_CONTENTTYPE);
	headers = curl_slist_append(headers, ZBX_XML_HEADER_SOAPACTION_QUERY);

	if (FAIL == remedy_init_connection(&easyhandle, headers, service_url, proxy, error))
		goto out;

	xml = zbx_dsprintf(xml, ZBX_POST_REMEDY_QUERY_SERVICE, user_esc, password_esc, externalid);

	zabbix_log(LOG_LEVEL_TRACE, "Soap post: %s", xml);

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_POSTFIELDS, xml)))
	{
		*error = zbx_dsprintf(*error, "Cannot set cURL option [%d]: %s", opt, curl_easy_strerror(err));
		goto out;
	}

	if (CURLE_OK != (err = curl_easy_perform(easyhandle)))
	{
		*error = zbx_strdup(*error, curl_easy_strerror(err));
		goto out;
	}

	if (NULL != (*error = zbx_xml_read_value(page.data, ZBX_XPATH_LN1("faultstring"))))
	{
		if (0 == strncmp(*error, ZBX_REMEDY_ERROR_INVALID_INCIDENT,
				ZBX_CONST_STRLEN(ZBX_REMEDY_ERROR_INVALID_INCIDENT)))
		{
			/* in the case of invalid incident number error we return SUCCEED with NULL */
			/* incident number field value                                              */
			zbx_free(*error);
			ret = SUCCEED;

			goto out;
		}
	}

	if (SUCCEED == (ret = xml_read_remedy_fields(page.data, fields, fields_num, error)))
		remedy_fields_set_value(fields, fields_num, ZBX_REMEDY_FIELD_INCIDENT_NUMBER, externalid);
out:
	curl_easy_cleanup(easyhandle);
	curl_slist_free_all(headers);

	zbx_free(xml);
	zbx_free(password_esc);
	zbx_free(user_esc);
	zbx_free(service_url);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s '%s'", __function_name, zbx_result_string(ret),
			SUCCEED == ret ? "" : *error);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: remedy_modify_ticket                                             *
 *                                                                            *
 * Purpose: modify Remedy service ticket                                      *
 *                                                                            *
 * Parameters: url        - [IN] the Remedy service URL                       *
 *             proxy      - [IN] the http(s) proxy URL, pass empty string to  *
 *                               disable proxy                                *
 *             user       - [IN] the Remedy user name                         *
 *             password   - [IN] the Remedy user password                     *
 *             fields     - [IN/OUT] the array of fields to read.             *
 *                          To ensure that old data is not carried over the   *
 *                          fields[*].value members must be set to NULL.      *
 *             fields_num - [IN] the number of items in fields array          *
 *             error      - [OUT] the error description                       *
 *                                                                            *
 * Return value: SUCCEED - the ticket was created successfully                *
 *               FAIL - ticekt creation failed, error contains                *
 *                      allocated string with error description               *
 *                                                                            *
 * Comments: The Incident_Number field must be set with the number of target  *
 *           ticket.                                                          *
 *                                                                            *
 ******************************************************************************/
static int	remedy_modify_ticket(const char *url, const char *proxy, const char *user, const char *password,
		zbx_remedy_field_t *fields, int fields_num, char **error)
{
	const char		*__function_name = "remedy_modify_ticket";
	CURL			*easyhandle = NULL;
	struct curl_slist	*headers = NULL;
	int			ret = FAIL, err, opt, i;
	char			*xml = NULL, *service_url = NULL, *user_esc = NULL, *password_esc = NULL;
	size_t			xml_alloc = 0, xml_offset = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	service_url = zbx_dsprintf(service_url, "%s" ZBX_SOAP_URL, url);

	user_esc = xml_escape_dyn(user);
	password_esc = xml_escape_dyn(password);

	headers = curl_slist_append(headers, ZBX_XML_HEADER_CONTENTTYPE);
	headers = curl_slist_append(headers, ZBX_XML_HEADER_SOAPACTION_MODIFY);

	if (FAIL == remedy_init_connection(&easyhandle, headers, service_url, proxy, error))
		goto out;

	remedy_fields_set_value(fields, fields_num, ZBX_REMEDY_FIELD_ACTION, ZBX_REMEDY_ACTION_MODIFY);

	zbx_snprintf_alloc(&xml, &xml_alloc, &xml_offset,
			ZBX_SOAP_ENVELOPE_OPEN
			ZBX_SOAP_HEADER
			ZBX_SOAP_BODY_OPEN
			ZBX_HELPDESK_MODIFY_SERVICE_OPEN,
			user_esc, password_esc);

	for (i = 0; i < fields_num; i++)
	{
		if (NULL != fields[i].value)
		{
			char	*value;

			value = xml_escape_dyn(fields[i].value);

			zbx_snprintf_alloc(&xml, &xml_alloc, &xml_offset, "<urn:%s>%s</urn:%s>", fields[i].name, value,
				fields[i].name);

			zbx_free(value);
		}
		else
			zbx_snprintf_alloc(&xml, &xml_alloc, &xml_offset, "<urn:%s/>", fields[i].name);
	}

	zbx_snprintf_alloc(&xml, &xml_alloc, &xml_offset,
			ZBX_HELPDESK_MODIFY_SERVICE_CLOSE
			ZBX_SOAP_BODY_CLOSE
			ZBX_SOAP_ENVELOPE_CLOSE);

	zabbix_log(LOG_LEVEL_TRACE, "Soap post: %s", xml);

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_POSTFIELDS, xml)))
	{
		*error = zbx_dsprintf(*error, "Cannot set cURL option [%d]: %s", opt, curl_easy_strerror(err));
		goto out;
	}

	if (CURLE_OK != (err = curl_easy_perform(easyhandle)))
	{
		*error = zbx_strdup(*error, curl_easy_strerror(err));
		goto out;
	}

	if (NULL != (*error = zbx_xml_read_value(page.data, ZBX_XPATH_LN1("faultstring"))))
		goto out;

	ret = SUCCEED;
out:
	remedy_fields_clean_values(fields, fields_num);

	curl_easy_cleanup(easyhandle);
	curl_slist_free_all(headers);

	zbx_free(xml);
	zbx_free(password_esc);
	zbx_free(user_esc);
	zbx_free(service_url);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s '%s'", __function_name, zbx_result_string(ret),
			SUCCEED == ret ? "" : *error);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: remedy_get_service_by_host                                       *
 *                                                                            *
 * Purpose: gets remedy service linked to the specified host                  *
 *                                                                            *
 * Parameters: hostid       - [IN] the host id                                *
 *             group_name   - [IN] the name of value mapping containing       *
 *                               mapping of host group names to Remedy        *
 *                               names                                        *
 *             service_name - [OUT] the corresponding service name            *
 *             service_id   - [OUT] the corresponding service reconciliation  *
 *                                  id                                        *
 *                                                                            *
 * Comments: The Service CI is linked to the hosts with a help of host groups.*
 *           All hosts in the group defined in Remedy media configuration     *
 *           (service mapping) are linked to Network & Telecoms Service CI,   *
 *           while the rest of hosts are linked to Server & Storage Service   *
 *           CI.                                                              *
 *                                                                            *
 ******************************************************************************/
static void	remedy_get_service_by_host(zbx_uint64_t hostid, const char *group_name, char **service_name,
		char **service_id)
{
	const char	*__function_name = "remedy_get_service_by_host";
	char		*group_name_esc;
	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() %s", __function_name, group_name);

	group_name_esc = DBdyn_escape_string(group_name);

	result = DBselect(
			"select g.name"
			" from groups g,hosts_groups hg"
			" where hg.hostid=" ZBX_FS_UI64
				" and g.groupid=hg.groupid"
				" and g.name='%s'",
			hostid, group_name_esc);

	/* If the host group name matches the specified service mapping value from remedy configuration */
	/* use predefined network service CI. Otherwise use predefined server service CI.               */
	if (NULL != (row = DBfetch(result)))
	{
		*service_name = zbx_strdup(NULL, ZBX_REMEDY_SERVICECI_NETWORK);
		*service_id = zbx_strdup(NULL, ZBX_REMEDY_SERVICECI_RID_NETWORK);
	}
	else
	{
		*service_name = zbx_strdup(NULL, ZBX_REMEDY_SERVICECI_SERVER);
		*service_id = zbx_strdup(NULL, ZBX_REMEDY_SERVICECI_RID_SERVER);
	}

	DBfree_result(result);

	zbx_free(group_name_esc);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s (%s)", __function_name, *service_name, *service_id);
}

/******************************************************************************
 *                                                                            *
 * Function: remedy_init_ticket                                               *
 *                                                                            *
 * Purpose: initializes ticket properties                                     *
 *                                                                            *
 ******************************************************************************/
static void	remedy_update_ticket(zbx_ticket_t *ticket, const char *incident_number, const char *status,
		const char *assignee, int action, const DB_MEDIATYPE *mediatype)
{
	const char	*ptr;
	size_t		url_alloc = 0, url_offset = 0;

	ticket->ticketid = zbx_strdup(NULL, incident_number);

	if (ZBX_TICKET_ACTION_NONE == (ticket->action = action))
	{
		/* calculated allowed action based on incident status */
		if (NULL != status)
		{
			ticket->status = zbx_strdup(NULL, status);

			if (0 == strcmp(status, ZBX_REMEDY_STATUS_CLOSED) ||
					0 == strcmp(status, ZBX_REMEDY_STATUS_CANCELLED))
			{
				ticket->action = ZBX_TICKET_ACTION_CREATE;
			}
			else if (0 == strcmp(status, ZBX_REMEDY_STATUS_RESOLVED))
			{
				ticket->action = ZBX_TICKET_ACTION_REOPEN;
			}
			else
			{
				ticket->action = ZBX_TICKET_ACTION_UPDATE;
			}
		}
	}

	if (NULL != assignee)
		ticket->assignee = zbx_strdup(NULL, assignee);

	ticket->clock = zbx_xmedia_get_ticket_creation_time(ticket->ticketid);

	if (NULL != (ptr = strstr(mediatype->smtp_server, "://")) &&
			NULL != (ptr = strchr(ptr + 3, '/')))
	{
		zbx_strncpy_alloc(&ticket->url, &url_alloc, &url_offset, mediatype->smtp_server,
				ptr - mediatype->smtp_server);
		zbx_snprintf_alloc(&ticket->url, &url_alloc, &url_offset,
				"/arsys/forms/onbmc-s/SHR:LandingConsole/Default Administrator View/"
				"?mode=search&F304255500=HPD:Help Desk&F1000000076=FormOpenNoAppList"
				"&F303647600=SearchTicketWithQual&F304255610='1000000161'=\"%s\"", incident_number);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: remedy_process_event                                             *
 *                                                                            *
 * Purpose: processes event by either creating, reopening or just updating    *
 *          an incident in Remedy service                                     *
 *                                                                            *
 * Parameters: eventid    - [IN] the event to process                         *
 *             userid     - [IN] the user processing the event                *
 *             loginid    - [IN] the Remedy loginid field (Customer)          *
 *             subject    - [IN] the message subject                          *
 *             message    - [IN] the message contents                         *
 *             media      - [IN] the media object containing Remedy service   *
 *                               and ticket information                       *
 *             state      - [IN] the processing state automatic/manual -      *
 *                               (ZBX_REMEDY_PROCESS_*).                      *
 *                               During manual processing events aren't       *
 *                               acknowledged and the message is used instead *
 *                               of subject when updating incident.           *
 *             ticket     - [OUT] the updated/created ticket data (optional)  *
 *             error      - [OUT] the error description                       *
 *                                                                            *
 * Return value: SUCCEED - the alert was processed successfully               *
 *               FAIL - alert processing failed, error contains               *
 *                      allocated string with error description               *
 *                                                                            *
 * Comments: The caller must free the error description if it was set.        *
 *                                                                            *
 ******************************************************************************/
static int	remedy_process_event(zbx_uint64_t eventid, zbx_uint64_t userid, const char *loginid, const char *subject,
		const char *message, const DB_MEDIATYPE *media, int state, zbx_ticket_t *ticket, char **error)
{
#define ZBX_EVENT_REMEDY_WARNING	0
#define ZBX_EVENT_REMEDY_CRITICAL	1

#define ZBX_REMEDY_DEFAULT_SERVICECI	""

/* the number of fields at the end of fields array used only to query data */
/* and should not be passed to modify function                             */
#define ZBX_REMEDY_QUERY_FIELDS		1

	const char	*__function_name = "remedy_process_event";
	int		ret = FAIL, action = ZBX_XMEDIA_ACTION_NONE, event_value, trigger_severity,
			is_registered = 0;
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	triggerid, hostid;
	const char	*status;
	char		*incident_number = NULL, *incident_status = NULL, *trigger_expression = NULL;

	zbx_remedy_field_t	fields[] = {
			{"Categorization_Tier_1", NULL},
			{"Categorization_Tier_2", NULL},
			{"Categorization_Tier_3", NULL},
			{"Closure_Manufacturer", NULL},
			{"Closure_Product_Category_Tier1", NULL},
			{"Closure_Product_Category_Tier2", NULL},
			{"Closure_Product_Category_Tier3", NULL},
			{"Closure_Product_Model_Version", NULL},
			{"Closure_Product_Name", NULL},
			{"Company", NULL},
			{"Summary", NULL},
			{"Notes", NULL},
			{"Impact", NULL},
			{"Manufacturer", NULL},
			{"Product_Categorization_Tier_1", NULL},
			{"Product_Categorization_Tier_2", NULL},
			{"Product_Categorization_Tier_3", NULL},
			{"Product_Model_Version", NULL},
			{"Product_Name", NULL},
			{"Reported_Source", NULL},
			{"Resolution", NULL},
			{"Resolution_Category", NULL},
			{"Resolution_Category_Tier_2", NULL},
			{"Resolution_Category_Tier_3", NULL},
			{"Resolution_Method", NULL},
			{"Service_Type", NULL},
			{ZBX_REMEDY_FIELD_STATUS, NULL},
			{"Urgency", NULL},
			{ZBX_REMEDY_FIELD_ACTION, NULL},
			{"Work_Info_Summary", NULL},
			{"Work_Info_Notes", NULL},
			{"Work_Info_Type", NULL},
			{"Work_Info_Date", NULL},
			{"Work_Info_Source", NULL},
			{"Work_Info_Locked", NULL},
			{"Work_Info_View_Access", NULL},
			{ZBX_REMEDY_FIELD_INCIDENT_NUMBER, NULL},
			{"Status_Reason", NULL},
			{"ServiceCI", NULL},
			{"ServiceCI_ReconID", NULL},
			{"HPD_CI", NULL},
			{"HPD_CI_ReconID", NULL},
			{"HPD_CI_FormName", NULL},
			{"z1D_CI_FormName", NULL},
			{"WorkInfoAttachment1Name", NULL},
			{"WorkInfoAttachment1Data", NULL},
			{"WorkInfoAttachment1OrigSize", NULL},
			{ZBX_REMEDY_FIELD_ASSIGNEE, NULL},
	};

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	result = DBselect("select e.value,t.priority,t.triggerid,t.expression from events e,triggers t"
				" where e.eventid=" ZBX_FS_UI64
					" and e.source=%d"
					" and e.object=%d"
					" and t.triggerid=e.objectid",
				eventid, EVENT_SOURCE_TRIGGERS,  EVENT_OBJECT_TRIGGER);

	if (NULL == (row = DBfetch(result)))
	{
		*error = zbx_strdup(*error, "Cannot find corresponding event in database");
		goto out;
	}

	event_value = atoi(row[0]);
	trigger_severity = atoi(row[1]);
	ZBX_STR2UINT64(triggerid, row[2]);
	trigger_expression = zbx_strdup(NULL, row[3]);

	/* get a ticket directly linked to the event or the latest linked to event generated by the same trigger */
	if (NULL == (incident_number = zbx_xmedia_get_incident_by_eventid(eventid, media->mediatypeid)))
		incident_number = zbx_xmedia_get_incident_by_triggerid(triggerid, media->mediatypeid);
	else
		is_registered = 1;

	if (NULL != incident_number && SUCCEED != remedy_query_ticket(media->smtp_server, media->smtp_helo,
			media->username, media->passwd, incident_number, fields, ARRSIZE(fields), error))
	{
		*error = zbx_strdup(*error, "Cannot get incident information from Remedy service");
		goto out;
	}

	if (TRIGGER_VALUE_OK != event_value)
	{
		char		*service_name = NULL, *service_id = NULL, *severity_name = NULL;
		int		remedy_event;
		char		*impact_map[] = {"3-Moderate/Limited", "2-Significant/Large"};
		char		*urgency_map[] = {"3-Medium", "2-High"};
		zbx_uint64_t	functionid;

		if (NULL != incident_number)
		{
			if (NULL == (status = remedy_fields_get_value(fields, ARRSIZE(fields), ZBX_REMEDY_FIELD_STATUS)))
			{
				*error = zbx_dsprintf(*error, "Incident %s query did not return status field",
						incident_number);
				goto out;
			}

			/* check if the ticket should be reopened */
			if (0 == strcmp(status, ZBX_REMEDY_STATUS_RESOLVED))
			{
				action = ZBX_XMEDIA_ACTION_REOPEN;
				incident_status = zbx_strdup(NULL, ZBX_REMEDY_STATUS_ASSIGNED);

				remedy_fields_set_value(fields, ARRSIZE(fields), ZBX_REMEDY_FIELD_STATUS,
						ZBX_REMEDY_STATUS_ASSIGNED);

				remedy_fields_set_value(fields, ARRSIZE(fields), ZBX_REMEDY_STATUS_WORK_INFO_SUMMARY,
						ZBX_XMEDIA_PROCESS_AUTOMATED == state ? subject : message);

				ret = remedy_modify_ticket(media->smtp_server, media->smtp_helo, media->username,
						media->passwd, fields, ARRSIZE(fields) - ZBX_REMEDY_QUERY_FIELDS, error);

				goto out;
			}

			/* if ticket is still being worked on, add work info */
			if (0 != strcmp(status, ZBX_REMEDY_STATUS_CLOSED) &&
					0 != strcmp(status, ZBX_REMEDY_STATUS_CANCELLED))
			{
				action = ZBX_XMEDIA_ACTION_UPDATE;
				incident_status = zbx_strdup(NULL, status);

				remedy_fields_set_value(fields, ARRSIZE(fields), ZBX_REMEDY_STATUS_WORK_INFO_SUMMARY,
						ZBX_XMEDIA_PROCESS_AUTOMATED == state ? subject : message);

				ret = remedy_modify_ticket(media->smtp_server, media->smtp_helo, media->username,
						media->passwd, fields, ARRSIZE(fields) - ZBX_REMEDY_QUERY_FIELDS, error);

				goto out;
			}
		}

		/* create a new ticket */

		if (SUCCEED != get_N_functionid(trigger_expression, 1, &functionid, NULL))
		{
			*error = zbx_strdup(*error, "Failed to extract function id from the trigger expression");
			goto out;
		}

		DBfree_result(result);

		/* find the host */
		result = DBselect("select h.host,h.hostid,hi." ZBX_REMEDY_CI_ID_FIELD "," ZBX_REMEDY_SERVICECLASS_FIELD
				" from items i,functions f,hosts h left join host_inventory hi"
					" on hi.hostid=h.hostid"
				" where f.functionid=" ZBX_FS_UI64
					" and f.itemid=i.itemid"
					" and i.hostid=h.hostid",
				functionid);

		if (NULL == (row = DBfetch(result)))
		{
			*error = zbx_strdup(*error, "Failed find host of the trigger expression");
			goto out;
		}

		if (SUCCEED == DBis_null(row[2]))
		{
			*error = zbx_dsprintf(NULL, "Host inventory is not enabled for the host '%s'", row[0]);
			goto out;
		}

		if ('\0' == *row[2])
		{
			*error = zbx_dsprintf(NULL, "Host '%s' inventory Recon ID field (" ZBX_REMEDY_CI_ID_FIELD
					") is not set", row[0]);
			goto out;
		}

		if ('\0' == *row[3])
		{
			*error = zbx_dsprintf(NULL, "Host '%s' inventory Service Class field ("
					ZBX_REMEDY_SERVICECLASS_FIELD ") is not set", row[0]);
			goto out;
		}

		/* map trigger severity */
		switch (trigger_severity)
		{
			case TRIGGER_SEVERITY_WARNING:
				remedy_event = ZBX_EVENT_REMEDY_WARNING;
				break;
			case TRIGGER_SEVERITY_AVERAGE:
			case TRIGGER_SEVERITY_HIGH:
			case TRIGGER_SEVERITY_DISASTER:
				remedy_event = ZBX_EVENT_REMEDY_CRITICAL;
				break;
			default:
				if (SUCCEED != zbx_get_trigger_severity_name(trigger_severity, &severity_name))
					severity_name = zbx_dsprintf(severity_name, "[%d]", trigger_severity);

				*error = zbx_dsprintf(*error, "Unsupported trigger severity: %s", severity_name);
				zbx_free(severity_name);

				goto out;
		}

		ZBX_STR2UINT64(hostid, row[1]);

		remedy_get_service_by_host(hostid, media->smtp_email, &service_name, &service_id);

		zbx_free(incident_number);

		action = ZBX_XMEDIA_ACTION_CREATE;
		incident_status = zbx_strdup(NULL, ZBX_REMEDY_STATUS_NEW);

		ret = remedy_create_ticket(media->smtp_server, media->smtp_helo, media->username, media->passwd,
				loginid, service_name, service_id, row[0], row[2], subject, message,
				impact_map[remedy_event], urgency_map[remedy_event], media->exec_path, row[3],
				&incident_number, error);

		zbx_free(service_name);
		zbx_free(service_id);
	}
	else
	{
		if (NULL == incident_number)
		{
			/* trigger without an associated ticket was switched to OK state */
			ret = SUCCEED;
			goto out;
		}
		if (NULL == (status = remedy_fields_get_value(fields, ARRSIZE(fields), ZBX_REMEDY_FIELD_STATUS)))
		{
			*error = zbx_dsprintf(*error, "Incident %s query did not return status field", incident_number);
			goto out;
		}

		incident_status = zbx_strdup(NULL, status);

		if (0 == strcmp(incident_status, ZBX_REMEDY_STATUS_RESOLVED) ||
				0 == strcmp(incident_status, ZBX_REMEDY_STATUS_CLOSED) ||
				0 == strcmp(incident_status, ZBX_REMEDY_STATUS_CANCELLED))
		{
			/* don't update already resolved, closed or canceled incidents */
			ret = SUCCEED;
			goto out;
		}

		remedy_fields_set_value(fields, ARRSIZE(fields), ZBX_REMEDY_STATUS_WORK_INFO_SUMMARY,
				ZBX_XMEDIA_PROCESS_AUTOMATED == state ? subject : message);

		ret = remedy_modify_ticket(media->smtp_server, media->smtp_helo, media->username, media->passwd, fields,
				ARRSIZE(fields) - ZBX_REMEDY_QUERY_FIELDS, error);
	}
out:
	DBfree_result(result);

	if (SUCCEED == ret)
	{
		if (ZBX_XMEDIA_ACTION_NONE != action)
		{
			DBbegin();

			if (state == ZBX_XMEDIA_PROCESS_AUTOMATED)
				zbx_xmedia_acknowledge_event(eventid, userid, incident_number, action);

			if (0 == is_registered || ZBX_XMEDIA_ACTION_CREATE == action)
			{
				zbx_xmedia_register_incident(incident_number, eventid, triggerid, media->mediatypeid,
						action);
			}

			DBcommit();
		}

		if (NULL != ticket && NULL != incident_number)
		{
			remedy_update_ticket(ticket, incident_number, incident_status,
					remedy_fields_get_value(fields, ARRSIZE(fields), ZBX_REMEDY_FIELD_ASSIGNEE),
					action, media);
		}
	}

	zbx_free(incident_number);
	zbx_free(incident_status);
	zbx_free(trigger_expression);

	remedy_fields_clean_values(fields, ARRSIZE(fields));

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/*
 * Public API
 */

/******************************************************************************
 *                                                                            *
 * Function: zbx_remedy_process_alert                                         *
 *                                                                            *
 * Purpose: processes an alert by either creating, reopening or just updating *
 *          an incident in Remedy service                                     *
 *                                                                            *
 * Parameters: eventid    - [IN]                                              *
 *             userid     - [IN]                                              *
 *             sendto     - [IN]                                              *
 *             subject    - [IN]                                              *
 *             message    - [IN]                                              *
 *             media      - [IN] the media object containing Remedy service   *
 *                               and ticket information                       *
 *             error      - [OUT] the error description                       *
 *                                                                            *
 * Return value: SUCCEED - the alert was processed successfully               *
 *               FAIL - alert processing failed, error contains               *
 *                      allocated string with error description               *
 *                                                                            *
 * Comments: The caller must free the error description if it was set.        *
 *                                                                            *
 ******************************************************************************/
int	zbx_remedy_process_alert(zbx_uint64_t eventid, zbx_uint64_t userid, const char *sendto, const char *subject,
		const char *message, const struct DB_MEDIATYPE *mediatype, char **error)
{
	const char	*__function_name = "zbx_remedy_process_alert";

	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	ret = remedy_process_event(eventid, userid, sendto, subject, message, mediatype, ZBX_XMEDIA_PROCESS_AUTOMATED,
			NULL, error);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_remedy_query_events                                          *
 *                                                                            *
 * Purpose: retrieves status of Remedy incidents associated to the specified  *
 *          events                                                            *
 *                                                                            *
 * Parameters: mediatype  - [IN] the remedy mediatype data                    *
 *             eventids   - [IN] the events to query                          *
 *             tickets    - [OUT] the incident data                           *
 *             error      - [OUT] the error messagee                          *
 *                                                                            *
 * Comments: The caller must free the error description if it was set and     *
 *           tickets vector contents.                                         *
 *                                                                            *
 ******************************************************************************/
void	zbx_remedy_query_events(const DB_MEDIATYPE *mediatype, zbx_vector_uint64_t *eventids, zbx_vector_ptr_t *tickets)
{
	const char	*__function_name = "zbx_remedy_query_events";
	int		i;

	zbx_remedy_field_t	fields[] = {
			{ZBX_REMEDY_FIELD_STATUS, NULL},
			{ZBX_REMEDY_FIELD_ASSIGNEE, NULL}
	};

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	for (i = 0; i < eventids->values_num; i++)
	{
		zbx_ticket_t	*ticket;
		char		*externalid = NULL;

		ticket = zbx_malloc(NULL, sizeof(zbx_ticket_t));
		memset(ticket, 0, sizeof(zbx_ticket_t));
		ticket->eventid = eventids->values[i];

		if (SUCCEED == zbx_xmedia_get_last_ticketid(ticket->eventid, mediatype->mediatypeid, &externalid) &&
				SUCCEED == remedy_query_ticket(mediatype->smtp_server, mediatype->smtp_helo,
				mediatype->username, mediatype->passwd, externalid, fields, ARRSIZE(fields),
				&ticket->error))
		{
			remedy_update_ticket(ticket, externalid,
					remedy_fields_get_value(fields, ARRSIZE(fields), ZBX_REMEDY_FIELD_STATUS),
					remedy_fields_get_value(fields, ARRSIZE(fields), ZBX_REMEDY_FIELD_ASSIGNEE),
					0, mediatype);
		}

		zbx_vector_ptr_append(tickets, ticket);
		zbx_free(externalid);
	}

	remedy_fields_clean_values(fields, ARRSIZE(fields));

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_remedy_acknowledge_events                                    *
 *                                                                            *
 * Purpose: acknowledges events in Remedy service with specified message      *
 *          subjects and contents                                             *
 *                                                                            *
 * Parameters: mediatype     - [IN] the remedy mediatype data                 *
 *             media         - [IN] the user media                            *
 *             userid        - [IN] the user acknowledging events             *
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
void	zbx_remedy_acknowledge_events(const DB_MEDIATYPE *mediatype, const zbx_media_t *media, zbx_uint64_t userid,
		zbx_vector_ptr_t *acknowledges, zbx_vector_ptr_t *tickets)
{
	const char	*__function_name = "zbx_remedy_acknowledge_events";

	int		i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	for (i = 0; i < acknowledges->values_num; i++)
	{
		zbx_acknowledge_t	*ack = acknowledges->values[i];
		zbx_ticket_t		*ticket;

		ticket = zbx_malloc(NULL, sizeof(zbx_ticket_t));
		memset(ticket, 0, sizeof(zbx_ticket_t));
		ticket->eventid = ack->eventid;

		remedy_process_event(ack->eventid, userid, media->sendto, ack->subject, ack->message, mediatype,
				ZBX_XMEDIA_PROCESS_MANUAL, ticket, &ticket->error);

		zbx_vector_ptr_append(tickets, ticket);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

#else

int	zbx_remedy_process_alert(zbx_uint64_t eventid, zbx_uint64_t userid, const char *sendto, const char *subject,
		const char *message, const struct DB_MEDIATYPE *mediatype, char **error)
{
	ZBX_UNUSED(eventid);
	ZBX_UNUSED(userid);
	ZBX_UNUSED(sendto);
	ZBX_UNUSED(subject);
	ZBX_UNUSED(message);
	ZBX_UNUSED(mediatype);
	ZBX_UNUSED(error);

	*error = zbx_strdup(*error, "Zabbix server is built without Remedy ticket support");
	return FAIL;
}

void	zbx_remedy_query_events(const DB_MEDIATYPE *mediatype, zbx_vector_uint64_t *eventids, zbx_vector_ptr_t *tickets)
{
	int	i;

	ZBX_UNUSED(mediatype);

	for (i = 0; i < eventids->values_num; i++)
	{
		zbx_ticket_t	*ticket;

		ticket = zbx_malloc(NULL, sizeof(zbx_ticket_t));
		memset(ticket, 0, sizeof(zbx_ticket_t));
		ticket->eventid = eventids->values[i];

		ticket->error = zbx_strdup(NULL, "Zabbix server is built without Remedy ticket support");
		zbx_vector_ptr_append(tickets, ticket);
	}
}

void	zbx_remedy_acknowledge_events(const DB_MEDIATYPE *mediatype, const zbx_media_t *media, zbx_uint64_t userid,
		zbx_vector_ptr_t *acknowledges, zbx_vector_ptr_t *tickets)
{
	int	i;

	ZBX_UNUSED(mediatype);
	ZBX_UNUSED(media);
	ZBX_UNUSED(userid);

	for (i = 0; i < acknowledges->values_num; i++)
	{
		zbx_acknowledge_t	*ack = acknowledges->values[i];
		zbx_ticket_t		*ticket;

		ticket = zbx_malloc(NULL, sizeof(zbx_ticket_t));
		memset(ticket, 0, sizeof(zbx_ticket_t));
		ticket->eventid = ack->eventid;

		ticket->error = zbx_strdup(NULL, "Zabbix server is built without Remedy ticket support");
		zbx_vector_ptr_append(tickets, ticket);
	}
}

#endif
