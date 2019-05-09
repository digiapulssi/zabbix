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
$page['type'] = detect_page_type(hasRequest('export') ? PAGE_TYPE_XML : PAGE_TYPE_HTML);

require_once dirname(__FILE__).'/include/page_header.php';

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields = [
	'export' =>			[T_ZBX_STR, O_OPT,  null,	null,		null],
	'filter_set' =>		[T_ZBX_STR, O_OPT,  null,	null,		null],
	'filter_search' =>	[T_ZBX_STR, O_OPT,  null,	null,		null],
	'filter_year' =>	[T_ZBX_INT, O_OPT,  null,	null,		null],
	'filter_month' =>	[T_ZBX_INT, O_OPT,  null,	null,		null]
];

check_fields($fields);

$data = [
	'tld' => [],
	'url' => '',
	'sid' => CWebUser::getSessionCookie(),
	'filter_search' => getRequest('filter_search'),
	'filter_year' => (int) getRequest('filter_year', date('Y')),
	'filter_month' => (int) getRequest('filter_month', date('n'))
];

/*
 * Filter
 */
if ($data['filter_year'] == date('Y') && $data['filter_month'] > date('n')) {
	show_error_message(_('Incorrect report period.'));
}
elseif ($data['filter_search']) {
	$error = '';
	$master = $DB;

	foreach ($DB['SERVERS'] as $server_nr => $server) {
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
		$data['server_nr'] = $server_nr;

		break;
	}
}

if ($data['tld']) {
	// Searching for pregenerated SLA report in database.
	$report_row = DB::find('sla_reports', [
		'hostid'	=> $data['tld']['hostid'],
		'month'		=> $data['filter_month'],
		'year'		=> $data['filter_year']
	]);
	$report_row = reset($report_row);

	if (!$report_row) {
		// Include file by build in autoloader.
		new CSlaReport();

		if (!class_exists('CSlaReport')) {
			show_error_message(_('SLA Report generation file is missing.'));
		}
		else {
			$report_row = CSlaReport::generate($data['server_nr'], [$data['tld']['host']], $data['filter_year'],
				$data['filter_month']
			);

			if ($report_row === null) {
				show_error_message(_s('Unable to generate XML report: %1$s', CSlaReport::$error));
				if ($data['filter_year'] == date('Y') && $data['filter_month'] == date('n')) {
					show_error_message(_('Please try again after 5 minutes.'));
				}
			}
			else {
				$report_row = reset($report_row);
				$report_row += ['year' => $data['filter_year'], 'month' => $data['filter_month']];
			}
		}
	}

	// SLA Report download as XML file
	if ($report_row && hasRequest('export')) {
		header('Content-Type: text/xml');
		header(sprintf('Content-disposition: attachment; filename="%s-%d-%s.xml"',
			$data['tld']['host'], $report_row['year'], getMonthCaption($report_row['month']))
		);
		echo $report_row['report'];

		exit;
	}

	if ($report_row && array_key_exists('report', $report_row)) {
		$xml = new SimpleXMLElement($report_row['report']);
		$details = $xml->attributes();

		$ns_items = [];
		foreach ($xml->DNS->nsAvailability as $ns_item) {
			$attrs = $ns_item->attributes();
			$ns_items[] = [
				'from'	=> (int) $attrs->from,
				'to'	=> (int) $attrs->to,
				'host'	=> (string) $attrs->hostname,
				'ip'	=> (string) $attrs->ipAddress,
				'slv'	=> (string) $ns_item[0],
				'slr'	=> (string) $attrs->downtimeSLR
			];
		}

		$data += [
			'ns_items'	=> $ns_items,
			'details'	=> [
				'from'		=> (int) $details->reportPeriodFrom,
				'to'		=> (int) $details->reportPeriodTo,
				'generated'	=> (int) $details->generationDateTime
			],
			'slv_dns_downtime'			=> (string) $xml->DNS->serviceAvailability,
			'slr_dns_downtime'			=> (string) $xml->DNS->serviceAvailability->attributes()->downtimeSLR,

			'slv_dns_tcp_pfailed'		=> (string) $xml->DNS->rttTCP,
			'slr_dns_tcp_pfailed'		=> (String) $xml->DNS->rttTCP->attributes()->percentageSLR,
			'slr_dns_tcp_pfailed_ms'	=> (string) $xml->DNS->rttTCP->attributes()->rttSLR,

			'slv_dns_udp_pfailed'		=> (string) $xml->DNS->rttUDP,
			'slr_dns_udp_pfailed'		=> (string) $xml->DNS->rttUDP->attributes()->percentageSLR,
			'slr_dns_udp_pfailed_ms'	=> (string) $xml->DNS->rttUDP->attributes()->rttSLR,

			'slv_rdds_downtime'			=> (string) $xml->RDDS->serviceAvailability,
			'slr_rdds_downtime'			=> (string) $xml->RDDS->serviceAvailability->attributes()->downtimeSLR,

			'slv_rdds_rtt_downtime'		=> (string) $xml->RDDS->rtt,
			'slr_rdds_rtt_downtime'		=> (string) $xml->RDDS->rtt->attributes()->percentageSLR,
			'slr_rdds_rtt_downtime_ms'	=> (string) $xml->RDDS->rtt->attributes()->rttSLR
		];

		if ($data['tld']['host'] !== strval($details->id)) {
			show_error_message(_('Incorrect report tld value.'));
		}
	}

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
