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
	->addItem((new CTag('th', true, _('Probe ID')))->setAttribute('rowspan', 3)
		->setAttribute('style', 'border-left: 0px;'));

$row_2 = (new CTag('tr', true))
	->addItem((new CTag('th', true, _('Status')))->setAttribute('rowspan', 2))
	->addItem((new CTag('th', true, _('Name Servers')))
		->setAttribute('colspan', 2)
		->setAttribute('class', 'center'));

$row_3 = [_('UP'), _('DOWN')];

if (array_key_exists('dns_udp_nameservers', $data)) {
	foreach ($data['dns_udp_nameservers'] as $ns_name => $ns_ips) {
		$row_3[] = [_('Status')];
		$cols_cnt = 1;

		if (array_key_exists('ipv4', $ns_ips)) {
			$cols_cnt += count($ns_ips['ipv4']);
			$row_3 = array_merge($row_3, array_keys($ns_ips['ipv4']));
		}
		if (array_key_exists('ipv6', $ns_ips)) {
			$cols_cnt += count($ns_ips['ipv6']);
			$row_3 = array_merge($row_3, array_keys($ns_ips['ipv6']));
		}

		$row_2->addItem((new CTag('th', true, $ns_name))
			->setAttribute('colspan', $cols_cnt)
			->setAttribute('class', 'center'));
	}
}

$row_1->addItem((new CTag('th', true, _('DNS UDP')))
	->setAttribute('colspan', count($row_3) + 1)
	->setAttribute('class', 'center'));

$table = (new CTableInfo())
	->setMultirowHeader([$row_1, $row_2, new CRowHeader($row_3)], count($row_3) + 4)
	->setAttribute('class', 'list-table table-bordered-head');

$down = (new CSpan(_('Down')))->addClass(ZBX_STYLE_RED);
$offline = (new CSpan(_('Offline')))->addClass(ZBX_STYLE_GREY);
$no_result = (new CSpan(_('No result')))->addClass(ZBX_STYLE_GREY);
$up = (new CSpan(_('Up')))->addClass(ZBX_STYLE_GREEN);

// Results summary.
$offline_probes = 0;
$no_result_probes = 0;
$down_probes = 0;

// Add results for each probe.
foreach ($data['probes'] as $probe) {
	if (array_key_exists('status_udp', $probe)) {
		if ($probe['status_udp'] == PROBE_OFFLINE) {
			$probe_status_color = ZBX_STYLE_GREY;
			$udp_status = $offline;
			$offline_probes++;
		}
		elseif ($probe['status_udp'] == PROBE_DOWN) {
			$probe_status_color = ZBX_STYLE_RED;
			$udp_status = $down;
			$down_probes++;
		}
		elseif ($probe['status_udp'] == PROBE_UP) {
			$probe_status_color = ZBX_STYLE_GREEN;
			$udp_status = $up;
		}
	}
	else {
		$udp_status = $no_result;
		$probe_status_color = ZBX_STYLE_GREY;
		$no_result_probes++;
	}

	$row = [
		(new CSpan($probe['name']))->addClass($probe_status_color),
		$udp_status,
		$probe['udp_ns_up'],
		$probe['udp_ns_down']
	];

	foreach ($data['dns_udp_nameservers'] as $dns_udp_ns => $ipvs) {
		if (array_key_exists($dns_udp_ns, $probe['results_udp'])) {
			if (array_key_exists('status', $probe['results_udp'][$dns_udp_ns])) {
				$row[] = ($probe['results_udp'][$dns_udp_ns]['status'] == NAMESERVER_DOWN) ? $down : $up;
			}
			else {
				$row[] = '-';
			}

			foreach (['ipv4', 'ipv6'] as $ipv) {
				if (array_key_exists($ipv, $probe['results_udp'][$dns_udp_ns])) {
					foreach (array_keys($ipvs[$ipv]) as $ip) {
						if (array_key_exists($ip, $probe['results_udp'][$dns_udp_ns][$ipv])) {
							$result = $probe['results_udp'][$dns_udp_ns][$ipv][$ip];
							$is_dns_error = isDNSErrorCode($result, $data['type']);

							if ($result == 0) {
								$row[] = '-';
							}
							elseif (0 > $result) {
								$row[] = (new CSpan($result))
									->setHint($data['error_msgs'][$result])
									->setAttribute('class', $is_dns_error ? ZBX_STYLE_RED : ZBX_STYLE_GREEN);
							}
							elseif ($data['type'] == RSM_DNS && $result > $data['udp_rtt']) {
								$row[] = (new CSpan($result))
									->setAttribute('class', ZBX_STYLE_RED);
							}
							else {
								$row[] = (new CSpan($result))
									->setAttribute('class', ZBX_STYLE_GREEN);
							}
						}
						else {
							$row[] = '-';
						}
					}
				}
			}
		}
		else {
			$cell_cnt = 1;
			$cell_cnt += array_key_exists('ipv4', $ipv) ? count($ipv['ipv4']) : 0;
			$cell_cnt += array_key_exists('ipv6', $ipv) ? count($ipv['ipv6']) : 0;
			$row[] = (new CTag('td', true, '-'))->setAttribute('colspan', $cell_cnt);
		}
	}

	foreach ($probe['results_udp'] as $ns => $result_udp) {
		foreach ($result_udp as $result) {

		}
	}

	$table->addRow($row);
}

// Add error rows at the bottom of table.
foreach ($data['errors'] as $error_code => $errors) {
	$row = [(new CSpan(_('Total ') . $error_code))->setHint($data['error_msgs'][$error_code]), '', '', ''];

	// Add number of error cells.
	foreach ($data['dns_udp_nameservers'] as $ns_name => $ns_ips) {
		$row[] = '';

		if (array_key_exists('ipv4', $ns_ips)) {
			foreach (array_keys($ns_ips['ipv4']) as $ip) {
				$error_key = 'udp_'.$ns_name.'_ipv4_'.$ip;
				$row[] = array_key_exists($error_key, $errors) ? $errors[$error_key] : '';
			}
		}

		if (array_key_exists('ipv6', $ns_ips)) {
			foreach (array_keys($ns_ips['ipv6']) as $ip) {
				$error_key = 'udp_'.$ns_name.'_ipv6_'.$ip;
				$row[] = array_key_exists($error_key, $errors) ? $errors[$error_key] : '';
			}
		}
	}

	$table->addRow($row);
}

	// Add 'Total above max rtt' row:
if ($data['type'] == RSM_DNS) {
	$row = [_('Total above max. RTT'), '', '', ''];
	foreach ($data['dns_udp_nameservers'] as $ns_name => $ns_ips) {
		$row[] = '';

		if (array_key_exists('ipv4', $ns_ips)) {
			foreach (array_keys($ns_ips['ipv4']) as $ipv => $ip) {
				$error_key = 'udp_'.$ns_name.'_ipv4_'.$ip;
				$row[] = array_key_exists($error_key, $data['probes_above_max_rtt']) ? $data['probes_above_max_rtt'][$error_key] : '0';
			}
		}

		if (array_key_exists('ipv6', $ns_ips)) {
			foreach (array_keys($ns_ips['ipv6']) as $ipv => $ip) {
				$error_key = 'udp_'.$ns_name.'_ipv6_'.$ip;
				$row[] = array_key_exists($error_key, $data['probes_above_max_rtt']) ? $data['probes_above_max_rtt'][$error_key] : '0';
			}
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
