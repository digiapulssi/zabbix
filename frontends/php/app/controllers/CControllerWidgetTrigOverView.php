<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


require_once dirname(__FILE__).'/../../include/hostgroups.inc.php';

class CControllerWidgetTrigOverView extends CControllerWidget {

	public function __construct() {
		parent::__construct();

		$this->setType(WIDGET_TRIG_OVERVIEW);
		$this->setValidationRules([
			'name' => 'string',
			'fields' => 'json'
		]);
	}

	protected function doAction() {
		$fields = $this->getForm()->getFieldsData();

		$data = [
			'name' => $this->getInput('name', $this->getDefaultHeader()),
			'style' => $fields['style'],
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		$trigger_options = [
			'only_true' => ($fields['show'] == TRIGGERS_OPTION_RECENT_PROBLEM) ? true : null,
			'filter' => ['value' => ($fields['show'] == TRIGGERS_OPTION_IN_PROBLEM) ? TRIGGER_VALUE_TRUE : null]
		];

		list($data['hosts'], $data['triggers']) = getTriggersOverviewData(getSubGroups($fields['groupids']),
			$fields['application'], $fields['style'], [], $trigger_options
		);

		$this->setResponse(new CControllerResponseData($data));
	}
}
