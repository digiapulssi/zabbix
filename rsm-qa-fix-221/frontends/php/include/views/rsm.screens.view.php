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


$widget = (new CWidget())->setTitle(_('SLA report'));

$filterForm = new CFilter('web.rsm.screens.filter.state');

$filterColumn = new CFormList();
$filterColumn->addVar('filter_set', 1);
$filterColumn->addVar('checkallvalue', 0);
$filterColumn->addVar('filter_set', 1);
$filterColumn->addVar('tld', $data['tld']);
$filterColumn->addVar('type', $data['type']);
$filterColumn->addVar('item_key', $data['item_key']);

$months = [];
for ($i = 1; $i <= 12; $i++) {
	$months[$i] = getMonthCaption($i);
}

$years = [];
for ($i = SLA_MONITORING_START_YEAR; $i <= date('Y', time()); $i++) {
	$years[$i] = $i;
}

$filterColumn->addRow(_('Period'), [
	new CComboBox('filter_month', $data['filter_month'], null, $months),
	SPACE,
	new CComboBox('filter_year', $data['filter_year'], null, $years)
]);

$filterForm->addColumn($filterColumn);

$widget->addItem($filterForm);

// append form to widget
$widget->addItem($data['screen']);

return $widget;
