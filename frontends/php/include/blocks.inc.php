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


require_once dirname(__FILE__).'/graphs.inc.php';
require_once dirname(__FILE__).'/screens.inc.php';
require_once dirname(__FILE__).'/maps.inc.php';
require_once dirname(__FILE__).'/users.inc.php';

/**
 * @param array  $filter
 * @param array  $filter['groupids']           (optional)
 * @param array  $filter['exclude_groupids']   (optional)
 * @param array  $filter['hostids']            (optional)
 * @param string $filter['problem']            (optional)
 * @param array  $filter['severities']         (optional)
 * @param int    $filter['maintenance']        (optional)
 * @param int    $filter['hide_empty_groups']  (optional)
 * @param int    $filter['ext_ack']            (optional)
 *
 * @return array
 */
function getSystemStatusData(array $filter) {
	$filter_groupids = (array_key_exists('groupids', $filter) && $filter['groupids']) ? $filter['groupids'] : null;
	$filter_hostids = (array_key_exists('hostids', $filter) && $filter['hostids']) ? $filter['hostids'] : null;
	$filter_triggerids = null;
	$filter_severities = (array_key_exists('severities', $filter) && $filter['severities'])
		? $filter['severities']
		: range(TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_COUNT - 1);
	$filter_ext_ack = array_key_exists('ext_ack', $filter)
		? $filter['ext_ack']
		: EXTACK_OPTION_ALL;

	if (array_key_exists('exclude_groupids', $filter) && $filter['exclude_groupids']) {
		if ($filter_hostids === null) {
			// Get all groups if no selected groups defined.
			if ($filter_groupids === null) {
				$filter_groupids = array_keys(API::HostGroup()->get([
					'output' => [],
					'preservekeys' => true
				]));
			}

			$filter_groupids = array_diff($filter_groupids, $filter['exclude_groupids']);

			// Get available hosts.
			$filter_hostids = array_keys(API::Host()->get([
				'output' => [],
				'groupids' => $filter_groupids,
				'preservekeys' => true
			]));
		}

		$exclude_hostids = array_keys(API::Host()->get([
			'output' => [],
			'groupids' => $filter['exclude_groupids'],
			'preservekeys' => true
		]));

		$filter_hostids = array_diff($filter_hostids, $exclude_hostids);
	}

	if (array_key_exists('problem', $filter) && $filter['problem'] !== '') {
		$filter_triggerids = array_keys(API::Trigger()->get([
			'output' => [],
			'groupids' => $filter_groupids,
			'hostids' => $filter_hostids,
			'search' => ['description' => $filter['problem']],
			'preservekeys' => true
		]));

		$filter_groupids = null;
		$filter_hostids = null;
	}

	$data = [
		'groups' => API::HostGroup()->get([
			'output' => ['groupid', 'name'],
			'groupids' => $filter_groupids,
			'hostids' => $filter_hostids,
			'monitored_hosts' => true,
			'preservekeys' => true
		]),
		'triggers' => [],
		'actions' => []
	];

	CArrayHelper::sort($data['groups'], [['field' => 'name', 'order' => ZBX_SORT_UP]]);

	$default_stats = [];

	for ($severity = TRIGGER_SEVERITY_COUNT - 1; $severity >= TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity--) {
		if (in_array($severity, $filter_severities)) {
			$default_stats[$severity] = ['count' => 0, 'problems' => [], 'count_unack' => 0, 'problems_unack' => []];
		}
	}

	foreach ($data['groups'] as &$group) {
		$group['stats'] = $default_stats;
		$group['has_problems'] = false;
	}
	unset($group);

	$options = [
		'output' => ['eventid', 'objectid', 'clock', 'ns', 'name', 'acknowledged', 'severity'],
		'groupids' => array_keys($data['groups']),
		'hostids' => $filter_hostids,
		'source' => EVENT_SOURCE_TRIGGERS,
		'object' => EVENT_OBJECT_TRIGGER,
		'objectids' => $filter_triggerids,
		'sortfield' => ['eventid'],
		'sortorder' => ZBX_SORT_DOWN,
		'preservekeys' => true
	];
	if (array_key_exists('severities', $filter)) {
		$filter_severities = implode(',', $filter['severities']);
		$all_severities = implode(',', range(TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_COUNT - 1));

		if ($filter_severities !== '' && $filter_severities !== $all_severities) {
			$options['severities'] = $filter['severities'];
		}
	}
	if ($filter_ext_ack == EXTACK_OPTION_UNACK) {
		$options['acknowledged'] = false;
	}

	$problems = API::Problem()->get($options);

	if ($problems) {
		$triggerids = [];

		foreach ($problems as $problem) {
			$triggerids[$problem['objectid']] = true;
		}

		$options = [
			'output' => ['priority'],
			'selectGroups' => ['groupid'],
			'selectHosts' => ['name'],
			'triggerids' => array_keys($triggerids),
			'monitored' => true,
			'skipDependent' => true,
			'preservekeys' => true
		];
		if (array_key_exists('maintenance', $filter) && $filter['maintenance'] == 0) {
			$options['maintenance'] = false;
		}

		$data['triggers'] = API::Trigger()->get($options);

		foreach ($data['triggers'] as &$trigger) {
			CArrayHelper::sort($trigger['hosts'], [['field' => 'name', 'order' => ZBX_SORT_UP]]);
		}
		unset($trigger);

		foreach ($problems as $eventid => $problem) {
			if (!array_key_exists($problem['objectid'], $data['triggers'])) {
				unset($problems[$eventid]);
			}
		}

		$visible_problems = [];

		foreach ($problems as $eventid => $problem) {
			$trigger = $data['triggers'][$problem['objectid']];

			// groups
			foreach ($trigger['groups'] as $trigger_group) {
				if (!array_key_exists($trigger_group['groupid'], $data['groups'])) {
					continue;
				}

				$group = &$data['groups'][$trigger_group['groupid']];

				if (in_array($filter_ext_ack, [EXTACK_OPTION_ALL, EXTACK_OPTION_BOTH])) {
					if ($group['stats'][$problem['severity']]['count'] < ZBX_WIDGET_ROWS) {
						$group['stats'][$problem['severity']]['problems'][] = $problem;
						$visible_problems[$eventid] = ['eventid' => $eventid];
					}

					$group['stats'][$problem['severity']]['count']++;
				}

				if (in_array($filter_ext_ack, [EXTACK_OPTION_UNACK, EXTACK_OPTION_BOTH])
						&& $problem['acknowledged'] == EVENT_NOT_ACKNOWLEDGED) {
					if ($group['stats'][$problem['severity']]['count_unack'] < ZBX_WIDGET_ROWS) {
						$group['stats'][$problem['severity']]['problems_unack'][] = $problem;
						$visible_problems[$eventid] = ['eventid' => $eventid];
					}

					$group['stats'][$problem['severity']]['count_unack']++;
				}

				$group['has_problems'] = true;
			}
			unset($group);
		}

		// actions & tags
		$problems_data = API::Problem()->get([
			'output' => ['eventid', 'r_eventid', 'clock', 'objectid', 'severity'],
			'eventids' => array_keys($visible_problems),
			'selectAcknowledges' => ['userid', 'clock', 'message', 'action', 'old_severity', 'new_severity'],
			'selectTags' => ['tag', 'value'],
			'preservekeys' => true
		]);

		// actions
		// Possible performance improvement: one API call may be saved, if r_clock for problem will be used.
		$actions = getEventsActionsIconsData($problems_data, $data['triggers']);
		$data['actions'] = [
			'all_actions' => $actions['data'],
			'users' => API::User()->get([
				'output' => ['alias', 'name', 'surname'],
				'userids' => array_keys($actions['userids']),
				'preservekeys' => true
			]),
			'mediatypes' => API::Mediatype()->get([
				'output' => ['description', 'maxattempts'],
				'mediatypeids' => array_keys($actions['mediatypeids']),
				'preservekeys' => true
			])
		];

		// tags
		foreach ($data['groups'] as &$group) {
			foreach ($group['stats'] as &$stat) {
				foreach (['problems', 'problems_unack'] as $key) {
					foreach ($stat[$key] as &$problem) {
						$problem['tags'] = array_key_exists($problem['eventid'], $problems_data)
							? $problems_data[$problem['eventid']]['tags']
							: [];
					}
					unset($problem);
				}
			}
			unset($stat);
		}
		unset($group);
	}

	return $data;
}

