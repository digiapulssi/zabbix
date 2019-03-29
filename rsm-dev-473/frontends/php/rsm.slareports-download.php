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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/classes/services/CSlaReport.php';

//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
$fields = [
	'tld' =>		[T_ZBX_STR, O_MAND,	P_SYS,	null,											null],
	'month' =>		[T_ZBX_INT, O_MAND,	null,	IN(implode(',', range(1, 12))),					null],
	'year' =>		[T_ZBX_INT, O_MAND,	null,	null,											null],
	'server' =>		[T_ZBX_INT, O_MAND, null,	IN(implode(',', array_keys($DB['SERVERS']))),	null],
	'source_url' => [T_ZBX_STR, O_MAND,	P_SYS,	null,											null]
];
check_fields($fields);

// Get TLD.
$tlds = API::Host()->get([
	'output' => ['hostid', 'host'],
	'tlds' => true,
	'filter' => [
		'host' => getRequest('tld')
	]
]);

if (($tld = reset($tlds)) === false) {
	require_once dirname(__FILE__).'/include/page_header.php';
	show_error_message(_s('No permissions to referred host "%1$s" or it does not exist!', getRequest('tld')));
	require_once dirname(__FILE__).'/include/page_footer.php';
	exit;
}

$month = (int) getRequest('month');
$year = (int) getRequest('year');
$server = (int) getRequest('server');

// Find pre-generated report in database.
$reports = DBselect(
	'SELECT *'.
	' FROM sla_reports'.
	' WHERE hostid='.zbx_dbstr($tld['hostid']).
		' AND year='.zbx_dbstr($year).
		' AND month='.zbx_dbstr($month)
);

if (($report = DBfetch($reports)) === false) {
	$report = CSlaReport::generate($server, [$tld['host']], $year, $month);

	if ($report !== null) {
		$report =  $report[0] + [
			'year' => $year,
			'month' => $month
		];
	}
	else {
		CSession::setValue('messageError', _s('Unable to generate XML report: "%1$s".', CSlaReport::$error));
		redirect(base64_decode(getRequest('source_url')));
	}
}

if ($report) {
	$file_name = sprintf('%s-%d-%s.xml', $report['host'], $report['year'], getMonthCaption($report['month']));

	header('Content-Type: text/xml');
	header("Content-disposition: attachment; filename=\"" . $file_name . "\"");
	echo $report['report'];
}
