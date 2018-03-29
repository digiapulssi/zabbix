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


require_once dirname(__FILE__).'/js/rsm.rollingweekstatus.list.js.php';

$widget = (new CWidget())->setTitle(_('TLD Rolling week status'));

// filter
$filter = (new CFilter('web.rsm.rollingweekstatus.filter.state'))
	->addVar('filter_set', 1)
	->addVar('checkAllServicesValue', 0)
	->addVar('checkAllGroupsValue', 0);
$filterColumn1 = new CFormList();
$filterColumn2 = new CFormList();
$filterColumn3 = new CFormList();

$filter_value = new CComboBox('filter_slv', isset($this->data['filter_slv']) ? $this->data['filter_slv'] : null);
$slvs = explode(',', $this->data['slv']);
$filter_value->addItem('', _('any'));
$filter_value->addItem(SLA_MONITORING_SLV_FILTER_NON_ZERO, _('non-zero'));

foreach ($slvs as $slv) {
	$filter_value->addItem($slv, $slv.'%');
}

// set disabled for no permission elements
// ccTLD's group
$filterCctldGroup = (new CCheckBox('filter_cctld_group'))->setChecked($this->data['filter_cctld_group']);
if (!$this->data['allowedGroups'][RSM_CC_TLD_GROUP]) {
	$filterCctldGroup->setAttribute('disabled', true);
}

// gTLD's group
$filterGtldGroup = (new CCheckBox('filter_gtld_group'))->setChecked($this->data['filter_gtld_group']);
if (!$this->data['allowedGroups'][RSM_G_TLD_GROUP]) {
	$filterGtldGroup->setAttribute('disabled', true);
}

// other TLD's group
$filterOtherGroup = (new CCheckBox('filter_othertld_group'))->setChecked($this->data['filter_othertld_group']);
if (!$this->data['allowedGroups'][RSM_OTHER_TLD_GROUP]) {
	$filterOtherGroup->setAttribute('disabled', true);
}

// test TLD's group
$filterTestGroup = (new CCheckBox('filter_test_group'))->setChecked($this->data['filter_test_group']);
if (!$this->data['allowedGroups'][RSM_TEST_GROUP]) {
	$filterTestGroup->setAttribute('disabled', true);
}

$filterColumn1
	->addRow(_('TLD'), (new CTextBox('filter_search', $this->data['filter_search']))
		->setWidth(ZBX_TEXTAREA_FILTER_SMALL_WIDTH)
		->setAttribute('autocomplete', 'off')
	)
	->addRow(SPACE);
$filterColumn2
	->addRow((new CSpan(_('Services')))->addStyle('padding: 0 25px;'), [
		[
			(new CCheckBox('filter_dns'))->setChecked($this->data['filter_dns']),
			SPACE,
			_('DNS')
		],
		SPACE,
		new CSpan([
			(new CCheckBox('filter_dnssec'))->setChecked($this->data['filter_dnssec']),
			SPACE,
			_('DNSSEC')
		], 'checkbox-block'),
		SPACE,
		new CSpan([
			(new CCheckBox('filter_rdds'))->setChecked($this->data['filter_rdds']),
			SPACE,
			_('RDDS')
		], 'checkbox-block'),
		SPACE,
		new CSpan([
			(new CCheckBox('filter_epp'))->setChecked($this->data['filter_epp']),
			SPACE,
			_('EPP')
		], 'checkbox-block'),
		SPACE,
		(new CButton('checkAllServices', _('All/Any')))->addClass(ZBX_STYLE_BTN_LINK)
	])
	->addRow((new CSpan(_('TLD types')))->addStyle('padding: 0 25px;'), [
		[
			$filterCctldGroup,
			SPACE,
			_(RSM_CC_TLD_GROUP)
		],
		SPACE,
		new CSpan([
			$filterGtldGroup,
			SPACE,
			_(RSM_G_TLD_GROUP)
		], 'checkbox-block'),
		SPACE,
		new CSpan([
			$filterOtherGroup,
			SPACE,
			_(RSM_OTHER_TLD_GROUP)
		], 'checkbox-block'),
		SPACE,
		new CSpan([
			$filterTestGroup,
			SPACE,
			_(RSM_TEST_GROUP)
		], 'checkbox-block'),
		SPACE,
		(new CButton('checkAllGroups', _('All/Any')))->addClass(ZBX_STYLE_BTN_LINK)
	]);
