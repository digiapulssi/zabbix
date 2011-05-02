<?php
/*
** Zabbix
** Copyright (C) 2001-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
require_once('include/graphs.inc.php');
require_once('include/screens.inc.php');
require_once('include/maps.inc.php');
require_once('include/users.inc.php');
require_once('include/requirements.inc.php');


// Author: Aly
function make_favorite_graphs(){
	$favList = new CList(null, 'favorites');

	$graphids = array();
	$itemids = array();

	$fav_graphs = get_favorites('web.favorite.graphids');
	foreach($fav_graphs as $key => $favorite){
		if('itemid' == $favorite['source']){
			$itemids[$favorite['value']] = $favorite['value'];
		}
		else{
			$graphids[$favorite['value']] = $favorite['value'];
		}
	}

	$options = array(
			'graphids' => $graphids,
			'selectHosts' => API_OUTPUT_EXTEND,
			'output' => API_OUTPUT_EXTEND,
		);
	$graphs = API::Graph()->get($options);
	$graphs = zbx_toHash($graphs, 'graphid');

	$options = array(
			'itemids' => $itemids,
			'selectHosts' => API_OUTPUT_EXTEND,
			'filter' => array('flags' => array(ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED)),
			'output' => API_OUTPUT_EXTEND,
			'webitems' => 1,
		);
	$items = API::Item()->get($options);
	$items = zbx_toHash($items, 'itemid');

	foreach($fav_graphs as $key => $favorite){
		$sourceid = $favorite['value'];

		if('itemid' == $favorite['source']){
			if(!isset($items[$sourceid])) continue;

			$item = $items[$sourceid];
			$host = reset($item['hosts']);

			$item['name'] = itemName($item);

			$link = new CLink(get_node_name_by_elid($sourceid, null, ': ').$host['name'].':'.$item['name'],'history.php?action=showgraph&itemid='.$sourceid);
			$link->setTarget('blank');
		}
		else{
			if(!isset($graphs[$sourceid])) continue;

			$graph = $graphs[$sourceid];
			$ghost = reset($graph['hosts']);

			$link = new CLink(get_node_name_by_elid($sourceid, null, ': ').$ghost['name'].':'.$graph['name'],'charts.php?graphid='.$sourceid);
			$link->setTarget('blank');
		}

		$favList->addItem($link, 'nowrap');
	}

return $favList;
}

// Author: Aly
function make_favorite_screens(){
	$favList = new CList(null, 'favorites');

	$fav_screens = get_favorites('web.favorite.screenids');

	$screenids = array();
	foreach($fav_screens as $key => $favorite){
		if('screenid' == $favorite['source']){
			$screenids[$favorite['value']] = $favorite['value'];
		}
	}

	$options = array(
		'screenids' => $screenids,
		'output' => API_OUTPUT_EXTEND,
	);
	$screens = API::Screen()->get($options);
	$screens = zbx_toHash($screens, 'screenid');

	foreach($fav_screens as $key => $favorite){
		$source = $favorite['source'];
		$sourceid = $favorite['value'];

		if('slideshowid' == $source){
			if(!slideshow_accessible($sourceid, PERM_READ_ONLY)) continue;
			if(!$slide = get_slideshow_by_slideshowid($sourceid)) continue;

			$link = new CLink(get_node_name_by_elid($sourceid, null, ': ').$slide['name'],'slides.php?elementid='.$sourceid);
			$link->setTarget('blank');
		}
		else{
			if(!isset($screens[$sourceid])) continue;
			$screen = $screens[$sourceid];

			$link = new CLink(get_node_name_by_elid($sourceid, null, ': ').$screen['name'],'screens.php?elementid='.$sourceid);
			$link->setTarget('blank');
		}

		$favList->addItem($link, 'nowrap');
	}


return $favList;
}

// Author: Aly
function make_favorite_maps(){
	$favList = new CList(null, 'favorites');

	$fav_sysmaps = get_favorites('web.favorite.sysmapids');

	$sysmapids = array();
	foreach($fav_sysmaps as $key => $favorite){
		$sysmapids[$favorite['value']] = $favorite['value'];
	}

	$options = array(
			'sysmapids' => $sysmapids,
			'output' => API_OUTPUT_EXTEND,
		);
	$sysmaps = API::Map()->get($options);

	foreach($sysmaps as $snum => $sysmap){
		$sysmapid = $sysmap['sysmapid'];

		$link = new CLink(get_node_name_by_elid($sysmapid, null, ': ').$sysmap['name'],'maps.php?sysmapid='.$sysmapid);
		$link->setTarget('blank');

		$favList->addItem($link, 'nowrap');
	}

return $favList;
}

// Author: Aly
function make_system_status($filter){
	$config = select_config();

	$table = new CTableInfo();
	$table->setHeader(array(
		is_show_all_nodes() ? S_NODE : null,
		S_HOST_GROUP,
		is_null($filter['severity'])||isset($filter['severity'][TRIGGER_SEVERITY_DISASTER])?getSeverityCaption(TRIGGER_SEVERITY_DISASTER):null,
		is_null($filter['severity'])||isset($filter['severity'][TRIGGER_SEVERITY_HIGH])?getSeverityCaption(TRIGGER_SEVERITY_HIGH):null,
		is_null($filter['severity'])||isset($filter['severity'][TRIGGER_SEVERITY_AVERAGE])?getSeverityCaption(TRIGGER_SEVERITY_AVERAGE):null,
		is_null($filter['severity'])||isset($filter['severity'][TRIGGER_SEVERITY_WARNING])?getSeverityCaption(TRIGGER_SEVERITY_WARNING):null,
		is_null($filter['severity'])||isset($filter['severity'][TRIGGER_SEVERITY_INFORMATION])?getSeverityCaption(TRIGGER_SEVERITY_INFORMATION):null,
		is_null($filter['severity'])||isset($filter['severity'][TRIGGER_SEVERITY_NOT_CLASSIFIED])?getSeverityCaption(TRIGGER_SEVERITY_NOT_CLASSIFIED):null
	));

// SELECT HOST GROUPS {{{
	$options = array(
		'nodeids' => get_current_nodeid(),
		'monitored_hosts' => 1,
		'groupids' => $filter['groupids'],
		'output' => API_OUTPUT_EXTEND,
	);
	$groups = API::HostGroup()->get($options);
	$groups = zbx_toHash($groups, 'groupid');
	order_result($groups, 'name');

	$groupids = array();
	foreach($groups as $gnum => $group){
		$groupids[] = $group['groupid'];
		$group['tab_priority'] = array();
		$group['tab_priority'][TRIGGER_SEVERITY_DISASTER] = array('count' => 0, 'triggers' => array(), 'count_unack' => 0, 'triggers_unack' => array());
		$group['tab_priority'][TRIGGER_SEVERITY_HIGH] = array('count' => 0, 'triggers' => array(), 'count_unack' => 0, 'triggers_unack' => array());
		$group['tab_priority'][TRIGGER_SEVERITY_AVERAGE] = array('count' => 0, 'triggers' => array(), 'count_unack' => 0, 'triggers_unack' => array());
		$group['tab_priority'][TRIGGER_SEVERITY_WARNING] = array('count' => 0, 'triggers' => array(), 'count_unack' => 0, 'triggers_unack' => array());
		$group['tab_priority'][TRIGGER_SEVERITY_INFORMATION] = array('count' => 0, 'triggers' => array(), 'count_unack' => 0, 'triggers_unack' => array());
		$group['tab_priority'][TRIGGER_SEVERITY_NOT_CLASSIFIED] = array('count' => 0, 'triggers' => array(), 'count_unack' => 0, 'triggers_unack' => array());
		$groups[$group['groupid']] = $group;
	}
// }}} SELECT HOST GROUPS


// SELECT TRIGGERS {{{
	$options = array(
		'nodeids' => get_current_nodeid(),
		'groupids' => $groupids,
		'monitored' => 1,
		'maintenance' => $filter['maintenance'],
// Deprecated method, use selectHosts instead
//		'expandData' => 1,
		'skipDependent' => 1,
		'expandDescription' => 1,
		'filter' => array(
			'priority' => $filter['severity'],
			'value' => TRIGGER_VALUE_TRUE
		),
		'output' => API_OUTPUT_EXTEND,
// Interested in host name
		'selectHosts' => array('name')
	);
	if($filter['extAck'] == EXTACK_OPTION_UNACK) $options['withLastEventUnacknowledged'] = 1;
	$triggers = API::Trigger()->get($options);
	order_result($triggers, 'lastchange', ZBX_SORT_DOWN);

	foreach($triggers as $tnum => $trigger){
		$options = array(
			'nodeids' => get_current_nodeid(),
			'object' => EVENT_SOURCE_TRIGGERS,
			'triggerids' => $trigger['triggerid'],
			'filter'=> array(
				'value' => TRIGGER_VALUE_TRUE,
				'value_changed' => TRIGGER_VALUE_CHANGED_YES
			),
			'output' => API_OUTPUT_EXTEND,
			'nopermissions' => 1,
			'select_acknowledges' => API_OUTPUT_COUNT,
			'sortfield' => 'clock',
			'sortorder' => ZBX_SORT_DOWN,
			'limit' => 1
		);
		$events = API::Event()->get($options);
		if(empty($events)){
			$trigger['event'] = array(
				'value_changed' => 0,
				'value' => $trigger['value'],
				'acknowledged' => 1,
				'clock' => $trigger['lastchange'],
			);
		}
		else{
			$trigger['event'] = reset($events);
		}

		foreach($trigger['groups'] as $group){
			if($groups[$group['groupid']]['tab_priority'][$trigger['priority']]['count'] < 30){
				$groups[$group['groupid']]['tab_priority'][$trigger['priority']]['triggers'][] = $trigger;
			}
			if(($groups[$group['groupid']]['tab_priority'][$trigger['priority']]['count_unack'] < 30) && !$trigger['event']['acknowledged']){
				$groups[$group['groupid']]['tab_priority'][$trigger['priority']]['triggers_unack'][] = $trigger;
			}

			$groups[$group['groupid']]['tab_priority'][$trigger['priority']]['count']++;
			if(!$trigger['event']['acknowledged']){
				$groups[$group['groupid']]['tab_priority'][$trigger['priority']]['count_unack']++;
			}
		}
	}
	unset($triggers);
	order_result($groups, 'name');
// }}} SELECT TRIGGERS


	foreach($groups as $gnum => $group){
		$group_row = new CRow();
		if(is_show_all_nodes())
			$group_row->addItem(get_node_name_by_elid($group['groupid']));

		$name = new CLink($group['name'], 'tr_status.php?groupid='.$group['groupid'].'&show_triggers='.TRIGGERS_OPTION_ONLYTRUE);
		$group_row->addItem($name);

		foreach($group['tab_priority'] as $severity => $data){
			if(!is_null($filter['severity']) && !isset($filter['severity'][$severity])) continue;

			if($data['count'] && in_array($filter['extAck'], array(EXTACK_OPTION_ALL, EXTACK_OPTION_BOTH))){
				$table_inf = new CTableInfo();
				$table_inf->setAttribute('style', 'width: 400px;');
				$table_inf->setHeader(array(
					is_show_all_nodes() ? S_NODE : null,
					S_HOST,
					S_ISSUE,
					S_AGE,
					S_INFO,
					($config['event_ack_enable']) ? S_ACK : NULL,
					S_ACTIONS
				));

				foreach($data['triggers'] as $tnum => $trigger){
					$event = $trigger['event'];
					$ack = getEventAckState($event);

					if(isset($event['eventid'])) $actions = get_event_actions_status($event['eventid']);
					else $actions = S_NO_DATA_SMALL;

// Unknown triggers
					$unknown = SPACE;
					if($trigger['value_flags'] == TRIGGER_VALUE_FLAG_UNKNOWN){
						$unknown = new CDiv(SPACE,'status_icon iconunknown');
						$unknown->setHint($trigger['error'], '', 'on');
					}
//----
					$trigger['hostname'] = $trigger['hosts'][0]['name'];
					$table_inf->addRow(array(
						get_node_name_by_elid($trigger['triggerid']),
						$trigger['hostname'],
						new CCol($trigger['description'], getSeverityStyle($trigger['priority'])),
						zbx_date2age($event['clock']),
						$unknown,
						($config['event_ack_enable']) ? (new CCol($ack, 'center')) : NULL,
						$actions
					));
				}
			}

			if($data['count_unack'] && in_array($filter['extAck'], array(EXTACK_OPTION_UNACK, EXTACK_OPTION_BOTH))){
				$table_inf_unack = new CTableInfo();
				$table_inf_unack->setAttribute('style', 'width: 400px;');
				$table_inf_unack->setHeader(array(
					is_show_all_nodes() ? S_NODE : null,
					S_HOST,
					S_ISSUE,
					S_AGE,
					S_INFO,
					($config['event_ack_enable']) ? S_ACK : NULL,
					S_ACTIONS
				));

				foreach($data['triggers_unack'] as $tnum => $trigger){
					$event = $trigger['event'];
					$ack = getEventAckState($event);

					if(isset($event['eventid'])) $actions = get_event_actions_status($event['eventid']);
					else $actions = S_NO_DATA_SMALL;

// Unknown triggers
					$unknown = SPACE;
					if($trigger['value_flags'] == TRIGGER_VALUE_FLAG_UNKNOWN){
						$unknown = new CDiv(SPACE,'status_icon iconunknown');
						$unknown->setHint($trigger['error'], '', 'on');
					}
//----

					$table_inf_unack->addRow(array(
						get_node_name_by_elid($trigger['triggerid']),
						$trigger['host'],
						new CCol($trigger['description'], getSeverityStyle($trigger['priority'])),
						zbx_date2age($event['clock']),
						$unknown,
						($config['event_ack_enable']) ? (new CCol($ack, 'center')) : NULL,
						$actions
					));
				}
			}


			switch($filter['extAck']){
				case EXTACK_OPTION_ALL:
					$trigger_count = new CSpan($data['count'], 'pointer');
					if($data['count'])
						$trigger_count->setHint($table_inf);

					$group_row->addItem(new CCol($trigger_count, getSeverityStyle($severity, $data['count'])));
				break;
				case EXTACK_OPTION_UNACK:
					$trigger_count = $data['count_unack'];
					if($trigger_count){
						$trigger_count = new CSpan($data['count_unack'], 'pointer red bold');
						$trigger_count->setHint($table_inf_unack);
					}
					$group_row->addItem(new CCol($trigger_count, getSeverityStyle($severity, $data['count_unack'])));
				break;
				case EXTACK_OPTION_BOTH:
					if($data['count_unack']){
						$unack_count = new CSpan($data['count_unack'], 'bold red pointer');
						$unack_count->setHint($table_inf_unack);
						$unack_count = new CSpan(array($unack_count, SPACE.S_OF.SPACE));
					}
					else{
						$unack_count = null;
					}

					$trigger_count = new CSpan($data['count'], 'pointer');
					if($data['count'])
						$trigger_count->setHint($table_inf);

					$group_row->addItem(new CCol(array($unack_count, $trigger_count), getSeverityStyle($severity, $data['count'])));
				break;
			}
		}

		$table->addRow($group_row);
	}

	$script = new CJSScript(get_js("jQuery('#hat_syssum_footer').html('"._s('Updated: %s',zbx_date2str(S_BLOCKS_SYSTEM_SUMMARY_TIME_FORMAT))."')"));

return new CDiv(array($table, $script));
}

function make_hoststat_summary($filter){
	$table = new CTableInfo();
	$table->setHeader(array(
		is_show_all_nodes() ? S_NODE : null,
		S_HOST_GROUP,
		S_WITHOUT_PROBLEMS,
		S_WITH_PROBLEMS,
		S_TOTAL
	));

// SELECT HOST GROUPS {{{
	$options = array(
		'nodeids' => get_current_nodeid(),
		'groupids' => $filter['groupids'],
		'monitored_hosts' => 1,
		'output' => API_OUTPUT_EXTEND
	);
	$groups = API::HostGroup()->get($options);
	$groups = zbx_toHash($groups, 'groupid');
	order_result($groups, 'name');
// }}} SELECT HOST GROUPS

// SELECT HOSTS {{{
	$options = array(
		'nodeids' => get_current_nodeid(),
		'groupids' => zbx_objectValues($groups, 'groupid'),
		'monitored_hosts' => 1,
		'filter' => array('maintenance_status' => $filter['maintenance']),
		'output' => array('hostid', 'name')
	);
	$hosts = API::Host()->get($options);
	$hosts = zbx_toHash($hosts, 'hostid');
// }}} SELECT HOSTS

// SELECT TRIGGERS {{{
	$options = array(
		'nodeids' => get_current_nodeid(),
		'monitored' => 1,
		'maintenance' => $filter['maintenance'],
		'expandData' => 1,
		'filter' => array(
			'priority' => $filter['severity'],
			'value' => TRIGGER_VALUE_TRUE
		),
		'output' => API_OUTPUT_EXTEND,
	);
	$triggers = API::Trigger()->get($options);

	if($filter['extAck']){
		$options = array(
			'nodeids' => get_current_nodeid(),
			'monitored' => 1,
			'maintenance' => $filter['maintenance'],
			'withLastEventUnacknowledged' => 1,
			'selectHosts' => API_OUTPUT_REFER,
			'filter' => array(
				'priority' => $filter['severity'],
				'value' => TRIGGER_VALUE_TRUE
			),
			'output' => API_OUTPUT_REFER,
		);
		$triggers_unack = API::Trigger()->get($options);
		$triggers_unack = zbx_toHash($triggers_unack, 'triggerid');

		foreach($triggers_unack as $tunack){
			foreach($tunack['hosts'] as $unack_host){
				$hosts_with_unack_triggers[$unack_host['hostid']] = $unack_host['hostid'];
			}
		}
	}
// }}} SELECT TRIGGERS

	$hosts_data = array();
	$problematic_host_list = array();
	$lastUnack_host_list = array();
	$highest_severity = array();
	$highest_severity2 = array();

	foreach($triggers as $tnum => $trigger){
		foreach($trigger['hosts'] as $thnum => $trigger_host){
			if(!isset($hosts[$trigger_host['hostid']])) continue;
			else $host = $hosts[$trigger_host['hostid']];

			if($filter['extAck'] && isset($hosts_with_unack_triggers[$host['hostid']])){
				if(!isset($lastUnack_host_list[$host['hostid']])){
					$lastUnack_host_list[$host['hostid']] = array();
					$lastUnack_host_list[$host['hostid']]['host'] = $host['host'];
					$lastUnack_host_list[$host['hostid']]['hostid'] = $host['hostid'];
					$lastUnack_host_list[$host['hostid']]['severities'] = array();
					$lastUnack_host_list[$host['hostid']]['severities'][TRIGGER_SEVERITY_DISASTER] = 0;
					$lastUnack_host_list[$host['hostid']]['severities'][TRIGGER_SEVERITY_HIGH] = 0;
					$lastUnack_host_list[$host['hostid']]['severities'][TRIGGER_SEVERITY_AVERAGE] = 0;
					$lastUnack_host_list[$host['hostid']]['severities'][TRIGGER_SEVERITY_WARNING] = 0;
					$lastUnack_host_list[$host['hostid']]['severities'][TRIGGER_SEVERITY_INFORMATION] = 0;
					$lastUnack_host_list[$host['hostid']]['severities'][TRIGGER_SEVERITY_NOT_CLASSIFIED] = 0;
				}
				if(isset($triggers_unack[$trigger['triggerid']])){
					$lastUnack_host_list[$host['hostid']]['severities'][$trigger['priority']]++;
				}

				foreach($host['groups'] as $gnum => $group){
					if(!isset($highest_severity2[$group['groupid']]))
						$highest_severity2[$group['groupid']] = 0;

					if($trigger['priority'] > $highest_severity2[$group['groupid']]){
						$highest_severity2[$group['groupid']] = $trigger['priority'];
					}

					if(!isset($hosts_data[$group['groupid']])){
						$hosts_data[$group['groupid']] = array(
							'problematic' => 0,
							'ok' => 0,
							'lastUnack' => 0,
							'hostids_all' => array(),
							'hostids_unack' => array(),
						);
					}

					if(!isset($hosts_data[$group['groupid']]['hostids_unack'][$host['hostid']])){
						$hosts_data[$group['groupid']]['hostids_unack'][$host['hostid']] = $host['hostid'];
						$hosts_data[$group['groupid']]['lastUnack']++;
					}
				}
			}

			if(!isset($problematic_host_list[$host['hostid']])){
				$problematic_host_list[$host['hostid']] = array();
				$problematic_host_list[$host['hostid']]['host'] = $host['name'];
				$problematic_host_list[$host['hostid']]['hostid'] = $host['hostid'];
				$problematic_host_list[$host['hostid']]['severities'] = array();
				$problematic_host_list[$host['hostid']]['severities'][TRIGGER_SEVERITY_DISASTER] = 0;
				$problematic_host_list[$host['hostid']]['severities'][TRIGGER_SEVERITY_HIGH] = 0;
				$problematic_host_list[$host['hostid']]['severities'][TRIGGER_SEVERITY_AVERAGE] = 0;
				$problematic_host_list[$host['hostid']]['severities'][TRIGGER_SEVERITY_WARNING] = 0;
				$problematic_host_list[$host['hostid']]['severities'][TRIGGER_SEVERITY_INFORMATION] = 0;
				$problematic_host_list[$host['hostid']]['severities'][TRIGGER_SEVERITY_NOT_CLASSIFIED] = 0;
			}
			$problematic_host_list[$host['hostid']]['severities'][$trigger['priority']]++;

			foreach($host['groups'] as $gnum => $group){
				if(!isset($highest_severity[$group['groupid']]))
					$highest_severity[$group['groupid']] = 0;

				if($trigger['priority'] > $highest_severity[$group['groupid']]){
					$highest_severity[$group['groupid']] = $trigger['priority'];
				}

				if(!isset($hosts_data[$group['groupid']])){
					$hosts_data[$group['groupid']] = array(
						'problematic' => 0,
						'ok' => 0,
						'lastUnack' => 0,
						'hostids_all' => array(),
						'hostids_unack' => array(),
					);
				}

				if(!isset($hosts_data[$group['groupid']]['hostids_all'][$host['hostid']])){
					$hosts_data[$group['groupid']]['hostids_all'][$host['hostid']] = $host['hostid'];
					$hosts_data[$group['groupid']]['problematic']++;
				}
			}
		}
	}

	foreach($hosts as $hnum => $host){
		foreach($host['groups'] as $gnum => $group){
			if(!isset($groups[$group['groupid']]['hosts']))
				$groups[$group['groupid']]['hosts'] = array();

			$groups[$group['groupid']]['hosts'][$host['hostid']] = array('hostid'=> $host['hostid']);

			if(!isset($highest_severity[$group['groupid']]))
				$highest_severity[$group['groupid']] = 0;

			if(!isset($hosts_data[$group['groupid']]))
				$hosts_data[$group['groupid']] = array('problematic' => 0, 'ok' => 0, 'lastUnack' => 0);

			if(!isset($problematic_host_list[$host['hostid']]))
				$hosts_data[$group['groupid']]['ok']++;
		}
	}

	foreach($groups as $gnum => $group){
		if(!isset($hosts_data[$group['groupid']])) continue;

		$group_row = new CRow();
		if(is_show_all_nodes())
			$group_row->addItem(get_node_name_by_elid($group['groupid']));

		$name = new CLink($group['name'], 'tr_status.php?groupid='.$group['groupid'].'&hostid=0&show_triggers='.TRIGGERS_OPTION_ONLYTRUE);
		$group_row->addItem($name);

		$group_row->addItem(new CCol($hosts_data[$group['groupid']]['ok'], 'normal'));

		if($filter['extAck']){
			if($hosts_data[$group['groupid']]['lastUnack']){
				$table_inf = new CTableInfo();
				$table_inf->setAttribute('style', 'width: 400px;');
				$table_inf->setHeader(array(
					S_HOST,
					is_null($filter['severity'])||isset($filter['severity'][TRIGGER_SEVERITY_DISASTER])?getSeverityCaption(TRIGGER_SEVERITY_DISASTER):null,
					is_null($filter['severity'])||isset($filter['severity'][TRIGGER_SEVERITY_HIGH])?getSeverityCaption(TRIGGER_SEVERITY_HIGH):null,
					is_null($filter['severity'])||isset($filter['severity'][TRIGGER_SEVERITY_AVERAGE])?getSeverityCaption(TRIGGER_SEVERITY_AVERAGE):null,
					is_null($filter['severity'])||isset($filter['severity'][TRIGGER_SEVERITY_WARNING])?getSeverityCaption(TRIGGER_SEVERITY_WARNING):null,
					is_null($filter['severity'])||isset($filter['severity'][TRIGGER_SEVERITY_INFORMATION])?getSeverityCaption(TRIGGER_SEVERITY_INFORMATION):null,
					is_null($filter['severity'])||isset($filter['severity'][TRIGGER_SEVERITY_NOT_CLASSIFIED])?getSeverityCaption(TRIGGER_SEVERITY_NOT_CLASSIFIED):null
				));

				$popup_rows = 0;

				foreach($group['hosts'] as $hnum => $host){
					$hostid = $host['hostid'];
					if(!isset($lastUnack_host_list[$hostid])) continue;

					if($popup_rows >= ZBX_WIDGET_ROWS) break;
					$popup_rows++;

					$host_data = $lastUnack_host_list[$hostid];

					$r = new CRow();
					$r->addItem(new CLink($host_data['host'], 'tr_status.php?groupid='.$group['groupid'].'&hostid='.$hostid.'&show_triggers='.TRIGGERS_OPTION_ONLYTRUE));

					foreach($lastUnack_host_list[$host['hostid']]['severities'] as $severity => $trigger_count){
						if(!is_null($filter['severity'])&&!isset($filter['severity'][$severity])) continue;

						$r->addItem(new CCol($trigger_count, getSeverityStyle($severity, $trigger_count)));
					}
					$table_inf->addRow($r);
				}

				$lastUnack_count = new CSpan($hosts_data[$group['groupid']]['lastUnack'], 'pointer red bold');
				$lastUnack_count->setHint($table_inf);
			}
			else{
				$lastUnack_count = 0;
			}
		}

// if hostgroup contains problematic hosts, hint should be built
		if($hosts_data[$group['groupid']]['problematic']){
			$table_inf = new CTableInfo();
			$table_inf->setAttribute('style', 'width: 400px;');
			$table_inf->setHeader(array(
				S_HOST,
				is_null($filter['severity'])||isset($filter['severity'][TRIGGER_SEVERITY_DISASTER])?getSeverityCaption(TRIGGER_SEVERITY_DISASTER):null,
				is_null($filter['severity'])||isset($filter['severity'][TRIGGER_SEVERITY_HIGH])?getSeverityCaption(TRIGGER_SEVERITY_HIGH):null,
				is_null($filter['severity'])||isset($filter['severity'][TRIGGER_SEVERITY_AVERAGE])?getSeverityCaption(TRIGGER_SEVERITY_AVERAGE):null,
				is_null($filter['severity'])||isset($filter['severity'][TRIGGER_SEVERITY_WARNING])?getSeverityCaption(TRIGGER_SEVERITY_WARNING):null,
				is_null($filter['severity'])||isset($filter['severity'][TRIGGER_SEVERITY_INFORMATION])?getSeverityCaption(TRIGGER_SEVERITY_INFORMATION):null,
				is_null($filter['severity'])||isset($filter['severity'][TRIGGER_SEVERITY_NOT_CLASSIFIED])?getSeverityCaption(TRIGGER_SEVERITY_NOT_CLASSIFIED):null
			));

			$popup_rows = 0;

			foreach($group['hosts'] as $hnum => $host){
				$hostid = $host['hostid'];
				if(!isset($problematic_host_list[$hostid])) continue;

				if($popup_rows >= ZBX_WIDGET_ROWS) break;
				$popup_rows++;

				$host_data = $problematic_host_list[$hostid];

				$r = new CRow();
				$r->addItem(new CLink($host_data['host'], 'tr_status.php?groupid='.$group['groupid'].'&hostid='.$hostid.'&show_triggers='.TRIGGERS_OPTION_ONLYTRUE));

				foreach($problematic_host_list[$host['hostid']]['severities'] as $severity => $trigger_count){
					if(!is_null($filter['severity'])&&!isset($filter['severity'][$severity])) continue;

					$r->addItem(new CCol($trigger_count, getSeverityStyle($severity, $trigger_count)));
				}
				$table_inf->addRow($r);
			}

			$problematic_count = new CSpan($hosts_data[$group['groupid']]['problematic'], 'pointer');
			$problematic_count->setHint($table_inf);
		}
		else{
			$problematic_count = 0;
		}

		switch($filter['extAck']){
			case EXTACK_OPTION_ALL:
		        $group_row->addItem(new CCol(
					$problematic_count,
					getSeverityStyle($highest_severity[$group['groupid']], $hosts_data[$group['groupid']]['problematic']))
				);
		        $group_row->addItem($hosts_data[$group['groupid']]['problematic'] + $hosts_data[$group['groupid']]['ok']);
		        break;
			case EXTACK_OPTION_UNACK:
				$group_row->addItem(new CCol(
					$lastUnack_count,
					getSeverityStyle((isset($highest_severity2[$group['groupid']]) ? $highest_severity2[$group['groupid']] : 0),
						$hosts_data[$group['groupid']]['lastUnack']))
				);
				$group_row->addItem($hosts_data[$group['groupid']]['lastUnack'] + $hosts_data[$group['groupid']]['ok']);
				break;
			case EXTACK_OPTION_BOTH:
				$unackspan = $lastUnack_count ? new CSpan(array($lastUnack_count, SPACE.S_OF.SPACE)) : null;
				$group_row->addItem(new CCol(array(
					$unackspan, $problematic_count),
					getSeverityStyle($highest_severity[$group['groupid']], $hosts_data[$group['groupid']]['problematic']))
				);
				$group_row->addItem($hosts_data[$group['groupid']]['problematic'] + $hosts_data[$group['groupid']]['ok']);
				break;
		}

		$table->addRow($group_row);
	}

	$script = new CJSScript(get_js("jQuery('#hat_hoststat_footer').html('"._s('Updated: %s',zbx_date2str(S_BLOCKS_SYSTEM_SUMMARY_TIME_FORMAT))."')"));

return new CDiv(array($table, $script));
}

// Author: Aly
function make_status_of_zbx(){
	global $ZBX_SERVER, $ZBX_SERVER_PORT;

	$table = new CTableInfo();
	$table->setHeader(array(
		S_PARAMETER,
		S_VALUE,
		S_DETAILS
	));

	show_messages(); //because in function get_status(); function clear_messages() is called when fsockopen() fails.
	$status = get_status();

	$table->addRow(array(
		S_ZABBIX_SERVER_IS_RUNNING,
		new CSpan($status['zabbix_server'], ($status['zabbix_server'] == S_YES ? 'off' : 'on')),
		isset($ZBX_SERVER, $ZBX_SERVER_PORT) ? $ZBX_SERVER.':'.$ZBX_SERVER_PORT : S_ZABBIX_SERVER_IP_OR_PORT_IS_NOT_SET
	));
	$title = new CSpan(S_NUMBER_OF_HOSTS);
	$title->setAttribute('title', 'asdad');
	$table->addRow(array(S_NUMBER_OF_HOSTS ,$status['hosts_count'],
		array(
			new CSpan($status['hosts_count_monitored'],'off'),' / ',
			new CSpan($status['hosts_count_not_monitored'],'on'),' / ',
			new CSpan($status['hosts_count_template'],'unknown')
		)
	));
	$title = new CSpan(S_NUMBER_OF_ITEMS);
	$title->setAttribute('title', S_NUMBER_OF_ITEMS_TOOLTIP);
	$table->addRow(array($title, $status['items_count'],
		array(
			new CSpan($status['items_count_monitored'],'off'),' / ',
			new CSpan($status['items_count_disabled'],'on'),' / ',
			new CSpan($status['items_count_not_supported'],'unknown')
		)
	));
	$title = new CSpan(S_NUMBER_OF_TRIGGERS);
	$title->setAttribute('title', S_NUMBER_OF_TRIGGERS_TOOLTIP);
	$table->addRow(array($title,$status['triggers_count'],
		array(
			$status['triggers_count_enabled'],' / ',
			$status['triggers_count_disabled'].SPACE.SPACE.'[',
			new CSpan($status['triggers_count_on'],'on'),' / ',
			new CSpan($status['triggers_count_unknown'],'unknown'),' / ',
			new CSpan($status['triggers_count_off'],'off'),']'
		)
	));

/*
	$table->addRow(array(S_NUMBER_OF_EVENTS,$status['events_count'],' - '));
	$table->addRow(array(S_NUMBER_OF_ALERTS,$status['alerts_count'],' - '));
//*/

	$table->addRow(array(S_NUMBER_OF_USERS, $status['users_count'], new CSpan($status['users_online'],'green')));
	$table->addRow(array(S_REQUIRED_SERVER_PERFORMANCE_NVPS, $status['qps_total'],' - '));


