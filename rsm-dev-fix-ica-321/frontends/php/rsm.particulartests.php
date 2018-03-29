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


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/incidentdetails.inc.php';

$page['title'] = _('Details of particular test');
$page['file'] = 'rsm.particulartests.php';
$page['hist_arg'] = ['groupid', 'hostid'];

require_once dirname(__FILE__).'/include/page_header.php';

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields = [
	'host' =>		[T_ZBX_STR, O_OPT,	P_SYS,	null,			null],
	'type' =>		[T_ZBX_INT, O_OPT,	null,	IN('0,1,2,3'),	null],
	'time' =>		[T_ZBX_INT, O_OPT,	null,	null,			null],
	'slvItemId' =>	[T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,			null]
];
check_fields($fields);

$data['probes'] = [];
$data['host'] = null;
$data['time'] = null;
$data['slvItemId'] = null;
$data['type'] = null;
$data['errors'] = [];

if (getRequest('host') && getRequest('time') && getRequest('slvItemId') && getRequest('type') !== null) {
	$data['host'] = getRequest('host');
	$data['time'] = getRequest('time');
	$data['slvItemId'] = getRequest('slvItemId');
	$data['type'] = getRequest('type');
	CProfile::update('web.rsm.particulartests.host', $data['host'], PROFILE_TYPE_STR);
	CProfile::update('web.rsm.particulartests.time', $data['time'], PROFILE_TYPE_ID);
	CProfile::update('web.rsm.particulartests.slvItemId', $data['slvItemId'], PROFILE_TYPE_ID);
	CProfile::update('web.rsm.particulartests.type', $data['type'], PROFILE_TYPE_ID);
}
elseif (!getRequest('host') && !getRequest('time') && !getRequest('slvItemId') && getRequest('type') === null) {
	$data['host'] = CProfile::get('web.rsm.particulartests.host');
	$data['time'] = CProfile::get('web.rsm.particulartests.time');
	$data['slvItemId'] = CProfile::get('web.rsm.particulartests.slvItemId');
	$data['type'] = CProfile::get('web.rsm.particulartests.type');
}

