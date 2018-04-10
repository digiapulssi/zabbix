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


$widget = (new CWidget())->setTitle(_('Incidents'));

// filter
$filter = (new CFilter('web.rsm.incidents.filter.state'))
	->addVar('filter_set', 1)
	->addVar('filter_from', zbxDateToTime($data['filter_from']))
	->addVar('filter_to', zbxDateToTime($data['filter_to']));
$filterColumn1 = new CFormList();
$filterColumn2 = new CFormList();
$filterColumn3 = new CFormList();
$filterColumn4 = new CFormList();

$filterColumn1
	->addRow(_('TLD'), (new CTextBox('filter_search', $this->data['filter_search']))
		->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
		->setAttribute('autocomplete', 'off')
	);
$filterColumn2
	->addRow(_('From'), createDateSelector('filter_from', zbxDateToTime($this->data['filter_from'])));
$filterColumn3
	->addRow(_('To'), createDateSelector('filter_to', zbxDateToTime($this->data['filter_to'])));
$filterColumn4
	->addRow((new CLink(_('Rolling week'),
		$this->data['url'].'rsm.incidents.php?incident_type='.$this->data['type'].'&filter_set=1&filter_search='.
			$this->data['filter_search'].'&filter_rolling_week=1&sid='.$this->data['sid'].'&set_sid=1'
	))
		->addClass(ZBX_STYLE_BTN_LINK));

$filter
	->addColumn($filterColumn1)
	->addColumn($filterColumn2)
	->addColumn($filterColumn3)
	->addColumn($filterColumn4);

$widget->addItem($filter);

if (isset($this->data['tld'])) {
	$infoBlock = new CTable(null, 'filter info-block');
	$dateFrom = date(DATE_TIME_FORMAT, zbxDateToTime($this->data['filter_from']));
	$dateTill = date(DATE_TIME_FORMAT, zbxDateToTime($this->data['filter_to']));
	$infoBlock->addRow([[
		bold(_('TLD')),
		':',
		SPACE,
		$this->data['tld']['name'],
		BR(),
		_s('From %1$s till %2$s', $dateFrom, $dateTill),
		BR(),
		bold(_('Server')),
		':',
		SPACE,
		new CLink($this->data['server'],
			$this->data['url'].'rsm.rollingweekstatus.php?sid='.$this->data['sid'].'&set_sid=1'
		),
	]]);
	$widget->additem($infoBlock);
}

$headers = [
	_('Incident ID'),
	_('Status'),
	_('Start time'),
	_('End time'),
	_('Failed tests within incident'),
	_('Total number of tests')
];
$noData = _('No incidents found.');

$dnsTab = new CDiv();
$dnssecTab = new CDiv();
$rddsTab = new CDiv();
$eppTab = new CDiv();

