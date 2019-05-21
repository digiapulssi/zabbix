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

if ($this->data['type'] == RSM_DNS || $this->data['type'] == RSM_DNSSEC) {
	$headers = [
		_('Probe ID'),
		_('Row result')
	];

	$table = (new CTableInfo())->setHeader($headers);
}
elseif ($this->data['type'] == RSM_RDDS) {
	/**
	 * If 'status' is not set, probe is UP. So, we need to check if all (length of $probes_status = 0) or
	 * at least one (array_sum($probes_status) > 0) probe is UP.
	 *
	 * Do not show URL or 'disabled' label at header if probe 'status' == PROBE_DOWN.
	 */
	$probes_status = zbx_objectValues($this->data['probes'], 'status');

	if (!$probes_status || array_sum($probes_status) > 0) {
		$rdds_43_base_url = array_key_exists('rdds_43_base_url', $data) ? $data['rdds_43_base_url'] : _('disabled');
		$rdds_80_base_url = array_key_exists('rdds_80_base_url', $data) ? $data['rdds_80_base_url'] : _('disabled');
		$rdap_base_url = array_key_exists('rdap_base_url', $data) ? $data['rdap_base_url'] : _('disabled');
		$rdds_43_base_url = ' ('.$rdds_43_base_url.')';
		$rdds_80_base_url = ' ('.$rdds_80_base_url.')';
		$rdap_base_url = ' ('.$rdap_base_url.')';
	}
	else {
		$rdds_43_base_url = '';
		$rdds_80_base_url = '';
		$rdap_base_url = '';
	}

	$row_1 = (new CTag('tr', true))
		->addItem((new CTag('th', true, _('Probe ID')))->setAttribute('rowspan', 2)->setAttribute('style', 'border-left: 0px;'))
		->addItem((new CTag('th', true, [_('RDDS43'), $rdds_43_base_url]))->setAttribute('colspan', 3)->setAttribute('class', 'center'))
		->addItem((new CTag('th', true, [_('RDDS80'), $rdds_80_base_url]))->setAttribute('colspan', 3)->setAttribute('class', 'center'))
		->addItem((new CTag('th', true, [_('RDAP'), $rdap_base_url]))->setAttribute('colspan', 3)->setAttribute('class', 'center'));

	$row_2 = (new CTag('tr', true))
		->addItem((new CTag('th', true, _('Status'))))
		->addItem((new CTag('th', true, _('IP'))))
		->addItem((new CTag('th', true, _('RTT'))))
		->addItem((new CTag('th', true, _('Status'))))
		->addItem((new CTag('th', true, _('IP'))))
		->addItem((new CTag('th', true, _('RTT'))))
		->addItem((new CTag('th', true, _('Status'))))
		->addItem((new CTag('th', true, _('IP'))))
		->addItem((new CTag('th', true, _('RTT'))));

	$table = (new CTableInfo())
		->setMultirowHeader([$row_1, $row_2], 10)
		->setAttribute('class', 'list-table table-bordered-head');
}
else {
	$headers = [
		_('Probe ID'),
		_('Row result'),
		_('IP'),
		_('Login'),
		_('Update'),
		_('Info')
	];

	$table = (new CTableInfo())->setHeader($headers);
}

$down = (new CSpan(_('Down')))->addClass(ZBX_STYLE_RED);
$offline = (new CSpan(_('Offline')))->addClass(ZBX_STYLE_GREY);
$noResult = (new CSpan(_('No result')))->addClass(ZBX_STYLE_GREY);
$disabled = (new CSpan(_('Disabled')))->addClass(ZBX_STYLE_GREY);
$up = (new CSpan(_('Up')))->addClass(ZBX_STYLE_GREEN);

$offlineProbes = 0;
$noResultProbes = 0;
$rdds80_above_max_rtt = 0;
$rdds43_above_max_rtt = 0;
$rdap_above_max_rtt = 0;

if ($this->data['type'] == RSM_DNSSEC) {
	$testTotal = 0;
	$testUp = 0;
	$testDown = 0;
}
elseif ($this->data['type'] == RSM_RDDS || $this->data['type'] == RSM_EPP) {
	$downProbes = 0;
}

