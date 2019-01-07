<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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


require_once dirname(__FILE__).'/js/configuration.triggers.list.js.php';

if (!$this->data['hostid']) {
	$create_button = (new CSubmit('form', _('Create trigger (select host first)')))->setEnabled(false);
}
else {
	$create_button = new CSubmit('form', _('Create trigger'));
}

$filter = (new CFilter('web.triggers.filter.state'))
	->addColumn(
		(new CFormList())
			->addRow(_('Severity'),
				new CSeverity([
					'name' => 'filter_priority', 'value' => (int) $this->data['filter_priority'], 'all' => true
				])
			)
			->addRow(_('State'),
				(new CRadioButtonList('filter_state', (int) $this->data['filter_state']))
					->addValue(_('all'), -1)
					->addValue(_('Normal'), TRIGGER_STATE_NORMAL)
					->addValue(_('Unknown'), TRIGGER_STATE_UNKNOWN)
					->setModern(true)
			)
			->addRow(_('Status'),
				(new CRadioButtonList('filter_status', (int) $this->data['filter_status']))
					->addValue(_('all'), -1)
					->addValue(triggerIndicator(TRIGGER_STATUS_ENABLED), TRIGGER_STATUS_ENABLED)
					->addValue(triggerIndicator(TRIGGER_STATUS_DISABLED), TRIGGER_STATUS_DISABLED)
					->setModern(true)
			)
	);

$widget = (new CWidget())
	->setTitle(_('Triggers'))
	->setControls((new CForm('get'))
		->cleanItems()
		->addItem((new CList())
			->addItem([
				new CLabel(_('Group'), 'groupid'),
				(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
				$this->data['pageFilter']->getGroupsCB()
			])
			->addItem([
				new CLabel(_('Host'), 'hostid'),
				(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
				$this->data['pageFilter']->getHostsCB()
			])
			->addItem($create_button)
		)
	);

if ($this->data['hostid']) {
	$widget->addItem(get_header_host_table('triggers', $this->data['hostid']));
}

$widget->addItem($filter);

// create form
$triggersForm = (new CForm())
	->setName('triggersForm')
	->addVar('hostid', $this->data['hostid']);

$url = (new CUrl('triggers.php'))
	->setArgument('hostid', $data['hostid'])
	->getUrl();

// create table
$triggersTable = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_triggers'))->onClick("checkAll('".$triggersForm->getName()."', 'all_triggers', 'g_triggerid');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		make_sorting_header(_('Severity'), 'priority', $data['sort'], $data['sortorder'], $url),
		($this->data['hostid'] == 0) ? _('Host') : null,
		make_sorting_header(_('Name'), 'description', $data['sort'], $data['sortorder'], $url),
		_('Expression'),
		make_sorting_header(_('Status'), 'status', $data['sort'], $data['sortorder'], $url),
		$this->data['showInfoColumn'] ? _('Info') : null
	]);

$this->data['triggers'] = CMacrosResolverHelper::resolveTriggerExpressions($this->data['triggers'], ['html' => true]);

