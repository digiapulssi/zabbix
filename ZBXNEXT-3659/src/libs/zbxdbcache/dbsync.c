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
#include "log.h"
#include "dbcache.h"

#define ZBX_DBCONFIG_IMPL
#include "dbconfig.h"
#include "dbsync.h"

extern unsigned char	program_type;

/******************************************************************************
 *                                                                            *
 * Function: dbsync_compare_uint64                                            *
 *                                                                            *
 * Purpose: compares 64 bit unsigned integer with a raw database value        *
 *                                                                            *
 ******************************************************************************/
static int	dbsync_compare_uint64(const char *value_raw, zbx_uint64_t value)
{
	zbx_uint64_t	value_ui64;

	ZBX_DBROW2UINT64(value_ui64, value_raw);

	return (value_ui64 == value ? SUCCEED : FAIL);
}

/******************************************************************************
 *                                                                            *
 * Function: dbsync_compare_int                                               *
 *                                                                            *
 * Purpose: compares 32 bit signed integer with a raw database value          *
 *                                                                            *
 ******************************************************************************/
static int	dbsync_compare_int(const char *value_raw, int value)
{
	return (atoi(value_raw) == value ? SUCCEED : FAIL);
}

/******************************************************************************
 *                                                                            *
 * Function: dbsync_compare_uchar                                             *
 *                                                                            *
 * Purpose: compares unsigned character with a raw database value             *
 *                                                                            *
 ******************************************************************************/

static int	dbsync_compare_uchar(const char *value_raw, unsigned char value)
{
	unsigned char	value_uchar;

	ZBX_STR2UCHAR(value_uchar, value_raw);
	return (value_uchar == value ? SUCCEED : FAIL);
}

/******************************************************************************
 *                                                                            *
 * Function: dbsync_compare_str                                               *
 *                                                                            *
 * Purpose: compares string with a raw database value                         *
 *                                                                            *
 ******************************************************************************/

static int	dbsync_compare_str(const char *value_raw, const char *value)
{
	return (0 == strcmp(value_raw, value) ? SUCCEED : FAIL);
}

/******************************************************************************
 *                                                                            *
 * Function: dbsync_add_row                                                   *
 *                                                                            *
 * Purpose: adds a new row to the changeset                                   *
 *                                                                            *
 * Parameter: sync  - [IN] the changeset                                      *
 *            rowid - [IN] the row identifier                                 *
 *            tag   - [IN] the row tag (see ZBX_DBSYNC_ROW_ defines)          *
 *            row   - [IN] the row contents (NULL for ZBX_DBSYNC_ROW_REMOVE)  *
 *                                                                            *
 ******************************************************************************/
