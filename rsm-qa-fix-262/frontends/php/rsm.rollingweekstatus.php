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
require_once dirname(__FILE__).'/include/rollingweekstatus.inc.php';

$page['title'] = _('TLD Rolling week status');
$page['file'] = 'rsm.rollingweekstatus.php';
$page['hist_arg'] = array('groupid', 'hostid');
$page['type'] = detect_page_type(PAGE_TYPE_HTML);

if (PAGE_TYPE_HTML == $page['type']) {
	define('ZBX_PAGE_DO_REFRESH', 1);
}

require_once dirname(__FILE__).'/include/page_header.php';

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields = [
	// filter
	'filter_set' =>				[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'filter_rst' =>				[T_ZBX_STR, O_OPT, P_SYS,	null,		null],
	'filter_search' =>			[T_ZBX_STR, O_OPT,  null,	null,		null],
	'filter_dns' =>				[T_ZBX_STR, O_OPT,  null,	null,		null],
	'filter_dnssec' =>			[T_ZBX_STR, O_OPT,  null,	null,		null],
	'filter_rdds' =>			[T_ZBX_STR, O_OPT,  null,	null,		null],
	'filter_epp' =>				[T_ZBX_STR, O_OPT,  null,	null,		null],
	'filter_slv' =>				[T_ZBX_STR, O_OPT,  null,	null,		null],
	'filter_status' =>			[T_ZBX_INT, O_OPT,  null,	null,		null],
	'filter_gtld_group' =>		[T_ZBX_STR, O_OPT,  null,	null,		null],
	'filter_cctld_group' =>		[T_ZBX_STR, O_OPT,  null,	null,		null],
	'filter_othertld_group' =>	[T_ZBX_STR, O_OPT,  null,	null,		null],
	'filter_test_group' =>		[T_ZBX_STR, O_OPT,  null,	null,		null],
	// ajax
	'favobj' =>					[T_ZBX_STR, O_OPT, P_ACT,	null,		null],
	'favref' =>					[T_ZBX_STR, O_OPT, P_ACT,  NOT_EMPTY,	'isset({favobj})'],
	'favstate' =>				[T_ZBX_INT, O_OPT, P_ACT,  NOT_EMPTY,	'isset({favobj})&&("filter"=={favobj})']
];

check_fields($fields);

if (isset($_REQUEST['favobj'])) {
	if('filter' == $_REQUEST['favobj']){
		CProfile::update('web.rsm.rollingweekstatus.filter.state', getRequest('favstate'), PROFILE_TYPE_INT);
	}
}

if ((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])) {
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit();
}

$data = [];

/*
 * Filter
 */
if (hasRequest('filter_set')) {
	CProfile::update('web.rsm.rollingweekstatus.filter_search', getRequest('filter_search'), PROFILE_TYPE_STR);
	CProfile::update('web.rsm.rollingweekstatus.filter_dns', getRequest('filter_dns', 0), PROFILE_TYPE_INT);
	CProfile::update('web.rsm.rollingweekstatus.filter_dnssec', getRequest('filter_dnssec', 0), PROFILE_TYPE_INT);
	CProfile::update('web.rsm.rollingweekstatus.filter_rdds', getRequest('filter_rdds', 0), PROFILE_TYPE_INT);
	CProfile::update('web.rsm.rollingweekstatus.filter_epp', getRequest('filter_epp', 0), PROFILE_TYPE_INT);
	CProfile::update('web.rsm.rollingweekstatus.filter_slv', getRequest('filter_slv', 0), PROFILE_TYPE_INT);
	CProfile::update('web.rsm.rollingweekstatus.filter_status', getRequest('filter_status', 0), PROFILE_TYPE_INT);
	CProfile::update('web.rsm.rollingweekstatus.filter_gtld_group', getRequest('filter_gtld_group', 0), PROFILE_TYPE_INT);
	CProfile::update('web.rsm.rollingweekstatus.filter_cctld_group', getRequest('filter_cctld_group', 0), PROFILE_TYPE_INT);
	CProfile::update('web.rsm.rollingweekstatus.filter_othertld_group', getRequest('filter_othertld_group', 0), PROFILE_TYPE_INT);
	CProfile::update('web.rsm.rollingweekstatus.filter_test_group', getRequest('filter_test_group', 0), PROFILE_TYPE_INT);
}
elseif (hasRequest('filter_rst')) {
	DBStart();
	CProfile::delete('web.rsm.rollingweekstatus.filter_search');
	CProfile::delete('web.rsm.rollingweekstatus.filter_dns');
	CProfile::delete('web.rsm.rollingweekstatus.filter_dnssec');
	CProfile::delete('web.rsm.rollingweekstatus.filter_rdds');
	CProfile::delete('web.rsm.rollingweekstatus.filter_epp');
	CProfile::delete('web.rsm.rollingweekstatus.filter_slv');
	CProfile::delete('web.rsm.rollingweekstatus.filter_status');
	CProfile::delete('web.rsm.rollingweekstatus.filter_gtld_group');
	CProfile::delete('web.rsm.rollingweekstatus.filter_cctld_group');
	CProfile::delete('web.rsm.rollingweekstatus.filter_othertld_group');
	CProfile::delete('web.rsm.rollingweekstatus.filter_test_group');
	DBend();
}

