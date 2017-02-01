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


require_once dirname(__FILE__).'/js/rsm.incidents.list.js.php';

$widget = (new CWidget())->setTitle(_('Incidents'));

// filter
$filter = (new CFilter('web.rsm.incidents.filter.state'))
	->addVar('filter_set', 1);
$filterColumn1 = new CFormList();
$filterColumn2 = new CFormList();
$filterColumn3 = new CFormList();
$filterColumn4 = new CFormList();

$filterColumn1
	->addRow(_('TLD'), (new CTextBox('filter_search', $this->data['filter_search']))
		->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
		->setAttribute('autocomplete', 'off')
	);
$filterColumn2
	->addRow(_('From'), createDateSelector('filter_from', $this->data['filter_from']));
$filterColumn3
	->addRow(_('To'), createDateSelector('filter_to', $this->data['filter_to']));
$filterColumn4
	->addRow((new CButton('rollingweek', _('Rolling week')))->addClass(ZBX_STYLE_BTN_LINK));

$filter
	->addColumn($filterColumn1)
	->addColumn($filterColumn2)
	->addColumn($filterColumn3)
	->addColumn($filterColumn4);

$widget->addItem($filter);



// create form
$form = (new CForm())
	->setName('incidents');

$table = (new CTableInfo())
	->setHeader([
		_('TLD'),
		_('Type'),
		_('DNS (4Hrs)'),
		_('DNSSEC (4Hrs)'),
		_('RDDS (24Hrs)'),
		_('EPP (24Hrs)')
]);