foreach ($this->data['probes'] as $probe) {
	$status = null;
	if (isset($probe['status']) && $probe['status'] === PROBE_DOWN) {
		if ($this->data['type'] == RSM_DNS || $this->data['type'] == RSM_DNSSEC) {
			$link = $offline;
		}
		elseif ($this->data['type'] == RSM_RDDS) {
			$rdds = ZBX_STYLE_GREY;
			$rdds43 = $offline;
			$rdds80 = $offline;
			$rdap = $offline;
		}
		else {
			$epp = $offline;
		}

		$offlineProbes++;
	}
	else {
		if ($this->data['type'] == RSM_DNS) {
			if (isset($probe['value'])) {
				$values = [];

				if ($probe['result'] === null) {
					$noResultProbes++;
					$link = (new CSpan(_('No result')))->addClass(ZBX_STYLE_GREY);
				}
				else {
					if ($probe['result'] !== null && $probe['result'] != 0) {
						$values[] = _s('%1$s OK', $probe['result']);
					}
					if ($probe['value']['fail']) {
						$values[] = _s('%1$s FAILED', $probe['value']['fail']);
					}

					$link = (new CLink(
						implode(', ', $values),
						'rsm.particularproxys.php?slvItemId='.$this->data['slvItemId'].'&host='.$this->data['tld']['host'].
							'&time='.$this->data['time'].'&probe='.$probe['host'].'&type='.$this->data['type']
					))
						->addClass($probe['class']);
				}
			}
			else {
				$link = (new CSpan(_('Not monitored')))->addClass(ZBX_STYLE_RED);
			}
		}
		elseif ($this->data['type'] == RSM_DNSSEC) {
			if (isset($probe['value'])) {
				$values = [];
				$okResults = false;
				$failResults = false;
				$noResults = false;

				if ($probe['value']['ok']) {
					$values[] = _s('%1$s OK', $probe['value']['ok']);
					$okResults = true;
					$testUp += $probe['value']['ok'];
				}
				if ($probe['value']['fail']) {
					$values[] = _s('%1$s FAILED', $probe['value']['fail']);
					$failResults = true;
					$testDown += $probe['value']['fail'];
				}
				if ($probe['value']['noResult']) {
					$values[] = _s('%1$s NO RESULT', $probe['value']['noResult']);
					$noResults = true;
				}
				if ($probe['value']['total']) {
					$testTotal += $probe['value']['total'];
				}

				// get test results color
				if ($okResults && !$failResults && !$noResults) {
					$class = ZBX_STYLE_GREEN;
				}
				elseif ($failResults && !$okResults && !$noResults) {
					$class = ZBX_STYLE_RED;
				}
				elseif ($noResults && !$okResults && !$failResults) {
					$class = ZBX_STYLE_GREY;
					$noResultProbes++;
				}
				else {
					$class = null;
				}

				$link = (new CLink(
					implode(', ', $values),
					'rsm.particularproxys.php?slvItemId='.$this->data['slvItemId'].'&host='.$this->data['tld']['host'].
						'&time='.$this->data['time'].'&probe='.$probe['host'].'&type='.$this->data['type']
				))
					->addClass($class);
			}
			else {
				$link = (new CSpan(_('Not monitored')))->addClass(ZBX_STYLE_RED);
			}
		}
		elseif ($this->data['type'] == RSM_RDDS) {
			$probe_down = false;
			$probe_no_result = false;

			// RDDS
			if (isset($this->data['tld']['macros'][RSM_RDDS_ENABLED])
					&& $this->data['tld']['macros'][RSM_RDDS_ENABLED] == 0) {
				$rdds43 = $disabled;
				$rdds80 = $disabled;
				$rdds = ZBX_STYLE_GREY;
			}
			elseif (!isset($probe['value']) || $probe['value'] === null) {
				$rdds43 = $noResult;
				$rdds80 = $noResult;
				$rdds = ZBX_STYLE_GREY;
				$probe_no_result = true;
			}
			elseif ($probe['value'] == 0) {
				$rdds43 = $down;
				$rdds80 = $down;
				$rdds = ZBX_STYLE_RED;
				$probe_down = true;
			}
			elseif ($probe['value'] == 1) {
				$rdds43 = $up;
				$rdds80 = $up;
				$rdds = ZBX_STYLE_GREEN;
			}
			elseif ($probe['value'] == 2) {
				$rdds43 = $up;
				$rdds80 = $down;
				$rdds = ZBX_STYLE_RED;
				$probe_down = true;
			}
			elseif ($probe['value'] == 3) {
				$rdds43 = $down;
				$rdds80 = $up;
				$rdds = ZBX_STYLE_RED;
				$probe_down = true;
			}
			elseif ($probe['value'] == 4) {
				$rdds43 = $down;
				$rdds80 = $down;
				$rdds = ZBX_STYLE_RED;
				$probe_down = true;
			}
			elseif ($probe['value'] == 5) {
				$rdds43 = $noResult;
				$rdds80 = $up;
				$rdds = ZBX_STYLE_RED;
				$probe_down = true;
			}
			elseif ($probe['value'] == 6) {
				$rdds43 = $up;
				$rdds80 = $noResult;
				$rdds = ZBX_STYLE_RED;
				$probe_down = true;
			}
			elseif ($probe['value'] == 7) {
				$rdds43 = $up;
				$rdds80 = $up;
				$rdds = ZBX_STYLE_RED;
				$probe_down = true;
			}
			else {
				$rdds = ZBX_STYLE_GREY;
			}

			if (isset($this->data['tld']['macros'][RSM_RDAP_TLD_ENABLED])
					&& $this->data['tld']['macros'][RSM_RDAP_TLD_ENABLED] == 0) {
				$rdap = $disabled;
			}
			elseif (!isset($probe['value_rdap']) || $probe['value_rdap'] === null) {
				$rdap = $noResult;
			}
			elseif ($probe['value_rdap'] == 0) {
				$rdds = ZBX_STYLE_RED;
				$probe_down = true;
				$rdap = $down;
			}
			elseif ($probe['value_rdap'] == 1) {
				if ($rdds !== ZBX_STYLE_RED) {
					$rdds = ZBX_STYLE_GREEN;
				}

				$rdap = $up;
			}

			/**
			 * An exception: if sub-service is disabled at TLD level, sub-services should be disabled at probe level
			 * too. This need to be added as exception because in case if sub-service is disabled at TLD level, we never
			 * request values of related items. As the result, we cannot detect what is a reason why there are no
			 * results for sub-service.
			 *
			 * See ICA-386 for more details.
			 */
			if (!array_key_exists('rdds_43_base_url', $data) && $rdds43 === $noResult) {
				$rdds43 = $disabled;
				$rdds80 = $disabled;
				$probe_no_result = false;
			}
			/**
			 * Another exception: if RDDS is disabled at probe level, this is another case when we doesn't request data
			 * and cannot distinguish when probe has no data and when it is disabled. So, ask help to macros.
			 *
			 * Macros {$RSM.RDDS.ENABLED} is used to disable all 3 sub-services, so, if its 0, all three are displayed
			 * as disabled.
			 */
			elseif (isset($probe['macros'][RSM_RDDS_ENABLED]) && $probe['macros'][RSM_RDDS_ENABLED] == 0) {
				$rdds43 = $disabled;
				$rdds80 = $disabled;
				$rdap = $disabled;
			}

			if (($rdds43 === $disabled || $rdds43 === $noResult)
					&& ($rdds80 === $disabled || $rdds80 === $noResult)
					&& ($rdap === $disabled || $rdap === $noResult)) {
				$probe_no_result = true;
				$probe_down = false;
				$rdds = ZBX_STYLE_GREY;
			}

			if ($probe_down) {
				$downProbes++;
			}
			elseif ($probe_no_result) {
				$noResultProbes++;
			}
		}
		else {
			// EPP
			if (!isset($probe['value']) || $probe['value'] === null) {
				$epp = $noResult;
				$noResultProbes++;
			}
			elseif ($probe['value'] == 0) {
				$epp = $down;
				$downProbes++;
			}
			elseif ($probe['value'] == 1) {
				$epp = $up;
			}
		}
	}

	if ($this->data['type'] == RSM_DNS || $this->data['type'] == RSM_DNSSEC) {
		$row = [
			$probe['name'],
			$link
		];
	}
	elseif ($this->data['type'] == RSM_RDDS) {
		if (isset($probe['rdds43']['rtt'])) {
			$rdds43_rtt = (new CSpan($probe['rdds43']['rtt']['value']))
				->setAttribute('class', $rdds43 === $down ? ZBX_STYLE_RED : ZBX_STYLE_GREEN);

			if ($probe['rdds43']['rtt']['description']) {
				$rdds43_rtt->setHint($probe['rdds43']['rtt']['description']);
			}
		}
		else {
			$rdds43_rtt = '-';
		}

		if (isset($probe['rdds80']['rtt'])) {
			$rdds80_rtt = (new CSpan($probe['rdds80']['rtt']['value']))
				->setAttribute('class', $rdds80 === $down ? ZBX_STYLE_RED : ZBX_STYLE_GREEN);

			if ($probe['rdds80']['rtt']['description']) {
				$rdds80_rtt->setHint($probe['rdds80']['rtt']['description']);
			}
		}
		else {
			$rdds80_rtt = '-';
		}

		if (isset($probe['rdap']['rtt'])) {
			$rdap_rtt = (new CSpan($probe['rdap']['rtt']['value']))
				->setAttribute('class', $rdap === $down ? ZBX_STYLE_RED : ZBX_STYLE_GREEN);

			if ($probe['rdap']['rtt']['description']) {
				$rdap_rtt->setHint($probe['rdap']['rtt']['description']);
			}
		}
		else {
			$rdap_rtt = '-';
		}

		$row = [
			(new CSpan($probe['name']))->addClass($rdds),
			$rdds43,
			(isset($probe['rdds43']['ip']) && $probe['rdds43']['ip'])
				? (new CSpan($probe['rdds43']['ip']))->setAttribute('class', $rdds43 === $down ? ZBX_STYLE_RED : ZBX_STYLE_GREEN)
				: '-',
			$rdds43_rtt,
			$rdds80,
			(isset($probe['rdds80']['ip']) && $probe['rdds80']['ip'])
				? (new CSpan($probe['rdds80']['ip']))->setAttribute('class', $rdds80 === $down ? ZBX_STYLE_RED : ZBX_STYLE_GREEN)
				: '-',
			$rdds80_rtt,
			$rdap,
			(isset($probe['rdap']['ip']) && $probe['rdap']['ip'])
				? (new CSpan($probe['rdap']['ip']))->setAttribute('class', $rdap === $down ? ZBX_STYLE_RED : ZBX_STYLE_GREEN)
				: '-',
			$rdap_rtt
		];

		/**
		 * If $rddsNN is DOWN and RTT is non-negative, it is considered as above max RTT.
		 *
		 * Following scenarios are possible:
		 * - If RTT is negative, it is an error and is considered as DOWN.
		 * - If RTT is positive but $rddsNN is still DOWN, it indicates that at the time of calculation, RTT was greater
		 *	 than max allowed RTT.
		 * - If RTT is positive but $rddsNN is UP, it indicates that at the time of calculation, RTT was in the range of
		 *	 allowed values - greater than 0 (was not an error) and smaller than max allowed RTT.
		 */
		if ($rdds80 === $down && isset($probe['rdds80']['rtt']) && $probe['rdds80']['rtt']['value'] > 0) {
			$rdds80_above_max_rtt++;
		}
		if ($rdds43 === $down && isset($probe['rdds43']['rtt']) && $probe['rdds43']['rtt']['value'] > 0) {
			$rdds43_above_max_rtt++;
		}
		if ($rdap === $down && isset($probe['rdap']['rtt']) && $probe['rdap']['rtt']['value'] > 0) {
			$rdap_above_max_rtt++;
		}
	}
	else {
		$row = [
			$probe['name'],
			$epp,
			(isset($probe['ip']) && $probe['ip']) ? $probe['ip'] : '-',
			(isset($probe['login']) && $probe['login']) ? $probe['login'] : '-',
			(isset($probe['update']) && $probe['update']) ? $probe['update'] : '-',
			(isset($probe['info']) && $probe['info']) ? $probe['info'] : '-'
		];
	}

	$table->addRow($row);
}

