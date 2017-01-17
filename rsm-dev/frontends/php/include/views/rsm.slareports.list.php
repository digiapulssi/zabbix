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


require_once dirname(__FILE__).'/js/rsm.slareports.list.js.php';

$widget = (new CWidget())->setTitle(_('SLA report'));

$filterForm = new CFilter('web.rsm.slareports.filter.state');

$filterColumn = new CFormList();
$filterColumn->addRow(_('TLD'), (new CTextBox('filter_search', $this->data['filter_search']))
	->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
	->setAttribute('autocomplete', 'off')
);

$months = [];
for ($i = 1; $i <= 12; $i++) {
	$months[$i] = getMonthCaption($i);
}

$years = [];
for ($i = SLA_MONITORING_START_YEAR; $i <= date('Y', time()); $i++) {
	$years[$i] = $i;
}

$filterColumn->addRow(_('Period'), [
	new CComboBox('filter_month', $this->data['filter_month'], null, $months),
	SPACE,
	new CComboBox('filter_year', $this->data['filter_year'], null, $years)
]);

$filterForm->addColumn($filterColumn)
	->addNavigator();

$widget->addItem($filterForm);

// create form
$form = (new CForm())
	->setName('scenarios');

$table = (new CTableInfo())
	->setHeader([
		_('Service'),
		_('Detail'),
		_('From'),
		_('To'),
		_('SLV'),
		_('Monthly SLR'),
		SPACE
	]);


foreach ($data['services'] as $name => $service) {
	$table->addRow(array(
		$service['main'] ? bold($service['name']) : $service['name'],
		$service['details'],
		$service['from'],
		$service['to'],
		$service['slv'],
		$service['slr'],
		$service['screen']
	));
}

$form->addItem([
	$table
]);
// append form to widget
$widget->addItem($form);

return $widget;