static void	dbsync_add_row(zbx_dbsync_t *sync, zbx_uint64_t rowid, unsigned char tag, const DB_ROW dbrow)
{
	int			i;
	zbx_dbsync_row_t	*row;

	row = (zbx_dbsync_row_t *)zbx_malloc(NULL, sizeof(zbx_dbsync_row_t));
	row->rowid = rowid;
	row->tag = tag;

	if (ZBX_DBSYNC_ROW_REMOVE != tag)
	{
		row->row = (char **)zbx_malloc(NULL, sizeof(char *) * sync->columns_num);

		for (i = 0; i < sync->columns_num; i++)
			row->row[i] = (SUCCEED == DBis_null(dbrow[i]) ? NULL : zbx_strdup(NULL, dbrow[i]));
	}
	else
		row->row = NULL;

	zbx_vector_ptr_append(&sync->rows, row);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_dbsync_init                                                  *
 *                                                                            *
 * Purpose: initializes changeset                                             *
 *                                                                            *
 ******************************************************************************/
void	zbx_dbsync_init(zbx_dbsync_t *sync)
{
	sync->columns_num = 0;
	zbx_vector_ptr_create(&sync->rows);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_dbsync_clear                                                 *
 *                                                                            *
 * Purpose: frees resources allocated by changeset                            *
 *                                                                            *
 ******************************************************************************/
void	zbx_dbsync_clear(zbx_dbsync_t *sync)
{
	int			i, j;
	zbx_dbsync_row_t	*row;

	for (i = 0; i < sync->rows.values_num; i++)
	{
		row = (zbx_dbsync_row_t *)sync->rows.values[i];

		if (ZBX_DBSYNC_ROW_ADD == row->tag || ZBX_DBSYNC_ROW_UPDATE == row->tag)
		{
			for (j = 0; j < sync->columns_num; j++)
				zbx_free(row->row[j]);

			zbx_free(row->row);
		}

		zbx_free(row);
	}

	zbx_vector_ptr_destroy(&sync->rows);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_dbsync_get_stats                                             *
 *                                                                            *
 * Purpose: calculates changeset statistics                                   *
 *                                                                            *
 * Parameters: sync  - [IN] the changeset                                     *
 *             stats - [OUT] the statistics                                   *
 *                                                                            *
 ******************************************************************************/
void	zbx_dbsync_get_stats(const zbx_dbsync_t *sync, zbx_dbsync_stats_t *stats)
{
	int			i;
	zbx_dbsync_row_t	*row;

	stats->add_num = 0;
	stats->update_num = 0;
	stats->remove_num = 0;

	for (i = 0; i < sync->rows.values_num; i++)
	{
		row = (zbx_dbsync_row_t *)sync->rows.values[i];
		switch (row->tag)
		{
			case ZBX_DBSYNC_ROW_ADD:
				stats->add_num++;
				break;
			case ZBX_DBSYNC_ROW_UPDATE:
				stats->update_num++;
				break;
			case ZBX_DBSYNC_ROW_REMOVE:
				stats->remove_num++;
				break;
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Function: dbsync_compare_config_row                                        *
 *                                                                            *
 * Purpose: compares config table row with cached configuration data          *
 *                                                                            *
 * Parameter: config - [IN] the cached configuration                          *
 *            row    - [IN] the table row                                     *
 *                                                                            *
 * Return value: SUCCEED - the row matches configuration data                 *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	dbsync_compare_config_row(ZBX_DC_CONFIG_TABLE *config, const DB_ROW row)
{
	int		i;

	if (FAIL == dbsync_compare_int(row[0], config->refresh_unsupported))
		return FAIL;

	if (FAIL == dbsync_compare_uint64(row[1], config->discovery_groupid))
		return FAIL;

	if (FAIL == dbsync_compare_int(row[2], config->snmptrap_logging))
		return FAIL;

	if (FAIL == dbsync_compare_int(row[26], config->default_inventory_mode))
		return FAIL;

	for (i = 0; TRIGGER_SEVERITY_COUNT > i; i++)
	{
		if (FAIL == dbsync_compare_str(row[3 + i], config->severity_name[i]))
			return FAIL;
	}

	/* read housekeeper configuration */
	if (FAIL == dbsync_compare_int(row[9], config->hk.events_mode))
		return FAIL;

	if (FAIL == dbsync_compare_int(row[10], config->hk.events_trigger))
		return FAIL;

	if (FAIL == dbsync_compare_int(row[11], config->hk.events_internal))
		return FAIL;

	if (FAIL == dbsync_compare_int(row[12], config->hk.events_discovery))
		return FAIL;

	if (FAIL == dbsync_compare_int(row[13], config->hk.events_autoreg))
		return FAIL;

	if (FAIL == dbsync_compare_int(row[14], config->hk.services_mode))
		return FAIL;

	if (FAIL == dbsync_compare_int(row[15], config->hk.services))
		return FAIL;

	if (FAIL == dbsync_compare_int(row[16], config->hk.audit_mode))
		return FAIL;

	if (FAIL == dbsync_compare_int(row[17], config->hk.audit))
		return FAIL;

	if (FAIL == dbsync_compare_int(row[18], config->hk.sessions_mode))
		return FAIL;

	if (FAIL == dbsync_compare_int(row[19], config->hk.sessions))
		return FAIL;

	if (FAIL == dbsync_compare_int(row[20], config->hk.history_mode))
		return FAIL;

	if (FAIL == dbsync_compare_int(row[22], config->hk.history))
		return FAIL;

	if (FAIL == dbsync_compare_int(row[21], config->hk.history_global))
		return FAIL;

	if (FAIL == dbsync_compare_int(row[23], config->hk.trends_mode))
		return FAIL;

	if (FAIL == dbsync_compare_int(row[25], config->hk.trends))
		return FAIL;

	if (FAIL == dbsync_compare_int(row[24], config->hk.trends_global))
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_dbsync_compare_config                                        *
 *                                                                            *
 * Purpose: compares config table with cached configuration data              *
 *                                                                            *
 * Parameter: cache - [IN] the configuration cache                            *
 *            sync  - [OUT] the changeset                                     *
 *                                                                            *
 * Return value: SUCCEED - the changeset was successfully calculated          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbsync_compare_config(ZBX_DC_CONFIG *cache, zbx_dbsync_t *sync)
{
	DB_ROW		row;
	DB_RESULT	result;
	unsigned char	tag = ZBX_DBSYNC_ROW_NONE;

	sync->columns_num = 27;

	if (NULL == (result = DBselect("select refresh_unsupported,discovery_groupid,snmptrap_logging,"
				"severity_name_0,severity_name_1,severity_name_2,"
				"severity_name_3,severity_name_4,severity_name_5,"
				"hk_events_mode,hk_events_trigger,hk_events_internal,"
				"hk_events_discovery,hk_events_autoreg,hk_services_mode,"
				"hk_services,hk_audit_mode,hk_audit,hk_sessions_mode,hk_sessions,"
				"hk_history_mode,hk_history_global,hk_history,hk_trends_mode,"
				"hk_trends_global,hk_trends,default_inventory_mode"
			" from config")))
	{
		return FAIL;
	}

	if (NULL == (row = DBfetch(result)))
	{
		if (0 != (program_type & ZBX_PROGRAM_TYPE_SERVER))
			zabbix_log(LOG_LEVEL_ERR, "no records in table 'config'");

		goto out;
	}

	if (NULL == cache->config)
		tag = ZBX_DBSYNC_ROW_ADD;
	else if (FAIL == dbsync_compare_config_row(cache->config, row))
		tag = ZBX_DBSYNC_ROW_UPDATE;

	if (ZBX_DBSYNC_ROW_NONE != tag)
		dbsync_add_row(sync, 0, tag, row);

	if (NULL != (row = DBfetch(result)))	/* config table should have only one record */
		zabbix_log(LOG_LEVEL_ERR, "table 'config' has multiple records");

out:
	DBfree_result(result);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: dbsync_compare_host                                              *
 *                                                                            *
 * Purpose: compares hosts table row with cached configuration data           *
 *                                                                            *
 * Parameter: cache - [IN] the configuration cache                            *
 *            host  - [IN] the cached host                                    *
 *            sync  - [OUT] the changeset                                     *
 *                                                                            *
 * Return value: SUCCEED - the row matches configuration data                 *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	dbsync_compare_host(ZBX_DC_CONFIG *cache, const ZBX_DC_HOST *host, const DB_ROW row)
{
	signed char	ipmi_authtype;
	unsigned char	ipmi_privilege;
	ZBX_DC_IPMIHOST	*ipmihost;

	if (FAIL == dbsync_compare_uint64(row[1], host->proxy_hostid))
		return FAIL;

	if (FAIL == dbsync_compare_uchar(row[22], host->status))
		return FAIL;

	if (FAIL == dbsync_compare_str(row[2], host->host))
		return FAIL;

	if (FAIL == dbsync_compare_str(row[23], host->name))
		return FAIL;

#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	if (FAIL == dbsync_compare_str(row[31], host->tls_issuer))
		return FAIL;

	if (FAIL == dbsync_compare_str(row[32], host->tls_subject))
		return FAIL;

	if ('\0' == *row[33] || '\0' == *row[34])
	{
		if (NULL != host->tls_dc_psk)
			return FAIL;
	}
	else
	{
		if (NULL == host->tls_dc_psk)
			return FAIL;

		if (FAIL == dbsync_compare_str(row[33], host->tls_dc_psk->tls_psk_identity))
			return FAIL;

		if (FAIL == dbsync_compare_str(row[34], host->tls_dc_psk->tls_psk))
			return FAIL;
	}
#endif
	if (FAIL == dbsync_compare_uchar(row[29], host->tls_connect))
		return FAIL;

	if (FAIL == dbsync_compare_uchar(row[30], host->tls_accept))
		return FAIL;

	/* IPMI hosts */

	ipmi_authtype = (signed char)atoi(row[3]);
	ipmi_privilege = (unsigned char)atoi(row[4]);

	if (0 != ipmi_authtype || 2 != ipmi_privilege || '\0' != *row[5] || '\0' != *row[6])	/* useipmi */
	{
		if (NULL == (ipmihost = (ZBX_DC_IPMIHOST *)zbx_hashset_search(&cache->ipmihosts, &host->hostid)))
			return FAIL;

		if (ipmihost->ipmi_authtype != ipmi_authtype)
			return FAIL;

		if (ipmihost->ipmi_privilege != ipmi_privilege)
			return FAIL;

		if (FAIL == dbsync_compare_str(row[5], ipmihost->ipmi_username))
			return FAIL;

		if (FAIL == dbsync_compare_str(row[6], ipmihost->ipmi_password))
			return FAIL;
	}
	else if (NULL != zbx_hashset_search(&cache->ipmihosts, &host->hostid))
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_dbsync_compare_hosts                                         *
 *                                                                            *
 * Purpose: compares hosts table with cached configuration data               *
 *                                                                            *
 * Parameter: cache - [IN] the configuration cache                            *
 *            sync  - [OUT] the changeset                                     *
 *                                                                            *
 * Return value: SUCCEED - the changeset was successfully calculated          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbsync_compare_hosts(ZBX_DC_CONFIG *cache, zbx_dbsync_t *sync)
{
	DB_ROW			row;
	DB_RESULT		result;
	zbx_hashset_t		ids;
	zbx_hashset_iter_t	iter;
	zbx_uint64_t		rowid;
	ZBX_DC_HOST		*host;

#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	if (NULL == (result = DBselect(
			"select hostid,proxy_hostid,host,ipmi_authtype,ipmi_privilege,ipmi_username,"
				"ipmi_password,maintenance_status,maintenance_type,maintenance_from,"
				"errors_from,available,disable_until,snmp_errors_from,"
				"snmp_available,snmp_disable_until,ipmi_errors_from,ipmi_available,"
				"ipmi_disable_until,jmx_errors_from,jmx_available,jmx_disable_until,"
				"status,name,lastaccess,error,snmp_error,ipmi_error,jmx_error,tls_connect,tls_accept"
				",tls_issuer,tls_subject,tls_psk_identity,tls_psk"
			" from hosts"
			" where status in (%d,%d,%d,%d)"
				" and flags<>%d",
			HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED,
			HOST_STATUS_PROXY_ACTIVE, HOST_STATUS_PROXY_PASSIVE,
			ZBX_FLAG_DISCOVERY_PROTOTYPE)))
	{
		return FAIL;
	}

	sync->columns_num = 35;
#else
	if (NULL == (result = DBselect(
			"select hostid,proxy_hostid,host,ipmi_authtype,ipmi_privilege,ipmi_username,"
				"ipmi_password,maintenance_status,maintenance_type,maintenance_from,"
				"errors_from,available,disable_until,snmp_errors_from,"
				"snmp_available,snmp_disable_until,ipmi_errors_from,ipmi_available,"
				"ipmi_disable_until,jmx_errors_from,jmx_available,jmx_disable_until,"
				"status,name,lastaccess,error,snmp_error,ipmi_error,jmx_error,tls_connect,tls_accept"
			" from hosts"
			" where status in (%d,%d,%d,%d)"
				" and flags<>%d",
			HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED,
			HOST_STATUS_PROXY_ACTIVE, HOST_STATUS_PROXY_PASSIVE,
			ZBX_FLAG_DISCOVERY_PROTOTYPE)))
	{
		return FAIL;
	}

	sync->columns_num = 31;
#endif

	zbx_hashset_create(&ids, cache->hosts.num_data, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	while (NULL != (row = DBfetch(result)))
	{
		unsigned char	tag = ZBX_DBSYNC_ROW_NONE;

		ZBX_STR2UINT64(rowid, row[0]);
		zbx_hashset_insert(&ids, &rowid, sizeof(rowid));

		if (NULL == (host = (ZBX_DC_HOST *)zbx_hashset_search(&cache->hosts, &rowid)))
			tag = ZBX_DBSYNC_ROW_ADD;
		else if (FAIL == dbsync_compare_host(cache, host, row))
			tag = ZBX_DBSYNC_ROW_UPDATE;

		if (ZBX_DBSYNC_ROW_NONE != tag)
			dbsync_add_row(sync, rowid, tag, row);

	}

	zbx_hashset_iter_reset(&cache->hosts, &iter);
	while (NULL != (host = (ZBX_DC_HOST *)zbx_hashset_iter_next(&iter)))
	{
		if (NULL == zbx_hashset_search(&ids, &host->hostid))
			dbsync_add_row(sync, host->hostid, ZBX_DBSYNC_ROW_REMOVE, NULL);
	}

	zbx_hashset_destroy(&ids);
	DBfree_result(result);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: dbsync_compare_host_inventory                                    *
 *                                                                            *
 * Purpose: compares host inventory table row with cached configuration data  *
 *                                                                            *
 * Parameter: hi    - [IN] the cached host inventory data                     *
 *            sync  - [OUT] the changeset                                     *
 *                                                                            *
 * Return value: SUCCEED - the row matches configuration data                 *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	dbsync_compare_host_inventory(const ZBX_DC_HOST_INVENTORY *hi, const DB_ROW row)
{
	return dbsync_compare_uchar(row[1], hi->inventory_mode);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_dbsync_compare_host_inventory                                *
 *                                                                            *
 * Purpose: compares host_inventory table with cached configuration data      *
 *                                                                            *
 * Parameter: cache - [IN] the configuration cache                            *
 *            sync  - [OUT] the changeset                                     *
 *                                                                            *
 * Return value: SUCCEED - the changeset was successfully calculated          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbsync_compare_host_inventory(ZBX_DC_CONFIG *cache, zbx_dbsync_t *sync)
{
	DB_ROW			row;
	DB_RESULT		result;
	zbx_hashset_t		ids;
	zbx_hashset_iter_t	iter;
	zbx_uint64_t		rowid;
	ZBX_DC_HOST_INVENTORY	*hi;

	if (NULL == (result = DBselect(
			"select hostid,inventory_mode"
			" from host_inventory")))
	{
		return FAIL;
	}

	sync->columns_num = 2;

	zbx_hashset_create(&ids, cache->host_inventories.num_data, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	while (NULL != (row = DBfetch(result)))
	{
		unsigned char	tag = ZBX_DBSYNC_ROW_NONE;

		ZBX_STR2UINT64(rowid, row[0]);
		zbx_hashset_insert(&ids, &rowid, sizeof(rowid));

		if (NULL == (hi = (ZBX_DC_HOST_INVENTORY *)zbx_hashset_search(&cache->host_inventories, &rowid)))
			tag = ZBX_DBSYNC_ROW_ADD;
		else if (FAIL == dbsync_compare_host_inventory(hi, row))
			tag = ZBX_DBSYNC_ROW_UPDATE;

		if (ZBX_DBSYNC_ROW_NONE != tag)
			dbsync_add_row(sync, rowid, tag, row);

	}

	zbx_hashset_iter_reset(&cache->host_inventories, &iter);
	while (NULL != (hi = (ZBX_DC_HOST_INVENTORY *)zbx_hashset_iter_next(&iter)))
	{
		if (NULL == zbx_hashset_search(&ids, &hi->hostid))
			dbsync_add_row(sync, hi->hostid, ZBX_DBSYNC_ROW_REMOVE, NULL);
	}

	zbx_hashset_destroy(&ids);
	DBfree_result(result);

	return SUCCEED;
}


/******************************************************************************
 *                                                                            *
 * Function: zbx_dbsync_compare_hosts                                         *
 *                                                                            *
 * Purpose: compares items table with cached configuration data               *
 *                                                                            *
 * Parameter: cache - [IN] the configuration cache                            *
 *            sync  - [OUT] the changeset                                     *
 *                                                                            *
 * Return value: SUCCEED - the changeset was successfully calculated          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbsync_compare_items(ZBX_DC_CONFIG *cache, zbx_dbsync_t *sync)
{
	DB_ROW			row;
	DB_RESULT		result;
	zbx_hashset_t		ids;
	zbx_hashset_iter_t	iter;
	zbx_uint64_t		rowid;
	ZBX_DC_ITEM		*item;


	return SUCCEED;
}