// CHECK REQUIREMENTS {{{
	if(CWebUser::$data['type'] == USER_TYPE_SUPER_ADMIN){
		$reqs = check_php_requirements();
		foreach($reqs as $req){
			if($req['result'] == false){
				$table->addRow(array(
					new CSpan($req['name'], 'red'),
					new CSpan($req['current'], 'red'),
					new CSpan($req['error'], 'red')
				));
			}
		}
	}
// }}}CHECK REQUIREMENTS

	$script = new CJSScript(get_js("jQuery('#hat_stszbx_footer').html('"._s('Updated: %s',zbx_date2str(S_BLOCKS_SYSTEM_SUMMARY_TIME_FORMAT))."')"));

return new CDiv(array($table, $script));
}


// author Aly
function make_latest_issues($filter = array()){
	$config = select_config();

	$limit = isset($filter['limit']) ? $filter['limit'] : 20;
	$options = array(
		'groupids' => $filter['groupids'],
		'monitored' => 1,
		'maintenance' => $filter['maintenance'],
		'skipDependent' => 1,
		'filter' => array(
			'priority' => $filter['severity'],
			'value' => TRIGGER_VALUE_TRUE,
		),
		'selectGroups' => API_OUTPUT_EXTEND,
		'selectHosts' => array('hostid', 'name', 'maintenance_status', 'maintenance_type', 'maintenanceid'),
		'output' => API_OUTPUT_EXTEND,
		'sortfield' => 'lastchange',
		'sortorder' => ZBX_SORT_DOWN,
		'limit' => $limit
	);

	if(isset($filter['hostids'])) $options['hostids'] = $filter['hostids'];
	$triggers = API::Trigger()->get($options);

// GATHER HOSTS FOR SELECTED TRIGGERS {{{
	$triggers_hosts = array();
	foreach($triggers as $tnum => $trigger){
// if trigger is lost(broken expression) we skip it
		if(empty($trigger['hosts'])){
			unset($triggers[$tnum]);
			continue;
		}

		$triggers_hosts = array_merge($triggers_hosts, $trigger['hosts']);
	}

	$triggers_hosts = zbx_toHash($triggers_hosts, 'hostid');
	$triggers_hostids = array_keys($triggers_hosts);
// }}} GATHER HOSTS FOR SELECTED TRIGGERS

	$scripts_by_hosts = API::Script()->getScriptsByHosts($triggers_hostids);

	$table  = new CTableInfo();
	$table->setHeader(array(
		is_show_all_nodes() ? S_NODE : null,
		S_HOST,
		S_ISSUE,
		S_LAST_CHANGE,
		S_AGE,
		S_INFO,
		($config['event_ack_enable'])? S_ACK : NULL,
		S_ACTIONS
	));

	$thosts_cache = array();
	foreach($triggers as $tnum => $trigger){
// Check for dependencies
		$group = reset($trigger['groups']);
		$host = reset($trigger['hosts']);

		$trigger['hostid'] = $host['hostid'];
		$trigger['hostname'] = $host['name'];

		$host = null;
		$menus = '';

		$host_nodeid = id2nodeid($trigger['hostid']);
		foreach($scripts_by_hosts[$trigger['hostid']] as $id => $script){
			$script_nodeid = id2nodeid($script['scriptid']);
			if( (bccomp($host_nodeid ,$script_nodeid ) == 0)){
				$str_tmp = zbx_jsvalue('javascript: executeScript('.$trigger['hostid'].', '.
						$script['scriptid'].', '.
						zbx_jsvalue($script['confirmation']).
						')');

				$menus.= "[".zbx_jsvalue($script['name']).", ".$str_tmp.", null,{'outer' : ['pum_o_item'],'inner' : ['pum_i_item']}],";
			}
		}

		if(!empty($scripts_by_hosts[$trigger['hostid']])){
			$menus = "['"._('Scripts')."',null,null,{'outer' : ['pum_oheader'],'inner' : ['pum_iheader']}],".$menus;
		}

		if(isset($thosts_cache[$trigger['hostid']])){
			$hprofile = $thosts_cache[$trigger['hostid']];
		}
		else{
			$hprofile = API::Host()->get(array(
				'hostids' => $trigger['hostid'],
				'output' => API_OUTPUT_SHORTEN,
				'selectProfile' => true,
			));
			$hprofile = reset($hprofile);
			$thosts_cache[$hprofile['hostid']] = $hprofile;
		}

		$menus.= "['".S_LINKS."',null,null,{'outer' : ['pum_oheader'],'inner' : ['pum_iheader']}],";
		$menus.= "['".S_LATEST_DATA."',\"javascript: redirect('latest.php?groupid=".$group['groupid'].'&hostid='.$trigger['hostid']."')\", null,{'outer' : ['pum_o_item'],'inner' : ['pum_i_item']}],";
		if(!empty($hprofile['profile']))
			$menus.= "['".S_PROFILE."',\"javascript: redirect('hostprofiles.php?hostid=".$trigger['hostid']."&prof_type=0')\", null,{'outer' : ['pum_o_item'],'inner' : ['pum_i_item']}],";

		$menus = rtrim($menus,',');
		$menus = 'show_popup_menu(event,['.$menus.'],180);';

		$host = new CSpan($trigger['hostname'], 'link_menu pointer');
		$host->setAttribute('onclick', 'javascript: '.$menus);
		//$host = new CSpan($trigger['host'],'link_menu pointer');
		//$host->setAttribute('onclick','javascript: '.$menus);

// Maintenance {{{

		$trigger_host = $triggers_hosts[$trigger['hostid']];

		$text = null;
		$style = 'link_menu';
		if($trigger_host['maintenance_status']){
			$style.= ' orange';

			$options = array(
				'maintenanceids' => $trigger_host['maintenanceid'],
				'output' => API_OUTPUT_EXTEND
			);
			$maintenances = API::Maintenance()->get($options);
			$maintenance = reset($maintenances);

			$text = $maintenance['name'];
			$text.=' ['.($trigger_host['maintenance_type'] ? S_NO_DATA_MAINTENANCE : S_NORMAL_MAINTENANCE).']';
		}

		$host = new CSpan($trigger['hostname'], $style.' pointer');
		$host->setAttribute('onclick','javascript: '.$menus);
		if(!is_null($text)) $host->setHint($text, '', '', false);
// }}} Maintenance

// Unknown triggers
		$unknown = SPACE;
		if($trigger['value_flags'] == TRIGGER_VALUE_FLAG_UNKNOWN){
			$unknown = new CDiv(SPACE,'status_icon iconunknown');
			$unknown->setHint($trigger['error'], '', 'on');
		}
//----
		$options = array(
			'output' => API_OUTPUT_EXTEND,
			'select_acknowledges' => API_OUTPUT_COUNT,
			'triggerids' => $trigger['triggerid'],
			'filter' => array(
				'object' => EVENT_OBJECT_TRIGGER,
				'value' => TRIGGER_VALUE_TRUE,
			),
			'sortfield' => 'object',
			'sortorder' => ZBX_SORT_DOWN,
			'limit' => 1
		);
		$events = API::Event()->get($options);
		if($event = reset($events)){
			$ack = getEventAckState($event);

			$description = expand_trigger_description_by_data(zbx_array_merge($trigger, array('clock'=>$event['clock'], 'ns'=>$event['ns'])),ZBX_FLAG_EVENT);


//actions
			$actions = get_event_actions_stat_hints($event['eventid']);

			$clock = new CLink(
					zbx_date2str(S_BLOCKS_LATEST_ISSUES_DATE_FORMAT,$event['clock']),
					'events.php?triggerid='.$trigger['triggerid'].'&source=0&show_unknown=1&nav_time='.$event['clock']
					);

			if($trigger['url'])
				$description = new CLink($description, $trigger['url'], null, null, true);
			else
				$description = new CSpan($description,'pointer');

			$description = new CCol($description,getSeverityStyle($trigger['priority']));
			$description->setHint(make_popup_eventlist($event['eventid'], $trigger['type'], $trigger['triggerid']), '', '', false);

			$table->addRow(array(
				get_node_name_by_elid($trigger['triggerid']),
				$host,
				$description,
				$clock,
				zbx_date2age($event['clock']),
				$unknown,
				$ack,
				$actions
			));
		}
		unset($trigger,$description,$actions);
	}

	$script = new CJSScript(get_js("jQuery('#hat_lastiss_footer').html('"._s('Updated: %s',zbx_date2str(S_BLOCKS_SYSTEM_SUMMARY_TIME_FORMAT))."')"));

return new CDiv(array($table, $script));
}

