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

	// Get TLD level macros.
	if ($data['type'] == RSM_RDDS) {
		$tld_templates = API::Template()->get(array(
			'output' => [],
			'filter' => array(
				'host' => ['Template '.$data['tld']['host']]
			),
			'preservekeys' => true
		));

		$template_macros = API::UserMacro()->get(array(
			'output' => ['macro', 'value'],
			'hostids' => array_keys($tld_templates),
			'filter' => array(
				'macro' => array(RSM_TLD_RDDS_ENABLED, RDAP_BASE_URL, RSM_RDAP_TLD_ENABLED)
			)
		));

		$data['tld']['macros'] = [];
		foreach ($template_macros as $template_macro) {
			$data['tld']['macros'][$template_macro['macro']] = $template_macro['value'];
		}
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
			$test_result_label = ($test_result['value'] !== null)
				? getMappedValue($test_result['value'], RSM_SERVICE_AVAIL_VALUE_MAP)
				: false;

			if (!$test_result_label) {
				$test_result_label = _('No result');
				$test_result_color = ZBX_STYLE_GREY;
			}
			else {
				$test_result_color = ($test_result['value'] == PROBE_DOWN) ? ZBX_STYLE_RED : ZBX_STYLE_GREEN;
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
	$tlds_probes = [];
	foreach ($hosts as $host) {
		$pos = strrpos($host['host'], ' - mon');
		if ($pos === false) {
			show_error_message(_s('Unexpected host name "%1$s" among probe hosts.', $host['host']));
			continue;
		}
		$data['probes'][$host['hostid']] = [
			'host' => substr($host['host'], 0, $pos),
			'name' => substr($host['host'], 0, $pos)
		];

		$tlds_probes[] = $data['tld']['host'].' '.$data['probes'][$host['hostid']]['host'];
		$hostIds[] = $host['hostid'];
	}

	$data['totalProbes'] = count($hostIds);

	if ($tlds_probes) {
		$tlds_probes = API::Host()->get([
			'output' => [],
			'filter' => [
				'host' => $tlds_probes
			],
			'monitored_hosts' => true,
			'preservekeys' => true
		]);

		/**
		 * If there are multiple TLD probes, each with different historical value for RDAP_ENABLED, we still take only
		 * the first one, because others will be synchronized in less then minute.
		 */
		$_enabled_itemid = $tlds_probes ? API::Item()->get([
			'output' => ['itemid', 'key_'],
			'hostids' => array_keys($tlds_probes),
			'filter' => [
				'key_' => [RDAP_ENABLED, RDDS_ENABLED]
			]
		]) : null;

		if ($_enabled_itemid) {
			$_enabled_item_map = [
				RDAP_ENABLED => null,
				RDDS_ENABLED => null
			];

			foreach ($_enabled_itemid as $_enabled_itemid) {
				// Only first item should be checked.
				if ($_enabled_item_map[$_enabled_itemid['key_']] === null) {
					$_enabled_item_map[$_enabled_itemid['key_']] = $_enabled_itemid['itemid'];
				}
			}

			$history_values = API::History()->get([
				'output' => API_OUTPUT_EXTEND,
				'itemids' => array_values($_enabled_item_map),
				'time_from' => $testTimeFrom,
				'time_till' => $testTimeTill,
				'limit' => 2
			]);

			// Overwrite selected historical values over macros values selected before.
			foreach ($history_values as $history_value) {
				switch ($history_value['itemid']) {
					case $_enabled_item_map[RDDS_ENABLED]:
						$data['tld']['macros'][RSM_TLD_RDDS_ENABLED] = $history_value['value'];
						break;
					case $_enabled_item_map[RDAP_ENABLED]:
						$data['tld']['macros'][RSM_RDAP_TLD_ENABLED] = $history_value['value'];
						break;
				}
			}
		}
	}

	/**
	 * Since here we have obtained a final (historical) macros values, we can also check if there should be
	 * RDAP base url be displayed.
	 */
	if (!array_key_exists(RSM_RDAP_TLD_ENABLED, $data['tld']['macros'])
			|| $data['tld']['macros'][RSM_RDAP_TLD_ENABLED] != 0) {
		$data['rdap_base_url'] = $data['tld']['macros'][RDAP_BASE_URL];
	}

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

	$hosts = empty($hostNames) ? [] : API::Host()->get([
		'output' => ['hostid', 'host', 'name'],
		'selectParentTemplates' => ['templateid'],
		'filter' => [
			'host' => $hostNames
		],
		'preservekeys' => true
	]);

	// Get hostids; Find probe level macros.
	$hostIds = [];
	$hosted_templates = [];
	$hosts_templates = [];
	foreach ($hosts as &$host) {
		$hostIds[$host['hostid']] = $host['hostid'];

		$host['macros'] = [];
		foreach ($host['parentTemplates'] as $parent_template) {
			$hosts_templates[$host['hostid']][$parent_template['templateid']] = true;
			$hosted_templates[$parent_template['templateid']] = true;
		}
		unset($host['parentTemplates']);
	}
	unset($host);

	$probe_macros = API::UserMacro()->get(array(
		'output' => ['hostid', 'macro', 'value'],
		'hostids' => array_merge(array_keys($hosted_templates), $hostIds),
		'filter' => array(
			'macro' => RSM_RDDS_ENABLED
		)
	));

	foreach ($probe_macros as $probe_macro) {
		$hostid = null;

		if (array_key_exists($probe_macro['hostid'], $hosts_templates)) {
			$hostid = $probe_macro['hostid'];
		}
		else {
			foreach ($hosts_templates as $host => $templates) {
				if (array_key_exists($probe_macro['hostid'], $templates)) {
					$hostid = $host;
				}
			}
		}

		if ($hostid && array_key_exists($hostid, $hosts)) {
			$hosts[$hostid]['macros'][$probe_macro['macro']] = $probe_macro['value'];

			// No need to select items for disabled probes.
			if ($probe_macro['macro'] === RSM_RDDS_ENABLED && $probe_macro['value'] == 0) {
				unset($hostIds[$hostid]);
			}
		}
	}
	unset($hosts_templates, $hosted_templates);

	// get only used items
	if ($data['type'] == RSM_DNS || $data['type'] == RSM_DNSSEC) {
		$probeItemKey = ' AND (i.key_ LIKE ('.zbx_dbstr(PROBE_DNS_UDP_ITEM_RTT.'%').') OR i.key_='.zbx_dbstr(PROBE_DNS_UDP_ITEM).')';
	}
	elseif ($data['type'] == RSM_RDDS) {
		$items_to_check = [];
		$probeItemKey = [];

		if (!isset($data['tld']['macros'][RSM_RDAP_TLD_ENABLED]) || $data['tld']['macros'][RSM_RDAP_TLD_ENABLED] != 0) {
			$items_to_check[] = PROBE_RDAP_IP;
			$items_to_check[] = PROBE_RDAP_RTT;
			$probeItemKey[] = 'i.key_ LIKE ('.zbx_dbstr(PROBE_RDAP_ITEM.'%').')';
		}
		if (!isset($data['tld']['macros'][RSM_TLD_RDDS_ENABLED]) || $data['tld']['macros'][RSM_TLD_RDDS_ENABLED] != 0) {
			$items_to_check[] = PROBE_RDDS43_IP;
			$items_to_check[] = PROBE_RDDS43_RTT;
			$items_to_check[] = PROBE_RDDS80_IP;
			$items_to_check[] = PROBE_RDDS80_RTT;
			$probeItemKey[] = 'i.key_ LIKE ('.zbx_dbstr(PROBE_RDDS_ITEM.'%').')';
		}

		if ($items_to_check) {
			$probeItemKey[] = dbConditionString('i.key_', $items_to_check);
		}
		$probeItemKey = $probeItemKey ? ' AND ('.implode(' OR ', $probeItemKey).')' : '';
	}
	else {
		$probeItemKey = ' AND (i.key_ LIKE ('.zbx_dbstr(PROBE_EPP_RESULT.'%').')'.
		' OR '.dbConditionString('i.key_', [PROBE_EPP_IP, PROBE_EPP_UPDATE, PROBE_EPP_INFO, PROBE_EPP_LOGIN]).')';
	}

	// get items
	$items = ($probeItemKey !== '') ? DBselect(
		'SELECT i.itemid,i.key_,i.hostid,i.value_type,i.valuemapid,i.units'.
		' FROM items i'.
		' WHERE '.dbConditionInt('i.hostid', $hostIds).
			' AND i.status='.ITEM_STATUS_ACTIVE.
			$probeItemKey
	) : null;

	$nsArray = [];

	// get items value
	if ($items) {
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
						|| $itemValue['value'] == ZBX_EC_DNS_UDP_RES_NOREPLY)) {
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
					if (ZBX_EC_DNS_UDP_DNSKEY_NONE <= $itemValue['value'] && $itemValue['value'] <= ZBX_EC_DNS_UDP_RES_NOADBIT) {
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
					if ($itemValue['value']) {
						//$rtt_value = convert_units(['value' => $itemValue['value'], 'units' => $item['units']]);
						$rtt_value = convert_units(['value' => $itemValue['value']]);
						$hosts[$item['hostid']]['rdds43']['rtt'] = [
							'description' => $rtt_value < 0 ? applyValueMap($rtt_value, $item['valuemapid']) : null,
							'value' => $rtt_value
						];
					}
				}
				elseif ($item['key_'] == PROBE_RDDS80_IP) {
					$hosts[$item['hostid']]['rdds80']['ip'] = $itemValue['value'];
				}
				elseif ($item['key_'] == PROBE_RDDS80_RTT) {
					if ($itemValue['value']) {
						//$rtt_value = convert_units(['value' => $itemValue['value'], 'units' => $item['units']]);
						$rtt_value = convert_units(['value' => $itemValue['value']]);
						$hosts[$item['hostid']]['rdds80']['rtt'] = [
							'description' => $rtt_value < 0 ? applyValueMap($rtt_value, $item['valuemapid']) : null,
							'value' => $rtt_value
						];
					}
				}
				elseif ($item['key_'] == PROBE_RDAP_IP) {
					$hosts[$item['hostid']]['rdap']['ip'] = $itemValue['value'];
				}
				elseif ($item['key_'] == PROBE_RDAP_RTT) {
					if ($itemValue['value']) {
						//$rtt_value = convert_units(['value' => $itemValue['value'], 'units' => $item['units']]);
						$rtt_value = convert_units(['value' => $itemValue['value']]);
						$hosts[$item['hostid']]['rdap']['rtt'] = [
							'description' => $rtt_value < 0 ? applyValueMap($rtt_value, $item['valuemapid']) : null,
							'value' => $rtt_value
						];
					}
				}
				elseif (substr($item['key_'], 0, strlen(PROBE_RDAP_ITEM)) === PROBE_RDAP_ITEM) {
					$hosts[$item['hostid']]['value_rdap'] = $itemValue['value'];
				}
				elseif (substr($item['key_'], 0, strlen(PROBE_RDDS_ITEM)) === PROBE_RDDS_ITEM) {
					if (!array_key_exists(RSM_TLD_RDDS_ENABLED, $data['tld']['macros'])
							|| $data['tld']['macros'][RSM_TLD_RDDS_ENABLED] != 0) {
						preg_match('/^[^\[]+\[([^\]]+)]$/', $item['key_'], $matches);
						list($tld_macros, $rdds_43, $rdds_80) = explode(',', $matches[1]);

						$data['rdds_43_base_url'] = trim($rdds_43, '"');
						$data['rdds_80_base_url'] = trim($rdds_80, '"');
					}

					$hosts[$item['hostid']]['value'] = $itemValue['value'];
				}

				// Count result for table bottom summary rows.
				if ($item['key_'] == PROBE_RDAP_RTT && 0 > $itemValue['value']) {
					$error_code = (int)$itemValue['value'];

					if (!array_key_exists($error_code, $data['errors'])) {
						$data['errors'][$error_code] = [
							'description' => applyValueMap($error_code, $item['valuemapid'])
						];
					}
					if (!array_key_exists('rdap', $data['errors'][$error_code])) {
						$data['errors'][$error_code]['rdap'] = 0;
					}

					$data['errors'][$error_code]['rdap']++;
				}
				elseif ($item['key_'] == PROBE_RDDS43_RTT || $item['key_'] == PROBE_RDDS80_RTT) {
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
				$hosts[$hostId]['class'] = ZBX_STYLE_RED;
			}
			else {
				$hosts[$hostId]['class'] = ZBX_STYLE_GREEN;
			}
		}
	}

	foreach ($hosts as $host) {
		foreach ($data['probes'] as $hostId => $probe) {
			if (mb_strtoupper($host['host']) == mb_strtoupper($data['tld']['host'].' '.$probe['host'])) {
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
