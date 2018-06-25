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

class CControllerWidgetProblemsView extends CControllerWidget {

	public function __construct() {
		parent::__construct();

		$this->setType(WIDGET_PROBLEMS);
		$this->setValidationRules([
			'name' => 'string',
			'fullscreen' => 'in 0,1',
			'kioskmode' => 'in 0,1',
			'fields' => 'json'
		]);
	}

	protected function doAction() {
		$fullscreen = (bool) $this->getInput('fullscreen', false);
		$kioskmode = $fullscreen && (bool) $this->getInput('kioskmode', false);

		$fields = $this->getForm()->getFieldsData();

		$config = select_config();

		list($sortfield, $sortorder) = self::getSorting($fields['sort_triggers']);

		$data = CScreenProblem::getData([
			'show' => $fields['show'],
			'groupids' => getSubGroups($fields['groupids']),
			'exclude_groupids' => getSubGroups($fields['exclude_groupids']),
			'hostids' => $fields['hostids'],
			'name' => $fields['problem'],
			'severities' => $fields['severities'],
			'evaltype' => $fields['evaltype'],
			'tags' => $fields['tags'],
			'maintenance' => $fields['maintenance'],
			'unacknowledged' => $fields['unacknowledged'],
			'show_timeline' => ($sortfield === 'clock') ? $fields['show_timeline'] : 0
		], $config);

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
		], true);

		if ($fields['show_tags']) {
			$data['tags'] = makeEventsTags($data['problems'], true, $fields['show_tags'], $fields['tags']);
		}

		if ($data['problems']) {
			$data['triggers_hosts'] = getTriggersHostsList($data['triggers']);
		}

		$this->setResponse(new CControllerResponseData([
			'name' => $this->getInput('name', $this->getDefaultHeader()),
			'fields' => [
				'show' => $fields['show'],
				'show_tags' => $fields['show_tags'],
				'show_timeline' => $fields['show_timeline'],
			],
			'config' => [
				'problem_unack_style' => $config['problem_unack_style'],
				'problem_ack_style' => $config['problem_ack_style'],
				'blink_period' => timeUnitToSeconds($config['blink_period']),
				'severity_name_0' => $config['severity_name_0'],
				'severity_name_1' => $config['severity_name_1'],
				'severity_name_2' => $config['severity_name_2'],
				'severity_name_3' => $config['severity_name_3'],
				'severity_name_4' => $config['severity_name_4'],
				'severity_name_5' => $config['severity_name_5']
			],
			'data' => $data,
			'info' => $info,
			'sortfield' => $sortfield,
			'sortorder' => $sortorder,
			'fullscreen' => $fullscreen,
			'kioskmode' => $kioskmode,
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
				return ['name', ZBX_SORT_UP];

			case SCREEN_SORT_TRIGGERS_NAME_DESC:
				return ['name', ZBX_SORT_DOWN];
		}
	}
}
