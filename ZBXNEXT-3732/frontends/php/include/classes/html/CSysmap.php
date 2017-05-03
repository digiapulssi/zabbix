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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

// TODO miks: 
// since it's made as prototype to show customer how it works, 
// it is build based on existing CScreenBuilder functionality. 
// 
// IT SHOULD BE MADE INDEPENDENT FROM SCREENS later.

class CSysmap extends CDiv {
	private $error;
	private $script_file;
	private $script_run;
	private $sysmap_data;

	public function __construct($config_data) {
		parent::__construct();
		$this->error = null;
		
		$this->sysmap_conf = $config_data;
		
		$this->setId(uniqid());
		$this->addClass(ZBX_STYLE_SYSMAP);

		if ($this->sysmap_conf['sysmap_id']) {
			$this->sysmap_data = CMapHelper::get($this->sysmap_conf['sysmap_id'], $this->sysmap_conf['severity_min']);
			
			if ($this->sysmap_data) {
				$this->sysmap_data['container'] = "#map_{$this->sysmap_conf['widgetid']}";
			}
		}

		// TODO miks: let them be loaded only once.
		$this->script_file = [
			'js/gtlc.js',
			'js/flickerfreescreen.js',
			'js/vector/class.svg.canvas.js',
			'js/vector/class.svg.map.js'
		];
		$this->script_run = '';
	}

	public function setError($value) {
		$this->error = $value;

		return $this;
	}

	public function getScriptFile() {
		return $this->script_file;
	}

	public function getScriptRun() {
		if ($this->error === null) {
			if (is_numeric($this->sysmap_conf['filter_id']) && $this->sysmap_conf['source_type'] == WIDGET_NAVIGATION_TREE) {
				$this->script_run =
					//'console.log(\'loading map: \'+'.$this->sysmap_conf['sysmap_id'].');' .
					'jQuery(".dashbrd-grid-widget-container").dashboardGrid(\'registerWidget\', {' .
						'widgetid: '.$this->sysmap_conf['widgetid'].',' .
						'sourceWidget: '.$this->sysmap_conf['filter_id'].',' .
						'callback: function(widget, data){' .
						' if(data[0].mapid !== +data[0].mapid) return;'.
						'	jQuery(".dashbrd-grid-widget-container").dashboardGrid(\'setWidgetFieldValue\', widget.widgetid, \'sysmap_id\', data[0].mapid);' .
						'	jQuery(".dashbrd-grid-widget-container").dashboardGrid(\'refreshWidget\', widget.widgetid);' .
						'}' .
					'});';
			}

			if ($this->sysmap_data) {
				$this->script_run .= 'jQuery(document).ready(function(){'.
					(new CScreenBase(['resourcetype' => SCREEN_RESOURCE_MAP]))->buildFlickerfreeJs($this->sysmap_data, true).
				'});';
			}
		}

		return $this->script_run;
	}

	private function build() {
		$map = (new CDiv())
			->addClass(ZBX_STYLE_TABLE_FORMS_CONTAINER)
			->addClass(ZBX_STYLE_SYSMAP)
			->addItem(
				CScreenBuilder::getScreen([
					'resourcetype' => SCREEN_RESOURCE_MAP,
					'mode' => SCREEN_MODE_PREVIEW,
					'dataId' => 'mapimg',
					'screenitem' => [
						'screenitemid' => $this->sysmap_conf['widgetid'],
						'screenid' => null,
						'resourceid' => $this->sysmap_conf['widgetid'],
						'width' => null,
						'height' => null,
						'severity_min' => $this->sysmap_conf['severity_min'],
						'fullscreen' => $this->sysmap_conf['fullscreen']
					]
				])->get()
			);

		if ($this->error !== null) {
			$map->addClass(ZBX_STYLE_DISABLED);
		}

		$this->addItem($map);
	}

	public function toString($destroy = true) {
		$this->build();

		return parent::toString($destroy);
	}
}
