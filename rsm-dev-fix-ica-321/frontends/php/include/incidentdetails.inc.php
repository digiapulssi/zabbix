<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/

/**
 * Get first item value from history_uint table.
 *
 * @param int $itemId
 * @param int $startTime
 *
 * @return string
 */
function getFirstUintValue($itemId, $startTime) {
	$query = DBfetch(DBselect(DBaddLimit(
		'SELECT h.value'.
		' FROM history_uint h'.
		' WHERE h.itemid='.$itemId.
			' AND h.clock<='.$startTime.
		' ORDER BY h.clock ASC',
		1
	)));

	return $query ? $query['value'] : 0;
}

/**
 * Returned boolean indicates either passed value is valid DNS error code.
 *
 * @param int $item_value		Error code.
 * @param int $type				Type of DNS service. Allowed values are RSM_DNSSEC and RSM_DNS.
 *
 * @return bool
 */
function isDNSErrorCode($item_value, $type) {
	if ($type == RSM_DNSSEC) {
		return (ZBX_EC_DNS_UDP_DNSKEY_NONE <= $item_value && $item_value <= ZBX_EC_DNS_UDP_RES_NOADBIT
			|| $item_value == ZBX_EC_DNS_NS_ERRSIG || $item_value == ZBX_EC_DNS_RES_NOADBIT);
	}
	elseif ($type == RSM_DNS) {
		return !($item_value > ZBX_EC_DNS_UDP_NS_NOREPLY
			|| $item_value == ZBX_EC_DNS_UDP_RES_NOREPLY || $item_value == ZBX_EC_DNS_RES_NOREPLY);
	}
	else {
		throw new Exception(_s('Unsupported DNS servcice.'));
	}
}
