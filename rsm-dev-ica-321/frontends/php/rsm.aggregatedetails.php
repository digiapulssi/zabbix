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
$page['file'] = 'rsm.aggregatedetails.php';
$page['hist_arg'] = ['groupid', 'hostid'];

require_once dirname(__FILE__).'/include/page_header.php';

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields = [
	'tld_host' =>	[T_ZBX_STR, O_OPT,	P_SYS,	null,			null],
	'type' =>		[T_ZBX_INT, O_OPT,	null,	IN('0,1'),		null],
	'time' =>		[T_ZBX_INT, O_OPT,	null,	null,			null],
	'slvItemId' =>	[T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,			null]
];
check_fields($fields);

$data['probes'] = [];
$data['tld_host'] = null;
$data['time'] = null;
$data['slvItemId'] = null;
$data['type'] = null;
$data['errors'] = [];

if (getRequest('tld_host') && getRequest('time') && getRequest('slvItemId') && getRequest('type') !== null) {
	$data['tld_host'] = getRequest('tld_host');
	$data['time'] = getRequest('time');
	$data['slvItemId'] = getRequest('slvItemId');
	$data['type'] = getRequest('type');
	CProfile::update('web.rsm.aggregatedresults.tld_host', $data['tld_host'], PROFILE_TYPE_STR);
	CProfile::update('web.rsm.aggregatedresults.time', $data['time'], PROFILE_TYPE_ID);
	CProfile::update('web.rsm.aggregatedresults.slvItemId', $data['slvItemId'], PROFILE_TYPE_ID);
	CProfile::update('web.rsm.aggregatedresults.type', $data['type'], PROFILE_TYPE_ID);
}
elseif (!getRequest('tld_host') && !getRequest('time') && !getRequest('slvItemId') && getRequest('type') === null) {
	$data['tld_host'] = CProfile::get('web.rsm.aggregatedresults.tld_host');
	$data['time'] = CProfile::get('web.rsm.aggregatedresults.time');
	$data['slvItemId'] = CProfile::get('web.rsm.aggregatedresults.slvItemId');
	$data['type'] = CProfile::get('web.rsm.aggregatedresults.type');
}

if ($data['type'] == RSM_DNS) {
	$data['probes_above_max_rtt'] = [];
}