// author Aly
function make_webmon_overview($filter){
	$options = array(
		'groupids' => $filter['groupids'],
		'monitored_hosts' => 1,
		'filter' => array('maintenance_status' => $filter['maintenance'])
	);

	$available_hosts = API::Host()->get($options);
	$available_hosts = zbx_objectValues($available_hosts,'hostid');

	$table  = new CTableInfo();
	$table->setHeader(array(
		is_show_all_nodes() ? S_NODE : null,
		S_HOST_GROUP,
		S_OK,
		S_FAILED,
		S_IN_PROGRESS,
		S_UNKNOWN
		));

	$options = array(
		'monitored_hosts' => 1,
		'with_monitored_httptests' => 1,
		'output' => API_OUTPUT_EXTEND
	);
	$groups = API::HostGroup()->get($options);
	foreach($groups as $gnum => $group){
		$showGroup = false;
		$apps['ok'] = 0;
		$apps['failed'] = 0;
		$apps[HTTPTEST_STATE_BUSY] = 0;
		$apps[HTTPTEST_STATE_UNKNOWN] = 0;

		$sql = 'SELECT DISTINCT ht.name, ht.httptestid, ht.curstate, ht.lastfailedstep '.
				' FROM httptest ht, applications a, hosts_groups hg, groups g '.
				' WHERE g.groupid='.$group['groupid'].
					' AND '.DBcondition('hg.hostid',$available_hosts).
					' AND hg.groupid=g.groupid '.
					' AND a.hostid=hg.hostid '.
					' AND ht.applicationid=a.applicationid '.
					' AND ht.status='.HTTPTEST_STATUS_ACTIVE;
		$db_httptests = DBselect($sql);
		while($httptest_data = DBfetch($db_httptests)){
			$showGroup = true;
			if( HTTPTEST_STATE_BUSY == $httptest_data['curstate'] ){
				$apps[HTTPTEST_STATE_BUSY]++;
			}
			else if( HTTPTEST_STATE_IDLE == $httptest_data['curstate'] ){
				if($httptest_data['lastfailedstep'] > 0){
					$apps['failed']++;
				}
				else{
					$apps['ok']++;
				}
			}
			else{
				$apps[HTTPTEST_STATE_UNKNOWN]++;
			}
		}

		if(!$showGroup) continue;

		$table->addRow(array(
			is_show_all_nodes() ? get_node_name_by_elid($group['groupid']) : null,
			$group['name'],
			new CSpan($apps['ok'],'off'),
			new CSpan($apps['failed'],$apps['failed']? 'on':'off'),
			new CSpan($apps[HTTPTEST_STATE_BUSY],$apps[HTTPTEST_STATE_BUSY]? 'orange':'off'),
			new CSpan($apps[HTTPTEST_STATE_UNKNOWN],'unknown')
		));
	}

	$script = new CJSScript(get_js("jQuery('#hat_webovr_footer').html('"._s('Updated: %s',zbx_date2str(S_BLOCKS_SYSTEM_SUMMARY_TIME_FORMAT))."')"));

return new CDiv(array($table, $script));
}

