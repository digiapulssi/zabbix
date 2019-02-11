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


$widget = (new CWidget())->setTitle(_('Tests'));

// filter
$filter = (new CFilter('web.rsm.tests.filter.state'))
	->addVar('filter_set', 1)
	->addVar('host', $this->data['tld']['name'])
	->addVar('type', $this->data['type'])
	->addVar('slvItemId', $this->data['slvItemId'])
	->addVar('filter_from', zbxDateToTime($data['filter_from']))
	->addVar('filter_to', zbxDateToTime($data['filter_to']));
$filterColumn1 = new CFormList();
$filterColumn2 = new CFormList();
$filterColumn3 = new CFormList();

$filterColumn1
	->addRow(_('From'), createDateSelector('filter_from', zbxDateToTime($this->data['filter_from'])));
$filterColumn2
	->addRow(_('To'), createDateSelector('filter_to', zbxDateToTime($this->data['filter_to'])));
$filterColumn3
	->addRow((new CLink(_('Rolling week'),
		'rsm.tests.php?type='.$this->data['type'].'&filter_set=1&filter_rolling_week=1'
			.'&host='.$this->data['tld']['name'].'&slvItemId='.$this->data['slvItemId'])
	)
		->addClass(ZBX_STYLE_BTN_LINK));

$filter
	->addColumn($filterColumn1)
	->addColumn($filterColumn2)
	->addColumn($filterColumn3);

$widget->addItem($filter);

$table = (new CTableInfo())
	->setHeader([
		_('Time'),
		_('Affects rolling week'),
		SPACE
]);

foreach ($this->data['tests'] as $test) {
	if (!$test['incident']) {
		$rollingWeekEffects = _('No');
	}
	elseif ($test['incident'] == 1) {
		$rollingWeekEffects = _('Yes');
	}
	else {
		$rollingWeekEffects = _('No / False positive');
	}

	$row = [
		date(DATE_TIME_FORMAT_SECONDS, $test['clock']),
		$rollingWeekEffects,
		new CLink(
			_('details'),
			'rsm.particulartests.php?slvItemId='.$this->data['slvItemId'].'&host='.$this->data['tld']['host'].
				'&time='.$test['clock'].'&type='.$this->data['type']
		)
	];

	$table->addRow($row);
}

if ($this->data['type'] == RSM_DNS) {
	$serviceName = _('DNS service availability');
}
elseif ($this->data['type'] == RSM_DNSSEC) {
	$serviceName = _('DNSSEC service availability');
}
elseif ($this->data['type'] == RSM_RDDS) {
	$serviceName = _('RDDS service availability');
}
else {
	$serviceName = _('EPP service availability');
}

$testsInfoTable = (new CTable(null))->addClass('incidents-info');

$testsInfoTable->addRow([[
	new CSpan([bold(_('TLD')), ':', SPACE, $this->data['tld']['name']]),
	BR(),
	new CSpan([bold(_('Service')), ':', SPACE, $serviceName])
]]);

$testsInfoTable->addRow([[
	[
		(new CSpan([bold(_('Number of tests downtime')), ':', SPACE, $this->data['downTests']]))->addClass('first-row-element'),
		new CSpan([bold(_('Number of mimutes downtime')), ':', SPACE, $this->data['downTimeMinutes']])
	],
	BR(),
	[
		(new CSpan([bold(_('Number of state changes')), ':', SPACE, $this->data['statusChanges']]))->addClass('first-row-element'),
		new CSpan([bold(_('Total time within selected period')), ':', SPACE, convertUnitsS($this->data['downPeriod'])])
	]
]]);

$widget->additem([$testsInfoTable]);

$widget->addItem([$table, $data['paging']]);

return $widget;
