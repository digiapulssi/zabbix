<?php
/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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

$page['title'] = _('SLA report');
$page['file'] = 'rsm.slareports.php';
$page['hist_arg'] = array('groupid', 'hostid');
$page['type'] = detect_page_type(PAGE_TYPE_HTML);

require_once dirname(__FILE__).'/include/page_header.php';

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields = array(
	'export' =>			array(T_ZBX_INT, O_OPT,	P_ACT,	null,		null),
	// filter
	'filter_set' =>		array(T_ZBX_STR, O_OPT,  null,	null,		null),
	'filter_search' =>	array(T_ZBX_STR, O_OPT,  null,	null,		null),
	'filter_year' =>	array(T_ZBX_INT, O_OPT,  null,	null,		null),
	'filter_month' =>	array(T_ZBX_INT, O_OPT,  null,	null,		null),
	// ajax
	'favobj' =>			array(T_ZBX_STR, O_OPT, P_ACT,	null,		null),
	'favref' =>			array(T_ZBX_STR, O_OPT, P_ACT,  NOT_EMPTY,	'isset({favobj})'),
	'favstate' =>		array(T_ZBX_INT, O_OPT, P_ACT,  NOT_EMPTY,	'isset({favobj})&&("filter"=={favobj})')
);

check_fields($fields);

if (isset($_REQUEST['favobj'])) {
	if('filter' == $_REQUEST['favobj']){
		CProfile::update('web.rsm.slareports.filter.state', getRequest('favstate'), PROFILE_TYPE_INT);
	}
}

if ((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])) {
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit();
}

$error = '';
$data = [
	'tld' => [],
	'url' => '',
	'sid' => CWebUser::getSessionCookie(),
	'filter_search' => getRequest('filter_search'),
	'filter_year' => getRequest('filter_year', date('Y')),
	'filter_month' => getRequest('filter_month', date('n'))
];

// Time limits.
$start_time = mktime(
	0,
	0,
	0,
	$data['filter_month'],
	1,
	$data['filter_year']
);
$end_time = strtotime('+1 month', $start_time);
$data['start_time'] = $start_time;
$data['end_time'] = $end_time;

/*
 * Filter
 */
if (hasRequest('filter_set') && $start_time > time()) {
	show_error_message(_('Incorrect report period.'));
}
else if ($data['filter_search']) {
	$master = $DB;

	foreach ($DB['SERVERS'] as $server) {
		if (!multiDBconnect($server, $error)) {
			show_error_message(_($server['NAME'].': '.$error));
			continue;
		}

		$tld = API::Host()->get([
			'output' => ['hostid', 'host', 'name'],
			'tlds' => true,
			'selectMacros' => ['macro', 'value'],
			'selectItems' => ['itemid', 'key_', 'value_type'],
			'filter' => ['name' => $data['filter_search']]
		]);

		// TLD not found, proceed to search on another server.
		if (!$tld) {
			continue;
		}

		$data['tld'] = $tld[0];
		$data['url'] = $server['URL'];
		$data['server'] = $server['NAME'];

		break;
	}
}