$data['filter_search'] = CProfile::get('web.rsm.rollingweekstatus.filter_search');
$data['filter_dns'] = CProfile::get('web.rsm.rollingweekstatus.filter_dns');
$data['filter_dnssec'] = CProfile::get('web.rsm.rollingweekstatus.filter_dnssec');
$data['filter_rdds'] = CProfile::get('web.rsm.rollingweekstatus.filter_rdds');
$data['filter_epp'] = CProfile::get('web.rsm.rollingweekstatus.filter_epp');
$data['filter_slv'] = CProfile::get('web.rsm.rollingweekstatus.filter_slv', 0);
$data['filter_status'] = CProfile::get('web.rsm.rollingweekstatus.filter_status');
$data['filter_gtld_group'] = CProfile::get('web.rsm.rollingweekstatus.filter_gtld_group');
$data['filter_cctld_group'] = CProfile::get('web.rsm.rollingweekstatus.filter_cctld_group');
$data['filter_othertld_group'] = CProfile::get('web.rsm.rollingweekstatus.filter_othertld_group');
$data['filter_test_group'] = CProfile::get('web.rsm.rollingweekstatus.filter_test_group');

$macro = API::UserMacro()->get(array(
	'globalmacro' => true,
	'output' => ['macro', 'value'],
	'filter' => array(
		'macro' => array(RSM_PAGE_SLV, RSM_ROLLWEEK_SECONDS)
	)
));

foreach ($macro as $macros) {
	if ($macros['macro'] === RSM_PAGE_SLV) {
		$data['slv'] = $macros['value'];
	}
	else {
		$data['rollWeekSeconds'] = $macros['value'];
	}
}

if (!array_key_exists('slv', $data)) {
	show_error_message(_s('Macro "%1$s" doesn\'t not exist.', RSM_PAGE_SLV));
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit;
}

if (!array_key_exists('rollWeekSeconds', $data)) {
	show_error_message(_s('Macro "%1$s" doesn\'t not exist.', RSM_ROLLWEEK_SECONDS));
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit;
}

$data['allowedGroups'] = array(
	RSM_CC_TLD_GROUP => false,
	RSM_G_TLD_GROUP => false,
	RSM_OTHER_TLD_GROUP => false,
	RSM_TEST_GROUP => false
);

$master = $DB;
$data['tld'] = [];

if ($data['filter_status'] != 0 || $data['filter_slv'] != 0 || $data['filter_dns'] || $data['filter_dnssec']
		|| $data['filter_rdds'] || $data['filter_epp']) {
	$no_history = false;
}
else {
	$no_history = true;
}

