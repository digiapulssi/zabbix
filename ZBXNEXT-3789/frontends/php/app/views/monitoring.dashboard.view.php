<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


$this->addJsFile('dashboard.grid.js');
$this->includeJSfile('app/views/monitoring.dashboard.view.js.php');

$sharing_form = include 'monitoring.dashboard.sharing_form.php';
$edit_form = include 'monitoring.dashboard.edit_form.php';
$breadcrumbs = include 'monitoring.dashboard.breadcrumbs.php';

$item_groupid = null;
$item_hostid = null;
if ($data['dynamic']['has_dynamic_widgets']) {
	$item_groupid = [
		new CLabel(_('Group'), 'groupid'),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		$data['pageFilter']->getGroupsCB()
	];
	$item_hostid = [
		new CLabel(_('Host'), 'hostid'),
		(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
		$data['pageFilter']->getHostsCB()
	];
}

$url_create = (new CUrl('zabbix.php'))
	->setArgument('action', 'dashboard.view')
	->setArgument('new', '1');
$url_clone = (new CUrl('zabbix.php'))
	->setArgument('action', 'dashboard.view')
	->setArgument('source_dashboardid', $data['dashboard']['dashboardid']);
if ($data['dashboard']['editable']) {
	$url_delete = (new CUrl('zabbix.php'))
		->setArgument('action', 'dashboard.delete')
		->setArgument('dashboardids', [$data['dashboard']['dashboardid']])
		->setArgumentSID();
}
if ($data['fullscreen']) {
	$url_create->setArgument('fullscreen', '1');
	$url_clone->setArgument('fullscreen', '1');
	if ($data['dashboard']['editable']) {
		$url_delete->setArgument('fullscreen', '1');
	}
}

(new CWidget())
	->setTitle($data['dashboard']['name'])
	->setControls((new CForm('post', 'zabbix.php?action=dashboard.view'))
		->cleanItems()
		->addItem((new CList())
			// $item_groupid and $item_hostid will be hidden, when 'Edit Dashboard' will be clicked.
			->addItem($item_groupid)
			->addItem($item_hostid)
			// 'Edit dashboard' should be first one in list,
			// because it will be visually replaced by last item of new list, when clicked
			->addItem((new CButton('dashbrd-edit', _('Edit dashboard')))->setEnabled($data['dashboard']['editable']))
			->addItem((new CButton(SPACE))
				->addClass(ZBX_STYLE_BTN_ACTION)
				->setId('dashbrd-actions')
				->setTitle(_('Actions'))
				->setMenuPopup([
					'type' => 'dashboard',
					'label' => _('Actions'),
					'items' => [
						'sharing' => [
							'label' => _('Sharing'),
							'form_data' => [
								'dashboardid' => $data['dashboard']['dashboardid']
							],
							'disabled' => !$data['dashboard']['editable']
						],
						'create' => [
							'label' => _('Create new'),
							'url' => $url_create->getUrl()
						],
						'clone' => [
							'label' => _('Clone'),
							'url' => $url_clone->getUrl()
						],
						'delete' => [
							'label' => _('Delete'),
							'confirmation' => _('Delete dashboard?'),
							'url' => 'javascript:void(0)',
							'redirect' => $data['dashboard']['editable']
								? $url_delete->getUrl()
								: null,
							'disabled' => !$data['dashboard']['editable']
						]
					]
				])
			)
			->addItem(get_icon('fullscreen', ['fullscreen' => $data['fullscreen']]))
		)
	)
	->addItem((new CList())
		->addItem($breadcrumbs)
		->addClass(ZBX_STYLE_OBJECT_GROUP)
	)
	->addItem((new CDiv())->addClass(ZBX_STYLE_DASHBRD_GRID_WIDGET_CONTAINER))
	->addItem($edit_form)
	->addItem($sharing_form)
	->show();

/*
 * Javascript
 */
// activating blinking
$this->addPostJS('jqBlink.blink();');

$dashboard_data = [
	// name is required for new dashboard creation
	'name'   => $data['dashboard']['name'],
	'userid' => $data['dashboard']['owner']['id'],
	'dynamic' => $data['dynamic']
];
$dashboard_options = [
	'fullscreen' => $data['fullscreen']
];
if ($data['dashboard']['dashboardid'] != 0) {
	$dashboard_data['id'] = $data['dashboard']['dashboardid'];
}
else {
	$dashboard_options['updated'] = true;
}
// Initialize dashboard grid
$this->addPostJS(
	'jQuery(".'.ZBX_STYLE_DASHBRD_GRID_WIDGET_CONTAINER.'")'.
		'.dashboardGrid('.CJs::encodeJson($dashboard_options).')'.
		'.dashboardGrid("setDashboardData", '.CJs::encodeJson($dashboard_data).')'.
		'.dashboardGrid("setWidgetDefaults", '.CJs::encodeJson($data['widget_defaults']).')'.
		'.dashboardGrid("addWidgets", '.CJs::encodeJson($data['grid_widgets']).');'
);