/**
 * @param array  $filter
 * @param array  $filter['hostids']            (optional)
 * @param string $filter['problem']            (optional)
 * @param array  $filter['severities']         (optional)
 * @param int    $filter['maintenance']        (optional)
 * @param int    $filter['hide_empty_groups']  (optional)
 * @param int    $filter['ext_ack']            (optional)
 * @param array  $data
 * @param array  $data['groups']
 * @param string $data['groups'][]['groupid']
 * @param string $data['groups'][]['name']
 * @param bool   $data['groups'][]['has_problems']
 * @param array  $data['groups'][]['stats']
 * @param int    $data['groups'][]['stats']['count']
 * @param array  $data['groups'][]['stats']['problems']
 * @param string $data['groups'][]['stats']['problems'][]['eventid']
 * @param string $data['groups'][]['stats']['problems'][]['objectid']
 * @param int    $data['groups'][]['stats']['problems'][]['clock']
 * @param int    $data['groups'][]['stats']['problems'][]['ns']
 * @param int    $data['groups'][]['stats']['problems'][]['acknowledged']
 * @param array  $data['groups'][]['stats']['problems'][]['tags']
 * @param string $data['groups'][]['stats']['problems'][]['tags'][]['tag']
 * @param string $data['groups'][]['stats']['problems'][]['tags'][]['value']
 * @param int    $data['groups'][]['stats']['count_unack']
 * @param array  $data['groups'][]['stats']['problems_unack']
 * @param array  $data['triggers']
 * @param string $data['triggers'][<triggerid>]['expression']
 * @param string $data['triggers'][<triggerid>]['description']
 * @param array  $data['triggers'][<triggerid>]['hosts']
 * @param string $data['triggers'][<triggerid>]['hosts'][]['name']
 * @param array  $config
 * @param string $config['severity_name_*']
 * @param string $backurl
 * @param int    $fullscreen
 *
 * @return CDiv
 */