foreach ($DB['SERVERS'] as $key => $value) {
	if ($data['filter_cctld_group'] || $data['filter_gtld_group'] || $data['filter_othertld_group']
			|| $data['filter_test_group']) {
		if ($DB['SERVER'] !== $DB['SERVERS'][$key]['SERVER'] || $DB['PORT'] !== $DB['SERVERS'][$key]['PORT']
				|| $DB['DATABASE'] !== $DB['SERVERS'][$key]['DATABASE'] || $DB['USER'] !== $DB['SERVERS'][$key]['USER']
				|| $DB['PASSWORD'] !== $DB['SERVERS'][$key]['PASSWORD']) {
			if (!multiDBconnect($DB['SERVERS'][$key], $error)) {
				show_error_message(_($DB['SERVERS'][$key]['NAME'].': '.$error));
				continue;
			}
		}

		$where_condition = [];
		$itemIds = [];

		// get "TLDs" groupId
		$tldGroups = API::HostGroup()->get(array(
			'output' => array('groupid', 'name'),
			'filter' => array(
				'name' => array(RSM_TLDS_GROUP, RSM_CC_TLD_GROUP, RSM_G_TLD_GROUP, RSM_OTHER_TLD_GROUP, RSM_TEST_GROUP)
			)
		));

		$selectedGroups = [];
		$included_groupids = [];
		$excluded_groupids = [];

		foreach ($tldGroups as $tldGroup) {
			switch ($tldGroup['name']) {
				case RSM_TLDS_GROUP:
					$selectedGroups[$tldGroup['groupid']] = $tldGroup['groupid'];
					break;
				case RSM_CC_TLD_GROUP:
					$data['allowedGroups'][RSM_CC_TLD_GROUP] = true;

					if ($data['filter_cctld_group']) {
						$included_groupids[$tldGroup['groupid']] = $tldGroup['groupid'];
					}
					else {
						$excluded_groupids[$tldGroup['groupid']] = $tldGroup['groupid'];
					}
					break;
				case RSM_G_TLD_GROUP:
					$data['allowedGroups'][RSM_G_TLD_GROUP] = true;

					if ($data['filter_gtld_group']) {
						$included_groupids[$tldGroup['groupid']] = $tldGroup['groupid'];
					}
					else {
						$excluded_groupids[$tldGroup['groupid']] = $tldGroup['groupid'];
					}
					break;
				case RSM_OTHER_TLD_GROUP:
					$data['allowedGroups'][RSM_OTHER_TLD_GROUP] = true;

					if ($data['filter_othertld_group']) {
						$included_groupids[$tldGroup['groupid']] = $tldGroup['groupid'];
					}
					else {
						$excluded_groupids[$tldGroup['groupid']] = $tldGroup['groupid'];
					}
					break;
				case RSM_TEST_GROUP:
					$data['allowedGroups'][RSM_TEST_GROUP] = true;

					if ($data['filter_test_group']) {
						$included_groupids[$tldGroup['groupid']] = $tldGroup['groupid'];
					}
					else {
						$excluded_groupids[$tldGroup['groupid']] = $tldGroup['groupid'];
					}
					break;
			}
		}

		if (!$selectedGroups) {
			show_error_message(_s('No permissions to referred "%1$s" group or it doesn\'t not exist.', RSM_TLDS_GROUP));
			require_once dirname(__FILE__).'/include/page_footer.php';
			exit;
		}

		// get TLDs
		$where_condition[] = dbConditionInt('hg.groupid', $selectedGroups);

		if (CUser::$userData['type'] != USER_TYPE_SUPER_ADMIN) {
			$userid = CWebUser::$data['userid'];
			$userGroups = getUserGroupsByUserId($userid);
			$where_condition[] = 'EXISTS ('.
				'SELECT NULL'.
				' FROM hosts_groups hgg'.
					' JOIN rights r'.
						' ON r.id=hgg.groupid'.
							' AND '.dbConditionInt('r.groupid', $userGroups).
				' WHERE h.hostid=hgg.hostid'.
				' GROUP BY hgg.hostid'.
				' HAVING MIN(r.permission)>='.PERM_READ.
			')';
		}

		$where_host_group = ' WHERE '.implode(' AND ', $where_condition);

		$where_host = '';
		if ($data['filter_search']) {
			$where_host = ' AND h.name LIKE ('.zbx_dbstr('%'.$data['filter_search'].'%').')';
		}

		$where_in = '';
		if ($included_groupids) {
			$where_in = ' AND '.dbConditionInt('hg.groupid', $included_groupids);
		}

		$where_not_in = '';
		if ($excluded_groupids) {
			$where_not_in = ' AND '.dbConditionInt('hg2.groupid', $excluded_groupids, true);
		}

		$host_count = (count($selectedGroups) >= 2) ? 2 : 1;

		$db_tlds = DBselect(
			'SELECT h.hostid, h.host, h.name'.
			' FROM hosts h'.
			' WHERE hostid IN ('.
				'SELECT hg.hostid from hosts_groups hg'.$where_host_group.
				' GROUP BY hg.hostid HAVING COUNT(hg.hostid)>='.$host_count.')'.
				$where_host
		);

		if ($db_tlds) {
			$hostIds = [];
			while ($db_tld = DBfetch($db_tlds)) {
				$hostids[] = $db_tld['hostid'];

				$data['tld'][$DB['SERVERS'][$key]['NR'].$db_tld['hostid']] = [
					'hostid' => $db_tld['hostid'],
					'host' => $db_tld['host'],
					'name' => $db_tld['name'],
					'server' => $DB['SERVERS'][$key]['NAME'],
					'url' => $DB['SERVERS'][$key]['URL'],
					'db' => $key
				];
			}

			$hostGroups = API::HostGroup()->get(array(
				'output' => ['groupid', 'name'],
				'selectHosts' => ['hostid'],
				'hostids' => $hostids,
				'groupids' => $included_groupids
			));

			foreach ($hostGroups as $hostGroup) {
				foreach ($hostGroup['hosts'] as $hosts_array) {
					if (array_key_exists($DB['SERVERS'][$key]['NR'].$hosts_array['hostid'], $data['tld'])) {
						$data['tld'][$DB['SERVERS'][$key]['NR'].$hosts_array['hostid']]['type'] = $hostGroup['name'];
					}
				}
			}
		}
	}
	else {
		// get "TLDs" groupId
		$tldGroups = API::HostGroup()->get(array(
			'output' => array('groupid', 'name'),
			'filter' => array(
				'name' => array(RSM_TLDS_GROUP, RSM_CC_TLD_GROUP, RSM_G_TLD_GROUP, RSM_OTHER_TLD_GROUP, RSM_TEST_GROUP)
			)
		));

		foreach ($tldGroups as $tldGroup) {
			switch ($tldGroup['name']) {
				case RSM_CC_TLD_GROUP:
					$data['allowedGroups'][RSM_CC_TLD_GROUP] = true;
					break;
				case RSM_G_TLD_GROUP:
					$data['allowedGroups'][RSM_G_TLD_GROUP] = true;
					break;
				case RSM_OTHER_TLD_GROUP:
					$data['allowedGroups'][RSM_OTHER_TLD_GROUP] = true;
					break;
				case RSM_TEST_GROUP:
					$data['allowedGroups'][RSM_TEST_GROUP] = true;
					break;
			}
		}
	}
}