// check
if ($data['host'] && $data['time'] && $data['slvItemId'] && $data['type'] !== null) {
	$testTimeFrom = mktime(
		date('H', $data['time']),
		date('i', $data['time']),
		0,
		date('n', $data['time']),
		date('j', $data['time']),
		date('Y', $data['time'])
	);

	$data['totalProbes'] = 0;

	// macro
	if ($data['type'] == RSM_DNS || $data['type'] == RSM_DNSSEC) {
		$calculatedItemKey[] = CALCULATED_ITEM_DNS_DELAY;

		if ($data['type'] == RSM_DNS) {
			$data['downProbes'] = 0;
		}
		else {
			$data['totalTests'] = 0;
		}
	}
	elseif ($data['type'] == RSM_RDDS) {
		$calculatedItemKey[] = CALCULATED_ITEM_RDDS_DELAY;
	}
	else {
		$calculatedItemKey[] = CALCULATED_ITEM_EPP_DELAY;
	}

	if ($data['type'] == RSM_DNS) {
		$calculatedItemKey[] = CALCULATED_ITEM_DNS_AVAIL_MINNS;
		$calculatedItemKey[] = CALCULATED_ITEM_DNS_UDP_RTT_HIGH;
	}

	// get host with calculated items
	$rsm = API::Host()->get([
		'output' => ['hostid'],
		'filter' => [
			'host' => RSM_HOST
		]
	]);

	if ($rsm) {
		$rsm = reset($rsm);
	}
	else {
		show_error_message(_s('No permissions to referred host "%1$s" or it does not exist!', RSM_HOST));
		require_once dirname(__FILE__).'/include/page_footer.php';
		exit;
	}

	// get macros old value
	$macroItems = API::Item()->get([
		'hostids' => $rsm['hostid'],
		'output' => ['itemid', 'key_', 'value_type'],
		'filter' => [
			'key_' => $calculatedItemKey
		]
	]);

	foreach ($macroItems as $macroItem) {
		$macroItemValue = API::History()->get([
			'itemids' => $macroItem['itemid'],
			'time_from' => $testTimeFrom,
			'history' => $macroItem['value_type'],
			'output' => API_OUTPUT_EXTEND,
			'limit' => 1
		]);

		$macroItemValue = reset($macroItemValue);

		if ($data['type'] == RSM_DNS) {
			if ($macroItem['key_'] == CALCULATED_ITEM_DNS_AVAIL_MINNS) {
				$minDnsCount = $macroItemValue['value'];
			}
			elseif ($macroItem['key_'] == CALCULATED_ITEM_DNS_UDP_RTT_HIGH) {
				$udpRtt = $macroItemValue['value'];
			}
			else {
				$macroTime = $macroItemValue['value'] - 1;
			}
		}
		else {
			$macroTime = $macroItemValue['value'] - 1;
		}
	}

	// time calculation
	$testTimeTill = $testTimeFrom + 59;
	$timeFrom = $macroTime - 59;
	$testTimeFrom -= $timeFrom;

	// get TLD
	$tld = API::Host()->get([
		'tlds' => true,
		'output' => ['hostid', 'host', 'name'],
		'filter' => [
			'host' => $data['host']
		]
	]);

	if ($tld) {
		$data['tld'] = reset($tld);
	}
	else {
		show_error_message(_('No permissions to referred TLD or it does not exist!'));
		require_once dirname(__FILE__).'/include/page_footer.php';
		exit;
	}

	// get slv item
	$slvItems = API::Item()->get([
		'itemids' => $data['slvItemId'],
		'output' => ['name']
	]);

	if ($slvItems) {
		$data['slvItem'] = reset($slvItems);
	}
	else {
		show_error_message(_('No permissions to referred SLV item or it does not exist!'));
		require_once dirname(__FILE__).'/include/page_footer.php';
		exit;
	}

	// get test resut
	if ($data['type'] == RSM_DNS) {
		$key = RSM_SLV_DNS_AVAIL;
	}
	elseif ($data['type'] == RSM_DNSSEC) {
		$key = RSM_SLV_DNSSEC_AVAIL;
	}
	elseif ($data['type'] == RSM_RDDS) {
		$key = RSM_SLV_RDDS_AVAIL;
	}
	else {
		$key = RSM_SLV_EPP_AVAIL;
	}

	// get items
	$availItems = API::Item()->get([
		'output' => ['itemid', 'value_type'],
		'hostids' => $data['tld']['hostid'],
		'filter' => [
			'key_' => $key
		],
		'preservekeys' => true
	]);

	if ($availItems) {
		$availItem = reset($availItems);
		$testResults = API::History()->get([
			'output' => API_OUTPUT_EXTEND,
			'itemids' => $availItem['itemid'],
			'time_from' => $testTimeFrom,
			'time_till' => $testTimeTill,
			'history' => $availItem['value_type'],
			'limit' => 1
		]);

		$test_result = reset($testResults);
		if ($test_result === false) {
			$test_result['value'] = null;
	}

		// Get mapped value for test result.
		if (in_array($data['type'], [RSM_DNS, RSM_DNSSEC, RSM_RDDS])) {
			$test_result_label = $test_result['value']
				? getMappedValue($test_result['value'], RSM_SERVICE_AVAIL_VALUE_MAP)
				: false;

			if (!$test_result_label) {
				$test_result_label = _('No result');
				$test_result_color = 'grey';
			}
	else {
				$test_result_color = ($test_result['value'] == PROBE_DOWN) ? 'red' : 'green';
			}

			$data['testResult'] = (new CSpan($test_result_label))->addClass($test_result_color);
		}
		else {
			$data['testResult'] = $test_result['value'];
		}
	}
	else {
		show_error_message(_s('Item with key "%1$s" not exist on TLD!', $key));
		require_once dirname(__FILE__).'/include/page_footer.php';
		exit;
	}

	// get probes
	$hosts = API::Host()->get([
		'groupids' => PROBES_MON_GROUPID,
		'output' => ['hostid', 'host'],
		'preservekeys' => true
	]);

	$hostIds = [];
	foreach ($hosts as $host) {
		$pos = strrpos($host['host'], " - mon");
		if ($pos === false) {
			show_error_message(_s('Unexpected host name "%1$s" among probe hosts.', $host['host']));
			continue;
		}
		$data['probes'][$host['hostid']] = [
			'host' => substr($host['host'], 0, $pos),
			'name' => substr($host['host'], 0, $pos)
		];
		$hostIds[] = $host['hostid'];
	}

	$data['totalProbes'] = count($hostIds);

	// Get probe status.
	$probeItems = API::Item()->get([
		'output' => ['itemid', 'key_', 'hostid'],
		'hostids' => $hostIds,
		'filter' => [
			'key_' => PROBE_KEY_ONLINE
		],
		'monitored' => true,
		'preservekeys' => true
	]);

	foreach ($probeItems as $probeItem) {
		$itemValue = DBfetch(DBselect(
			'SELECT h.value'.
			' FROM history_uint h'.
			' WHERE h.itemid='.$probeItem['itemid'].
				' AND h.clock='.$testTimeFrom
		));
		if ($itemValue && $itemValue['value'] == PROBE_DOWN) {
			$data['probes'][$probeItem['hostid']]['status'] = PROBE_DOWN;
		}
	}

	$hostNames = [];

	// get probes data hosts
	foreach ($data['probes'] as $hostId => $probe) {
		if (!isset($probe['status'])) {
			$hostNames[] = $data['tld']['host'].' '.$probe['host'];
		}
	}

	$hosts = API::Host()->get([
		'output' => ['hostid', 'host', 'name'],
		'filter' => [
			'host' => $hostNames
		],
		'preservekeys' => true
	]);

	$hostIds = [];
	foreach ($hosts as $host) {
		$hostIds[] = $host['hostid'];
	}

	// get only used items
	if ($data['type'] == RSM_DNS || $data['type'] == RSM_DNSSEC) {
		$probeItemKey = ' AND (i.key_ LIKE ('.zbx_dbstr(PROBE_DNS_UDP_ITEM_RTT.'%').') OR i.key_='.zbx_dbstr(PROBE_DNS_UDP_ITEM).')';
	}
	elseif ($data['type'] == RSM_RDDS) {
		$probeItemKey = ' AND (i.key_ LIKE ('.zbx_dbstr(PROBE_RDDS_ITEM.'%').')'.
			' OR '.dbConditionString('i.key_',
				[PROBE_RDDS43_IP, PROBE_RDDS43_RTT, PROBE_RDDS43_UPD, PROBE_RDDS80_IP, PROBE_RDDS80_RTT]
			).
		')';
	}
	else {
		$probeItemKey = ' AND (i.key_ LIKE ('.zbx_dbstr(PROBE_EPP_RESULT.'%').')'.
		' OR '.dbConditionString('i.key_', [PROBE_EPP_IP, PROBE_EPP_UPDATE, PROBE_EPP_INFO, PROBE_EPP_LOGIN]).')';
	}

	// get items
	$items = DBselect(
		'SELECT i.itemid,i.key_,i.hostid,i.value_type,i.valuemapid,i.units'.
		' FROM items i'.
		' WHERE '.dbConditionInt('i.hostid', $hostIds).
			' AND i.status='.ITEM_STATUS_ACTIVE.
			$probeItemKey
	);

	$nsArray = [];

	// get items value
	while ($item = DBfetch($items)) {
		$itemValue = API::History()->get([
			'itemids' => $item['itemid'],
			'time_from' => $testTimeFrom,
			'time_till' => $testTimeTill,
			'history' => $item['value_type'],
			'output' => API_OUTPUT_EXTEND
		]);

		$itemValue = reset($itemValue);

		if ($data['type'] == RSM_DNS && $item['key_'] === PROBE_DNS_UDP_ITEM) {
			$hosts[$item['hostid']]['result'] = $itemValue ? $itemValue['value'] : null;
		}
		elseif ($data['type'] == RSM_DNS && mb_substr($item['key_'], 0, 16) == PROBE_DNS_UDP_ITEM_RTT) {
			preg_match('/^[^\[]+\[([^\]]+)]$/', $item['key_'], $matches);
			$nsValues = explode(',', $matches[1]);

			if (!$itemValue) {
				$nsArray[$item['hostid']][$nsValues[1]]['value'][] = NS_NO_RESULT;
			}
			elseif ($itemValue['value'] < $udpRtt
					&& ($itemValue['value'] > ZBX_EC_DNS_UDP_NS_NOREPLY
					|| $itemValue['value'] == ZBX_EC_DNS_UDP_RES_NOREPLY || $itemValue['value'] == ZBX_EC_DNS_RES_NOREPLY)) {
				$nsArray[$item['hostid']][$nsValues[1]]['value'][] = NS_UP;
			}
			else {
				$nsArray[$item['hostid']][$nsValues[1]]['value'][] = NS_DOWN;
			}
		}
		elseif ($data['type'] == RSM_DNSSEC && mb_substr($item['key_'], 0, 16) == PROBE_DNS_UDP_ITEM_RTT) {
			if (!isset($hosts[$item['hostid']]['value'])) {
				$hosts[$item['hostid']]['value']['ok'] = 0;
				$hosts[$item['hostid']]['value']['fail'] = 0;
				$hosts[$item['hostid']]['value']['total'] = 0;
				$hosts[$item['hostid']]['value']['noResult'] = 0;
			}

			if ($itemValue) {
				if (ZBX_EC_DNS_UDP_DNSKEY_NONE <= $itemValue['value'] && $itemValue['value'] <= ZBX_EC_DNS_UDP_RES_NOADBIT
						|| $itemValue['value'] == ZBX_EC_DNS_NS_ERRSIG || $itemValue['value'] == ZBX_EC_DNS_RES_NOADBIT) {
					$hosts[$item['hostid']]['value']['fail']++;
				}
				else {
					$hosts[$item['hostid']]['value']['ok']++;
				}
			}
			else {
				$hosts[$item['hostid']]['value']['noResult']++;
			}

			$hosts[$item['hostid']]['value']['total']++;
		}
		elseif ($data['type'] == RSM_RDDS) {
			if ($item['key_'] == PROBE_RDDS43_IP) {
				$hosts[$item['hostid']]['rdds43']['ip'] = $itemValue['value'];
			}
			elseif ($item['key_'] == PROBE_RDDS43_RTT) {
				$rtt_value = convert_units(['value' => $itemValue['value'], 'units' => $item['units']]);
				$hosts[$item['hostid']]['rdds43']['rtt'] = [
					'description' => $itemValue['value'] ? applyValueMap($rtt_value, $item['valuemapid']) : null,
					'value' => $rtt_value
				];
			}
			elseif ($item['key_'] == PROBE_RDDS43_UPD) {
				$hosts[$item['hostid']]['rdds43']['upd'] = $itemValue['value']
					? applyValueMap(convert_units(['value' => $itemValue['value'], 'units' => $item['units']]), $item['valuemapid'])
					: null;
			}
			elseif ($item['key_'] == PROBE_RDDS80_IP) {
				$hosts[$item['hostid']]['rdds80']['ip'] = $itemValue['value'];
			}
			elseif ($item['key_'] == PROBE_RDDS80_RTT) {
				$rtt_value = convert_units(['value' => $itemValue['value'], 'units' => $item['units']]);
				$hosts[$item['hostid']]['rdds80']['rtt'] = [
					'description' => $itemValue['value'] ? applyValueMap($rtt_value, $item['valuemapid']) : null,
					'value' => $rtt_value
				];
			}
			else {
				$hosts[$item['hostid']]['value'] = $itemValue['value'];
			}

			// Count result for table bottom summary rows.
			if ($item['key_'] == PROBE_RDDS43_RTT || $item['key_'] == PROBE_RDDS80_RTT) {
				$column = $item['key_'] == PROBE_RDDS43_RTT ? 'rdds43' : 'rdds80';

				if (0 > $itemValue['value']) {
					$error_code = (int)$itemValue['value'];

					if (!array_key_exists($error_code, $data['errors'])) {
						$data['errors'][$error_code] = [
							'description' => applyValueMap($error_code, $item['valuemapid'])
						];
					}
					if (!array_key_exists($column, $data['errors'][$error_code])) {
						$data['errors'][$error_code][$column] = 0;
					}

					$data['errors'][$error_code][$column]++;
				}
			}
		}
		elseif ($data['type'] == RSM_EPP) {
			if ($item['key_'] == PROBE_EPP_IP) {
				$hosts[$item['hostid']]['ip'] = $itemValue['value'];
			}
			elseif ($item['key_'] == PROBE_EPP_UPDATE) {
				$hosts[$item['hostid']]['update'] = $itemValue['value']
					? applyValueMap(convert_units(['value' => $itemValue['value'], 'units' => $item['units']]), $item['valuemapid'])
					: null;
			}
			elseif ($item['key_'] == PROBE_EPP_INFO) {
				$hosts[$item['hostid']]['info'] = $itemValue['value']
					? applyValueMap(convert_units(['value' => $itemValue['value'], 'units' => $item['units']]), $item['valuemapid'])
					: null;
			}
			elseif ($item['key_'] == PROBE_EPP_LOGIN) {
				$hosts[$item['hostid']]['login'] = $itemValue['value']
					? applyValueMap(convert_units(['value' => $itemValue['value'], 'units' => $item['units']]), $item['valuemapid'])
					: null;
			}
			else {
				$hosts[$item['hostid']]['value'] = $itemValue['value'];
			}
		}
	}

	// Sort errors.
	krsort($data['errors']);

	if ($data['type'] == RSM_DNS) {
		foreach ($nsArray as $hostId => $nss) {
			$hosts[$hostId]['value']['fail'] = 0;

			foreach ($nss as $nsName => $nsValue) {
				if (in_array(NS_DOWN, $nsValue['value'])) {
					$hosts[$hostId]['value']['fail']++;
				}
			}

			// calculate Down probes
			if (count($nss) - $hosts[$hostId]['value']['fail'] < $minDnsCount) {
				$data['downProbes']++;
				$hosts[$hostId]['class'] = 'red';
			}
			else {
				$hosts[$hostId]['class'] = 'green';
			}
		}
	}

	foreach ($hosts as $host) {
		foreach ($data['probes'] as $hostId => $probe) {
			if (mb_strtoupper($host['host']) == mb_strtoupper($data['tld']['host'].' '.$probe['host'])
					&& isset($host['value'])) {
				$data['probes'][$hostId] = $host;
				$data['probes'][$hostId]['name'] = $probe['host'];
				break;
			}
		}
	}

	// Get value maps for error messages.
	if ($data['type'] == RSM_RDDS) {
		$error_msg_value_map = API::ValueMap()->get([
			'output' => [],
			'selectMappings' => ['value', 'newvalue'],
			'valuemapids' => [RSM_DNS_RTT_ERRORS_VALUE_MAP]
		]);

		if ($error_msg_value_map) {
			foreach ($error_msg_value_map[0]['mappings'] as $val) {
				$data['error_msgs'][$val['value']] = $val['newvalue'];
			}
		}
	}

	CArrayHelper::sort($data['probes'], ['name']);
}
else {
	access_deny();
}

$rsmView = new CView('rsm.particulartests.list', $data);

$rsmView->render();
$rsmView->show();

require_once dirname(__FILE__).'/include/page_footer.php';