function makeSystemStatus(array $filter, array $data, array $config, $backurl, $fullscreen = 0) {
	$filter_severities = (array_key_exists('severities', $filter) && $filter['severities'])
		? $filter['severities']
		: range(TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_COUNT - 1);
	$filter_hide_empty_groups = array_key_exists('hide_empty_groups', $filter) ? $filter['hide_empty_groups'] : 0;
	$filter_ext_ack = array_key_exists('ext_ack', $filter)
		? $filter['ext_ack']
		: EXTACK_OPTION_ALL;

	// indicator of sort field
	$sort_div = (new CSpan())->addClass(ZBX_STYLE_ARROW_UP);

	// Set trigger severities as table header starting from highest severity.
	$header = [[_('Host group'), $sort_div]];

	for ($severity = TRIGGER_SEVERITY_COUNT - 1; $severity >= TRIGGER_SEVERITY_NOT_CLASSIFIED; $severity--) {
		if (in_array($severity, $filter_severities)) {
			$header[] = getSeverityName($severity, $config);
		}
	}

	$table = (new CTableInfo())->setHeader($header);

	$url_group = (new CUrl('zabbix.php'))
		->setArgument('action', 'problem.view')
		->setArgument('filter_set', 1)
		->setArgument('filter_show', TRIGGERS_OPTION_RECENT_PROBLEM)
		->setArgument('filter_groupids', null)
		->setArgument('filter_hostids', array_key_exists('hostids', $filter) ? $filter['hostids'] : null)
		->setArgument('filter_name', array_key_exists('problem', $filter) ? $filter['problem'] : null)
		->setArgument('filter_maintenance', (array_key_exists('maintenance', $filter) && $filter['maintenance'])
			? 1
			: null
		)
		->setArgument('fullscreen', $fullscreen ? '1' : null);

	foreach ($data['groups'] as $group) {
		if ($filter_hide_empty_groups && !$group['has_problems']) {
			continue;
		}

		$row = new CRow();

		$url_group->setArgument('filter_groupids', [$group['groupid']]);
		$name = new CLink($group['name'], $url_group->getUrl());

		$row->addItem($name);

		foreach ($group['stats'] as $severity => $stat) {
			if ($stat['count'] == 0 && $stat['count_unack'] == 0) {
				$row->addItem('');
				continue;
			}

			$allTriggersNum = $stat['count'];
			if ($allTriggersNum) {
				$allTriggersNum = (new CLinkAction($allTriggersNum))
					->setHint(makeProblemsPopup(
							$stat['problems'], $data['triggers'], $backurl, $data['actions'], $config
					));
			}

			$unackTriggersNum = $stat['count_unack'];
			if ($unackTriggersNum) {
				$unackTriggersNum = (new CLinkAction($unackTriggersNum))
					->setHint(makeProblemsPopup(
							$stat['problems_unack'], $data['triggers'], $backurl, $data['actions'], $config
					));
			}

			switch ($filter_ext_ack) {
				case EXTACK_OPTION_ALL:
					$row->addItem(getSeverityCell($severity, null, $allTriggersNum));
					break;

				case EXTACK_OPTION_UNACK:
					$row->addItem(getSeverityCell($severity, null, $unackTriggersNum));
					break;

				case EXTACK_OPTION_BOTH:
					if ($stat['count_unack'] != 0) {
						$row->addItem(getSeverityCell($severity, $config, [
							$unackTriggersNum, ' '._('of').' ', $allTriggersNum
						]));
					}
					else {
						$row->addItem(getSeverityCell($severity, $config, $allTriggersNum));
					}
					break;
			}
		}

		$table->addRow($row);
	}

	return $table;
}