// Add table footer rows:
if ($data['type'] == RSM_RDDS) {
	foreach ($data['errors'] as $error_code => $error) {
		$table->addRow([
			(new CSpan(_('Total ') . $error_code))->setHint($error['description']),
			'',
			'',
			array_key_exists('rdds43', $error) ? $error['rdds43'] : '',
			'',
			'',
			array_key_exists('rdds80', $error) ? $error['rdds80'] : '',
			'',
			'',
			array_key_exists('rdap', $error) ? $error['rdap'] : ''
		]);
	}

	$table->addRow([
		_('Total above max. RTT'),
		'',
		'',
		$rdds43_above_max_rtt,
		'',
		'',
		$rdds80_above_max_rtt,
		'',
		'',
		$rdap_above_max_rtt
	]);
}

if ($this->data['type'] == RSM_DNS || $this->data['type'] == RSM_RDDS || $this->data['type'] == RSM_EPP) {
	$downProbes = $this->data['type'] == RSM_DNS ? $this->data['downProbes'] : $downProbes;

	$additionInfo = [
		new CSpan([bold(_('Probes total')), ':', SPACE, $this->data['totalProbes']]),
		BR(),
		new CSpan([bold(_('Probes offline')), ':', SPACE, $offlineProbes]),
		BR(),
		new CSpan([bold(_('Probes with No Result')), ':', SPACE, $noResultProbes]),
		BR(),
		new CSpan([bold(_('Probes with Result')), ':', SPACE,
			$this->data['totalProbes'] - $offlineProbes - $noResultProbes
		]),
		BR(),
		new CSpan([bold(_('Probes Up')), ':', SPACE,
			$this->data['totalProbes'] - $offlineProbes - $noResultProbes - $downProbes
		]),
		BR(),
		new CSpan([bold(_('Probes Down')), ':', SPACE, $downProbes])
	];
}
elseif ($this->data['type'] == RSM_DNSSEC) {
	$additionInfo = [
		new CSpan([bold(_('Probes total')), ':', SPACE, $this->data['totalProbes']]),
		BR(),
		new CSpan([bold(_('Probes offline')), ':', SPACE, $offlineProbes]),
		BR(),
		new CSpan([bold(_('Probes with No Result')), ':', SPACE, $noResultProbes]),
		BR(),
		new CSpan([bold(_('Probes with Result')), ':', SPACE,
			$this->data['totalProbes'] - $offlineProbes - $noResultProbes
		]),
		BR(),
		new CSpan([bold(_('Tests total')), ':', SPACE, $testTotal]),
		BR(),
		new CSpan([bold(_('Tests Up')), ':', SPACE, $testUp]),
		BR(),
		new CSpan([bold(_('Tests Down')), ':', SPACE, $testDown])
	];
}