// Author: Aly
function make_discovery_status(){

	$options = array(
		'filter' => array('status' => DHOST_STATUS_ACTIVE),
		'selectDHosts' => API_OUTPUT_EXTEND,
		'output' => API_OUTPUT_EXTEND
	);
	$drules = API::DRule()->get($options);
	order_result($drules, 'name');

	foreach($drules as $drnum => $drule){
		$drules[$drnum]['up'] = 0;
		$drules[$drnum]['down'] = 0;

		foreach($drule['dhosts'] as  $dhnum => $dhost){
			if(DRULE_STATUS_DISABLED == $dhost['status']){
				$drules[$drnum]['down']++;		}
			else{
				$drules[$drnum]['up']++;
			}
		}
	}

	$header = array(
		is_show_all_nodes() ? new CCol(S_NODE, 'center') : null,
		new CCol(S_DISCOVERY_RULE, 'center'),
		new CCol(S_UP),
		new CCol(S_DOWN)
		);

	$table  = new CTableInfo();
	$table->setHeader($header,'header');

	foreach($drules as $drnum => $drule){
		$table->addRow(array(
			get_node_name_by_elid($drule['druleid']),
			new CLink(get_node_name_by_elid($drule['druleid'], null, ': ').$drule['name'],'discovery.php?druleid='.$drule['druleid']),
			new CSpan($drule['up'],'green'),
			new CSpan($drule['down'],($drule['down'] > 0)? 'red':'green')
		));
	}

	$script = new CJSScript(get_js("jQuery('#hat_dscvry_footer').html('"._s('Updated: %s',zbx_date2str(S_BLOCKS_SYSTEM_SUMMARY_TIME_FORMAT))."')"));

return new CDiv(array($table, $script));
}