function make_status_of_zbx() {
	if (CWebUser::getType() == USER_TYPE_SUPER_ADMIN) {
		global $ZBX_SERVER, $ZBX_SERVER_PORT;

		$server_details = $ZBX_SERVER.':'.$ZBX_SERVER_PORT;
	}
	else {
		$server_details = '';
	}

	$table = (new CTableInfo())->setHeader([_('Parameter'), _('Value'), _('Details')]);

	$status = get_status();

	$table->addRow([
		_('Zabbix server is running'),
		(new CSpan($status['is_running'] ? _('Yes') : _('No')))
			->addClass($status['is_running'] ? ZBX_STYLE_GREEN : ZBX_STYLE_RED),
		$server_details
	]);
	$table->addRow([_('Number of hosts (enabled/disabled/templates)'),
		$status['has_status'] ? $status['hosts_count'] : '',
		$status['has_status']
			? [
				(new CSpan($status['hosts_count_monitored']))->addClass(ZBX_STYLE_GREEN), ' / ',
				(new CSpan($status['hosts_count_not_monitored']))->addClass(ZBX_STYLE_RED), ' / ',
				(new CSpan($status['hosts_count_template']))->addClass(ZBX_STYLE_GREY)
			]
			: ''
	]);
	$title = (new CSpan(_('Number of items (enabled/disabled/not supported)')))
		->setTitle(_('Only items assigned to enabled hosts are counted'));
	$table->addRow([$title, $status['has_status'] ? $status['items_count'] : '',
		$status['has_status']
			? [
				(new CSpan($status['items_count_monitored']))->addClass(ZBX_STYLE_GREEN), ' / ',
				(new CSpan($status['items_count_disabled']))->addClass(ZBX_STYLE_RED), ' / ',
				(new CSpan($status['items_count_not_supported']))->addClass(ZBX_STYLE_GREY)
			]
			: ''
	]);
	$title = (new CSpan(_('Number of triggers (enabled/disabled [problem/ok])')))
		->setTitle(_('Only triggers assigned to enabled hosts and depending on enabled items are counted'));
	$table->addRow([$title, $status['has_status'] ? $status['triggers_count'] : '',
		$status['has_status']
			? [
				$status['triggers_count_enabled'], ' / ',
				$status['triggers_count_disabled'], ' [',
				(new CSpan($status['triggers_count_on']))->addClass(ZBX_STYLE_RED), ' / ',
				(new CSpan($status['triggers_count_off']))->addClass(ZBX_STYLE_GREEN), ']'
			]
			: ''
	]);
	$table->addRow([_('Number of users (online)'), $status['has_status'] ? $status['users_count'] : '',
		$status['has_status'] ? (new CSpan($status['users_online']))->addClass(ZBX_STYLE_GREEN) : ''
	]);
	if (CWebUser::getType() == USER_TYPE_SUPER_ADMIN) {
		$table->addRow([_('Required server performance, new values per second'),
			($status['has_status'] && array_key_exists('vps_total', $status)) ? round($status['vps_total'], 2) : '', ''
		]);
	}

	// check requirements
	if (CWebUser::getType() == USER_TYPE_SUPER_ADMIN) {
		foreach ((new CFrontendSetup())->checkRequirements() as $req) {
			if ($req['result'] == CFrontendSetup::CHECK_FATAL) {
				$table->addRow(
					(new CRow([$req['name'], $req['current'], $req['error']]))->addClass(ZBX_STYLE_RED)
				);
			}
		}
	}

	return $table;
}