if (in_array($this->data['type'], [RSM_DNS, RSM_DNSSEC, RSM_RDDS])) {
	$test_result = $this->data['testResult'];
}
else {
	if ($this->data['testResult'] === null) {
		$test_result = $noResult;
	}
	elseif ($this->data['testResult'] == PROBE_UP) {
		$test_result = $up;
	}
	else {
		$test_result = $down;
	}
}

$object_name_label = ($data['rsm_monitoring_mode'] == RSM_MONITORING_TYPE_REGISTRAR) ? _('Registrar ID') : _('TLD');
$object_name = ($data['rsm_monitoring_mode'] == RSM_MONITORING_TYPE_REGISTRAR)
	? (new CSpan($data['tld']['name']))->setHint(getRegistrarDetailsHint($data['tld']))
	: $data['tld']['name'];

$particularTests = [
	new CSpan([bold($object_name_label), ':', SPACE, $object_name]),
	BR(),
	new CSpan([bold(_('Service')), ':', SPACE, $this->data['slvItem']['name']]),
	BR(),
	new CSpan([bold(_('Test time')), ':', SPACE, date(DATE_TIME_FORMAT_SECONDS, $this->data['time'])]),
	BR(),
	new CSpan([bold(_('Test result')), ':', SPACE, $test_result, SPACE,
		_s('(calculated at %1$s)', date(DATE_TIME_FORMAT_SECONDS, $this->data['time'] + RSM_ROLLWEEK_SHIFT_BACK))
	]),
	BR(),
	new CSpan([bold(_('Note')), ':', SPACE, _('The following table displays the data that has been received by '.
		'the central node, some of the values may not have been available at the time of the calculation of the '.
		'"Test result"')
	])
];
$particularTestsInfoTable = (new CTable(null))->addClass('incidents-info');
$particularTestsInfoTable->addRow([$particularTests, $additionInfo]);

$widget->addItem($particularTestsInfoTable);

$widget->addItem($table);

return $widget;
