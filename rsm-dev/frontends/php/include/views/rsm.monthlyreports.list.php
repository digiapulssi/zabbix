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


$widget = (new CWidget())->setTitle(_('Monthly report'));

$filter = (new CFilter('web.rsm.slareports.filter.state'))
	->addVar('filter_set', 1);

$filterColumn1 = new CFormList();
$filterColumn2 = new CFormList();
$filterColumn3 = new CFormList();

$months = [];
for ($i = 1; $i <= 12; $i++) {
	$months[$i] = getMonthCaption($i);
}

$years = [];
for ($i = SLA_MONITORING_START_YEAR; $i <= date('Y', time()); $i++) {
	$years[$i] = $i;
}

$filterColumn1
	->addRow(_('TLD'), (new CTextBox('filter_search', $this->data['filter_search']))
		->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
		->setAttribute('autocomplete', 'off')
	);
$filterColumn2
	->addRow(_('Period'), [
		new CComboBox('filter_month', $this->data['filter_month'], null, $months),
		SPACE,
		new CComboBox('filter_year', $this->data['filter_year'], null, $years)
	]);
$filterColumn3
	->addRow(new CLink(_('Download all TLD reports'),
		$this->data['url'].'rsm.monthlyreports.php?filter_set=1&filter_search='.$this->data['filter_search'].
			'&filter_year='.$this->data['filter_year'].'&filter_month='.$this->data['filter_month'].
			'&export=1&sid='.$this->data['sid'].'&set_sid=1'
	));

$filter
	->addColumn($filterColumn1)
	->addColumn($filterColumn2)
	->addColumn($filterColumn3);

$widget->addItem($filter);

if (isset($this->data['tld'])) {
	$infoBlock = (new CTable(null, 'filter info-block'))
		->addRow([bold(_('TLD')), ':', SPACE, $this->data['tld']['name']])
		->addRow([bold(_('Server')), ':', SPACE, new CLink($this->data['server'],
			$this->data['url'].'rsm.rollingweekstatus.php?sid='.$this->data['sid'].'&set_sid=1'
		)]);
	$widget->additem($infoBlock);
}

// create form
$form = (new CForm())
	->setName('scenarios');

$table = (new CTableInfo())
	->setHeader([
		_('Service'),
		_('Parameter'),
		_('SLV'),
		_('Acceptable SLA'),
		SPACE
]);


foreach ($this->data['services'] as $name => $services) {
	if (count($services['parameters']) > 1) {
		$table->addRow([
			$name,
			new CCol(SPACE, null, 4)
		]);

		foreach ($services['parameters'] as $key => $service) {
			$color = null;

			if (isset($services['acceptable_sla']) && isset($service['slv'])
					&& $services['acceptable_sla'] > $service['slv']) {
				$color = 'red';
			}
			else {
				$color = 'green';
			}

			$table->addRow([
				SPACE,
				$service['ns'],
				isset($service['slv']) ? new CSpan($service['slv'], $color) : '-',
				isset($services['acceptable_sla']) ? $services['acceptable_sla'] : '-',
				new CLink('graph', $this->data['url'].'history.php?action=showgraph&period=2592000'.
					'&stime='.$data['stime'].'&itemids[]='.$key.'&sid='.$this->data['sid'].'&set_sid=1'
				)
			]);
		}
	}
	else {
		$serviceValues = reset($services['parameters']);
		$itemIds = array_keys($services['parameters']);
		$itemId = $itemIds[0];
		$color = null;

		if (isset($services['acceptable_sla']) && isset($serviceValues['slv'])
				&& $services['acceptable_sla'] > $serviceValues['slv']) {
			$color = 'red';
		}
		else {
			$color = 'green';
		}

		$table->addRow([
			$name,
			SPACE,
			isset($serviceValues['slv']) ? new CSpan($serviceValues['slv'], $color) : '-',
			isset($services['acceptable_sla']) ? $services['acceptable_sla'] : '-',
			new CLink('graph', $this->data['url'].'history.php?action=showgraph&period=2592000&stime='.$data['stime'].
				'&itemids[]='.$itemId.'&sid='.$this->data['sid'].'&set_sid=1'
			)
		]);
	}
}

$form->addItem([
	$table
]);
// append form to widget
$widget->addItem($form);

return $widget;