/**
 * Create DIV with latest problem triggers.
 *
 * If no sortfield and sortorder are defined, the sort indicater in the column name will not be displayed.
 *
 * @param array  $filter['groupids']
 * @param array  $filter['hostids']
 * @param array  $filter['maintenance']
 * @param int    $filter['extAck']
 * @param int    $filter['severity']
 * @param int    $filter['limit']
 * @param string $filter['sortfield']
 * @param string $filter['sortorder']
 * @param string $backurl
 *
 * @return CDiv
 */
function make_latest_issues(array $filter = [], $backurl) {
	// hide the sort indicator if no sortfield and sortorder are given
	$show_sort_indicator = isset($filter['sortfield']) || isset($filter['sortorder']);

	if (isset($filter['sortfield']) && $filter['sortfield'] !== 'lastchange') {
		$sort_field = [$filter['sortfield'], 'lastchange'];
		$sort_order = [$filter['sortorder'], ZBX_SORT_DOWN];
	}
	else {
		$sort_field = ['lastchange'];
		$sort_order = [ZBX_SORT_DOWN];
	}

	$options = [
		'groupids' => $filter['groupids'],
		'hostids' => isset($filter['hostids']) ? $filter['hostids'] : null,
		'monitored' => true,
		'maintenance' => $filter['maintenance'],
		'search' => ($filter['trigger_name'] !== '') ? ['description' => $filter['trigger_name']] : null,
		'filter' => [
			'priority' => $filter['severity'],
			'value' => TRIGGER_VALUE_TRUE
		]
	];

	$triggers = API::Trigger()->get(array_merge($options, [
		'output' => [
			'triggerid', 'expression', 'description', 'url', 'priority', 'lastchange', 'comments', 'error', 'state'
		],
		'selectHosts' => ['hostid'],
		'withLastEventUnacknowledged' => (isset($filter['extAck']) && $filter['extAck'] == EXTACK_OPTION_UNACK)
			? true
			: null,
		'skipDependent' => true,
		'sortfield' => $sort_field,
		'sortorder' => $sort_order,
		'limit' => $filter['limit'],
		'preservekeys' => true,
		'expandComment' => true
	]));

	$triggers = CMacrosResolverHelper::resolveTriggerUrls($triggers);

	// don't use withLastEventUnacknowledged and skipDependent because of performance issues
	$triggers_total_count = API::Trigger()->get(array_merge($options, [
		'countOutput' => true
	]));

	$problems = getTriggerLastProblems(array_keys($triggers), ['eventid', 'objectid', 'clock', 'acknowledged', 'ns']);

	$eventids = [];
	foreach ($problems as $problem) {
		$triggers[$problem['objectid']]['lastEvent'] = $problem;
		$eventids[$problem['eventid']] = true;
	}

	// get acknowledges
	$hostids = [];
	foreach ($triggers as $trigger) {
		foreach ($trigger['hosts'] as $host) {
			$hostids[$host['hostid']] = true;
		}
	}

	// get actions
	$events = $eventids
		? API::Event()->get([
			'output' => ['eventid', 'objectid', 'clock', 'severity', 'r_eventid'],
			'eventids' => array_keys($eventids),
			'select_acknowledges' => ['userid', 'clock', 'message', 'action', 'old_severity', 'new_severity'],
			'preservekeys' => true
		])
		: [];

	$config = select_config();
	$severity_config = [
		'severity_name_0' => $config['severity_name_0'],
		'severity_name_1' => $config['severity_name_1'],
		'severity_name_2' => $config['severity_name_2'],
		'severity_name_3' => $config['severity_name_3'],
		'severity_name_4' => $config['severity_name_4'],
		'severity_name_5' => $config['severity_name_5']
	];
	$actions = getEventsActionsIconsData($events, $triggers);
	$users = API::User()->get([
		'output' => ['alias', 'name', 'surname'],
		'userids' => array_keys($actions['userids']),
		'preservekeys' => true
	]);
	$mediatypes = API::Mediatype()->get([
		'output' => ['description', 'maxattempts'],
		'mediatypeids' => array_keys($actions['mediatypeids']),
		'preservekeys' => true
	]);

	// indicator of sort field
	if ($show_sort_indicator) {
		$sort_div = (new CDiv())
			->addClass(($filter['sortorder'] === ZBX_SORT_DOWN) ? ZBX_STYLE_ARROW_DOWN : ZBX_STYLE_ARROW_UP);
	}

	$table = (new CTableInfo())
		->setHeader([
			($show_sort_indicator && $filter['sortfield'] === 'hostname') ? [_('Host'), $sort_div] : _('Host'),
			($show_sort_indicator && $filter['sortfield'] === 'priority') ? [_('Issue'), $sort_div] : _('Issue'),
			($show_sort_indicator && $filter['sortfield'] === 'lastchange')
				? [_('Last change'), $sort_div]
				: _('Last change'),
			_('Age'),
			_('Info'),
			_('Ack'),
			_('Actions')
		]);

	$hostids = array_keys($hostids);

	$scripts = API::Script()->getScriptsByHosts($hostids);

	// get hosts
	$hosts = API::Host()->get([
		'hostids' => $hostids,
		'output' => ['hostid', 'name', 'status', 'maintenance_status', 'maintenance_type', 'maintenanceid'],
		'selectGraphs' => API_OUTPUT_COUNT,
		'selectScreens' => API_OUTPUT_COUNT,
		'preservekeys' => true
	]);

	$maintenanceids = [];
	foreach ($hosts as $host) {
		if ($host['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON) {
			$maintenanceids[$host['maintenanceid']] = true;
		}
	}

	if ($maintenanceids) {
		$maintenances = API::Maintenance()->get([
			'maintenanceids' => array_keys($maintenanceids),
			'output' => ['name', 'description'],
			'preservekeys' => true
		]);
	}

	// triggers
	foreach ($triggers as $trigger) {
		$host_list = [];
		foreach ($trigger['hosts'] as $trigger_host) {
			$host = $hosts[$trigger_host['hostid']];

			$host_name = (new CLinkAction($host['name']))
				->setMenuPopup(CMenuPopupHelper::getHost($host, $scripts[$host['hostid']]));

			if ($host['maintenance_status'] == HOST_MAINTENANCE_STATUS_ON) {
				$maintenance_icon = (new CSpan())
					->addClass(ZBX_STYLE_ICON_MAINT)
					->addClass(ZBX_STYLE_CURSOR_POINTER);

				if (array_key_exists($host['maintenanceid'], $maintenances)) {
					$maintenance = $maintenances[$host['maintenanceid']];

					$hint = $maintenance['name'].' ['.($host['maintenance_type']
						? _('Maintenance without data collection')
						: _('Maintenance with data collection')).']';

					if ($maintenance['description']) {
						$hint .= "\n".$maintenance['description'];
					}

					$maintenance_icon->setHint($hint);
				}

				$host_name = (new CSpan([$host_name, $maintenance_icon]))->addClass(ZBX_STYLE_REL_CONTAINER);
			}

			$host_list[] = $host_name;
			$host_list[] = ', ';
		}
		array_pop($host_list);

		// unknown triggers
		$info_icons = [];
		if ($trigger['state'] == TRIGGER_STATE_UNKNOWN) {
			$info_icons[] = makeUnknownIcon($trigger['error']);
		}

		// trigger has events
		if (array_key_exists('lastEvent', $trigger)) {
			// description
			$description = CMacrosResolverHelper::resolveEventDescription(zbx_array_merge($trigger, [
				'clock' => $trigger['lastEvent']['clock'],
				'ns' => $trigger['lastEvent']['ns']
			]));
		}
		// trigger has no events
		else {
			// description
			$description = CMacrosResolverHelper::resolveEventDescription(zbx_array_merge($trigger, [
				'clock' => $trigger['lastchange'],
				'ns' => '999999999'
			]));
		}

		if (array_key_exists('lastEvent', $trigger)) {
			$acknowledged = ($trigger['lastEvent']['acknowledged'] == EVENT_ACKNOWLEDGED);

			$problem_update_url = (new CUrl('zabbix.php'))
				->setArgument('action', 'acknowledge.edit')
				->setArgument('eventids', [$trigger['lastEvent']['eventid']])
				->setArgument('backurl', $backurl)
				->getUrl();

			$ack = (new CLink($acknowledged ? _('Yes') : _('No'), $problem_update_url))
				->addClass($acknowledged ? ZBX_STYLE_GREEN : ZBX_STYLE_RED)
				->addClass(ZBX_STYLE_LINK_ALT);
		}
		else {
			$ack = (new CSpan(_('No events')))->addClass(ZBX_STYLE_GREY);
		}

		// description
		if (array_key_exists('lastEvent', $trigger) || $trigger['comments'] !== '' || $trigger['url'] !== '') {
			$eventid = array_key_exists('lastEvent', $trigger) ? $trigger['lastEvent']['eventid'] : 0;
			$description = (new CLinkAction($description))
				->setHint(make_popup_eventlist($trigger, $eventid, $backurl),'', true, 'max-width: 500px');
		}
		$description = (new CCol($description))->addClass(getSeverityStyle($trigger['priority']));

		// clock
		$clock = new CLink(zbx_date2str(DATE_TIME_FORMAT_SECONDS, $trigger['lastchange']),
			(new CUrl('zabbix.php'))
				->setArgument('action', 'problem.view')
				->setArgument('filter_triggerids[]', $trigger['triggerid'])
				->setArgument('filter_set', '1')
		);

		// actions
		$action_icons = (array_key_exists('lastEvent', $trigger))
			? makeEventActionsIcons($trigger['lastEvent']['eventid'], $actions['data'], $mediatypes, $users,
				$severity_config
			)
			: '';

		$table->addRow([
			(new CCol($host_list)),
			$description,
			$clock,
			zbx_date2age($trigger['lastchange']),
			makeInformationList($info_icons),
			$ack,
			$action_icons
		]);
	}

	// initialize blinking
	zbx_add_post_js('jqBlink.blink();');

	$info = _n('%1$d of %2$d issue is shown', '%1$d of %2$d issues are shown', count($triggers), $triggers_total_count);

	return [$table, $info];
}

/**
 * Generate table for dashboard triggers popup.
 *
 * @see makeSystemStatus
 *
 * @param array  $problems
 * @param string $problems[]['objectid']
 * @param int    $problems[]['clock']
 * @param int    $problems[]['ns']
 * @param array  $problems[]['acknowledged']
 * @param array  $problems[]['severity']
 * @param array  $problems[]['tags']
 * @param string $problems[]['tags'][]['tag']
 * @param string $problems[]['tags'][]['value']
 * @param array  $triggers
 * @param string $triggers[<triggerid>]['expression']
 * @param string $triggers[<triggerid>]['description']
 * @param array  $triggers[<triggerid>]['hosts']
 * @param string $triggers[<triggerid>]['hosts'][]['name']
 * @param string $backurl
 * @param array  $actions
 * @param array  $config
 *
 * @return CTableInfo
 */
function makeProblemsPopup(array $problems, array $triggers, $backurl, array $actions, array $config) {
	if ($problems) {
		$tags = makeEventsTags($problems);
	}

	$table = (new CTableInfo())
		->setHeader([
			_('Host'),
			_('Problem'),
			_('Duration'),
			_('Ack'),
			_('Actions'),
			_('Tags')
		]);

	foreach ($problems as $problem) {
		$trigger = $triggers[$problem['objectid']];

		$hosts = zbx_objectValues($trigger['hosts'], 'name');

		// ack
		$problem_update_url = (new CUrl('zabbix.php'))
			->setArgument('action', 'acknowledge.edit')
			->setArgument('eventids', [$problem['eventid']])
			->setArgument('backurl', $backurl)
			->getUrl();

		$ack = (new CLink($problem['acknowledged'] == EVENT_ACKNOWLEDGED ? _('Yes') : _('No'), $problem_update_url))
			->addClass($problem['acknowledged'] == EVENT_ACKNOWLEDGED ? ZBX_STYLE_GREEN : ZBX_STYLE_RED)
			->addClass(ZBX_STYLE_LINK_ALT);

		$table->addRow([
			implode(', ', $hosts),
			getSeverityCell($problem['severity'], null, $problem['name']),
			zbx_date2age($problem['clock']),
			$ack,
			makeEventActionsIcons($problem['eventid'], $actions['all_actions'], $actions['mediatypes'], $actions['users'], $config),
			$tags[$problem['eventid']]
		]);
	}

	return $table;
}
