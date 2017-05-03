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

/*
 * Dashboard grid
 */
$widgets = [
	1 => [
		'header' => _('Map Navigation Tree Widget'),
		'type' => WIDGET_NAVIGATION_TREE,
		'pos' => ['row' => 0, 'col' => 0, 'height' => 12, 'width' => 3],
		'rf_rate' => 15 * SEC_PER_MIN,
		'fields' => [
			'type' => WIDGET_NAVIGATION_TREE,
			'widget_name' => 'Map Navigation Tree Widget'
		]
	],
	2 => [
		'header' => _('Map widget'),
		'type' => WIDGET_SYSMAP,
		'pos' => ['row' => 0, 'col' => 3, 'height' => 12, 'width' => 9],
		'rf_rate' => 15 * SEC_PER_MIN,
		'fields' => [
			'type' => WIDGET_SYSMAP,
			'widget_name' => 'Map widget',
			'source_type' => WIDGET_NAVIGATION_TREE,
			'sysmap_id' => null,
			'filter_id' => null
		]
	],/*
	2 => [
		'header' => _('Favourite graphs'),
		'type' => WIDGET_FAVOURITE_GRAPHS,
		'pos' => ['row' => 1, 'col' => 2, 'height' => 3, 'width' => 2],
		'rf_rate' => 15 * SEC_PER_MIN
	],
	3 => [
		'header' => _('Favourite screens'),
		'type' => WIDGET_FAVOURITE_SCREENS,
		'pos' => ['row' => 1, 'col' => 4, 'height' => 3, 'width' => 2],
		'rf_rate' => 15 * SEC_PER_MIN
	],
	4 => [
		'header' => _('Favourite maps'),
		'type' => WIDGET_FAVOURITE_MAPS,
		'pos' => ['row' => 0, 'col' => 4, 'height' => 3, 'width' => 2],
		'rf_rate' => 15 * SEC_PER_MIN
	],
	4 => [
		'header' => _n('Last %1$d issue', 'Last %1$d issues', DEFAULT_LATEST_ISSUES_CNT),
		'type' => WIDGET_LAST_ISSUES,
		'pos' => ['row' => 4, 'col' => 0, 'height' => 6, 'width' => 6],
		'rf_rate' => SEC_PER_MIN
	],
	5 => [
		'header' => _('Web monitoring'),
		'type' => WIDGET_WEB_OVERVIEW,
		'pos' => ['row' => 9, 'col' => 0, 'height' => 4, 'width' => 3],
		'rf_rate' => SEC_PER_MIN
	],
	6 => [
		'header' => _('Host status'),
		'type' => WIDGET_HOST_STATUS,
		'pos' => ['row' => 0, 'col' => 6, 'height' => 4, 'width' => 6],
		'rf_rate' => SEC_PER_MIN
	],
	7 => [
		'header' => _('System status'),
		'type' => WIDGET_SYSTEM_STATUS,
		'pos' => ['row' => 4, 'col' => 6, 'height' => 4, 'width' => 6],
		'rf_rate' => SEC_PER_MIN
	],
	8 => [
		'header' => _('Clock'),
		'type' => WIDGET_CLOCK,
		'pos' => ['row' => 9, 'col' => 3, 'height' => 4, 'width' => 3],
		'rf_rate' => 15 * SEC_PER_MIN
	],
	9 => [
		'header' => _('URL'),
		'type' => WIDGET_URL,
		'pos' => ['row' => 13, 'col' => 0, 'height' => 4, 'width' => 3],
		'rf_rate' => 0
	]
	*/
];

if (!empty($data['grid_widgets'])) {
	$grid_widgets = $data['grid_widgets'];
} else { // TODO VM: delete. Later it should be managed by API or dashboards.
	$grid_widgets = [];

	foreach ($widgets as $widgetid => $widget) {
		$grid_widgets[] = [
			'widgetid' => $widgetid,
			'type' => $widget['type'],
			'header' => $widget['header'],
			'pos' => [
				'col' => (int) CProfile::get('web.dashbrd.widget.'.$widgetid.'.col', $widget['pos']['col']),
				'row' => (int) CProfile::get('web.dashbrd.widget.'.$widgetid.'.row', $widget['pos']['row']),
				'height' => (int) CProfile::get('web.dashbrd.widget.'.$widgetid.'.height', $widget['pos']['height']),
				'width' => (int) CProfile::get('web.dashbrd.widget.'.$widgetid.'.width', $widget['pos']['width'])
			],
			'rf_rate' => (int) CProfile::get('web.dashbrd.widget.'.$widgetid.'.rf_rate', $widget['rf_rate']),
			'event_triggers' => array_key_exists('event_triggers', $widget) ? $widget['event_triggers'] : [],
			'fields' => $widget['fields']
		];
	}
}

// TODO miks: don't leave it here. Widgettype can be changed after initial json is loaded.
$_widget_default_features = [
	WIDGET_NAVIGATION_TREE => [
		'event_triggers' => [
			'beforeSave' => '$("#tree").zbx_navtree("beforeSave")',
			'onEditStart' => '$("#tree").zbx_navtree("onEditStart")',
			'onEditStop' => '$("#tree").zbx_navtree("onEditStop")',
		]
	]
];

if ($grid_widgets) {
	$grid_widgets = array_map(function(&$widget) use($_widget_default_features) {
		if (array_key_exists($widget['type'], $_widget_default_features)) {
			$widget += $_widget_default_features[$widget['type']];
		}
		return $widget;
	}, $grid_widgets);
}

(new CWidget())
	->setTitle(_('Dashboard'))
	->setControls((new CForm())
		->cleanItems()
		->addItem((new CList())
			// 'Edit dashboard' should be first one in list,
			// because it will be visually replaced by last item of new list, when clicked
			->addItem((new CButton('dashbrd-edit',_('Edit dashboard'))))
			->addItem(get_icon('dashconf', ['enabled' => $data['filter_enabled']]))
			->addItem(get_icon('fullscreen', ['fullscreen' => $data['fullscreen']]))
		)
	)
	->addItem((new CDiv())->addClass(ZBX_STYLE_DASHBRD_GRID_WIDGET_CONTAINER))
	->show();

/*
 * Javascript
 */
// activating blinking
$this->addPostJS('jqBlink.blink();');

// Initialize dashboard grid
$this->addPostJS(
	'jQuery(".'.ZBX_STYLE_DASHBRD_GRID_WIDGET_CONTAINER.'")'.
		'.dashboardGrid()'.
		'.dashboardGrid("addWidgets", '.CJs::encodeJson($grid_widgets).');'
);
