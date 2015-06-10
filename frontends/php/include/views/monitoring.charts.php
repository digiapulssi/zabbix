<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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


$chartsWidget = (new CWidget())->setTitle(_('Graphs'));

$chartForm = new CForm('get');
$chartForm->addVar('fullscreen', $this->data['fullscreen']);

$controls = new CList();

$controls->addItem([_('Group').SPACE, $this->data['pageFilter']->getGroupsCB()]);
$controls->addItem([_('Host').SPACE, $this->data['pageFilter']->getHostsCB()]);
$controls->addItem([_('Graph').SPACE, $this->data['pageFilter']->getGraphsCB()]);

if ($this->data['graphid']) {
	$controls->addItem(get_icon('favourite', ['fav' => 'web.favorite.graphids', 'elname' => 'graphid', 'elid' => $this->data['graphid']]));
	$controls->addItem(get_icon('reset', ['id' => $this->data['graphid']]));
	$controls->addItem(get_icon('fullscreen', ['fullscreen' => $this->data['fullscreen']]));
}
else {
	$controls->addItem([get_icon('fullscreen', ['fullscreen' => $this->data['fullscreen']])]);
}

$chartForm->addItem($controls);
$chartsWidget->setControls($chartForm);

$filterForm = new CFilter('web.charts.filter.state');
$filterForm->addNavigator();
$chartsWidget->addItem($filterForm);

if (!empty($this->data['graphid'])) {
	// append chart to widget
	$screen = CScreenBuilder::getScreen([
		'resourcetype' => SCREEN_RESOURCE_CHART,
		'graphid' => $this->data['graphid'],
		'profileIdx' => 'web.screens',
		'profileIdx2' => $this->data['graphid']
	]);

	$chartTable = (new CTable())
		->addClass('maxwidth')
		->addRow($screen->get());

	$chartsWidget->addItem($chartTable);

	CScreenBuilder::insertScreenStandardJs([
		'timeline' => $screen->timeline,
		'profileIdx' => $screen->profileIdx
	]);
}
else {
	$screen = new CScreenBuilder();
	CScreenBuilder::insertScreenStandardJs([
		'timeline' => $screen->timeline
	]);

	$chartsWidget->addItem(new CTableInfo());
}

return $chartsWidget;
