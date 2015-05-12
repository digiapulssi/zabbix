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

$maintenanceWidget = (new CWidget())->setTitle(_('Maintenance periods'));

// create new maintenance button
$createForm = (new CForm('get'))->cleanItems();
$controls = new CList();
$controls->addItem(array(_('Group').SPACE, $this->data['pageFilter']->getGroupsCB()));
$controls->addItem(new CSubmit('form', _('Create maintenance period')));
$createForm->addItem($controls);
$maintenanceWidget->setControls($createForm);

// create form
$maintenanceForm = new CForm();
$maintenanceForm->setName('maintenanceForm');

// create table
$maintenanceTable = new CTableInfo();
$maintenanceTable->setHeader(array(
	new CColHeader(
		new CCheckBox('all_maintenances', null, "checkAll('".$maintenanceForm->getName()."', 'all_maintenances', 'maintenanceids');"),
		'cell-width'),
	make_sorting_header(_('Name'), 'name', $this->data['sort'], $this->data['sortorder']),
	make_sorting_header(_('Type'), 'maintenance_type', $this->data['sort'], $this->data['sortorder']),
	make_sorting_header(_('Active since'), 'active_since', $this->data['sort'], $this->data['sortorder']),
	make_sorting_header(_('Active till'), 'active_till', $this->data['sort'], $this->data['sortorder']),
	_('State'),
	_('Description')
));

foreach ($this->data['maintenances'] as $maintenance) {
	$maintenanceid = $maintenance['maintenanceid'];

	switch ($maintenance['status']) {
		case MAINTENANCE_STATUS_EXPIRED:
			$maintenanceStatus = new CSpan(_x('Expired', 'maintenance status'), ZBX_STYLE_RED);
			break;
		case MAINTENANCE_STATUS_APPROACH:
			$maintenanceStatus = new CSpan(_x('Approaching', 'maintenance status'), ZBX_STYLE_ORANGE);
			break;
		case MAINTENANCE_STATUS_ACTIVE:
			$maintenanceStatus = new CSpan(_x('Active', 'maintenance status'), ZBX_STYLE_GREEN);
			break;
	}

	$maintenanceTable->addRow(array(
		new CCheckBox('maintenanceids['.$maintenanceid.']', null, null, $maintenanceid),
		new CLink($maintenance['name'], 'maintenance.php?form=update&maintenanceid='.$maintenanceid),
		$maintenance['maintenance_type'] ? _('No data collection') : _('With data collection'),
		zbx_date2str(DATE_TIME_FORMAT, $maintenance['active_since']),
		zbx_date2str(DATE_TIME_FORMAT, $maintenance['active_till']),
		$maintenanceStatus,
		$maintenance['description']
	));
}

// append table to form
$maintenanceForm->addItem(array(
	$maintenanceTable,
	$this->data['paging'],
	new CActionButtonList('action', 'maintenanceids', array(
		'maintenance.massdelete' => array('name' => _('Delete'), 'confirm' => _('Delete selected maintenance periods?'))
	))
));

// append form to widget
$maintenanceWidget->addItem($maintenanceForm);

return $maintenanceWidget;
