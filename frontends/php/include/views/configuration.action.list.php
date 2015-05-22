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

$actionWidget = (new CWidget())->setTitle(_('Actions'));

// create new action button
$createForm = new CForm('get');
$createForm->cleanItems();
$createForm->addVar('eventsource', $data['eventsource']);
$controls = new CList();

// create widget header
$controls->addItem(array(_('Event source'), SPACE, new CComboBox('eventsource', $data['eventsource'], 'submit()',
	array(
		EVENT_SOURCE_TRIGGERS => _('Triggers'),
		EVENT_SOURCE_DISCOVERY => _('Discovery'),
		EVENT_SOURCE_AUTO_REGISTRATION => _('Auto registration'),
		EVENT_SOURCE_INTERNAL => _x('Internal', 'event source')
	)
)));
$controls->addItem(new CSubmit('form', _('Create action')));

$createForm->addItem($controls);

$actionWidget->setControls($createForm);

// create form
$actionForm = new CForm();
$actionForm->setName('actionForm');

// create table
$actionTable = new CTableInfo();
$actionTable->setHeader(array(
	new CColHeader(
		new CCheckBox('all_items', null, "checkAll('".$actionForm->getName()."', 'all_items', 'g_actionid');"),
		'cell-width'),
	make_sorting_header(_('Name'), 'name', $this->data['sort'], $this->data['sortorder']),
	_('Conditions'),
	_('Operations'),
	make_sorting_header(_('Status'), 'status', $this->data['sort'], $this->data['sortorder'])
));

if ($this->data['actions']) {
	$actionConditionStringValues = actionConditionValueToString($this->data['actions'], $this->data['config']);
	$actionOperationDescriptions = getActionOperationDescriptions($this->data['actions']);

	foreach ($this->data['actions'] as $aIdx => $action) {
		$conditions = array();
		$operations = array();

		order_result($action['filter']['conditions'], 'conditiontype', ZBX_SORT_DOWN);

		foreach ($action['filter']['conditions'] as $cIdx => $condition) {
			$conditions[] = getConditionDescription($condition['conditiontype'], $condition['operator'],
				$actionConditionStringValues[$aIdx][$cIdx]
			);
			$conditions[] = BR();
		}

		sortOperations($data['eventsource'], $action['operations']);

		foreach ($action['operations'] as $oIdx => $operation) {
			$operations[] = $actionOperationDescriptions[$aIdx][$oIdx];
		}

		if ($action['status'] == ACTION_STATUS_DISABLED) {
			$status = new CLink(_('Disabled'),
				'actionconf.php?action=action.massenable&g_actionid[]='.$action['actionid'].url_param('eventsource'),
				ZBX_STYLE_LINK_ACTION.' '.ZBX_STYLE_RED
			);
		}
		else {
			$status = new CLink(_('Enabled'),
				'actionconf.php?action=action.massdisable&g_actionid[]='.$action['actionid'].url_param('eventsource'),
				ZBX_STYLE_LINK_ACTION.' '.ZBX_STYLE_GREEN
			);
		}

		$actionTable->addRow([
			new CCheckBox('g_actionid['.$action['actionid'].']', null, null, $action['actionid']),
			new CLink($action['name'], 'actionconf.php?form=update&actionid='.$action['actionid']),
			$conditions,
			$operations,
			$status
		]);
	}
}

// append table to form
$actionForm->addItem(array(
	$actionTable,
	$this->data['paging'],
	new CActionButtonList('action', 'g_actionid', array(
		'action.massenable' => array('name' => _('Enable'), 'confirm' => _('Enable selected actions?')),
		'action.massdisable' => array('name' => _('Disable'), 'confirm' => _('Disable selected actions?')),
		'action.massdelete' => array('name' => _('Delete'), 'confirm' => _('Delete selected actions?'))
	))
));

// append form to widget
$actionWidget->addItem($actionForm);

return $actionWidget;