function make_graph_menu(&$menu,&$submenu){

	$menu['menu_graphs'][] = array(
				S_FAVOURITE_GRAPHS,
				null,
				null,
				array('outer'=> array('pum_oheader'), 'inner'=>array('pum_iheader'))
		);

	$menu['menu_graphs'][] = array(
				S_ADD.' '.S_GRAPH,
				'javascript: '.
				"PopUp('popup.php?srctbl=graphs".
					'&srcfld1=graphid'.
					'&reference=graphid'.
					'&real_hosts=1'.
					"&multiselect=1',800,450);".
				"void(0);",
				null,
				array('outer' => 'pum_o_submenu', 'inner'=>array('pum_i_submenu'))
		);
	$menu['menu_graphs'][] = array(
				S_ADD.' '.S_SIMPLE_GRAPH,
				'javascript: '.
				"PopUp('popup.php?srctbl=simple_graph".
					'&srcfld1=itemid'.
					'&real_hosts=1'.
					'&reference=itemid'.
					"&multiselect=1',800,450);".
				"void(0);",
				null,
				array('outer' => 'pum_o_submenu', 'inner'=>array('pum_i_submenu'))
		);
	$menu['menu_graphs'][] = array(
				S_REMOVE,
				null,
				null,
				array('outer' => 'pum_o_submenu', 'inner'=>array('pum_i_submenu'))
		);
	$submenu['menu_graphs'] = make_graph_submenu();
}

