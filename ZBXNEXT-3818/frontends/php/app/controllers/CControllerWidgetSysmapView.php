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

require_once dirname(__FILE__).'/../../include/blocks.inc.php';

class CControllerWidgetSysmapView extends CController {
	private $form;

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'name' =>			'string',
			'uniqueid' =>		'required|string',
			'edit_mode' =>		'in 0,1',
			'initial_load' =>	'in 0,1',
			'fullscreen' =>		'in 0,1',
			'fields' =>			'array',
			'storage' =>		'array'
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			/*
			 * @var array  $fields
			 * @var int    $fields['source_type']              (optional)
			 * @var string $fields['filter_widget_reference']  (optional)
			 * @var string $fields['sysmapid']                 (optional)
			 * @var array  $storage
			 * @var string $storage['current_sysmapid']
			 * @var string $storage['previous_maps']
			 */
			$this->form = CWidgetConfig::getForm(WIDGET_SYSMAP, $this->getInput('fields', []));

			if ($errors = $this->form->validate()) {
				$ret = false;
			}

			// TODO VM: implement validation for previous_maps and current_sysmapid in storage
		}

		if (!$ret) {
			$output = [];

			if (($messages = getMessages()) !== null) {
				$output['errors'][] = $messages->toString();
			}

			$this->setResponse(new CControllerResponseData(['main_block' => CJs::encodeJson($output)]));
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->getUserType() >= USER_TYPE_ZABBIX_USER);
	}

	protected function doAction() {
		$fields = $this->form->getFieldsData();
		$storage = $this->getInput('storage', []);
		$uniqueid = $this->getInput('uniqueid');
		$edit_mode = $this->getInput('edit_mode', 0);
		$initial_load = $this->getInput('initial_load', 1);
		$error = null;

		// Get previous map.
		$previous_map = null;
		if (array_key_exists('previous_maps', $storage)) {
			$previous_map = array_filter(explode(',', $storage['previous_maps']), 'is_numeric');

			if ($previous_map) {
				$previous_map = API::Map()->get([
					'sysmapids' => [array_pop($previous_map)],
					'output' => ['sysmapid', 'name']
				]);

				$previous_map = reset($previous_map);
			}
		}

		// Get requested map.
		$options = [
			'fullscreen' => $this->getInput('fullscreen', 0)
		];

		$sysmapid = array_key_exists('current_sysmapid', $storage) ? $storage['current_sysmapid']
			: (array_key_exists('sysmapid', $fields) ? $fields['sysmapid'] : null);
		$sysmap_data = CMapHelper::get(($sysmapid === null ? [] : [$sysmapid]), $options);

		if ($sysmapid === null) {
			$error = _('No map selected.');
		}
		elseif ($sysmap_data['id'] < 0) {
			$error = _('No permissions to selected map or it does not exist.');
		}

		// Rewrite actions to force Submaps be opened in same widget, instead of separate window.
		foreach ($sysmap_data['elements'] as &$element) {
			if ($edit_mode) {
				$element['actions'] = json_encode([]);
			}
			else {
				$actions = json_decode($element['actions'], true);
				if ($actions && array_key_exists('gotos', $actions) && array_key_exists('submap', $actions['gotos'])) {
					$actions['navigatetos']['submap'] = $actions['gotos']['submap'];
					$actions['navigatetos']['submap']['widget_uniqueid'] = $uniqueid;
					unset($actions['gotos']['submap']);
				}

				$element['actions'] = json_encode($actions);
			}
		}
		unset($element);

		// Pass variables to view.
		$this->setResponse(new CControllerResponseData([
			'name' => $this->getInput('name', CWidgetConfig::getKnownWidgetTypes()[WIDGET_SYSMAP]),
			'sysmap_data' => $sysmap_data,
			'widget_settings' => [
				'current_sysmapid' => $sysmapid,
				'filter_widget_reference' => array_key_exists('filter_widget_reference', $fields)
					? $fields['filter_widget_reference']
					: null,
				'source_type' => $fields['source_type'],
				'previous_map' => $previous_map,
				'initial_load' => $initial_load,
				'uniqueid' => $uniqueid,
				'error' => $error
			],
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		]));
	}
}