foreach ($this->data['triggers'] as $tnum => $trigger) {
	$triggerid = $trigger['triggerid'];

	// description
	$description = [];

	$trigger['hosts'] = zbx_toHash($trigger['hosts'], 'hostid');

	if ($trigger['templateid'] > 0) {
		if (!isset($this->data['realHosts'][$triggerid])) {
			$description[] = (new CSpan(_('Host')))->addClass(ZBX_STYLE_GREY);
			$description[] = NAME_DELIMITER;
		}
		else {
			$real_hosts = $this->data['realHosts'][$triggerid];
			$real_host = reset($real_hosts);

			if (array_key_exists($real_host['hostid'], $data['writable_templates'])) {
				$description[] = (new CLink(CHtml::encode($real_host['name']),
					'triggers.php?hostid='.$real_host['hostid']
				))
					->addClass(ZBX_STYLE_LINK_ALT)
					->addClass(ZBX_STYLE_GREY);
			}
			else {
				$description[] = (new CSpan(CHtml::encode($real_host['name'])))->addClass(ZBX_STYLE_GREY);
			}

			$description[] = NAME_DELIMITER;
		}
	}

	if ($trigger['discoveryRule']) {
		$description[] = (new CLink(
			CHtml::encode($trigger['discoveryRule']['name']),
			'trigger_prototypes.php?parent_discoveryid='.$trigger['discoveryRule']['itemid']))
			->addClass(ZBX_STYLE_LINK_ALT)
			->addClass(ZBX_STYLE_ORANGE);
		$description[] = NAME_DELIMITER.$trigger['description'];
	}
	else {
		$description[] = new CLink(
			CHtml::encode($trigger['description']),
			'triggers.php?form=update&hostid='.$this->data['hostid'].'&triggerid='.$triggerid
		);
	}

	if ($trigger['dependencies']) {
		$description[] = [BR(), bold(_('Depends on').':')];
		$triggerDependencies = [];

		foreach ($trigger['dependencies'] as $dependency) {
			$depTrigger = $this->data['dependencyTriggers'][$dependency['triggerid']];

			$depTriggerDescription = CHtml::encode(
				implode(', ', zbx_objectValues($depTrigger['hosts'], 'name')).NAME_DELIMITER.$depTrigger['description']
			);

			if ($depTrigger['flags'] == ZBX_FLAG_DISCOVERY_NORMAL) {
				$triggerDependencies[] = (new CLink(
					$depTriggerDescription,
					'triggers.php?form=update&triggerid='.$depTrigger['triggerid']))
					->addClass(ZBX_STYLE_LINK_ALT)
					->addClass(triggerIndicatorStyle($depTrigger['status']));
			}
			else {
				$triggerDependencies[] = $depTriggerDescription;
			}

			$triggerDependencies[] = BR();
		}
		array_pop($triggerDependencies);

		$description = array_merge($description, [(new CDiv($triggerDependencies))->addClass('dependencies')]);
	}


	// info
	if ($this->data['showInfoColumn']) {
		if ($trigger['status'] == TRIGGER_STATUS_ENABLED && $trigger['error']) {
			$info = makeErrorIcon($trigger['error']);
		}
		else {
			$info = '';
		}
	}
	else {
		$info = null;
	}

	// status
	$status = (new CLink(
		triggerIndicator($trigger['status'], $trigger['state']),
		'triggers.php?'.
			'action='.($trigger['status'] == TRIGGER_STATUS_DISABLED
				? 'trigger.massenable'
				: 'trigger.massdisable'
			).
			'&hostid='.$this->data['hostid'].
			'&g_triggerid='.$triggerid))
		->addClass(ZBX_STYLE_LINK_ACTION)
		->addClass(triggerIndicatorStyle($trigger['status'], $trigger['state']))
		->addSID();

	// hosts
	$hosts = null;
	if ($this->data['hostid'] == 0) {
		foreach ($trigger['hosts'] as $hostid => $host) {
			if (!empty($hosts)) {
				$hosts[] = ', ';
			}
			$hosts[] = $host['name'];
		}
	}

	// checkbox
	$checkBox = (new CCheckBox('g_triggerid['.$triggerid.']', $triggerid))
		->setEnabled(empty($trigger['discoveryRule']));

	$triggersTable->addRow([
		$checkBox,
		getSeverityCell($trigger['priority'], $this->data['config']),
		$hosts,
		$description,
		$trigger['expression'],
		$status,
		$info
	]);
}

zbx_add_post_js('cookie.prefix = "'.$this->data['hostid'].'";');

// append table to form
$triggersForm->addItem([
	$triggersTable,
	$this->data['paging'],
	new CActionButtonList('action', 'g_triggerid',
		[
			'trigger.massenable' => ['name' => _('Enable'), 'confirm' => _('Enable selected triggers?')],
			'trigger.massdisable' => ['name' => _('Disable'), 'confirm' => _('Disable selected triggers?')],
			'trigger.masscopyto' => ['name' => _('Copy')],
			'trigger.massupdateform' => ['name' => _('Mass update')],
			'trigger.massdelete' => ['name' => _('Delete'), 'confirm' => _('Delete selected triggers?')]
		],
		$this->data['hostid']
	)
]);

// append form to widget
$widget->addItem($triggersForm);

return $widget;
