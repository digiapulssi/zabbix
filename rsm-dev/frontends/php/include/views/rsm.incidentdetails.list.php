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
$filter = (new CFilter('web.rsm.incidentdetails.filter.state'))
	->addVar('filter_set', 1)
	->addVar('filter_from', zbxDateToTime($data['filter_from']))
	->addVar('filter_to', zbxDateToTime($data['filter_to']))
	->addVar('original_from', zbxDateToTime($data['filter_from']))
	->addVar('original_to', zbxDateToTime($data['filter_to']))
	->addVar('eventid', $data['eventid'])
	->addVar('slvItemId', $data['slvItemId'])
	->addVar('availItemId', $data['availItemId']);
$filterColumn1 = new CFormList();
$filterColumn2 = new CFormList();
$filterColumn3 = new CFormList();
$filterColumn4 = new CFormList();
$filterColumn5 = new CFormList();

$filterColumn1
	->addRow(_('From'), createDateSelector('filter_from', zbxDateToTime($this->data['filter_from'])));
$filterColumn2
	->addRow(_('To'), createDateSelector('filter_to', zbxDateToTime($this->data['filter_to'])));
$filterColumn3
	->addRow((new CLink(_('Rolling week'),
		'rsm.incidentdetails.php?incident_type='.$this->data['type'].'&filter_set=1&filter_rolling_week=1&host='.$this->data['tld']['name'])
	)
		->addClass(ZBX_STYLE_BTN_LINK));
$filterColumn4
	->addRow(new CSpan(array(
		new CCheckBox('filter_failing_tests',
			isset($data['filter_failing_tests']) ? $data['filter_failing_tests'] : null, null, 1),
		SPACE,
		_('Only failing tests')
)));
$filterColumn5
	->addRow(new CSpan(array(
		new CCheckBox('filter_show_all',
			isset($data['filter_show_all']) ? $data['filter_show_all'] : null, null, 1),
		SPACE,
		_('Show all')
)));

$filter
	->addColumn($filterColumn1)
	->addColumn($filterColumn2)
	->addColumn($filterColumn3)
	->addColumn($filterColumn4)
	->addColumn($filterColumn5);

$widget->addItem($filter);

$table = (new CTableInfo())
	->setHeader([
	_('Incident'),
	_('Time'),
	_('Result'),
	_('Historical rolling week value'),
	SPACE
]);

foreach ($data['tests'] as $test) {
	if (isset($test['startEvent']) && $test['startEvent']) {
		$startEndIncident = _('Start time');
	}
	elseif (isset($test['endEvent']) && $test['endEvent'] != TRIGGER_VALUE_TRUE) {
		if ($test['endEvent'] == TRIGGER_VALUE_FALSE) {
			$startEndIncident = _('Resolved');
		}
		else {
			$startEndIncident = _('Resolved (no data)');
		}
	}
	else {
		$startEndIncident = SPACE;
	}

	$value = $test['value'] ? _('Up') : _('Down');

	$row = array(
		$startEndIncident,
		date('d.m.Y H:i:s', $test['clock']),
		$value,
		isset($test['slv']) ? $test['slv'].'%' : '-',
		new CLink(
			_('details'),
			'rsm.particulartests.php?slvItemId='.$data['slvItemId'].'&host='.$data['tld']['host'].
				'&time='.$test['clock'].'&type='.$data['type']
		)
	);

	$table->addRow($row);
}

if ($data['incidentType'] == INCIDENT_ACTIVE) {
	$incidentType = _('Active');
	$changeIncidentType = INCIDENT_FALSE_POSITIVE;
	$changeIncidentTypeName = _('Mark incident as false positive');
}
elseif ($data['incidentType'] == INCIDENT_RESOLVED) {
	$incidentType = _('Resolved');
	$changeIncidentType = INCIDENT_FALSE_POSITIVE;
	$changeIncidentTypeName = _('Mark incident as false positive');
}
elseif ($data['incidentType'] == INCIDENT_RESOLVED_NO_DATA) {
	$incidentType = _('Resolved (no data)');
	$changeIncidentType = INCIDENT_FALSE_POSITIVE;
	$changeIncidentTypeName = _('Mark incident as false positive');
}
else {
	$incidentType = _('False positive');
	$changeIncidentType = $data['active'] ? NCIDENT_ACTIVE : INCIDENT_RESOLVED;
	$changeIncidentTypeName = _('Unmark incident as false positive');
}

$testsInfoTable = (new CTable(null))->addClass('incidents-info');

$testsInfoTable->addRow([[
	new CSpan([bold(_('TLD')), ':', SPACE, $this->data['tld']['name']]),
	BR(),
	new CSpan([bold(_('Service')), ':', SPACE, $data['slvItem']['name']]),
	BR(),
	new CSpan([bold(_('Incident type')), ':', SPACE, $incidentType])
]]);

$widget->additem([$testsInfoTable]);

$widget->addItem([$table]);

$widget->addItem((new CButton('mark_incident', $changeIncidentTypeName,
	'javascript: location.href = "rsm.incidents.php?mark_incident='.$changeIncidentType.
	'&eventid='.$data['eventid'].'&host='.$data['tld']['host'].'&type='.$data['type'].'";'
))
	->addStyle('margin-top: 5px;')
);

return $widget;