function make_graph_submenu(){
	$graphids = array();
	$itemids = array();

	$fav_graphs = get_favorites('web.favorite.graphids');
	foreach($fav_graphs as $key => $favorite){
		if('itemid' == $favorite['source']){
			$itemids[$favorite['value']] = $favorite['value'];
		}
		else{
			$graphids[$favorite['value']] = $favorite['value'];
		}
	}

	$options = array(
			'graphids' => $graphids,
			'selectHosts' => array('hostid', 'host'),
			'output' => API_OUTPUT_EXTEND
		);
	$graphs = API::Graph()->get($options);
	$graphs = zbx_toHash($graphs, 'graphid');

	$options = array(
			'itemids' => $itemids,
			'selectHosts' => array('hostid', 'host'),
			'filter' => array('flags' => array(ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED)),
			'output' => API_OUTPUT_EXTEND,
			'webitems' => 1,
		);
	$items = API::Item()->get($options);
	$items = zbx_toHash($items, 'itemid');

	$favGraphs = array();

	foreach($fav_graphs as $key => $favorite){
		$source = $favorite['source'];
		$sourceid = $favorite['value'];

		if('itemid' == $source){
			if(!isset($items[$sourceid])) continue;
			$item_added = true;

			$item = $items[$sourceid];
			$host = reset($item['hosts']);

			$item['name'] = itemName($item);

			$favGraphs[] = array(
							'name'	=>	$host['host'].':'.$item['name'],
							'favobj'=>	'itemid',
							'favid'	=>	$sourceid,
							'action'=>	'remove'
						);
		}
		else{
			if(!isset($graphs[$sourceid])) continue;
			$graph_added = true;

			$graph = $graphs[$sourceid];
			$ghost = reset($graph['hosts']);

			$favGraphs[] = array(
							'name'	=>	$ghost['host'].':'.$graph['name'],
							'favobj'=>	'graphid',
							'favid'	=>	$sourceid,
							'action'=>	'remove'
						);
		}
	}

	if(isset($graph_added)){
			$favGraphs[] = array(
			'name'	=>	S_REMOVE.' '.S_ALL_S.' '.S_GRAPHS,
			'favobj'=>	'graphid',
			'favid'	=>	0,
			'action'=>	'remove'
		);
	}

	if(isset($item_added)){
		$favGraphs[] = array(
			'name'	=>	S_REMOVE.' '.S_ALL_S.' '.S_SIMPLE_GRAPHS,
			'favobj'=>	'itemid',
			'favid'	=>	0,
			'action'=>	'remove'
		);
	}

return $favGraphs;
}

