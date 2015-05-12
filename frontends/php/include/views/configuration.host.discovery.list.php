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

$discoveryWidget = (new CWidget())->setTitle(_('Discovery rules'));

// create new discovery rule button
$createForm = new CForm('get');
$createForm->cleanItems();
$controls = new CList();
$createForm->addVar('hostid', $this->data['hostid']);
$controls->addItem(new CSubmit('form', _('Create discovery rule')));
$createForm->addItem($controls);
$discoveryWidget->setControls($createForm);

// header
$discoveryWidget->addItem(get_header_host_table('discoveries', $this->data['hostid']));

// create form
$discoveryForm = new CForm();
$discoveryForm->setName('discovery');
$discoveryForm->addVar('hostid', $this->data['hostid']);

// create table
$discoveryTable = new CTableInfo();

$discoveryTable->setHeader(array(
	new CColHeader(
		new CCheckBox('all_items', null, "checkAll('".$discoveryForm->getName()."', 'all_items', 'g_hostdruleid');"),
		'cell-width'),
	make_sorting_header(_('Name'), 'name', $this->data['sort'], $this->data['sortorder']),
	_('Items'),
	_('Triggers'),
	_('Graphs'),
	($data['host']['flags'] == ZBX_FLAG_DISCOVERY_NORMAL) ? _('Hosts') : null,
	make_sorting_header(_('Key'), 'key_', $this->data['sort'], $this->data['sortorder']),
	make_sorting_header(_('Interval'), 'delay', $this->data['sort'], $this->data['sortorder']),
	make_sorting_header(_('Type'), 'type', $this->data['sort'], $this->data['sortorder']),
	make_sorting_header(_('Status'), 'status', $this->data['sort'], $this->data['sortorder']),
	$data['showInfoColumn'] ? _('Info') : null
));

foreach ($data['discoveries'] as $discovery) {
	// description
	$description = array();

	if ($discovery['templateid']) {
		$dbTemplate = get_realhost_by_itemid($discovery['templateid']);

		$description[] = new CLink($dbTemplate['name'], '?hostid='.$dbTemplate['hostid'], ZBX_STYLE_LINK_ALT.' '.ZBX_STYLE_GREY);
		$description[] = NAME_DELIMITER;
	}

	$description[] = new CLink($discovery['name_expanded'], '?form=update&itemid='.$discovery['itemid']);

	// status
	$status = new CLink(
		itemIndicator($discovery['status'], $discovery['state']),
		'?hostid='.$_REQUEST['hostid'].
			'&g_hostdruleid='.$discovery['itemid'].
			'&action='.($discovery['status'] == ITEM_STATUS_DISABLED
				? 'discoveryrule.massenable'
				: 'discoveryrule.massdisable'
			),
		ZBX_STYLE_LINK_ACTION.' '.itemIndicatorStyle($discovery['status'], $discovery['state'])
	);

	// info
	if ($data['showInfoColumn']) {
		if ($discovery['status'] == ITEM_STATUS_ACTIVE && !zbx_empty($discovery['error'])) {
			$info = new CDiv(SPACE, 'status_icon iconerror');
			$info->setHint($discovery['error'], ZBX_STYLE_RED);
		}
		else {
			$info = '';
		}
	}
	else {
		$info = null;
	}

	// host prototype link
	$hostPrototypeLink = null;
	if ($data['host']['flags'] == ZBX_FLAG_DISCOVERY_NORMAL) {
		$hostPrototypeLink = array(
			new CLink(_('Host prototypes'), 'host_prototypes.php?parent_discoveryid='.$discovery['itemid']),
			CViewHelper::showNum($discovery['hostPrototypes'])
		);
	}

	$discoveryTable->addRow(array(
		new CCheckBox('g_hostdruleid['.$discovery['itemid'].']', null, null, $discovery['itemid']),
		$description,
		array(
			new CLink(
				_('Item prototypes'),
				'disc_prototypes.php?parent_discoveryid='.$discovery['itemid']
			),
			CViewHelper::showNum($discovery['items'])
		),
		array(
			new CLink(
				_('Trigger prototypes'),
				'trigger_prototypes.php?parent_discoveryid='.$discovery['itemid']
			),
			CViewHelper::showNum($discovery['triggers'])
		),
		array(
			new CLink(
				_('Graph prototypes'),
				'graphs.php?parent_discoveryid='.$discovery['itemid']
			),
			CViewHelper::showNum($discovery['graphs'])
		),
		$hostPrototypeLink,
		$discovery['key_'],
		convertUnitsS($discovery['delay']),
		item_type2str($discovery['type']),
		$status,
		$info
	));
}

zbx_add_post_js('cookie.prefix = "'.$this->data['hostid'].'";');

// append table to form
$discoveryForm->addItem(array(
	$discoveryTable,
	$this->data['paging'],
	new CActionButtonList('action', 'g_hostdruleid',
		array(
			'discoveryrule.massenable' => array('name' => _('Enable'),
				'confirm' =>_('Enable selected discovery rules?')
			),
			'discoveryrule.massdisable' => array('name' => _('Disable'),
				'confirm' =>_('Disable selected discovery rules?')
			),
			'discoveryrule.massdelete' => array('name' => _('Delete'),
				'confirm' =>_('Delete selected discovery rules?')
			)
		),
		$this->data['hostid']
	)
));

// append form to widget
$discoveryWidget->addItem($discoveryForm);

return $discoveryWidget;
