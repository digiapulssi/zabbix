<?php
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/incidentdetails.inc.php';

$page['title'] = _('Incidents details');
$page['file'] = 'rsm.incidentdetails.php';
$page['hist_arg'] = array('groupid', 'hostid');
$page['scripts'] = array('class.calendar.js');
$page['type'] = detect_page_type(PAGE_TYPE_HTML);

require_once dirname(__FILE__).'/include/page_header.php';

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'host' =>					array(T_ZBX_STR, O_OPT,	P_SYS,	null,		null),
	'eventid' =>				array(T_ZBX_INT, O_OPT,	P_SYS,	null,		null),
	'slvItemId' =>				array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,		null),
	'availItemId' =>			array(T_ZBX_INT, O_OPT,	P_SYS,	DB_ID,		null),
	'original_from' =>			array(T_ZBX_INT, O_OPT,	null,	null,		null),
	'original_to' =>			array(T_ZBX_INT, O_OPT,	null,	null,		null),
	// filter
	'filter_set' =>				array(T_ZBX_STR, O_OPT,	null,	null,		null),
	'filter_from' =>			array(T_ZBX_INT, O_OPT,	null,	null,		null),
	'filter_to' =>				array(T_ZBX_INT, O_OPT,	null,	null,		null),
	'filter_rolling_week' =>	array(T_ZBX_INT, O_OPT,	null,	null,		null),
	'filter_failing_tests' =>	array(T_ZBX_INT, O_OPT,	null,	IN('0,1'),	null),
	// ajax
	'favobj' =>					array(T_ZBX_STR, O_OPT,	P_ACT,	null,		null),
	'favref' =>					array(T_ZBX_STR, O_OPT,	P_ACT,  NOT_EMPTY,	'isset({favobj})'),
	'favstate' =>				array(T_ZBX_INT, O_OPT,	P_ACT,  NOT_EMPTY,	'isset({favobj})&&("filter"=={favobj})')
);
check_fields($fields);

if (isset($_REQUEST['favobj'])) {
	if('filter' == $_REQUEST['favobj']){
		CProfile::update('web.rsm.incidentdetails.filter.state', getRequest('favstate'), PROFILE_TYPE_INT);
	}
}

if ((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])) {
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit();
}

$macro = API::UserMacro()->get(array(
	'globalmacro' => true,
	'output' => API_OUTPUT_EXTEND,
	'filter' => array(
		'macro' => RSM_ROLLWEEK_SECONDS
	)
));

if (!$macro) {
	show_error_message(_s('Macro "%1$s" does not exist.', RSM_ROLLWEEK_SECONDS));
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit;
}

$rollWeekSeconds = reset($macro);
$serverTime = time() - RSM_ROLLWEEK_SHIFT_BACK;

/*
 * Filter
 */
if (getRequest('filter_set')) {
	$data['host'] = getRequest('host');
	$data['eventid'] = getRequest('eventid');
	$data['slvItemId'] = getRequest('slvItemId');
	$data['availItemId'] = getRequest('availItemId');
	$data['filter_failing_tests'] = getRequest('filter_failing_tests', 0);

	$data['filter_from'] = (getRequest('filter_from') == getRequest('original_from'))
		? date('YmdHis', getRequest('filter_from', $serverTime - $rollWeekSeconds['value']))
		: getRequest('filter_from', date('YmdHis', $serverTime - $rollWeekSeconds['value']));

	$data['filter_to'] = (getRequest('filter_to') == getRequest('original_to'))
		? date('YmdHis', getRequest('filter_to', $serverTime))
		: getRequest('filter_to', date('YmdHis', $serverTime));

	CProfile::update('web.rsm.incidentdetails.host', $data['host'], PROFILE_TYPE_STR);
	CProfile::update('web.rsm.incidentdetails.eventid', $data['eventid'], PROFILE_TYPE_ID);
	CProfile::update('web.rsm.incidentdetails.slvItemId', $data['slvItemId'], PROFILE_TYPE_ID);
	CProfile::update('web.rsm.incidentdetails.availItemId', $data['availItemId'], PROFILE_TYPE_ID);
	CProfile::update('web.rsm.incidentdetails.filter_from', $data['filter_from'], PROFILE_TYPE_ID);
	CProfile::update('web.rsm.incidentdetails.filter_to', $data['filter_to'], PROFILE_TYPE_ID);
	CProfile::update('web.rsm.incidentdetails.filter_failing_tests', $data['filter_failing_tests'], PROFILE_TYPE_ID);
}
elseif (getRequest('filter_rolling_week')) {
	$data['host'] = CProfile::get('web.rsm.incidentdetails.host');
	$data['eventid'] = CProfile::get('web.rsm.incidentdetails.eventid');
	$data['slvItemId'] = CProfile::get('web.rsm.incidentdetails.slvItemId');
	$data['availItemId'] = CProfile::get('web.rsm.incidentdetails.availItemId');
	$data['filter_failing_tests'] = CProfile::get('web.rsm.incidentdetails.filter_failing_tests');

	// set new filter from and filter to
	$data['filter_from'] = date('YmdHis', $serverTime - $rollWeekSeconds['value']);
	$data['filter_to'] = date('YmdHis', $serverTime);

	CProfile::update('web.rsm.incidentdetails.filter_from', $data['filter_from'], PROFILE_TYPE_ID);
	CProfile::update('web.rsm.incidentdetails.filter_to', $data['filter_to'], PROFILE_TYPE_ID);
}
else {
	$data['host'] = CProfile::get('web.rsm.incidentdetails.host');
	$data['eventid'] = CProfile::get('web.rsm.incidentdetails.eventid');
	$data['slvItemId'] = CProfile::get('web.rsm.incidentdetails.slvItemId');
	$data['availItemId'] = CProfile::get('web.rsm.incidentdetails.availItemId');
	$data['filter_from'] = CProfile::get('web.rsm.incidentdetails.filter_from',
		date('YmdHis', $serverTime - $rollWeekSeconds['value'])
	);
	$data['filter_to'] = CProfile::get('web.rsm.incidentdetails.filter_to', date('YmdHis', $serverTime));
	$data['filter_failing_tests'] = CProfile::get('web.rsm.incidentdetails.filter_failing_tests');
}