foreach ($data['tld'] as $key => $value) {
	if (!array_key_exists('type', $data['tld'][$key])) {
		unset($data['tld'][$key]);
	}
}

if ($data['tld']) {
	order_result($data['tld'], 'name');
	$data['sid'] = CWebUser::getSessionCookie();
}

if ($no_history) {
	$data['paging'] = getPagingLine($data['tld'], ZBX_SORT_UP, new CUrl());
}

$tlds_by_server = [];
foreach ($data['tld'] as $tld) {
	$tlds_by_server[$tld['db']][$tld['hostid']] = $tld['host'];
}

foreach ($tlds_by_server as $key => $hosts) {
	multiDBconnect($DB['SERVERS'][$key], $error);

	$itemIds = [];
	$filter_slv = [];

	if ($hosts) {
		// get items
		$item_keys = [RSM_SLV_DNS_ROLLWEEK, RSM_SLV_DNSSEC_ROLLWEEK, RSM_SLV_RDDS_ROLLWEEK, RSM_SLV_EPP_ROLLWEEK];

		$items = [];
		$db_items = DBselect(
			'SELECT i.itemid, i.hostid, i.key_'.
			' FROM items i'.
			' WHERE '.dbConditionString('i.key_', $item_keys)
		);

		$i = 0;
		$history_union = [];
		while ($item = DBfetch($db_items)) {
			$items[$item['itemid']] = [
				'itemid' => $item['itemid'],
				'hostid' => $item['hostid'],
				'key_' => $item['key_']
			];

			$history_union[] = 'SELECT itemid, value'.
				' FROM history'.
				' WHERE itemid = '.$item['itemid'].
					' AND clock = (SELECT MAX(clock) FROM history WHERE itemid = '.$item['itemid'].')';

			if ($i == 3) {
				$db_histories = DBselect(
					'SELECT itemid, value'.
					' FROM ('.implode(' UNION ', $history_union).') result'
				);

				while ($history = DBfetch($db_histories)) {
					$items[$history['itemid']]['lastvalue'] = $history['value'];
				}

				$history_union = [];
				$i = 0;
			}
			else {
				$i++;
			}
		}

		$avail_items = API::Item()->get(array(
			'hostids' => array_keys($hosts),
			'filter' => array(
				'key_' => array(
					RSM_SLV_DNS_AVAIL, RSM_SLV_DNSSEC_AVAIL, RSM_SLV_RDDS_AVAIL, RSM_SLV_EPP_AVAIL
				)
			),
			'output' => array('itemid', 'hostid', 'key_'),
			'preservekeys' => true
		));

		if ($items) {
			foreach ($items as $item) {
				// service type filter
				if ($data['filter_slv'] !== '' && ($data['filter_slv'] > $item['lastvalue']
						|| ($data['filter_slv'] == SLA_MONITORING_SLV_FILTER_NON_ZERO
							&& $item['lastvalue'] == 0))
						&& (($data['filter_dns'] && $item['key_'] == RSM_SLV_DNS_ROLLWEEK)
							|| ($data['filter_dnssec'] && $item['key_'] == RSM_SLV_DNSSEC_ROLLWEEK)
							|| ($data['filter_rdds'] && $item['key_'] == RSM_SLV_RDDS_ROLLWEEK)
							|| ($data['filter_epp'] && $item['key_'] == RSM_SLV_EPP_ROLLWEEK)
						&& !array_key_exists($item['hostid'], $filter_slv))) {
					$filter_slv[$item['hostid']] = false;
				}
				elseif ($data['filter_slv'] !== '') {
					$filter_slv[$item['hostid']] = true;
				}

				if (!array_key_exists($DB['SERVERS'][$key]['NR'].$item['hostid'], $data['tld'])) {
					continue;
				}

				if ($item['key_'] == RSM_SLV_DNS_ROLLWEEK) {
					$data['tld'][$DB['SERVERS'][$key]['NR'].$item['hostid']][RSM_DNS]['itemid'] = $item['itemid'];
					$data['tld'][$DB['SERVERS'][$key]['NR'].$item['hostid']][RSM_DNS]['lastvalue'] = sprintf(
						'%.3f',
						$item['lastvalue']
					);
					$data['tld'][$DB['SERVERS'][$key]['NR'].$item['hostid']][RSM_DNS]['trigger'] = false;
				}
				elseif ($item['key_'] == RSM_SLV_DNSSEC_ROLLWEEK) {
					$data['tld'][$DB['SERVERS'][$key]['NR'].$item['hostid']][RSM_DNSSEC]['itemid'] = $item['itemid'];
					$data['tld'][$DB['SERVERS'][$key]['NR'].$item['hostid']][RSM_DNSSEC]['lastvalue'] = sprintf(
						'%.3f',
						$item['lastvalue']
					);
					$data['tld'][$DB['SERVERS'][$key]['NR'].$item['hostid']][RSM_DNSSEC]['trigger'] = false;
				}
				elseif ($item['key_'] == RSM_SLV_RDDS_ROLLWEEK) {
					$data['tld'][$DB['SERVERS'][$key]['NR'].$item['hostid']][RSM_RDDS]['itemid'] = $item['itemid'];
					$data['tld'][$DB['SERVERS'][$key]['NR'].$item['hostid']][RSM_RDDS]['lastvalue'] = sprintf(
						'%.3f',
						$item['lastvalue']
					);
					$data['tld'][$DB['SERVERS'][$key]['NR'].$item['hostid']][RSM_RDDS]['trigger'] = false;
				}
				elseif ($item['key_'] == RSM_SLV_EPP_ROLLWEEK) {
					$data['tld'][$DB['SERVERS'][$key]['NR'].$item['hostid']][RSM_EPP]['itemid'] = $item['itemid'];
					$data['tld'][$DB['SERVERS'][$key]['NR'].$item['hostid']][RSM_EPP]['lastvalue'] = sprintf(
						'%.3f',
						$item['lastvalue']
					);
					$data['tld'][$DB['SERVERS'][$key]['NR'].$item['hostid']][RSM_EPP]['trigger'] = false;
				}
			}

			foreach ($avail_items as $item) {
				if ($item['key_'] == RSM_SLV_DNS_AVAIL) {
					$data['tld'][$DB['SERVERS'][$key]['NR'].$item['hostid']][RSM_DNS]['availItemId'] = $item['itemid'];
					$itemIds[$item['itemid']] = true;
				}
				elseif ($item['key_'] == RSM_SLV_DNSSEC_AVAIL) {
					$data['tld'][$DB['SERVERS'][$key]['NR'].$item['hostid']][RSM_DNSSEC]['availItemId'] = $item['itemid'];
					$itemIds[$item['itemid']] = true;
				}
				elseif ($item['key_'] == RSM_SLV_RDDS_AVAIL) {
					$data['tld'][$DB['SERVERS'][$key]['NR'].$item['hostid']][RSM_RDDS]['availItemId'] = $item['itemid'];
					$itemIds[$item['itemid']] = true;
				}
				elseif ($item['key_'] == RSM_SLV_EPP_AVAIL) {
					$data['tld'][$DB['SERVERS'][$key]['NR'].$item['hostid']][RSM_EPP]['availItemId'] = $item['itemid'];
					$itemIds[$item['itemid']] = true;
				}
			}

			$items += $avail_items;

			if ($data['filter_slv']) {
				foreach ($filter_slv as $filtred_hostid => $value) {
					if ($value === false) {
						unset($data['tld'][$DB['SERVERS'][$key]['NR'].$filtred_hostid], $hosts[$filtred_hostid]);
					}
				}
			}

			if ($hosts) {
				// disabled services check
				$templateName = [];
				foreach ($hosts as $hostid => $host) {
					$templateName[$hostid] = 'Template '.$host;
					$hostIdByTemplateName['Template '.$host] = $hostid;
				}

				$templates = API::Template()->get(array(
					'output' => array('templateid', 'host'),
					'filter' => array(
						'host' => $templateName
					),
					'preservekeys' => true
				));

				$templateIds = array_keys($templates);

				foreach ($templates as $template) {
					$templateName[$template['host']] = $template['templateid'];
				}

				$templateMacros = API::UserMacro()->get(array(
					'output' => API_OUTPUT_EXTEND,
					'hostids' => $templateIds,
					'filter' => array(
						'macro' => array(RSM_TLD_DNSSEC_ENABLED, RSM_TLD_EPP_ENABLED, RSM_TLD_RDDS43_ENABLED,
							RSM_TLD_RDDS80_ENABLED, RSM_TLD_RDAP_ENABLED, RSM_TLD_RDDS_ENABLED
						)
					)
				));

				foreach ($templateMacros as $templateMacro) {
					$current_hostid = $hostIdByTemplateName[$templates[$templateMacro['hostid']]['host']];
					if ($templateMacro['macro'] == RSM_TLD_DNSSEC_ENABLED || $templateMacro['macro'] == RSM_TLD_EPP_ENABLED) {
						if ($templateMacro['value'] == 0) {
							if ($templateMacro['macro'] == RSM_TLD_DNSSEC_ENABLED) {
								$service_type = RSM_DNSSEC;
							}
							else {
								$service_type = RSM_EPP;
							}

							// Unset disabled services
							if (isset($data['tld'][$DB['SERVERS'][$key]['NR'].$current_hostid][$service_type])) {
								unset($itemIds[$data['tld'][$DB['SERVERS'][$key]['NR'].$current_hostid][$service_type]['availItemId']]);
								unset($data['tld'][$DB['SERVERS'][$key]['NR'].$current_hostid][$service_type]);
							}
						}
					}
					else {
						if (array_key_exists(RSM_RDDS, $data['tld'][$DB['SERVERS'][$key]['NR'].$current_hostid])) {
							$data['tld'][$DB['SERVERS'][$key]['NR'].$current_hostid][RSM_RDDS]['subservices'][$templateMacro['macro']] = $templateMacro['value'];
						}
					}
				}

				foreach ($hosts as $hostid => $host) {
					$tld_key = $DB['SERVERS'][$key]['NR'].$hostid;
					$tld = $data['tld'][$tld_key];
					if (array_key_exists(RSM_RDDS, $tld)) {
						if (!array_key_exists('subservices', $tld[RSM_RDDS]) || !array_sum($tld[RSM_RDDS]['subservices'])) {
							unset($itemIds[$tld[RSM_RDDS]['availItemId']]);
							unset($tld[RSM_RDDS]);
						}
					}
				}

				// get triggers
				$triggers = API::Trigger()->get(array(
					'output' => array('triggerid', 'value'),
					'selectItems' => ['itemid'],
					'itemids' => array_keys($itemIds)
				));

				foreach ($triggers as $trigger) {
					if ($trigger['value'] == TRIGGER_VALUE_TRUE) {
						$trItem = $trigger['items'][0]['itemid'];
						$problem = [];

						if (!array_key_exists($DB['SERVERS'][$key]['NR'].$items[$trItem]['hostid'], $data['tld'])) {
							continue;
						}

						switch ($items[$trItem]['key_']) {
							case RSM_SLV_DNS_AVAIL:
								$data['tld'][$DB['SERVERS'][$key]['NR'].$items[$trItem]['hostid']][RSM_DNS]['incident'] = getLastEvent(
									$trigger['triggerid']
								);
								if ($data['tld'][$DB['SERVERS'][$key]['NR'].$items[$trItem]['hostid']][RSM_DNS]['incident']) {
									$data['tld'][$DB['SERVERS'][$key]['NR'].$items[$trItem]['hostid']][RSM_DNS]['trigger'] = true;
								}
								break;
							case RSM_SLV_DNSSEC_AVAIL:
								$data['tld'][$DB['SERVERS'][$key]['NR'].$items[$trItem]['hostid']][RSM_DNSSEC]['incident'] = getLastEvent(
									$trigger['triggerid']
								);
								if ($data['tld'][$DB['SERVERS'][$key]['NR'].$items[$trItem]['hostid']][RSM_DNSSEC]['incident']) {
									$data['tld'][$DB['SERVERS'][$key]['NR'].$items[$trItem]['hostid']][RSM_DNSSEC]['trigger'] = true;
								}
								break;
							case RSM_SLV_RDDS_AVAIL:
								$data['tld'][$DB['SERVERS'][$key]['NR'].$items[$trItem]['hostid']][RSM_RDDS]['incident'] = getLastEvent(
									$trigger['triggerid']
								);
								if ($data['tld'][$DB['SERVERS'][$key]['NR'].$items[$trItem]['hostid']][RSM_RDDS]['incident']) {
									$data['tld'][$DB['SERVERS'][$key]['NR'].$items[$trItem]['hostid']][RSM_RDDS]['trigger'] = true;
								}
								break;
							case RSM_SLV_EPP_AVAIL:
								$data['tld'][$DB['SERVERS'][$key]['NR'].$items[$trItem]['hostid']][RSM_EPP]['incident'] = getLastEvent(
									$trigger['triggerid']
								);
								if ($data['tld'][$DB['SERVERS'][$key]['NR'].$items[$trItem]['hostid']][RSM_EPP]['incident']) {
									$data['tld'][$DB['SERVERS'][$key]['NR'].$items[$trItem]['hostid']][RSM_EPP]['trigger'] = true;
								}
								break;
						}
					}
				}
			}
		}
	}
}