function make_sysmap_menu(&$menu,&$submenu){

	$menu['menu_sysmaps'][] = array(S_FAVOURITE_MAPS, null, null, array('outer'=> array('pum_oheader'), 'inner'=>array('pum_iheader')));
	$menu['menu_sysmaps'][] = array(
				S_ADD.' '.S_MAP,
				'javascript: '.
				"PopUp('popup.php?srctbl=sysmaps".
					'&srcfld1=sysmapid'.
					'&reference=sysmapid'.
					"&multiselect=1',800,450);".
				"void(0);",
				null,
				array('outer' => 'pum_o_submenu', 'inner'=>array('pum_i_submenu')
		));
	$menu['menu_sysmaps'][] = array(S_REMOVE, null, null, array('outer' => 'pum_o_submenu', 'inner'=>array('pum_i_submenu')));
	$submenu['menu_sysmaps'] = make_sysmap_submenu();
}

function make_sysmap_submenu(){
	$fav_sysmaps = get_favorites('web.favorite.sysmapids');

	$favMaps = array();
	$sysmapids = array();
	foreach($fav_sysmaps as $key => $favorite){
		$sysmapids[$favorite['value']] = $favorite['value'];
	}

	$options = array(
			'sysmapids' => $sysmapids,
			'output' => API_OUTPUT_EXTEND,
		);
	$sysmaps = API::Map()->get($options);

	foreach($sysmaps as $snum => $sysmap){
		$favMaps[] = array(
				'name'	=>	$sysmap['name'],
				'favobj'=>	'sysmapid',
				'favid'	=>	$sysmap['sysmapid'],
				'action'=>	'remove'
			);
	}

	if(!empty($favMaps)){
		$favMaps[] = array(
				'name'	=>	S_REMOVE.' '.S_ALL_S.' '.S_MAPS,
				'favobj'=>	'sysmapid',
				'favid'	=>	0,
				'action'=>	'remove'
			);
	}

return $favMaps;
}