$filterColumn3
	->addRow(_('Exceeding or equal to'), $filter_value)
	->addRow(_('Current status'),
		(new CComboBox('filter_status',
			array_key_exists('filter_status', $this->data) ? $this->data['filter_status'] : null)
		)
			->addItem(0, _('all'))
			->addItem(1, _('fail'))
			->addItem(2, _('disabled'))
	);

$filter
	->addColumn($filterColumn1)
	->addColumn($filterColumn2)
	->addColumn($filterColumn3);

$widget->addItem($filter);

// create form
$form = (new CForm())
	->setName('rollingweek');

$table = (new CTableInfo())
	->setHeader([
		make_sorting_header(_('TLD'), 'name', $data['sort'], $data['sortorder']),
		make_sorting_header(_('Type'), 'type', $data['sort'], $data['sortorder']),
		make_sorting_header(_('DNS (4Hrs)'), 'dns_lastvalue', $data['sort'], $data['sortorder']),
		make_sorting_header(_('DNSSEC (4Hrs)'), 'dnssec_lastvalue', $data['sort'], $data['sortorder']),
		make_sorting_header(_('RDDS (24Hrs)'), 'rdds_lastvalue', $data['sort'], $data['sortorder']),
		make_sorting_header(_('EPP (24Hrs)'), 'epp_lastvalue', $data['sort'], $data['sortorder']),
		make_sorting_header(_('Server'), 'server', $data['sort'], $data['sortorder'])
]);

