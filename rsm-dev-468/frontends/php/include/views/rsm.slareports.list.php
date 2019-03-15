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


$widget = (new CWidget())->setTitle(_('SLA report'));

$months = range(1, 12);
$years = range(SLA_MONITORING_START_YEAR, date('Y', time()));

$widget->addItem(
	(new CFilter('web.rsm.slareports.filter.state'))->addColumn(
		(new CFormList())
			->addVar('filter_set', 1)
			->addRow(_('TLD'), (new CTextBox('filter_search', $data['filter_search']))
				->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
				->setAttribute('autocomplete', 'off')
			)
			->addRow(_('Period'), [
				new CComboBox('filter_month', $data['filter_month'], null, array_combine($months,
					array_map('getMonthCaption', $months))),
				SPACE,
				new CComboBox('filter_year', $data['filter_year'], null, array_combine($years, $years))
			])
	)
);

$table = (new CTableInfo())->setHeader([
	_('Service'),
	_('FQDN and IP'),
	_('From'),
	_('To'),
	_('SLV'),
	_('Monthly SLR')
]);

if (!$data['tld']) {
	return $widget->addItem($table);
}

// TLD details.
$widget->additem((new CDiv())
	->addClass(ZBX_STYLE_TABLE_FORMS_CONTAINER)
	->addItem([
		bold(_s('Period: %1$s - %2$s', date('Y/m/d H:i:s', $data['start_time']),
			date('Y/m/d H:i:s', $data['end_time']))), BR(),
		bold(_s('Generation time: %1$s', gmdate('dS F Y, H:i:s e', time()))), BR(),
		bold(_s('TLD: %1$s', $data['tld']['name'])), BR(),
		bold(_('Server: ')), new CLink($data['server'], $data['rolling_week_url'])
	])
);

// DNS Server Availability.
$table->addRow([
		bold(_('DNS Server Availability')),
		'-',
		'-',
		'-',
		_s('%d (minutes of downtime)', $data['values'][RSM_SLV_DNS_DOWNTIME]['slv']),
		_s('%d (minutes of downtime)', $data['macro'][RSM_SLV_NS_AVAIL])
	],
	($data['values'][RSM_SLV_DNS_DOWNTIME]['slv'] > $data['macro'][RSM_SLV_NS_AVAIL]) ? 'red-bg' : null
);

// DNS Name Server availability.
foreach ($data['values'] as $item) {
	if (!array_key_exists('nsitem', $item)) {
		continue;
	}

	$table->addRow([
			_('DNS Name Server availability'),
			implode(', ', array_filter([$item['host'], $item['ip']], 'strlen')),
			gmdate('Y-m-d H:i:s', $item['from']),
			gmdate('Y-m-d H:i:s', $item['to']),
			_s('%1$s (minutes of downtime)', $item['slv']),
			_s('%1$s (minutes of downtime)', $data['macro'][RSM_SLV_NS_AVAIL])
		],
		($item['slv'] > $data['macro'][RSM_SLV_NS_AVAIL]) ? 'red-bg' : null
	);
}

// TCP/UDP DNS Resolution.
$table
	->addRow([
			_('DNS TCP Resolution RTT'),
			'-',
			'-',
			'-',
			_s('%1$s %% (queries <= %2$s ms)', $data['values'][RSM_SLV_DNS_TCP_NS_TESTS_PFAILED]['slv'],
				$data['macro'][RSM_DNS_TCP_RTT_LOW]
			),
			_s('<= %1$s ms, for at least %2$s %% of queries', $data['macro'][RSM_DNS_TCP_RTT_LOW],
				$data['macro'][RSM_SLV_DNS_TCP_RTT]
			)
		],
		($data['values'][RSM_SLV_DNS_TCP_NS_TESTS_PFAILED]['slv'] > $data['macro'][RSM_SLV_DNS_TCP_RTT])
			? 'red-bg' : null
	)->addRow([
			_('DNS UDP Resolutioin RTT'),
			'-',
			'-',
			'-',
			_s('%1$s %% (queries <= %2$s ms)', $data['values'][RSM_SLV_DNS_UDP_NS_TESTS_PFAILED]['slv'],
				$data['macro'][RSM_DNS_TCP_RTT_LOW]
			),
			_s('<= %1$s ms, for at least %2$s %% of queries', $data['macro'][RSM_DNS_TCP_RTT_LOW],
				$data['macro'][RSM_SLV_DNS_TCP_RTT]
			)
		],
		($data['values'][RSM_SLV_DNS_UDP_NS_TESTS_PFAILED]['slv'] > $data['macro'][RSM_SLV_DNS_TCP_RTT])
			? 'red-bg' : null
);

// RDDS Availability.
if (array_key_exists(RSM_SLV_RDDS_DOWNTIME, $data['values'])) {
	$table->addRow([
			bold(_('RDDS Availability')),
			'-',
			'-',
			'-',
			_s('%1$s (minutes of downtime)', $data['values'][RSM_SLV_RDDS_DOWNTIME]['slv']),
			_s('<= %1$s min of downtime', $data['macro'][RSM_DNS_UDP_RTT_LOW])
		],
		($data['values'][RSM_SLV_RDDS_DOWNTIME]['slv'] > $data['macro'][RSM_DNS_UDP_RTT_LOW])
			? 'red-bg' : null
	)->addRow([
			_('RDDS Query RTT'),
			'-',
			'-',
			'-',
			_s('%1$s %% (queries <= %2$s ms)', $data['values'][RSM_SLV_RDDS_UPD_PFAILED]['slv'],
				$data['macro'][RSM_RDDS_RTT_LOW]
			),
			_s('<= %1$s ms, for at least %2$s %% of the queries', $data['macro'][RSM_RDDS_RTT_LOW],
				$data['macro'][RSM_SLV_MACRO_RDDS_RTT]
			)
		],
		($data['values'][RSM_SLV_RDDS_UPD_PFAILED]['slv'] > $data['macro'][RSM_SLV_MACRO_RDDS_RTT])
			? 'red-bg' : null
	);
}

return $widget->addItem($table);
