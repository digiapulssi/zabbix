<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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


$widget = (new CWidget())->setTitle(_('Details of particular test'));

// Create table header.
$row_1 = (new CTag('tr', true))
	->addItem((new CTag('th', true, _('Probe ID')))->setAttribute('rowspan', 2))
	->addItem((new CTag('th', true, _('DNS UDP')))->setAttribute('rowspan', 2));

$row_2 = [];

if (array_key_exists('dns_udp_nameservers', $data)) {
	foreach ($data['dns_udp_nameservers'] as $ns_name => $ns_ips) {
		$row_1->addItem((new CTag('th', true, $ns_name))->setAttribute('colspan', count($ns_ips)));
		$row_2 = array_merge($row_2, array_values($ns_ips));
	}
}

$table = (new CTableInfo())->setMultirowHeader([$row_1, new CRowHeader($row_2)], count($row_2) + 3);

$down = (new CSpan(_('Down')))->addClass('red');
$offline = (new CSpan(_('Offline')))->addClass('grey');
$no_result = (new CSpan(_('No result')))->addClass('grey');
$up = (new CSpan(_('Up')))->addClass('green');

// Results summary.
$offline_probes = 0;
$no_result_probes = 0;
$down_probes = 0;

// Add results for each probe.
foreach ($data['probes'] as $probe) {
	if (array_key_exists('status_udp', $probe)) {
		if ($probe['status_udp'] == PROBE_OFFLINE) {
			$udp_status = $offline;
			$offline_probes++;
		}
		elseif ($probe['status_udp'] == PROBE_DOWN) {
			$udp_status = $down;
			$down_probes++;
		}
		elseif ($probe['status_udp'] == PROBE_UP) {
			$udp_status = $up;
		}
	}
	else {
		$udp_status = $no_result;
		$no_result_probes++;
	}

	$row = [$probe['name'], $udp_status];

	if (array_key_exists('results_udp', $probe)) {
		foreach ($probe['results_udp'] as $result_udp) {
			foreach ($result_udp as $result) {
				$row[] = 0 > $result
					? (new CSpan($result))->setHint($data['error_msgs'][$result])
					: $result;
			}
		}
	}

	$table->addRow($row);
}

// Add error rows at the bottom of table.
foreach ($data['errors'] as $error_code => $errors) {
	$row = [
		(new CSpan(_('Total ') . $error_code))->setHint($data['error_msgs'][$error_code]),
		''
	];

	// Add number of error cells.
	foreach ($data['dns_udp_nameservers'] as $ns_name => $ns_ips) {
		foreach ($ns_ips as $ipv => $ip) {
			$error_key = 'udp_'.$ns_name.'_'.$ipv.'_'.$ip;
			$row[] = array_key_exists($error_key, $errors) ? $errors[$error_key] : 0;
		}
	}

	$table->addRow($row);
}

if ($data['type'] == RSM_DNS) {
	// Add 'Total above max rtt' row:
	$row = [
		_('Total above max. RTT'),
		''
	];
	foreach ($data['dns_udp_nameservers'] as $ns_name => $ns_ips) {
		foreach ($ns_ips as $ipv => $ip) {
			$error_key = 'udp_'.$ns_name.'_'.$ipv.'_'.$ip;
			$row[] = array_key_exists($error_key, $data['probes_above_max_rtt']) ? $errors[$error_key] : 0;
		}
	}
	$table->addRow($row);
}

// Construct summary.
$addition_info = [
	new CSpan([bold(_('Probes total')), ':', SPACE, $data['totalProbes']]),
	BR(),
	new CSpan([bold(_('Probes offline')), ':', SPACE, $offline_probes]),
	BR(),
	new CSpan([bold(_('Probes with No Result')), ':', SPACE, $no_result_probes]),
	BR(),
	new CSpan([bold(_('Probes with Result')), ':', SPACE,
		$data['totalProbes'] - $offline_probes - $no_result_probes
	]),
	BR(),
	new CSpan([bold(_('Probes Up')), ':', SPACE,
		$data['totalProbes'] - $offline_probes - $no_result_probes - $down_probes
	]),
	BR(),
	new CSpan([bold(_('Probes Down')), ':', SPACE, $down_probes])
];

$particular_test = [
	new CSpan([bold(_('TLD')), ':', SPACE, $data['tld']['name']]),
	BR(),
	new CSpan([bold(_('Service')), ':', SPACE, $data['slvItem']['name']]),
	BR(),
	new CSpan([bold(_('Test time')), ':', SPACE, date(DATE_TIME_FORMAT_SECONDS, $data['time'])]),
	BR(),
	new CSpan([bold(_('Test result')), ':', SPACE, $data['testResult'], SPACE,
		_s('(calculated at %1$s)', date(DATE_TIME_FORMAT_SECONDS, $data['time'] + RSM_ROLLWEEK_SHIFT_BACK))
	]),
	BR(),
	new CSpan([bold(_('Note')), ':', SPACE, _('The following table displays the data that has been received by '.
		'the central node, some of the values may not have been available at the time of the calculation of the '.
		'"Test result"')
	])
];

$particular_tests_info_table = (new CTable(null))->addClass('incidents-info');
$particular_tests_info_table->addRow([$particular_test, $addition_info]);

$widget->addItem($particular_tests_info_table);

$widget->addItem($table);

return $widget;
