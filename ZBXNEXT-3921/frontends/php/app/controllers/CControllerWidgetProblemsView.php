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
require_once dirname(__FILE__).'/../../include/hostgroups.inc.php';

class CControllerWidgetProblemsView extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'name'			=> 'string',
			'fullscreen'	=> 'in 0,1',
			'fields'		=> 'array'
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			/*
			 * @var array        $fields
			 * @var array|string $fields['groupids']          (optional)
			 * @var array|string $fields['exclude_groupids']  (optional)
			 * @var array|string $fields['hostids']           (optional)
			 * @var string       $fields['problem']           (optional)
			 * @var array        $fields['severities']        (optional)
			 * @var int          $fields['maintenance']       (optional)
			 * @var int          $fields['unacknowledged']    (optional)
			 * @var int          $fields['sort_triggers']     (optional)
			 * @var int          $fields['show_lines']        (optional) BETWEEN 1,100
			 */
			$this->form = CWidgetConfig::getForm(WIDGET_PROBLEMS, $this->getInput('fields', []));

			if ($errors = $this->form->validate()) {
				$ret = false;
			}
		}

		if (!$ret) {
			// TODO VM: prepare propper response for case of incorrect fields
			$this->setResponse(new CControllerResponseData(['main_block' => CJs::encodeJson('')]));
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->getUserType() >= USER_TYPE_ZABBIX_USER);
	}

	protected function doAction() {
		$fields = $this->form->getFieldsData();

		$config = select_config();

		$data = CScreenProblem::getData([
			'show' => $fields['show'],
			'groupids' => getSubGroups((array) $fields['groupids']),
			'exclude_groupids' => getSubGroups((array) $fields['exclude_groupids']),
			'hostids' => (array) $fields['hostids'],
			'problem' => $fields['problem'],
			'severities' => $fields['severities'],
			'maintenance' => $fields['maintenance'],
			'unacknowledged' => $fields['unacknowledged']
		], $config, true);
		list($sortfield, $sortorder) = self::getSorting($fields['sort_triggers']);
		$data = CScreenProblem::sortData($data, $config, $sortfield, $sortorder);

		$info = _n('%1$d of %3$d%2$s problem is shown', '%1$d of %3$d%2$s problems are shown',
			min($fields['show_lines'], count($data['problems'])),
			(count($data['problems']) > $config['search_limit']) ? '+' : '',
			min($config['search_limit'], count($data['problems']))
		);
		$data['problems'] = array_slice($data['problems'], 0, $fields['show_lines'], true);

		$data = CScreenProblem::makeData($data, [
			'show' => $fields['show'],
			'details' => 0
		], $config, true);

		if ($data['problems']) {
			$data['triggers_hosts'] = getTriggersHostsList($data['triggers']);
		}

		$this->setResponse(new CControllerResponseData([
			'name' => $this->getInput('name', CWidgetConfig::getKnownWidgetTypes()[WIDGET_PROBLEMS]),
			'fields' => [
				'show' => $fields['show']
			],
			'config' => [
				'event_ack_enable' => $config['event_ack_enable']
			],
			'data' => $data,
			'info' => $info,
			'sortfield' => $sortfield,
			'sortorder' => $sortorder,
			'fullscreen' => $this->getInput('fullscreen', 0),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		]));
	}

	/**
	 * Get sorting.
	 *
	 * @param int $sort_triggers
	 *
	 * @static
	 *
	 * @return array
	 */
	private static function getSorting($sort_triggers)
	{
		switch ($sort_triggers) {
			case SCREEN_SORT_TRIGGERS_TIME_ASC:
				return ['clock', ZBX_SORT_UP];

			case SCREEN_SORT_TRIGGERS_TIME_DESC:
			default:
				return ['clock', ZBX_SORT_DOWN];

			case SCREEN_SORT_TRIGGERS_SEVERITY_ASC:
				return ['priority', ZBX_SORT_UP];

			case SCREEN_SORT_TRIGGERS_SEVERITY_DESC:
				return ['priority', ZBX_SORT_DOWN];

			case SCREEN_SORT_TRIGGERS_HOST_NAME_ASC:
				return ['host', ZBX_SORT_UP];

			case SCREEN_SORT_TRIGGERS_HOST_NAME_DESC:
				return ['host', ZBX_SORT_DOWN];

			case SCREEN_SORT_TRIGGERS_NAME_ASC:
				return ['problem', ZBX_SORT_UP];

			case SCREEN_SORT_TRIGGERS_NAME_DESC:
				return ['problem', ZBX_SORT_DOWN];
		}
	}
}