if (isset($this->data['tld'])) {
	$incidentPage = new CTabView(['remember' => true]);
	if (hasRequest('type')) {
		$incidentPage->setSelected($this->data['type']);
	}

	// DNS
	if (isset($this->data['dns']['events'])) {
		$dnsInfoTable = (new CTable(null))->addClass('incidents-info');

		$dnsTable = new CTableInfo($noData);
		$dnsTable->setHeader($headers);

		foreach ($this->data['dns']['events'] as $event) {
			$incidentStatus = getIncidentStatus($event['false_positive'], $event['status']);

			$row = [
				new CLink(
					$event['eventid'],
					$this->data['url'].'rsm.incidentdetails.php?host='.$this->data['tld']['host'].
						'&eventid='.$event['eventid'].'&slvItemId='.$this->data['dns']['itemid'].
						'&filter_from='.$this->data['filter_from'].'&filter_to='.$this->data['filter_to'].
						'&availItemId='.$this->data['dns']['availItemId'].'&filter_set=1&sid='.$this->data['sid'].'&set_sid=1'
				),
				$incidentStatus,
				date(DATE_TIME_FORMAT_SECONDS, $event['startTime']),
				isset($event['endTime']) ? date(DATE_TIME_FORMAT_SECONDS, $event['endTime']) : '-',
				$event['incidentFailedTests'],
				$event['incidentTotalTests']
			];

			$dnsTable->addRow($row);
		}

		$testsDown = new CLink(
			$this->data['dns']['totalTests'],
			$this->data['url'].'rsm.tests.php?filter_from='.$this->data['filter_from'].'&filter_to='.$this->data['filter_to'].
				'&filter_set=1&host='.$this->data['tld']['host'].'&type='.RSM_DNS.
				'&slvItemId='.$this->data['dns']['itemid'].'&sid='.$this->data['sid'].'&set_sid=1'
		);

		$testsInfo = [
			bold(_('Tests are down')),
			':',
			SPACE,
			$testsDown,
			SPACE,
			_n('test', 'tests', $this->data['dns']['totalTests']),
			SPACE,
			'('._s(
				'%1$s in incidents, %2$s outside incidents',
				$this->data['dns']['inIncident'],
				$this->data['dns']['totalTests'] - $this->data['dns']['inIncident']
			).')'
		];

		$details = new CSpan([
			bold(_('Incidents')),
			':',
			SPACE,
			isset($this->data['dns']) ? count($this->data['dns']['events']) : 0,
			BR(),
			$testsInfo,
			BR(),
			[[bold(_('SLA')), ':'.SPACE], convert_units(['value' => $this->data['dns']['slaValue'], 'units' => 's'])],
			BR(),
			[[bold(_('Frequency/delay')), ':'.SPACE], convert_units(['value' => $this->data['dns']['delay'], 'units' => 's'])]
		]);

		$rollingWeek = [
			(new CSpan(_s('%1$s Rolling week status', $this->data['dns']['slv'].'%')))->addClass('rolling-week-status'),
			BR(),
			(new CSpan(date(DATE_TIME_FORMAT, $this->data['dns']['slvTestTime'])))->addClass('rsm-date-time')
		];
		$dnsInfoTable->addRow([$details, $rollingWeek]);
		$dnsTab->additem($dnsInfoTable);

		$dnsTab->additem($dnsTable);
	}
	else {
		$dnsTab->additem(new CDiv(bold(_('Incorrect TLD configuration.')), 'red center'));
	}

	// DNSSEC
	if (isset($this->data['dnssec']['events'])) {
		$dnssecInfoTable = (new CTable(null))->addClass('incidents-info');

		$dnssecTable = new CTableInfo($noData);
		$dnssecTable->setHeader($headers);

		foreach ($this->data['dnssec']['events'] as $event) {
			$incidentStatus = getIncidentStatus($event['false_positive'], $event['status']);

			$row = [
				new CLink(
					$event['eventid'],
					$this->data['url'].'rsm.incidentdetails.php?host='.$this->data['tld']['host'].
						'&eventid='.$event['eventid'].'&slvItemId='.$this->data['dnssec']['itemid'].
						'&filter_from='.$this->data['filter_from'].'&filter_to='.$this->data['filter_to'].
						'&availItemId='.$this->data['dnssec']['availItemId'].'&filter_set=1&sid='.$this->data['sid'].'&set_sid=1'
				),
				$incidentStatus,
				date(DATE_TIME_FORMAT_SECONDS, $event['startTime']),
				isset($event['endTime']) ? date(DATE_TIME_FORMAT_SECONDS, $event['endTime']) : '-',
				$event['incidentFailedTests'],
				$event['incidentTotalTests']
			];

			$dnssecTable->addRow($row);
		}

		$testsDown = new CLink(
			$this->data['dnssec']['totalTests'],
			$this->data['url'].'rsm.tests.php?filter_from='.$this->data['filter_from'].'&filter_to='.$this->data['filter_to'].
				'&filter_set=1&host='.$this->data['tld']['host'].'&type='.RSM_DNSSEC.'&slvItemId='.
				$this->data['dnssec']['itemid'].'&sid='.$this->data['sid'].'&set_sid=1'
		);

		$testsInfo = [
			bold(_('Tests are down')),
			':',
			SPACE,
			$testsDown,
			SPACE,
			_n('test', 'tests', $this->data['dnssec']['totalTests']),
			SPACE,
			'('._s(
				'%1$s in incidents, %2$s outside incidents',
				$this->data['dnssec']['inIncident'],
				$this->data['dnssec']['totalTests'] - $this->data['dnssec']['inIncident']
			).')'
		];

		$details = new CSpan([
			bold(_('Incidents')),
			':',
			SPACE,
			isset($this->data['dnssec']) ? count($this->data['dnssec']['events']) : 0,
			BR(),
			$testsInfo,
			BR(),
			[[bold(_('SLA')), ':'.SPACE], convert_units(['value' => $this->data['dnssec']['slaValue'], 'units' => 's'])],
			BR(),
			[[bold(_('Frequency/delay')), ':'.SPACE], convert_units(['value' => $this->data['dnssec']['delay'], 'units' => 's'])]
		]);

		$rollingWeek = [
			(new CSpan(_s('%1$s Rolling week status', $this->data['dnssec']['slv'].'%')))->addClass('rolling-week-status'),
			BR(),
			(new CSpan(date(DATE_TIME_FORMAT, $this->data['dnssec']['slvTestTime'])))->addClass('rsm-date-time')
		];
		$dnssecInfoTable->addRow([$details, $rollingWeek]);
		$dnssecTab->additem($dnssecInfoTable);

		$dnssecTab->additem($dnssecTable);
	}
	else {
		$dnssecTab->additem(new CDiv(bold(_('DNSSEC is disabled.')), 'red center'));
	}

	// RDDS
	if (isset($this->data['rdds']['events'])) {
		$rddsInfoTable = (new CTable(null))->addClass('incidents-info');

		$rddsTable = new CTableInfo($noData);
		$rddsTable->setHeader($headers);

		foreach ($this->data['rdds']['events'] as $event) {
			$incidentStatus = getIncidentStatus($event['false_positive'], $event['status']);

			$row = [
				new CLink(
					$event['eventid'],
					$this->data['url'].'rsm.incidentdetails.php?host='.$this->data['tld']['host'].
						'&eventid='.$event['eventid'].'&slvItemId='.$this->data['rdds']['itemid'].
						'&filter_from='.$this->data['filter_from'].'&filter_to='.$this->data['filter_to'].
						'&availItemId='.$this->data['rdds']['availItemId'].'&filter_set=1&sid='.$this->data['sid'].'&set_sid=1'
				),
				$incidentStatus,
				date(DATE_TIME_FORMAT_SECONDS, $event['startTime']),
				isset($event['endTime']) ? date(DATE_TIME_FORMAT_SECONDS, $event['endTime']) : '-',
				$event['incidentFailedTests'],
				$event['incidentTotalTests']
			];

			$rddsTable->addRow($row);
		}

		$testsDown = new CLink(
			$this->data['rdds']['totalTests'],
			$this->data['url'].'rsm.tests.php?filter_from='.$this->data['filter_from'].'&filter_to='.$this->data['filter_to'].
				'&filter_set=1&host='.$this->data['tld']['host'].'&type='.RSM_RDDS.'&slvItemId='.
				$this->data['rdds']['itemid'].'&sid='.$this->data['sid'].'&set_sid=1'
		);

		$testsInfo = [
			bold(_('Tests are down')),
			':',
			SPACE,
			$testsDown,
			SPACE,
			_n('test', 'tests', $this->data['rdds']['totalTests']),
			SPACE,
			'('._s(
				'%1$s in incidents, %2$s outside incidents',
				$this->data['rdds']['inIncident'],
				$this->data['rdds']['totalTests'] - $this->data['rdds']['inIncident']
			).')'
		];

		$details = new CSpan([
			bold(_('Incidents')),
			':',
			SPACE,
			isset($this->data['rdds']) ? count($this->data['rdds']['events']) : 0,
			BR(),
			$testsInfo,
			BR(),
			[[bold(_('SLA')), ':'.SPACE], convert_units(['value' => $this->data['rdds']['slaValue'], 'units' => 's'])],
			BR(),
			[[bold(_('Frequency/delay')), ':'.SPACE], convert_units(['value' => $this->data['rdds']['delay'], 'units' => 's'])]
		]);

		$rollingWeek = [
			(new CSpan(_s('%1$s Rolling week status', $this->data['rdds']['slv'].'%')))->addClass('rolling-week-status'),
			BR(),
			(new CSpan(date(DATE_TIME_FORMAT, $this->data['rdds']['slvTestTime'])))->addClass('rsm-date-time')
		];
		$rddsInfoTable->addRow([$details, $rollingWeek]);
		$rddsTab->additem($rddsInfoTable);

		$rddsTab->additem($rddsTable);
	}
	else {
		$rddsTab->additem(new CDiv(bold(_('RDDS is disabled.')), 'red center'));
	}

	// EPP
	if (isset($this->data['epp']['events'])) {
		$eppInfoTable = (new CTable(null))->addClass('incidents-info');

		$eppTable = new CTableInfo($noData);
		$eppTable->setHeader($headers);

		foreach ($this->data['epp']['events'] as $event) {
			$incidentStatus = getIncidentStatus($event['false_positive'], $event['status']);

			$row = [
				new CLink(
					$event['eventid'],
					$this->data['url'].'rsm.incidentdetails.php?host='.$this->data['tld']['host'].
						'&eventid='.$event['eventid'].'&slvItemId='.$this->data['epp']['itemid'].
						'&filter_from='.$this->data['filter_from'].'&filter_to='.$this->data['filter_to'].
						'&availItemId='.$this->data['epp']['availItemId'].'&filter_set=1&sid='.$this->data['sid'].'&set_sid=1'
				),
				$incidentStatus,
				date(DATE_TIME_FORMAT_SECONDS, $event['startTime']),
				isset($event['endTime']) ? date(DATE_TIME_FORMAT_SECONDS, $event['endTime']) : '-',
				$event['incidentFailedTests'],
				$event['incidentTotalTests']
			];

			$eppTable->addRow($row);
		}

		$testsDown = new CLink(
			$this->data['epp']['totalTests'],
			$this->data['url'].'rsm.tests.php?filter_from='.$this->data['filter_from'].'&filter_to='.$this->data['filter_to'].
				'&filter_set=1&host='.$this->data['tld']['host'].'&type='.RSM_EPP.'&slvItemId='.
				$this->data['epp']['itemid'].'&sid='.$this->data['sid'].'&set_sid=1'
		);

		$testsInfo = [
			bold(_('Tests are down')),
			':',
			SPACE,
			$testsDown,
			SPACE,
			_n('test', 'tests', $this->data['epp']['totalTests']),
			SPACE,
			'('._s(
				'%1$s in incidents, %2$s outside incidents',
				$this->data['epp']['inIncident'],
				$this->data['epp']['totalTests'] - $this->data['epp']['inIncident']
			).')'
		];

		$details = new CSpan([
			bold(_('Incidents')),
			':',
			SPACE,
			isset($this->data['epp']) ? count($this->data['epp']['events']) : 0,
			BR(),
			$testsInfo,
			BR(),
			[[bold(_('SLA')), ':'.SPACE], convert_units(['value' => $this->data['epp']['slaValue'], 'units' => 's'])],
			BR(),
			[[bold(_('Frequency/delay')), ':'.SPACE], convert_units(['value' => $this->data['epp']['delay'], 'units' => 's'])]
		]);

		$rollingWeek = [
			(new CSpan(_s('%1$s Rolling week status', $this->data['epp']['slv'].'%')))->addClass('rolling-week-status'),
			BR(),
			(new CSpan(date(DATE_TIME_FORMAT, $this->data['epp']['slvTestTime'])))->addClass('rsm-date-time')
		];
		$eppInfoTable->addRow([$details, $rollingWeek]);
		$eppTab->additem($eppInfoTable);

		$eppTab->additem($eppTable);
	}
	else {
		$eppTab->additem(new CDiv(bold(_('EPP is disabled.')), 'red center'));
	}

	$incidentPage->addTab('dnsTab', _('DNS'), $dnsTab);
	$incidentPage->addTab('dnssecTab', _('DNSSEC'), $dnssecTab);
	$incidentPage->addTab('rddsTab', _('RDDS'), $rddsTab);
	$incidentPage->addTab('eppTab', _('EPP'), $eppTab);

}
else {
	$incidentPage = new CTableInfo(_('No TLD defined.'));
}

$widget->addItem($incidentPage);

return $widget;