function make_screen_menu(&$menu,&$submenu){

	$menu['menu_screens'][] = array(S_FAVOURITE_SCREENS, null, null, array('outer'=> array('pum_oheader'), 'inner'=>array('pum_iheader')));
	$menu['menu_screens'][] = array(
				S_ADD.' '.S_SCREEN,
				'javascript: '.
				"PopUp('popup.php?srctbl=screens".
					'&srcfld1=screenid'.
					'&reference=screenid'.
					"&multiselect=1',800,450);".
				"void(0);",
				null,
				array('outer' => 'pum_o_submenu', 'inner'=>array('pum_i_submenu')
		));
	$menu['menu_screens'][] = array(
				S_ADD.' '.S_SLIDESHOW,
				'javascript: '.
				"PopUp('popup.php?srctbl=slides".
					'&srcfld1=slideshowid'.
					'&reference=slideshowid'.
					"&multiselect=1',800,450);".
				"void(0);",
				null,
				array('outer' => 'pum_o_submenu', 'inner'=>array('pum_i_submenu')
		));
	$menu['menu_screens'][] = array(S_REMOVE, null, null, array('outer' => 'pum_o_submenu', 'inner'=>array('pum_i_submenu')));
	$submenu['menu_screens'] = make_screen_submenu();
}

function make_screen_submenu(){
	$fav_screens = get_favorites('web.favorite.screenids');

	$screenids = array();
	foreach($fav_screens as $key => $favorite){
		if('screenid' == $favorite['source']){
			$screenids[$favorite['value']] = $favorite['value'];
		}
	}

	$options = array(
		'screenids' => $screenids,
		'output' => API_OUTPUT_EXTEND,
	);
	$screens = API::Screen()->get($options);
	$screens = zbx_toHash($screens, 'screenid');

	$favScreens = array();
	foreach($fav_screens as $key => $favorite){
		$source = $favorite['source'];
		$sourceid = $favorite['value'];

		if('slideshowid' == $source){
			if(!slideshow_accessible($sourceid, PERM_READ_ONLY)) continue;
			if(!$slide = get_slideshow_by_slideshowid($sourceid)) continue;

			$slide_added = true;

			$favScreens[] = array(
				'name'	=>	$slide['name'],
				'favobj'=>	'slideshowid',
				'favid'	=>	$slide['slideshowid'],
				'action'=>	'remove'
			);

		}
		else{
			if(!isset($screens[$sourceid])) continue;
			$screen = $screens[$sourceid];

			$screen_added = true;

			$favScreens[] = array(
				'name'	=>	$screen['name'],
				'favobj'=>	'screenid',
				'favid'	=>	$screen['screenid'],
				'action'=>	'remove'
			);
		}
	}


	if(isset($screen_added)){
		$favScreens[] = array(
			'name'	=>	S_REMOVE.' '.S_ALL_S.' '.S_SCREENS,
			'favobj'=>	'screenid',
			'favid'	=>	0,
			'action'=>	'remove'
		);
	}

	if(isset($slide_added)){
		$favScreens[] = array(
			'name'	=>	S_REMOVE.' '.S_ALL_S.' '.S_SLIDES,
			'favobj'=>	'slideshowid',
			'favid'	=>	0,
			'action'=>	'remove'
		);
	}

return $favScreens;
}

?>