// check
if ($data['tld_host'] && $data['time'] && $data['slvItemId'] && $data['type'] !== null) {
	$test_time_from = mktime(
		date('H', $data['time']),
		date('i', $data['time']),
		0,
		date('n', $data['time']),
		date('j', $data['time']),
		date('Y', $data['time'])
	);

	$data['testResult'] = null;
	$data['totalProbes'] = 0;

	// Get host with calculated items.
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

	// Get macros old value.
	$macro_items = API::Item()->get([
		'hostids' => $rsm['hostid'],
		'output' => ['itemid', 'key_', 'value_type'],
		'filter' => [
			'key_' => [
				CALCULATED_ITEM_DNS_DELAY,
				CALCULATED_ITEM_DNS_AVAIL_MINNS,
				CALCULATED_ITEM_DNS_UDP_RTT_HIGH
			]
		]
	]);

	foreach ($macro_items as $macro_item) {
		/**
		 * To get value that actually was current at the time when data was collected, we need to get history record
		 * that was newest at the moment of requested time.
		 *
		 * In other words:
		 * SELECT * FROM history_uint WHERE itemid=<itemid> AND <test_time_from> >= clock ORDER BY clock DESC LIMIT 1
		 */
		$macro_item_value = API::History()->get([
			'itemids' => $macro_item['itemid'],
			'time_till' => $test_time_from,
			'history' => $macro_item['value_type'],
			'output' => API_OUTPUT_EXTEND,
			'sortfield' => 'clock',
			'sortorder' => 'DESC',
			'limit' => 1
		]);

		$macro_item_value = reset($macro_item_value);

		if ($macro_item['key_'] === CALCULATED_ITEM_DNS_AVAIL_MINNS) {
			$min_dns_count = $macro_item_value['value'];
		}
		elseif ($macro_item['key_'] === CALCULATED_ITEM_DNS_UDP_RTT_HIGH) {
			$udp_rtt = $macro_item_value['value'];
		}
		else {
			$macro_time = $macro_item_value['value'] - 1;
		}
	}

	// Time calculation.
	$test_time_till = $test_time_from + 59;
	$time_from = $macro_time - 59;
	$test_time_from -= $time_from;

	// Get TLD.
	$tld = API::Host()->get([
		'tlds' => true,
		'output' => ['hostid', 'host', 'name'],
		'filter' => [
			'host' => $data['tld_host']
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

	// Get slv item.
	$slv_items = API::Item()->get([
		'itemids' => $data['slvItemId'],
		'output' => ['name']
	]);

	if ($slv_items) {
		$data['slvItem'] = reset($slv_items);
	}
	else {
		show_error_message(_('No permissions to referred SLV item or it does not exist!'));
		require_once dirname(__FILE__).'/include/page_footer.php';
		exit;
	}

	// Get test result.
	$key = ($data['type'] == RSM_DNS) ? RSM_SLV_DNS_AVAIL : RSM_SLV_DNSSEC_AVAIL;

	// Get items.
	$avail_items = API::Item()->get([
		'output' => ['itemid', 'value_type'],
		'hostids' => $data['tld']['hostid'],
		'filter' => [
			'key_' => $key
		]
	]);

	if ($avail_items) {
		$avail_item = reset($avail_items);

		$test_results = API::History()->get([
			'output' => API_OUTPUT_EXTEND,
			'itemids' => $avail_item['itemid'],
			'time_from' => $test_time_from,
			'time_till' => $test_time_till,
			'history' => $avail_item['value_type'],
			'limit' => 1
		]);

		if (($test_result = reset($test_results)) !== false) {
			$data['testResult'] = $test_result['value'];
		}
	}
	else {
		show_error_message(_s('Item with key "%1$s" not exist on TLD!', $key));
		require_once dirname(__FILE__).'/include/page_footer.php';
		exit;
	}

	// Get probes.
	$probes = API::Host()->get([
		'groupids' => PROBES_MON_GROUPID,
		'output' => ['hostid', 'host'],
		'preservekeys' => true
	]);

	$tld_probe_names = [];
	foreach ($probes as $probe) {
		$pos = strrpos($probe['host'], ' - mon');
		if ($pos === false) {
			show_error_message(_s('Unexpected host name "%1$s" among probe hosts.', $probe['host']));
			continue;
		}

		$tld_probe_name = substr($probe['host'], 0, $pos);

		$data['probes'][$probe['hostid']] = [
			'host' => $tld_probe_name,
			'name' => $tld_probe_name
		];

		$tld_probe_names[$data['tld']['host'] . ' ' . $tld_probe_name] = $probe['hostid'];
	}

	// Get total number of probes for summary block before results table.
	$data['totalProbes'] = count($data['probes']);

	// Get probes for specific TLD.
	$tld_probes = API::Host()->get([
		'output' => ['hostid', 'host', 'name'],
		'filter' => [
			'host' => array_keys($tld_probe_names)
		],
		'preservekeys' => true
	]);

	/**
	 * Select what NameServers are used by each probe.
	 * NameServer host names and IP addresses are extracted from Item keys.
	 *
	 * Following items are used:
	 *  - PROBE_DNS_UDP_ITEM_RTT - for UDP based service monitoring;
	 *
	 * TCP based service monitoring will be implemented in phase 3.
	 */
	$probes_udp_items = API::Item()->get([
		'output' => ['key_', 'itemid', 'value_type'],
		'hostids' => array_keys($tld_probes),
		'selectHosts' => ['host'],
		'search' => [
			'key_' => PROBE_DNS_UDP_ITEM_RTT
		],
		'startSearch' => true,
		'monitored' => true,
		'preservekeys' => true
	]);

	if ($probes_udp_items) {
		$item_values_db = API::History()->get([
			'output' => API_OUTPUT_EXTEND,
			'itemids' => zbx_objectValues($probes_udp_items, 'itemid'),
			'time_from' => $test_time_from,
			'time_till' => $test_time_till,
			'history' => reset($probes_udp_items)['value_type']
		]);

		$item_values = [];
		foreach ($item_values_db as $item_value_db) {
			$item_values[$item_value_db['itemid']] = $item_value_db['value'];
		}
		unset($item_values_db);

		foreach ($probes_udp_items as $probes_item) {
			$probeid = $tld_probe_names[reset($probes_item['hosts'])['host']];
			$item_value = array_key_exists($probes_item['itemid'], $item_values)
				? (int) $item_values[$probes_item['itemid']]
				: 0;

			preg_match('/^[^\[]+\[([^\]]+)]$/', $probes_item['key_'], $matches);
			if (!$matches) {
				show_error_message(_s('Unexpected item key "%1$s".', $probes_item['key_']));
				continue;
			}

			$matches = explode(',', $matches[1]);
			$ipv = filter_var($matches[2], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? 'ipv4' : 'ipv6';

			$data['dns_udp_nameservers'][$matches[1]][$ipv] = $matches[2];
			$data['probes'][$probeid]['results_udp'][$matches[1]][$ipv] = $item_value;

			if ($data['type'] == RSM_DNSSEC) {
				// Holds the number of NameServers with errors for particular probe.
				if (!array_key_exists('probe_nameservers_with_no_errors', $data['probes'][$probeid])) {
					$data['probes'][$probeid]['probe_nameservers_with_no_errors'] = 0;
				}

				/**
				 * DNSSEC is considered to be DOWN if selected value is one of following:
				 * 1) -204 (ZBX_EC_DNS_NS_ERRSIG);
				 * 2) -206 (ZBX_EC_DNS_RES_NOADBIT);
				 * 3) in rage -427 and -401 (ZBX_EC_DNS_UDP_MALFORMED_DNSSEC & ZBX_EC_DNS_UDP_RES_NOADBIT).
				 */
				if (ZBX_EC_DNS_UDP_MALFORMED_DNSSEC <= $item_value && $item_value <= ZBX_EC_DNS_UDP_RES_NOADBIT
					|| $item_value == ZBX_EC_DNS_NS_ERRSIG
					|| $item_value == ZBX_EC_DNS_RES_NOADBIT
				) {
					// Error detected.
				}
				else {
					$data['probes'][$probeid]['probe_nameservers_with_no_errors']++;
				}
			}

			if (0 > $item_value) {
				$error_key = 'udp_'.$matches[1].'_'.$ipv.'_'.$matches[2];

				if (!array_key_exists($item_value, $data['errors'])) {
					$data['errors'][$item_value] = [];
				}

				if (!array_key_exists($error_key, $data['errors'])) {
					$data['errors'][$item_value][$error_key] = 0;
				}

				$data['errors'][$item_value][$error_key]++;
			}
			elseif ($item_value > $udp_rtt && $data['type'] == RSM_DNS) {
				if (!array_key_exists($error_key, $data['probes_above_max_rtt'])) {
					$data['probes_above_max_rtt'][$error_key] = 0;
				}

				$data['probes_above_max_rtt'][$error_key]++;
			}
		}
	}

	// Get status (displayed in columns 'DNS UDP') for each TLD probe.
	$probe_items = API::Item()->get([
		'output' => ['key_', 'hostid'],
		'hostids' => array_keys($tld_probes),
		'filter' => [
			'key_' => PROBE_KEY_ONLINE
		],
		'monitored' => true,
		'preservekeys' => true
	]);

	if ($probe_items) {
		$item_values = API::History()->get([
			'output' => API_OUTPUT_EXTEND,
			'itemids' => array_keys($probe_items),
			'time_from' => $test_time_from
		]);

		$probes_not_offline = [];

		foreach ($item_values as $item_value) {
			$probe_hostid = $probe_items[$item_value['itemid']]['hostid'];

			/**
			 * Value of probe item PROBE_KEY_ONLINE == PROBE_DOWN means that both DNS UDP and DNS TCP are offline.
			 * Support for TCP will be added in phase 3.
			 */
			if ($item_value['value'] == PROBE_DOWN) {
				$data['probes'][$probe_hostid]['status_udp'] = PROBE_OFFLINE;
			}
			elseif ($data['type'] == RSM_DNSSEC) {
				/**
				 * For DNSSEC, if at least one NameServer for particular probe is UP (do not have an errors), the whole
				 * probe is UP.
				 */
				if (array_key_exists('probe_nameservers_with_no_errors', $data['probes'][$probe_hostid])
					&& $data['probes'][$probe_hostid]['probe_nameservers_with_no_errors'] > 0
				) {
					$data['probes'][$probe_hostid]['status_udp'] = PROBE_UP;
				}
				else {
					$data['probes'][$probe_hostid]['status_udp'] = PROBE_OFFLINE;
				}

				unset($data['probes'][$probe_hostid]['probe_nameservers_with_no_errors']);
			}
			else {
				$probes_not_offline[$probe_hostid] = true;
			}
		}

		/**
		 * If probe is not offline we should check values of additional item PROBE_DNS_UDP_ITEM and compare selected
		 * values with value stored in CALCULATED_ITEM_DNS_AVAIL_MINNS.
		 */
		if ($probes_not_offline && $data['type'] == RSM_DNS) {
			$probe_items = API::Item()->get([
				'output' => ['hostid', 'key_'],
				'hostids' => array_keys($probes_not_offline),
				'filter' => [
					'key_' => PROBE_DNS_UDP_ITEM
				],
				'monitored' => true,
				'preservekeys' => true
			]);

			if ($probe_items) {
				$item_values = API::History()->get([
					'output' => API_OUTPUT_EXTEND,
					'itemids' => array_keys($probe_items),
					'time_from' => $test_time_from
				]);

				foreach ($item_values as $item_value) {
					$probe_item = $probe_items[$item_value['itemid']];

					/**
					 * DNS is considered to be UP in following cases:
					 * 1) if selected value is greater than rsm.configvalue[RSM.DNS.AVAIL.MINNS] for <RSM_HOST> at given time;
					 * 2) if selected value is -205 (ZBX_EC_DNS_RES_NOREPLY);
					 * 3) if selected value is -400 (ZBX_EC_DNS_UDP_RES_NOREPLY).
					 */
					if ($item_value['value'] >= $min_dns_count
						|| $item_value['value'] == ZBX_EC_DNS_UDP_RES_NOREPLY
						|| $item_value['value'] == ZBX_EC_DNS_RES_NOREPLY
					) {
						$data['probes'][$probe_item['hostid']]['status_udp'] = PROBE_UP;
					}
					else {
						$data['probes'][$probe_item['hostid']]['status_udp'] = PROBE_DOWN;
					}
				}
			}
		}
	}

	// Get value maps for error messages.
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

	// Get mapped value for test result.
	$test_result_label = getMappedValue($data['testResult'], RSM_SERVICE_AVAIL_VALUE_MAP);
	if (!$test_result_label) {
		$test_result_label = _('No result');
	}

	if ($data['testResult'] === null) {
		$test_result_color = 'grey';
	}
	elseif ($data['testResult'] === PROBE_UP) {
		$test_result_color = 'green';
	}
	else {
		$test_result_color = 'red';
	}

	$data['testResult'] = (new CSpan($test_result_label))->addClass($test_result_color);

	CArrayHelper::sort($data['probes'], ['name']);
}
else {
	access_deny();
}

$rsmView = new CView('rsm.aggregatedetails.list', $data);

$rsmView->render();
$rsmView->show();

require_once dirname(__FILE__).'/include/page_footer.php';