unset($DB['DB']);
$DB = $master;
DBconnect($error);

if ($data['filter_status']) {
	foreach ($data['tld'] as $key => $tld) {
		if ($data['filter_status'] == 1) {
			if ((!array_key_exists(RSM_DNS, $tld) || !$tld[RSM_DNS]['trigger'])
					&& (!array_key_exists(RSM_DNSSEC, $tld) || !$tld[RSM_DNSSEC]['trigger'])
					&& (!array_key_exists(RSM_RDDS, $tld) || !$tld[RSM_RDDS]['trigger'])
					&& (!array_key_exists(RSM_EPP, $tld) || !$tld[RSM_EPP]['trigger'])) {
				unset($data['tld'][$key]);
			}
		}
		elseif ($data['filter_status'] == 2) {
			if (array_key_exists(RSM_DNS, $tld) && array_key_exists(RSM_DNSSEC, $tld)
					&& array_key_exists(RSM_RDDS, $tld) && array_key_exists(RSM_EPP, $tld)) {
				unset($data['tld'][$key]);
			}
		}
	}
}

if (!$no_history) {
	$data['paging'] = getPagingLine($data['tld'], ZBX_SORT_UP, new CUrl());
}

$rsmView = new CView('rsm.rollingweekstatus.list', $data);
$rsmView->render();
$rsmView->show();

require_once dirname(__FILE__).'/include/page_footer.php';