// check
if (!$data['eventid'] || !$data['slvItemId'] || !$data['availItemId'] || !$data['host']) {
	access_deny();
}

// get TLD
$tld = API::Host()->get(array(
	'tlds' => true,
	'output' => array('hostid', 'host', 'name'),
	'filter' => array(
		'host' => $data['host']
	)
));

if ($tld) {
	$data['tld'] = reset($tld);
}
else {
	access_deny();
}

// get slv item
$slvItems = API::Item()->get(array(
	'itemids' => $data['slvItemId'],
	'output' => array('name', 'key_', 'lastvalue', 'lastclock')
));

if ($slvItems) {
	$data['slvItem'] = reset($slvItems);
}
else {
	access_deny();
}

// get start event
$mainEvent = API::Event()->get(array(
	'eventids' => $data['eventid'],
	'output' => API_OUTPUT_EXTEND
));

if ($mainEvent) {
	$mainEvent = reset($mainEvent);

	// get host with calculated items
	$rsm = API::Host()->get(array(
		'output' => array('hostid'),
		'filter' => array(
			'host' => RSM_HOST
		)
	));

	if ($rsm) {
		$rsm = reset($rsm);
	}
	else {
		show_error_message(_s('No permissions to referred host "%1$s" or it does not exist!', RSM_HOST));
		require_once dirname(__FILE__).'/include/page_footer.php';
		exit;
	}

	switch ($data['slvItem']['key_']) {
		case RSM_SLV_DNS_ROLLWEEK:
			$keys = array(CALCULATED_ITEM_DNS_FAIL, CALCULATED_ITEM_DNS_DELAY);
			$data['type'] = RSM_DNS;
			break;
		case RSM_SLV_DNSSEC_ROLLWEEK:
			$keys = array(CALCULATED_ITEM_DNSSEC_FAIL, CALCULATED_ITEM_DNS_DELAY);
			$data['type'] = RSM_DNSSEC;
			break;
		case RSM_SLV_RDDS_ROLLWEEK:
			$keys = array(CALCULATED_ITEM_RDDS_FAIL, CALCULATED_ITEM_RDDS_DELAY);
			$data['type'] = RSM_RDDS;

			$templates = API::Template()->get(array(
				'output' => array('templateid'),
				'filter' => array(
					'host' => 'Template '.$data['tld']['host']
				),
				'preservekeys' => true
			));

			$template = reset($templates);

			$template_macros = API::UserMacro()->get(array(
				'output' => API_OUTPUT_EXTEND,
				'hostids' => $template['templateid'],
				'filter' => array(
					'macro' => array(RSM_TLD_RDDS43_ENABLED, RSM_TLD_RDDS80_ENABLED, RSM_TLD_RDAP_ENABLED)
				)));

				$data['tld']['subservices'] = [];
				foreach ($template_macros as $template_macro) {
					$data['tld']['subservices'][$template_macro['macro']] = $template_macro['value'];
				}
			break;
		case RSM_SLV_EPP_ROLLWEEK:
			$keys = array(CALCULATED_ITEM_EPP_FAIL, CALCULATED_ITEM_EPP_DELAY);
			$data['type'] = RSM_EPP;
			break;
	}

	$items = API::Item()->get(array(
		'hostids' => $rsm['hostid'],
		'output' => array('itemid', 'key_'),
		'filter' => array(
			'key_' => $keys
		)
	));

	if (count($items) != 2) {
		show_error_message(_s('Missing items at host "%1$s"!', RSM_HOST));
		require_once dirname(__FILE__).'/include/page_footer.php';
		exit;
	}

	foreach ($items as $item) {
		if ($item['key_'] == CALCULATED_ITEM_DNS_FAIL || $item['key_'] == CALCULATED_ITEM_DNSSEC_FAIL
				|| $item['key_'] == CALCULATED_ITEM_RDDS_FAIL || $item['key_'] == CALCULATED_ITEM_EPP_FAIL) {
			$failCount = getFirstUintValue($item['itemid'], $mainEvent['clock']);
		}
		else {
			$delayTime = getFirstUintValue($item['itemid'], $mainEvent['clock']);
		}
	}

	$mainEventFromTime = $mainEvent['clock'] - $mainEvent['clock'] % $delayTime - ($failCount - 1) * $delayTime;

	if (getRequest('filter_set')) {
		$fromTime = ($mainEventFromTime >= zbxDateToTime($data['filter_from']))
			? $mainEventFromTime
			: zbxDateToTime($data['filter_from']);
	}
	else {
		$fromTime = $mainEventFromTime;
	}

	// get end event
	$endEventTimeTill = getRequest('filter_set') ? ' AND e.clock<='.zbxDateToTime($data['filter_to']) : null;

	$endEvent = DBfetch(DBselect(
		'SELECT e.clock,e.value'.
		' FROM events e'.
		' WHERE e.objectid='.$mainEvent['objectid'].
			' AND e.clock>='.$mainEvent['clock'].
			$endEventTimeTill.
			' AND e.object='.EVENT_OBJECT_TRIGGER.
			' AND e.source='.EVENT_SOURCE_TRIGGERS.
			' AND e.value='.TRIGGER_VALUE_FALSE.
		' ORDER BY e.clock',
		1
	));

	if ($endEvent) {
		$endEventToTime = $endEvent['clock'] - $endEvent['clock'] % $delayTime + $delayTime;
		if (getRequest('filter_set')) {
			$toTime = ($endEventToTime >= zbxDateToTime($data['filter_to']))
				? zbxDateToTime($data['filter_to'])
				: $endEventToTime;
		}
		else {
			$toTime = $endEventToTime;
		}
	}
	else {
		$toTime = getRequest('filter_set') ? zbxDateToTime($data['filter_to']) : $serverTime;
	}

	// result generation
	$data['slv'] = sprintf('%.3f', $data['slvItem']['lastvalue']);
	$data['slvTestTime'] = sprintf('%.3f', $data['slvItem']['lastclock']);
	if ($mainEvent['false_positive']) {
		$data['incidentType'] = INCIDENT_FALSE_POSITIVE;
	}
	elseif ($endEvent && $endEvent['value'] == TRIGGER_VALUE_FALSE) {
		$data['incidentType'] = INCIDENT_RESOLVED;
	}
	else {
		$data['incidentType'] = INCIDENT_ACTIVE;
	}

	$data['active'] = $endEvent ? true : false;

	$failingTests = $data['filter_failing_tests'] ? ' AND h.value='.DOWN : null;

	$tests = DBselect(
		'SELECT h.value, h.clock'.
		' FROM history_uint h'.
		' WHERE h.itemid='.zbx_dbstr($data['availItemId']).
			' AND h.clock>='.$fromTime.
			' AND h.clock<='.$toTime.
			$failingTests.
		' ORDER BY h.clock asc'
	);

	$data['tests'] = [];
	while ($test = DBfetch($tests)) {
		$data['tests'][] = array(
			'clock' => $test['clock'] - $test['clock'] % $delayTime,
			'value' => $test['value'],
			'startEvent' => $mainEvent['clock'] == $test['clock'] ? true : false,
			'endEvent' => $endEvent && $endEvent['clock'] == $test['clock'] ? $endEvent['value'] : TRIGGER_VALUE_TRUE
		);
	}

	$slvs = DBselect(
		'SELECT h.value,h.clock'.
		' FROM history h'.
		' WHERE h.itemid='.zbx_dbstr($data['slvItemId']).
			' AND h.clock>='.$fromTime.
			' AND h.clock<='.$toTime.
		' ORDER BY h.clock asc'
	);

	$slv = DBfetch($slvs);

	foreach ($data['tests'] as &$test) {
		while ($slv) {
			$latest = $slv;

			if (!($slv = DBfetch($slvs)) || $slv['clock'] > $test['clock'] + $delayTime) {
				$test['slv'] = sprintf('%.3f', $latest['value']);
				break;
			}
		}
	}

	$data['paging'] = getPagingLine($data['tests'], ZBX_SORT_UP, new CUrl());
}

$rsmView = new CView('rsm.incidentdetails.list', $data);

$rsmView->render();
$rsmView->show();

require_once dirname(__FILE__).'/include/page_footer.php';