if ($data['tld']) {
	// Get TLD template.
	$template = API::Template()->get([
		'output' => ['templateid'],
		'filter' => ['host' => 'Template '.$data['tld']['host']]
	])[0];

	$template_macros = API::UserMacro()->get([
		'output' => ['macro', 'value'],
		'hostids' => $template['templateid'],
		'filter' => [
			'macro' => [RSM_TLD_RDDS43_ENABLED, RSM_TLD_RDDS80_ENABLED, RSM_TLD_RDAP_ENABLED]
		]
	]);

	$item_keys = [RSM_SLV_DNS_DOWNTIME, RSM_SLV_DNS_TCP_NS_TESTS_PFAILED, RSM_SLV_DNS_UDP_NS_TESTS_PFAILED];
	$macro_keys = [RSM_SLV_NS_AVAIL, RSM_SLV_DNS_TCP_RTT, RSM_DNS_TCP_RTT_LOW, RSM_SLV_DNS_UDP_RTT, RSM_DNS_UDP_RTT_LOW];

	foreach ($template_macros as $tmpl_macro) {
		if ($tmpl_macro['value'] != 1) {
			continue;
		}

		// Add RDDS item keys and macro if RDDS is enabled.
		$item_keys = array_merge($item_keys, [RSM_SLV_RDDS_DOWNTIME, RSM_SLV_RDDS_UPD_PFAILED]);
		$macro_keys = array_merge($macro_keys, [RSM_SLV_MACRO_RDDS_AVAIL, RSM_SLV_RDDS_UPD, RSM_RDDS_UPDATE_TIME,
			RSM_RDDS_RTT_LOW, RSM_SLV_MACRO_RDDS_RTT
		]);
		break;
	}

	// Get TLD items.
	$items = zbx_toHash($data['tld']['items'], 'key_');
	unset($data['tld']['items']);
	$slv_keys = array_intersect($item_keys, array_keys($items));

	if (count($slv_keys) != count($item_keys)) {
		show_error_message(_s('Configuration error, cannot find items: "%1$s".', implode(', ',
			array_diff($item_keys, $slv_keys)))
		);

		require_once dirname(__FILE__).'/include/page_footer.php';
		exit;
	}

	$macros = API::UserMacro()->get([
		'globalmacro' => true,
		'output' => ['macro', 'value'],
		'filter' => ['macro' => $macro_keys]
	]);
	$macros = zbx_toHash($macros, 'macro');
	$macros = array_merge($macros, zbx_toHash($data['tld']['macros'], 'macro'));
	unset($data['tld']['macros']);
	$slv_keys = array_intersect($macro_keys, array_keys($macros));
	$undefined_macro = array_diff($macro_keys, $slv_keys);

	if ($undefined_macro) {
		show_error_message(_s('Configuration error, cannot find macros: "%1$s".', implode(', ', $undefined_macro)));

		require_once dirname(__FILE__).'/include/page_footer.php';
		exit;
	}

	$data['macro'] = [];
	foreach ($macro_keys as $macro_key) {
		$data['macro'][$macro_key] = $macros[$macro_key]['value'];
	}

	$db_items = [];
	foreach ($item_keys as $key_) {
		$db_items[$key_] = $items[$key_]['itemid'];
	}

	foreach ($items as $key => $item) {
		if (strpos($key, RSM_SLV_DNS_NS_DOWNTIME) === 0) {
			$db_items[$key] = $item['itemid'];
		}
	}

	$values = [];
	$item_key_parser = new CItemKey();
	foreach ($db_items as $key_ => $itemid) {
		/**
		 * CHistory request with 'output' set to ['clock', 'value'] will return only 'itemid'
		 * therefore API_OUTPUT_EXTEND is used instead.
		 */
		$history = API::History()->get([
			'output' => API_OUTPUT_EXTEND,
			'itemids' => $itemid,
			'history' => ITEM_VALUE_TYPE_UINT64,
			'time_from' => $start_time,
			'time_to' => $end_time
		]);

		if ($history) {
			CArrayHelper::sort($history, ['clock']);
			$from = reset($history);
			$to = end($history);
		} else {
			$from = $to = [
				'clock' => 0,
				'value' => 0
			];
		}

		$values[$key_] = [
			'from' => $from['clock'],
			'to' => $to['clock'],
			'slv' => $to['value']
		];

		if (strpos($key_, RSM_SLV_DNS_NS_DOWNTIME) === 0) {
			$item_key_parser->parse($key_);
			$values[$key_]['host'] = $item_key_parser->getParam(0);
			$values[$key_]['ip'] = $item_key_parser->getParam(1);
			$values[$key_]['nsitem'] = true;
		}
	}

	$data['values'] = $values;

	if ($DB === $master) {
		$data['rolling_week_url'] = (new CUrl('rsm.rollingweekstatus.php'))->getUrl();
	}
	else {
		$DB = $master;

		$data['rolling_week_url'] = (new CUrl($data['url'].'rsm.rollingweekstatus.php'))
			->setArgument('sid', $data['sid'])
			->setArgument('set_sid', 1)
			->getUrl();
	}
}

(new CView('rsm.slareports.list', $data))
	->render()
	->show();

require_once dirname(__FILE__).'/include/page_footer.php';