if (isset($this->data['tld'])) {
	$serverTime = time() - RSM_ROLLWEEK_SHIFT_BACK;
	$from = date('YmdHis', $serverTime - $this->data['rollWeekSeconds']);
	$till = date('YmdHis', $serverTime);
	foreach ($this->data['tld'] as $key => $tld) {
		// DNS
		if (isset($tld[RSM_DNS])) {
			if ($tld[RSM_DNS]['trigger']) {
				if ($tld[RSM_DNS]['incident'] && isset($tld[RSM_DNS]['availItemId'])
						&& isset($tld[RSM_DNS]['itemid'])) {
					$dnsStatus =  new CLink(
						new CDiv(null, 'service-icon status_icon_extra iconrollingweekfail cell-value pointer'),
						'rsm.incidentdetails.php?host='.$tld['host'].'&eventid='.$tld[RSM_DNS]['incident'].
							'&slvItemId='.$tld[RSM_DNS]['itemid'].'&filter_from='.$from.'&filter_to='.$till.
							'&availItemId='.$tld[RSM_DNS]['availItemId'].'&filter_set=1'
					);
				}
				else {
					$dnsStatus =  new CDiv(null,
						'service-icon status_icon_extra iconrollingweekfail cell-value pointer'
					);
				}
			}
			else {
				$dnsStatus =  new CDiv(null, 'service-icon status_icon_extra iconrollingweekok cell-value');
			}

			$dnsValue = ($tld[RSM_DNS]['lastvalue'] > 0)
				? new CLink(
					$tld[RSM_DNS]['lastvalue'].'%',
					'rsm.incidents.php?filter_set=1&filter_rolling_week=1&type='.RSM_DNS.'&host='.$tld['host'],
					'first-cell-value'
				)
				: new CSpan('0.000%', 'first-cell-value');

			$dnsGraph = ($tld[RSM_DNS]['lastvalue'] > 0)
				? new CLink('graph', 'history.php?action=showgraph&period='.$this->data['rollWeekSeconds'].'&itemid='.
						$tld[RSM_DNS]['itemid'], 'cell-value')
				: null;
			$dns = array(new CSpan($dnsValue, 'right'), $dnsStatus, $dnsGraph);
		}
		else {
			$dns = new CDiv(null, 'service-icon status_icon_extra iconrollingweekdisabled disabled-service');
			$dns->setHint('Incorrect TLD configuration.', '', 'on');
		}

		// DNSSEC
		if (isset($tld[RSM_DNSSEC])) {
			if ($tld[RSM_DNSSEC]['trigger']) {
				if ($tld[RSM_DNSSEC]['incident'] && isset($tld[RSM_DNSSEC]['availItemId'])
						&& isset($tld[RSM_DNSSEC]['itemid'])) {
					$dnssecStatus =  new CLink(
						new CDiv(null, 'service-icon status_icon_extra iconrollingweekfail cell-value pointer'),
						'rsm.incidentdetails.php?host='.$tld['host'].'&eventid='.$tld[RSM_DNSSEC]['incident'].
							'&slvItemId='.$tld[RSM_DNSSEC]['itemid'].'&filter_from='.$from.'&filter_to='.$till.
							'&availItemId='.$tld[RSM_DNSSEC]['availItemId'].'&filter_set=1'
					);
				}
				else {
					$dnssecStatus =  new CDiv(null,
						'service-icon status_icon_extra iconrollingweekfail cell-value pointer'
					);
				}
			}
			else {
				$dnssecStatus =  new CDiv(null, 'service-icon status_icon_extra iconrollingweekok cell-value');
			}

			$dnssecValue = ($tld[RSM_DNSSEC]['lastvalue'] > 0)
				? new CLink(
					$tld[RSM_DNSSEC]['lastvalue'].'%',
					'rsm.incidents.php?filter_set=1&filter_rolling_week=1&type='.RSM_DNSSEC.'&host='.$tld['host'],
					'first-cell-value'
				)
				: new CSpan('0.000%', 'first-cell-value');

			$dnssecGraph = ($tld[RSM_DNSSEC]['lastvalue'] > 0)
				? new CLink('graph', 'history.php?action=showgraph&period='.$this->data['rollWeekSeconds'].'&itemid='.
						$tld[RSM_DNSSEC]['itemid'], 'cell-value'
				)
				: null;
			$dnssec =  array(new CSpan($dnssecValue, 'right'), $dnssecStatus, $dnssecGraph);
		}
		else {
			$dnssec = new CDiv(null, 'service-icon status_icon_extra iconrollingweekdisabled disabled-service');
			$dnssec->setHint('DNSSEC is disabled.', '', 'on');
		}

		// RDDS
		if (isset($tld[RSM_RDDS])) {
			if ($tld[RSM_RDDS]['trigger']) {
				if ($tld[RSM_RDDS]['incident'] && isset($tld[RSM_RDDS]['availItemId'])
						&& isset($tld[RSM_RDDS]['itemid'])) {
					$rddsStatus =  new CLink(
						new CDiv(null, 'service-icon status_icon_extra iconrollingweekfail cell-value pointer'),
						'rsm.incidentdetails.php?host='.$tld['host'].'&eventid='.$tld[RSM_RDDS]['incident'].
							'&slvItemId='.$tld[RSM_RDDS]['itemid'].'&filter_from='.$from.'&filter_to='.$till.
							'&availItemId='.$tld[RSM_RDDS]['availItemId'].'&filter_set=1'
					);
				}
				else {
					$rddsStatus =  new CDiv(null,
						'service-icon status_icon_extra iconrollingweekfail cell-value pointer'
					);
				}
			}
			else {
				$rddsStatus =  new CDiv(null, 'service-icon status_icon_extra iconrollingweekok cell-value');
			}

			$rddsValue = ($tld[RSM_RDDS]['lastvalue'] > 0)
				? new CLink(
					$tld[RSM_RDDS]['lastvalue'].'%',
					'rsm.incidents.php?filter_set=1&filter_rolling_week=1&type='.RSM_RDDS.'&host='.$tld['host'],
					'first-cell-value'
				)
				: new CSpan('0.000%', 'first-cell-value');

			$rddsGraph = ($tld[RSM_RDDS]['lastvalue'] > 0)
				? new CLink('graph', 'history.php?action=showgraph&period='.$this->data['rollWeekSeconds'].'&itemid='.
						$tld[RSM_RDDS]['itemid'], 'cell-value')
				: null;

			$ok_rdds_services = [];
			if (array_key_exists(RSM_TLD_RDDS43_ENABLED, ($tld[RSM_RDDS]['subservices']))
					&& $tld[RSM_RDDS]['subservices'][RSM_TLD_RDDS43_ENABLED] == 1) {
				$ok_rdds_services[] = 'RDDS43';
			}
			if (array_key_exists(RSM_TLD_RDDS80_ENABLED, ($tld[RSM_RDDS]['subservices']))
					&& $tld[RSM_RDDS]['subservices'][RSM_TLD_RDDS80_ENABLED] == 1) {
				$ok_rdds_services[] = 'RDDS80';
			}
			if (array_key_exists(RSM_TLD_RDAP_ENABLED, ($tld[RSM_RDDS]['subservices']))
					&& $tld[RSM_RDDS]['subservices'][RSM_TLD_RDAP_ENABLED] == 1) {
				$ok_rdds_services[] = 'RDAP';
			}

			$rdds_services = implode('/', $ok_rdds_services);
			$rdds =  array(new CSpan($rddsValue, 'right'), $rddsStatus, $rddsGraph, SPACE,
				new CSpan($rdds_services, 'bold')
			);
		}
		else {
			$rdds = new CDiv(null, 'service-icon status_icon_extra iconrollingweekdisabled disabled-service');
			$rdds->setHint('RDDS is disabled.', '', 'on');
		}

		// EPP
		if (isset($tld[RSM_EPP])) {
			if ($tld[RSM_EPP]['trigger']) {
				if ($tld[RSM_EPP]['incident'] && isset($tld[RSM_EPP]['availItemId'])
						&& isset($tld[RSM_EPP]['itemid'])) {
					$eppStatus =  new CLink(
						new CDiv(null, 'service-icon status_icon_extra iconrollingweekfail cell-value pointer'),
						'rsm.incidentdetails.php?host='.$tld['host'].'&eventid='.$tld[RSM_EPP]['incident'].
							'&slvItemId='.$tld[RSM_EPP]['itemid'].'&filter_from='.$from.'&filter_to='.$till.
							'&availItemId='.$tld[RSM_EPP]['availItemId'].'&filter_set=1'
					);
				}
				else {
					$eppStatus =  new CDiv(null,
						'service-icon status_icon_extra iconrollingweekfail cell-value pointer'
					);
				}
			}
			else {
				$eppStatus =  new CDiv(null, 'service-icon status_icon_extra iconrollingweekok cell-value');
			}

			$eppValue = ($tld[RSM_EPP]['lastvalue'] > 0)
				? new CLink(
					$tld[RSM_EPP]['lastvalue'].'%',
					'rsm.incidents.php?filter_set=1&filter_rolling_week=1&type='.RSM_EPP.'&host='.$tld['host'],
					'first-cell-value'
				)
				: new CSpan('0.000%', 'first-cell-value');

			$eppGraph = ($tld[RSM_EPP]['lastvalue'] > 0)
				? new CLink('graph', 'history.php?action=showgraph&period='.$this->data['rollWeekSeconds'].'&itemid='.
					$tld[RSM_EPP]['itemid'], 'cell-value')
				: null;
			$epp =  array(new CSpan($eppValue, 'right'), $eppStatus, $eppGraph);
		}
		else {
			$epp = new CDiv(null, 'service-icon status_icon_extra iconrollingweekdisabled disabled-service');
			$epp->setHint('EPP is disabled.', '', 'on');
		}
		$row = array(
			$tld['name'],
			$tld['type'],
			$dns,
			$dnssec,
			$rdds,
			$epp
		);

		$table->addRow($row);
	}
}

$form->addItem([
	$table
]);
// append form to widget
$widget->addItem($form);

return $widget;
