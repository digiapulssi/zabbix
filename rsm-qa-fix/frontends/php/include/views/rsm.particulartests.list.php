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
}
elseif ($this->data['type'] == RSM_RDDS) {
	$headers = [
		_('Probe ID'),
		_('RDDS43'),
		_('IP'),
		_('RTT'),
		_('UPD'),
		_('RDDS80'),
		_('IP'),
		_('RTT')
	];
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
}

$table = (new CTableInfo())->setHeader($headers);

$down = (new CSpan(_('Down')))->addClass('red');
$offline = (new CSpan(_('Offline')))->addClass('grey');
$noResult = (new CSpan(_('No result')))->addClass('grey');
$up = (new CSpan(_('Up')))->addClass('green');

$offlineProbes = 0;
$noResultProbes = 0;
$rdds80_above_max_rtt = 0;
$rdds43_above_max_rtt = 0;

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
			$rdds = 'grey';
			$rdds43 = $offline;
			$rdds80 = $offline;
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
					$link = (new CSpan(_('No result')))->addClass('grey');
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
				$link = (new CSpan(_('Not monitored')))->addClass('red');
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
					$class = 'green';
				}
				elseif ($failResults && !$okResults && !$noResults) {
					$class = 'red';
				}
				elseif ($noResults && !$okResults && !$failResults) {
					$class = 'grey';
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
				$link = (new CSpan(_('Not monitored')))->addClass('red');
			}
		}
		elseif ($this->data['type'] == RSM_RDDS) {
			// RDDS
			if (!isset($probe['value']) || $probe['value'] === null) {
				$rdds43 = $noResult;
				$rdds80 = $noResult;
				$rdds = 'grey';
				$noResultProbes++;
			}
			elseif ($probe['value'] == 0) {
				$rdds43 = $down;
				$rdds80 = $down;
				$rdds = 'red';
				$downProbes++;
			}
			elseif ($probe['value'] == 1) {
				$rdds43 = $up;
				$rdds80 = $up;
				$rdds = 'green';
			}
			elseif ($probe['value'] == 2) {
				$rdds43 = $up;
				$rdds80 = $down;
				$rdds = 'red';
				$downProbes++;
			}
			elseif ($probe['value'] == 3) {
				$rdds43 = $down;
				$rdds80 = $up;
				$rdds = 'red';
				$downProbes++;
			}
			elseif ($probe['value'] == 4) {
				$rdds43 = $down;
				$rdds80 = $down;
				$rdds = 'red';
				$downProbes++;
			}
			elseif ($probe['value'] == 5) {
				$rdds43 = $noResult;
				$rdds80 = $up;
				$rdds = 'red';
				$downProbes++;
			}
			elseif ($probe['value'] == 6) {
				$rdds43 = $up;
				$rdds80 = $noResult;
				$rdds = 'red';
				$downProbes++;
			}
			elseif ($probe['value'] == 7) {
				$rdds43 = $up;
				$rdds80 = $up;
				$rdds = 'red';
				$downProbes++;
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
			$rdds43_rtt = $probe['rdds43']['rtt']['description']
				? (new CSpan($probe['rdds43']['rtt']['value']))->setHint($probe['rdds43']['rtt']['description'])
				: $probe['rdds43']['rtt']['value'];
		}
		else {
			$rdds43_rtt = '-';
		}

		if (isset($probe['rdds80']['rtt'])) {
			$rdds80_rtt = $probe['rdds80']['rtt']['description']
				? (new CSpan($probe['rdds80']['rtt']['value']))->setHint($probe['rdds80']['rtt']['description'])
				: $probe['rdds80']['rtt']['value'];
		}
		else {
			$rdds80_rtt = '-';
		}

		$row = [
			(new CSpan($probe['name']))->addClass($rdds),
			$rdds43,
			(isset($probe['rdds43']['ip']) && $probe['rdds43']['ip']) ? $probe['rdds43']['ip'] : '-',
			$rdds43_rtt,
			(isset($probe['rdds43']['upd'])) ? $probe['rdds43']['upd'] : '-',
			$rdds80,
			(isset($probe['rdds80']['ip']) && $probe['rdds80']['ip']) ? $probe['rdds80']['ip'] : '-',
			$rdds80_rtt
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
		if ($rdds80 === $down && array_key_exists('rtt', $probe['rdds80']) && $probe['rdds80']['rtt']['value'] > 0) {
			$rdds80_above_max_rtt++;
		}
		if ($rdds43 === $down && array_key_exists('rtt', $probe['rdds43']) && $probe['rdds43']['rtt']['value'] > 0) {
			$rdds43_above_max_rtt++;
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
			'',
			array_key_exists('rdds80', $error) ? $error['rdds80'] : ''
		]);
	}

	$table->addRow([
		_('Total above max. RTT'),
		'',
		'',
		$rdds43_above_max_rtt,
		'',
		'',
		'',
		$rdds80_above_max_rtt
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

$particularTests = [
	new CSpan([bold(_('TLD')), ':', SPACE, $this->data['tld']['name']]),
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