if ($this->data['tld']) {
	$serverTime = time() - RSM_ROLLWEEK_SHIFT_BACK;
	$from = date('YmdHis', $serverTime - $this->data['rollWeekSeconds']);
	$till = date('YmdHis', $serverTime);
	foreach ($this->data['tld'] as $key => $tld) {
		// DNS
		if (isset($tld[RSM_DNS]) && array_key_exists('trigger', $tld[RSM_DNS])) {
			if ($tld[RSM_DNS]['trigger'] && $tld[RSM_DNS]['incident']) {
				if (array_key_exists('availItemId', $tld[RSM_DNS]) && array_key_exists('itemid', $tld[RSM_DNS])) {
					$dnsStatus =  new CLink(
						(new CDiv(null))
							->addClass('service-icon status_icon_extra iconrollingweekfail cell-value pointer'),
						$tld['url'].'rsm.incidentdetails.php?host='.$tld['host'].'&eventid='.$tld[RSM_DNS]['incident'].
							'&slvItemId='.$tld[RSM_DNS]['itemid'].'&filter_from='.$from.'&filter_to='.$till.
							'&availItemId='.$tld[RSM_DNS]['availItemId'].'&filter_set=1&sid='.$this->data['sid'].
							'&set_sid=1'
					);
				}
				else {
					$dnsStatus = (new CDiv(null))
						->addClass('service-icon status_icon_extra iconrollingweekfail cell-value pointer');
				}
			}
			else {
				$dnsStatus = (new CDiv(null))
					->addClass('service-icon status_icon_extra iconrollingweekok cell-value');
			}

			$dnsValue = ($tld[RSM_DNS]['lastvalue'] > 0)
				? (new CLink(
					$tld[RSM_DNS]['lastvalue'].'%',
					$tld['url'].'rsm.incidents.php?filter_set=1&filter_rolling_week=1&type='.RSM_DNS.
						'&host='.$tld['host'].'&sid='.$this->data['sid'].'&set_sid=1'))
						->addClass('first-cell-value')
				: (new CSpan('0.000%'))->addClass('first-cell-value');

			$dnsGraph = ($tld[RSM_DNS]['lastvalue'] > 0)
				? new CLink('graph', $tld['url'].'history.php?action=showgraph&period='.$this->data['rollWeekSeconds'].
					'&itemids[]='.$tld[RSM_DNS]['itemid'].'&sid='.$this->data['sid'].'&set_sid=1', 'cell-value')
				: null;
			$dns = [(new CSpan($dnsValue))->addClass('right'), $dnsStatus, SPACE, $dnsGraph];
		}
		else {
			$dns = (new CDiv(null))
				->addClass('service-icon status_icon_extra iconrollingweekdisabled disabled-service')
				->setHint('Incorrect TLD configuration.', '', 'on');
		}

		// DNSSEC
		if (isset($tld[RSM_DNSSEC]) && array_key_exists('trigger', $tld[RSM_DNSSEC])) {
			if ($tld[RSM_DNSSEC]['trigger'] && $tld[RSM_DNSSEC]['incident']) {
				if (array_key_exists('availItemId', $tld[RSM_DNSSEC]) && array_key_exists('itemid', $tld[RSM_DNSSEC])) {
					$dnssecStatus =  new CLink(
						(new CDiv(null))
							->addClass('service-icon status_icon_extra iconrollingweekfail cell-value pointer'),
						$tld['url'].'rsm.incidentdetails.php?host='.$tld['host'].'&eventid='.$tld[RSM_DNSSEC]['incident'].
							'&slvItemId='.$tld[RSM_DNSSEC]['itemid'].'&filter_from='.$from.'&filter_to='.$till.
							'&availItemId='.$tld[RSM_DNSSEC]['availItemId'].'&filter_set=1&sid='.$this->data['sid'].
							'&set_sid=1'
					);
				}
				else {
					$dnssecStatus = (new CDiv(null))
						->addClass('service-icon status_icon_extra iconrollingweekfail cell-value pointer');
				}
			}
			else {
				$dnssecStatus = (new CDiv(null))
					->addClass('service-icon status_icon_extra iconrollingweekok cell-value');
			}

			$dnssecValue = ($tld[RSM_DNSSEC]['lastvalue'] > 0)
				? (new CLink(
					$tld[RSM_DNSSEC]['lastvalue'].'%',
					$tld['url'].'rsm.incidents.php?filter_set=1&filter_rolling_week=1&type='.RSM_DNSSEC.
						'&host='.$tld['host'].'&sid='.$this->data['sid'].'&set_sid=1'))
						->addClass('first-cell-value')
				: (new CSpan('0.000%'))->addClass('first-cell-value');

			$dnssecGraph = ($tld[RSM_DNSSEC]['lastvalue'] > 0)
				? new CLink('graph', 'history.php?action=showgraph&period='.$this->data['rollWeekSeconds'].'&itemids[]='.
						$tld[RSM_DNSSEC]['itemid'], 'cell-value'
				)
				: null;
			$dnssec = [(new CSpan($dnssecValue))->addClass('right'), $dnssecStatus, SPACE, $dnssecGraph];
		}
		else {
			$dnssec = (new CDiv(null))
				->addClass('service-icon status_icon_extra iconrollingweekdisabled disabled-service')
				->setHint('DNSSEC is disabled.', '', 'on');
		}

		// RDDS
		if (isset($tld[RSM_RDDS]) && array_key_exists('trigger', $tld[RSM_RDDS])) {
			if ($tld[RSM_RDDS]['trigger'] && $tld[RSM_RDDS]['incident']) {
				if (array_key_exists('availItemId', $tld[RSM_RDDS]) && array_key_exists('itemid', $tld[RSM_RDDS])) {
					$rddsStatus =  new CLink(
						(new CDiv(null))
							->addClass('service-icon status_icon_extra iconrollingweekfail cell-value pointer'),
						$tld['url'].'rsm.incidentdetails.php?host='.$tld['host'].'&eventid='.$tld[RSM_RDDS]['incident'].
							'&slvItemId='.$tld[RSM_RDDS]['itemid'].'&filter_from='.$from.'&filter_to='.$till.
							'&availItemId='.$tld[RSM_RDDS]['availItemId'].'&filter_set=1&sid='.$this->data['sid'].
							'&set_sid=1'
					);
				}
				else {
					$rddsStatus = (new CDiv(null))
						->addClass('service-icon status_icon_extra iconrollingweekfail cell-value pointer');
				}
			}
			else {
				$rddsStatus = (new CDiv(null))->addClass('service-icon status_icon_extra iconrollingweekok cell-value');
			}

			$rddsValue = ($tld[RSM_RDDS]['lastvalue'] > 0)
				? (new CLink(
					$tld[RSM_RDDS]['lastvalue'].'%',
					$tld['url'].'rsm.incidents.php?filter_set=1&filter_rolling_week=1&type='.RSM_RDDS.
						'&host='.$tld['host'].'&sid='.$this->data['sid'].'&set_sid=1'))
						->addClass('first-cell-value')
				: (new CSpan('0.000%'))->addClass('first-cell-value');

			$rddsGraph = ($tld[RSM_RDDS]['lastvalue'] > 0)
				? new CLink('graph', $tld['url'].'history.php?action=showgraph&period='.$this->data['rollWeekSeconds'].
					'&itemids[]='.$tld[RSM_RDDS]['itemid'].'&sid='.$this->data['sid'].'&set_sid=1', 'cell-value')
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
			$rdds = [(new CSpan($rddsValue))->addClass('right'), $rddsStatus, SPACE, $rddsGraph, SPACE,
				new CSpan($rdds_services, 'bold')
			];
		}
		else {
			$rdds = (new CDiv(null))
				->addClass('service-icon status_icon_extra iconrollingweekdisabled disabled-service')
				->setHint('RDDS is disabled.', '', 'on');
		}

		// EPP
		if (isset($tld[RSM_EPP]) && array_key_exists('trigger', $tld[RSM_EPP])) {
			if ($tld[RSM_EPP]['trigger'] && $tld[RSM_EPP]['incident']) {
				if (array_key_exists('availItemId', $tld[RSM_EPP]) && array_key_exists('itemid', $tld[RSM_EPP])) {
					$eppStatus = new CLink(
						(new CDiv(null))
							->addClass('service-icon status_icon_extra iconrollingweekfail cell-value pointer'),
						$tld['url'].'rsm.incidentdetails.php?host='.$tld['host'].'&eventid='.$tld[RSM_EPP]['incident'].
							'&slvItemId='.$tld[RSM_EPP]['itemid'].'&filter_from='.$from.'&filter_to='.$till.
							'&availItemId='.$tld[RSM_EPP]['availItemId'].'&filter_set=1&sid='.$this->data['sid'].
							'&set_sid=1'
					);
				}
				else {
					$eppStatus = (new CDiv(null))
						->addClass('service-icon status_icon_extra iconrollingweekfail cell-value pointer');
				}
			}
			else {
				$eppStatus = (new CDiv(null))
					->addClass('service-icon status_icon_extra iconrollingweekok cell-value');
			}

			$eppValue = ($tld[RSM_EPP]['lastvalue'] > 0)
				? (new CLink(
					$tld[RSM_EPP]['lastvalue'].'%',
					$tld['url'].'rsm.incidents.php?filter_set=1&filter_rolling_week=1&type='.RSM_EPP.
						'&host='.$tld['host'].'&sid='.$this->data['sid'].'&set_sid=1'))
						->addClass('first-cell-value')
				: (new CSpan('0.000%'))->addClass('first-cell-value');

			$eppGraph = ($tld[RSM_EPP]['lastvalue'] > 0)
				? new CLink('graph', $tld['url'].'history.php?action=showgraph&period='.$this->data['rollWeekSeconds'].
					'&itemids[]='.$tld[RSM_EPP]['itemid'].'&sid='.$this->data['sid'].'&set_sid=1', 'cell-value')
				: null;
			$epp = [(new CSpan($eppValue))->addClass('right'), $eppStatus, SPACE, $eppGraph];
		}
		else {
			$epp = (new CDiv(null))
				->addClass('service-icon status_icon_extra iconrollingweekdisabled disabled-service')
				->setHint('EPP is disabled.', '', 'on');
		}
		$row = [
			$tld['name'],
			$tld['type'],
			$dns,
			$dnssec,
			$rdds,
			$epp,
			new CLink($tld['server'], $tld['url'].'rsm.rollingweekstatus.php?sid='.$this->data['sid'].'&set_sid=1')
		];

		$table->addRow($row);
	}
}

$form->addItem([$table, $data['paging']]);
// append form to widget
$widget->addItem($form);

return $widget;
