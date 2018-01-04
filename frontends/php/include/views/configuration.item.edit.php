<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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


$widget = (new CWidget())->setTitle(_('Items'));

$host = $data['host'];

if (!empty($data['hostid'])) {
	$widget->addItem(get_header_host_table('items', $data['hostid']));
}

// Create form.
$itemForm = (new CForm())
	->setName('itemForm')
	->addVar('form', $data['form'])
	->addVar('hostid', $data['hostid']);

if (!empty($data['itemid'])) {
	$itemForm->addVar('itemid', $data['itemid']);
}

// Create form list.
$itemFormList = new CFormList('itemFormList');
if (!empty($data['templates'])) {
	$itemFormList->addRow(_('Parent items'), $data['templates']);
}

$discovered_item = false;
if (array_key_exists('item', $data) && $data['item']['flags'] == ZBX_FLAG_DISCOVERY_CREATED) {
	$discovered_item = true;
}
$readonly = false;
if ($data['limited'] || $discovered_item) {
	$readonly = true;
}

if ($discovered_item) {
	$itemFormList->addRow(_('Discovered by'), new CLink($data['item']['discoveryRule']['name'],
		'disc_prototypes.php?parent_discoveryid='.$data['item']['discoveryRule']['itemid']
	));
}

$itemFormList->addRow(
	(new CLabel(_('Name'), 'name'))->setAsteriskMark(),
	(new CTextBox('name', $data['name'], $readonly))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setAriaRequired()
		->setAttribute('autofocus', 'autofocus')
);

// Append type to form list.
if ($readonly) {
	$itemForm->addVar('type', $data['type']);
	$itemFormList->addRow((new CLabel(_('Type'), 'type_name')),
		(new CTextBox('type_name', item_type2str($data['type']), true))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
	);
}
else {
	$itemFormList->addRow((new CLabel(_('Type'), 'type')),
		(new CComboBox('type', $data['type'], null, $data['types']))
	);
}

// Append key to form list.
$key_controls = [(new CTextBox('key', $data['key'], $readonly))
	->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	->setAriaRequired()
];

if (!$readonly) {
	$key_controls[] = (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN);
	$key_controls[] = (new CButton('keyButton', _('Select')))
		->addClass(ZBX_STYLE_BTN_GREY)
		->onClick('return PopUp("popup.generic",jQuery.extend('.
			CJs::encodeJson([
				'srctbl' => 'help_items',
				'srcfld1' => 'key',
				'dstfrm' => $itemForm->getName(),
				'dstfld1' => 'key'
			]).
				',{itemtype: jQuery("#type option:selected").val()}));'
		);
}

$itemFormList->addRow((new CLabel(_('Key'), 'key'))->setAsteriskMark(), $key_controls);

// Append master item select.
$master_item = [
	(new CTextBox('master_itemname', $data['master_itemname'], true))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setAriaRequired(),
	(new CVar('master_itemid', $data['master_itemid'], 'master_itemid'))
];

if (!$readonly) {
	$master_item[] = (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN);
	$master_item[] = (new CButton('button', _('Select')))
		->addClass(ZBX_STYLE_BTN_GREY)
		->onClick('return PopUp("popup.generic",'.
			CJs::encodeJson([
				'srctbl' => 'items',
				'srcfld1' => 'itemid',
				'srcfld2' => 'master_itemname',
				'dstfrm' => $itemForm->getName(),
				'dstfld1' => 'master_itemid',
				'dstfld2' => 'master_itemname',
				'only_hostid' => $data['hostid'],
				'with_webitems' => '0',
				'excludeids' => [$data['itemid']]
			]).');'
		);
}

$itemFormList->addRow(
	(new CLabel(_('Master item'), 'master_itemname'))->setAsteriskMark(),
	$master_item,
	'row_master_item'
);

// Append interface(s) to form list.
if ($data['interfaces']) {
	if ($discovered_item) {
		if ($data['interfaceid'] != 0) {
			$data['interfaces'] = zbx_toHash($data['interfaces'], 'interfaceid');
			$interface = $data['interfaces'][$data['interfaceid']];

			$itemFormList->addRow((new CLabel(_('Host interface'), 'interface'))->setAsteriskMark(),
				(new CTextBox('interface',
					$interface['useip']
						? $interface['ip'].' : '.$interface['port']
						: $interface['dns'].' : '.$interface['port'],
					true
				))->setAriaRequired(),
				'interface_row'
			);
		}
	}
	else {
		$interfacesComboBox = (new CComboBox('interfaceid', $data['interfaceid']))
			->setAriaRequired();

		// Set up interface groups sorted by priority.
		$interface_types = zbx_objectValues($this->data['interfaces'], 'type');
		$interface_groups = [];
		foreach ([INTERFACE_TYPE_AGENT, INTERFACE_TYPE_SNMP, INTERFACE_TYPE_JMX, INTERFACE_TYPE_IPMI] as $interface_type) {
			if (in_array($interface_type, $interface_types)) {
				$interface_groups[$interface_type] = new COptGroup(interfaceType2str($interface_type));
			}
		}

		// add interfaces to groups
		foreach ($data['interfaces'] as $interface) {
			$option = new CComboItem($interface['interfaceid'],
				$interface['useip']
					? $interface['ip'].' : '.$interface['port']
					: $interface['dns'].' : '.$interface['port'],
				($interface['interfaceid'] == $data['interfaceid'])
			);
			$option->setAttribute('data-interfacetype', $interface['type']);
			$interface_groups[$interface['type']]->addItem($option);
		}
		foreach ($interface_groups as $interface_group) {
			$interfacesComboBox->addItem($interface_group);
		}

		$span = (new CSpan(_('No interface found')))
			->addClass(ZBX_STYLE_RED)
			->setId('interface_not_defined')
			->setAttribute('style', 'display: none;');

		$itemFormList->addRow((new CLabel(_('Host interface'), 'interfaceid'))->setAsteriskMark(),
			[$interfacesComboBox, $span], 'interface_row'
		);
		$itemForm->addVar('selectedInterfaceId', $data['interfaceid']);
	}
}

// Append SNMP common fields fields.
$itemFormList->addRow(
	(new CLabel(_('SNMP OID'), 'snmp_oid'))->setAsteriskMark(),
	(new CTextBox('snmp_oid', $data['snmp_oid'], $readonly, 512))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setAriaRequired(),
	'row_snmp_oid'
);
$itemFormList->addRow(_('Context name'),
	(new CTextBox('snmpv3_contextname', $data['snmpv3_contextname'], $discovered_item))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	'row_snmpv3_contextname'
);
$itemFormList->addRow(
	(new CLabel(_('SNMP community'), 'snmp_community'))->setAsteriskMark(),
	(new CTextBox('snmp_community', $data['snmp_community'], $discovered_item, 64))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setAriaRequired(),
	'row_snmp_community'
);
$itemFormList->addRow(_('Security name'),
	(new CTextBox('snmpv3_securityname', $data['snmpv3_securityname'], $discovered_item, 64))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	'row_snmpv3_securityname'
);

// Append snmpv3 security level to form list.
$security_levels = [
	ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV => 'noAuthNoPriv',
	ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV => 'authNoPriv',
	ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV => 'authPriv'
];
if ($discovered_item) {
	$itemForm->addVar('snmpv3_securitylevel', $data['snmpv3_securitylevel']);
	$securityLevelComboBox = new CTextBox('snmpv3_securitylevel_name', $security_levels[$data['snmpv3_securitylevel']],
		true
	);
}
else {
	$securityLevelComboBox = new CComboBox('snmpv3_securitylevel', $data['snmpv3_securitylevel'], null,
		$security_levels
	);
}
$itemFormList->addRow(_('Security level'), $securityLevelComboBox, 'row_snmpv3_securitylevel');

// Append snmpv3 authentication protocol to form list.
if ($discovered_item) {
	$itemForm->addVar('snmpv3_authprotocol', (int) $data['snmpv3_authprotocol']);
	$snmpv3_authprotocol = (new CRadioButtonList('snmpv3_authprotocol_names', (int) $data['snmpv3_authprotocol']))
		->addValue(_('MD5'), ITEM_AUTHPROTOCOL_MD5)
		->addValue(_('SHA'), ITEM_AUTHPROTOCOL_SHA)
		->setModern(true)
		->setEnabled(!$discovered_item);
}
else {
	$snmpv3_authprotocol = (new CRadioButtonList('snmpv3_authprotocol', (int) $data['snmpv3_authprotocol']))
		->addValue(_('MD5'), ITEM_AUTHPROTOCOL_MD5)
		->addValue(_('SHA'), ITEM_AUTHPROTOCOL_SHA)
		->setModern(true);
}

$itemFormList->addRow((new CLabel(_('Authentication protocol'), 'snmpv3_authprotocol')),
	$snmpv3_authprotocol,
	'row_snmpv3_authprotocol'
);

// Append snmpv3 authentication passphrase to form list.
$itemFormList->addRow(_('Authentication passphrase'),
	(new CTextBox('snmpv3_authpassphrase', $data['snmpv3_authpassphrase'], $discovered_item, 64))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	'row_snmpv3_authpassphrase'
);

// Append snmpv3 privacy protocol to form list.
if ($discovered_item) {
	$itemForm->addVar('snmpv3_privprotocol', (int) $data['snmpv3_privprotocol']);
	$snmpv3_privprotocol = (new CRadioButtonList('snmpv3_privprotocol_names', (int) $data['snmpv3_privprotocol']))
		->addValue(_('DES'), ITEM_PRIVPROTOCOL_DES)
		->addValue(_('AES'), ITEM_PRIVPROTOCOL_AES)
		->setModern(true)
		->setEnabled(!$discovered_item);
}
else {
	$snmpv3_privprotocol = (new CRadioButtonList('snmpv3_privprotocol', (int) $data['snmpv3_privprotocol']))
		->addValue(_('DES'), ITEM_PRIVPROTOCOL_DES)
		->addValue(_('AES'), ITEM_PRIVPROTOCOL_AES)
		->setModern(true);
}
$itemFormList->addRow((new CLabel(_('Privacy protocol'), 'snmpv3_privprotocol')),
	$snmpv3_privprotocol,
	'row_snmpv3_privprotocol'
);

// Append snmpv3 privacy passphrase to form list.
$itemFormList->addRow(_('Privacy passphrase'),
	(new CTextBox('snmpv3_privpassphrase', $data['snmpv3_privpassphrase'], $discovered_item, 64))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	'row_snmpv3_privpassphrase'
);
$itemFormList->addRow(_('Port'),
	(new CTextBox('port', $data['port'], $discovered_item, 64))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH),
	'row_port'
);
$itemFormList->addRow(
	(new CLabel(_('IPMI sensor'), 'ipmi_sensor'))->setAsteriskMark(),
	(new CTextBox('ipmi_sensor', $data['ipmi_sensor'], $readonly, 128))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setAriaRequired(),
	'row_ipmi_sensor'
);

// Append authentication method to form list.
$auth_types = [
	ITEM_AUTHTYPE_PASSWORD => _('Password'),
	ITEM_AUTHTYPE_PUBLICKEY => _('Public key')
];
if ($discovered_item) {
	$itemForm->addVar('authtype', $data['authtype']);
	$authTypeComboBox = new CTextBox('authtype_name', $auth_types[$data['authtype']], true);
}
else {
	$authTypeComboBox = new CComboBox('authtype', $data['authtype'], null, $auth_types);
}

$itemFormList->addRow(_('Authentication method'), $authTypeComboBox, 'row_authtype');
$itemFormList->addRow((new CLabel(_('JMX endpoint'), 'jmx_endpoint'))->setAsteriskMark(),
	(new CTextBox('jmx_endpoint', $data['jmx_endpoint'], $discovered_item, 255))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setAriaRequired(),
	'row_jmx_endpoint'
);
$itemFormList->addRow(_('User name'),
	(new CTextBox('username', $data['username'], $discovered_item, 64))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH),
	'row_username'
);
$itemFormList->addRow(
	(new CLabel(_('Public key file'), 'publickey'))->setAsteriskMark(),
	(new CTextBox('publickey', $data['publickey'], $discovered_item, 64))
		->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
		->setAriaRequired(),
	'row_publickey'
);
$itemFormList->addRow(
	(new CLabel(_('Private key file'), 'privatekey'))->setAsteriskMark(),
	(new CTextBox('privatekey', $data['privatekey'], $discovered_item, 64))
		->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
		->setAriaRequired(),
	'row_privatekey'
);
$itemFormList->addRow(_('Password'),
	(new CTextBox('password', $data['password'], $discovered_item, 64))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH),
	'row_password'
);
$itemFormList->addRow(
	(new CLabel(_('Executed script'), 'params_es'))->setAsteriskMark(),
	(new CTextArea('params_es', $data['params']))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setAriaRequired()
		->setReadonly($discovered_item),
	'label_executed_script'
);
$itemFormList->addRow(
	(new CLabel(_('SQL query'), 'params_ap'))->setAsteriskMark(),
	(new CTextArea('params_ap', $data['params']))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setAriaRequired()
		->setReadonly($discovered_item),
	'label_params'
);
$itemFormList->addRow(
	(new CLabel(_('Formula'), 'params_f'))->setAsteriskMark(),
	(new CTextArea('params_f', $data['params'], $discovered_item))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setAriaRequired()
		->setReadonly($discovered_item),
	'label_formula'
);

// Append value type to form list.
if ($readonly) {
	$itemForm->addVar('value_type', $data['value_type']);
	$itemFormList->addRow((new CLabel(_('Type of information'), 'value_type_name'))->setAsteriskMark(),
		(new CTextBox('value_type_name', itemValueTypeString($data['value_type']), true))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
	);
}
else {
	$itemFormList->addRow((new CLabel(_('Type of information'), 'value_type')),
		(new CComboBox('value_type', $data['value_type'], null, [
			ITEM_VALUE_TYPE_UINT64 => _('Numeric (unsigned)'),
			ITEM_VALUE_TYPE_FLOAT => _('Numeric (float)'),
			ITEM_VALUE_TYPE_STR => _('Character'),
			ITEM_VALUE_TYPE_LOG => _('Log'),
			ITEM_VALUE_TYPE_TEXT => _('Text')
		]))
	);
}

$itemFormList->addRow(_('Units'),
	(new CTextBox('units', $data['units'], $readonly))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	'row_units'
);

$itemFormList->addRow((new CLabel(_('Update interval'), 'delay'))->setAsteriskMark(),
	(new CTextBox('delay', $data['delay'], $discovered_item))
		->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
		->setAriaRequired(),
	'row_delay'
);

// Append custom intervals to form list.
$delayFlexTable = (new CTable())
	->setId('delayFlexTable')
	->setHeader([_('Type'), _('Interval'), _('Period'), $discovered_item ? null : _('Action')])
	->setAttribute('style', 'width: 100%;');

foreach ($data['delay_flex'] as $i => $delay_flex) {
	if ($discovered_item) {
		$itemForm->addVar('delay_flex['.$i.'][type]', (int) $delay_flex['type']);
		$type_input = (new CRadioButtonList('delay_flex['.$i.'][type_name]', (int) $delay_flex['type']))
			->addValue(_('Flexible'), ITEM_DELAY_FLEXIBLE)
			->addValue(_('Scheduling'), ITEM_DELAY_SCHEDULING)
			->setModern(true)
			->setEnabled(!$discovered_item);
	}
	else {
		$type_input = (new CRadioButtonList('delay_flex['.$i.'][type]', (int) $delay_flex['type']))
			->addValue(_('Flexible'), ITEM_DELAY_FLEXIBLE)
			->addValue(_('Scheduling'), ITEM_DELAY_SCHEDULING)
			->setModern(true);
	}

	if ($delay_flex['type'] == ITEM_DELAY_FLEXIBLE) {
		$delay_input = (new CTextBox('delay_flex['.$i.'][delay]', $delay_flex['delay'], $discovered_item))
			->setAttribute('placeholder', ZBX_ITEM_FLEXIBLE_DELAY_DEFAULT);
		$period_input = (new CTextBox('delay_flex['.$i.'][period]', $delay_flex['period'], $discovered_item))
			->setAttribute('placeholder', ZBX_DEFAULT_INTERVAL);
		$schedule_input = (new CTextBox('delay_flex['.$i.'][schedule]', '', $discovered_item))
			->setAttribute('placeholder', ZBX_ITEM_SCHEDULING_DEFAULT)
			->setAttribute('style', 'display: none;');
	}
	else {
		$delay_input = (new CTextBox('delay_flex['.$i.'][delay]', $discovered_item))
			->setAttribute('placeholder', ZBX_ITEM_FLEXIBLE_DELAY_DEFAULT)
			->setAttribute('style', 'display: none;');
		$period_input = (new CTextBox('delay_flex['.$i.'][period]', '', $discovered_item))
			->setAttribute('placeholder', ZBX_DEFAULT_INTERVAL)
			->setAttribute('style', 'display: none;');
		$schedule_input = (new CTextBox('delay_flex['.$i.'][schedule]', $delay_flex['schedule'], $discovered_item))
			->setAttribute('placeholder', ZBX_ITEM_SCHEDULING_DEFAULT);
	}

	$button = $discovered_item
		? null
		: (new CButton('delay_flex['.$i.'][remove]', _('Remove')))
			->addClass(ZBX_STYLE_BTN_LINK)
			->addClass('element-table-remove');

	$delayFlexTable->addRow([$type_input, [$delay_input, $schedule_input], $period_input, $button], 'form_row');
}

if (!$discovered_item) {
	$delayFlexTable->addRow([(new CButton('interval_add', _('Add')))
		->addClass(ZBX_STYLE_BTN_LINK)
		->addClass('element-table-add')]);
}

$itemFormList->addRow(_('Custom intervals'),
	(new CDiv($delayFlexTable))
		->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
		->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;'),
	'row_flex_intervals'
);

// Append history storage to form list.
$keepHistory = [];
$keepHistory[] = (new CTextBox('history', $data['history'], $discovered_item))
	->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
	->setAriaRequired();

if ($data['config']['hk_history_global']
		&& ($host['status'] == HOST_STATUS_MONITORED || $host['status'] == HOST_STATUS_NOT_MONITORED)) {
	$keepHistory[] = ' '._x('Overridden by', 'item_form').' ';

	if (CWebUser::getType() == USER_TYPE_SUPER_ADMIN) {
		$link = (new CLink(_x('global housekeeping settings', 'item_form'), 'adm.housekeeper.php'))
			->setAttribute('target', '_blank');
		$keepHistory[] = $link;
	}
	else {
		$keepHistory[] = _x('global housekeeping settings', 'item_form');
	}

	$keepHistory[] = ' ('.$data['config']['hk_history'].')';
}

$itemFormList->addRow((new CLabel(_('History storage period'), 'history'))->setAsteriskMark(),
	$keepHistory
);

// Append trend storage to form list.
$keepTrend = [];
$keepTrend[] = (new CTextBox('trends', $data['trends'], $discovered_item))
	->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
	->setAriaRequired();

if ($data['config']['hk_trends_global']
		&& ($host['status'] == HOST_STATUS_MONITORED || $host['status'] == HOST_STATUS_NOT_MONITORED)) {
	$keepTrend[] = ' '._x('Overridden by', 'item_form').' ';

	if (CWebUser::getType() == USER_TYPE_SUPER_ADMIN) {
		$link = (new CLink(_x('global housekeeping settings', 'item_form'), 'adm.housekeeper.php'))
			->setAttribute('target', '_blank');
		$keepTrend[] = $link;
	}
	else {
		$keepTrend[] = _x('global housekeeping settings', 'item_form');
	}

	$keepTrend[] = ' ('.$data['config']['hk_trends'].')';
}

$itemFormList->addRow((new CLabel(_('Trend storage period'), 'trends'))->setAsteriskMark(), $keepTrend,
	'row_trends'
);

$itemFormList->addRow(_('Log time format'),
	(new CTextBox('logtimefmt', $data['logtimefmt'], $readonly, 64))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	'row_logtimefmt'
);

// Append valuemap to form list.
if ($readonly) {
	$itemForm->addVar('valuemapid', $data['valuemapid']);
	$valuemapComboBox = (new CTextBox('valuemap_name',
		!empty($data['valuemaps']) ? $data['valuemaps'] : _('As is'),
		true
	))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH);
}
else {
	$valuemapComboBox = new CComboBox('valuemapid', $data['valuemapid']);
	$valuemapComboBox->addItem(0, _('As is'));
	foreach ($data['valuemaps'] as $valuemap) {
		$valuemapComboBox->addItem($valuemap['valuemapid'], CHtml::encode($valuemap['name']));
	}
}
$link = (new CLink(_('show value mappings'), 'adm.valuemapping.php'))
	->setAttribute('target', '_blank');
$itemFormList->addRow(_('Show value'), [$valuemapComboBox, SPACE, $link], 'row_valuemap');
$itemFormList->addRow(_('Allowed hosts'),
	(new CTextBox('trapper_hosts', $data['trapper_hosts'], $discovered_item))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
	'row_trapper_hosts');

// Add "New application" and list of applications to form list.
if ($discovered_item) {
	$itemForm->addVar('new_application', '');
	foreach ($data['db_applications'] as $db_application) {
		foreach ($data['applications'] as $application) {
			if ($db_application['applicationid'] == $application) {
				$itemForm->addVar('applications[]', $db_application['applicationid']);
			}
		}
	}

	$applicationComboBox = new CListBox('applications_names[]', $data['applications'], 6);
	foreach ($data['db_applications'] as $application) {
		$applicationComboBox->addItem($application['applicationid'], CHtml::encode($application['name']));
	}
	$applicationComboBox->setEnabled(!$discovered_item);
}
else {
	$itemFormList->addRow(new CLabel(_('New application'), 'new_application'), (new CSpan(
		(new CTextBox('new_application', $data['new_application']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	))->addClass(ZBX_STYLE_FORM_NEW_GROUP));

	$applicationComboBox = new CListBox('applications[]', $data['applications'], 6);
	$applicationComboBox->addItem(0, '-'._('None').'-');
	foreach ($data['db_applications'] as $application) {
		$applicationComboBox->addItem($application['applicationid'], CHtml::encode($application['name']));
	}
}

$itemFormList->addRow(_('Applications'), $applicationComboBox);

// Append populate host to form list.
if ($discovered_item) {
	$itemForm->addVar('inventory_link', 0);
}
else {
	$hostInventoryFieldComboBox = new CComboBox('inventory_link');
	$hostInventoryFieldComboBox->addItem(0, '-'._('None').'-', $data['inventory_link'] == '0' ? 'yes' : null);

	// A list of available host inventory fields.
	foreach ($data['possibleHostInventories'] as $fieldNo => $fieldInfo) {
		if (isset($data['alreadyPopulated'][$fieldNo])) {
			$enabled = isset($data['item']['inventory_link'])
				? $data['item']['inventory_link'] == $fieldNo
				: $data['inventory_link'] == $fieldNo && !hasRequest('clone');
		}
		else {
			$enabled = true;
		}
		$hostInventoryFieldComboBox->addItem(
			$fieldNo,
			$fieldInfo['title'],
			$data['inventory_link'] == $fieldNo && $enabled ? 'yes' : null,
			$enabled
		);
	}

	$itemFormList->addRow(_('Populates host inventory field'), $hostInventoryFieldComboBox, 'row_inventory_link');
}

// Append description to form list.
$itemFormList->addRow(_('Description'),
	(new CTextArea('description', $data['description']))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setReadonly($discovered_item)
);

// Append status to form list.
$enabledCheckBox = (new CCheckBox('status', ITEM_STATUS_ACTIVE))
	->setChecked($data['status'] == ITEM_STATUS_ACTIVE);
$itemFormList->addRow(_('Enabled'), $enabledCheckBox);

$preprocessing = (new CTable())
	->setId('preprocessing')
	->setHeader([
		$readonly ? null : '',
		new CColHeader(_('Name')),
		new CColHeader(_('Parameters')),
		new CColHeader(null),
		$readonly ? null : (new CColHeader(_('Action')))->setWidth(50)
	]);

foreach ($data['preprocessing'] as $i => $step) {
	// Depeding on preprocessing type, display corresponding params field and placeholders.
	$params = [];

	// Use numeric box for multiplier, otherwise use text box.
	if ($step['type'] == ZBX_PREPROC_MULTIPLIER) {
		$params[] = (new CTextBox('preprocessing['.$i.'][params][0]',
			array_key_exists('params', $step) ? $step['params'][0] : ''
		))
			->setAttribute('placeholder', _('number'))
			->setReadonly($readonly);
	}
	else {
		$params[] = (new CTextBox('preprocessing['.$i.'][params][0]',
			array_key_exists('params', $step) ? $step['params'][0] : ''
		))->setReadonly($readonly);
	}

	// Create a secondary param text box, so it can be hidden if necessary.
	$params[] = (new CTextBox('preprocessing['.$i.'][params][1]',
		(array_key_exists('params', $step) && array_key_exists(1, $step['params']))
			? $step['params'][1]
			: ''
	))
		->setAttribute('placeholder', _('output'))
		->setReadonly($readonly);

	// Add corresponding placeholders and show or hide text boxes.
	switch ($step['type']) {
		case ZBX_PREPROC_MULTIPLIER:
			$params[1]->addStyle('display: none;');
			break;

		case ZBX_PREPROC_RTRIM:
		case ZBX_PREPROC_LTRIM:
		case ZBX_PREPROC_TRIM:
			$params[0]->setAttribute('placeholder', _('list of characters'));
			$params[1]->addStyle('display: none;');
			break;

		case ZBX_PREPROC_XPATH:
		case ZBX_PREPROC_JSONPATH:
			$params[0]->setAttribute('placeholder', _('path'));
			$params[1]->addStyle('display: none;');
			break;

		case ZBX_PREPROC_REGSUB:
			$params[0]->setAttribute('placeholder', _('pattern'));
			break;

		case ZBX_PREPROC_BOOL2DEC:
		case ZBX_PREPROC_OCT2DEC:
		case ZBX_PREPROC_HEX2DEC:
		case ZBX_PREPROC_DELTA_VALUE:
		case ZBX_PREPROC_DELTA_SPEED:
			$params[0]->addStyle('display: none;');
			$params[1]->addStyle('display: none;');
			break;
	}

	if ($readonly) {
		$itemForm->addVar('preprocessing['.$i.'][type]', $step['type']);
	}

	$preproc_types_cbbox = new CComboBox('preprocessing['.$i.'][type]', $step['type']);

	foreach (get_preprocessing_types() as $group) {
		$cb_group = new COptGroup($group['label']);

		foreach ($group['types'] as $type => $label) {
			$cb_group->addItem(new CComboItem($type, $label, ($type == $step['type'])));
		}

		$preproc_types_cbbox->addItem($cb_group);
	}

	$preprocessing->addRow(
		(new CRow([
			$readonly
				? null
				: (new CCol(
					(new CDiv())->addClass(ZBX_STYLE_DRAG_ICON)
				))->addClass(ZBX_STYLE_TD_DRAG_ICON),
			$readonly
				? (new CTextBox('preprocessing['.$i.'][type_name]', get_preprocessing_types($step['type'])))
						->setReadonly(true)
				: $preproc_types_cbbox,
			$params[0],
			$params[1],
			$readonly
				? null
				: (new CButton('preprocessing['.$i.'][remove]', _('Remove')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->addClass('element-table-remove')
		]))->addClass('sortable')
	);
}

$preprocessing->addRow(
	$readonly
		? null
		: (new CCol(
			(new CButton('param_add', _('Add')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass('element-table-add')
		))->setColSpan(5)
);

$item_preproc_list = (new CFormList('item_preproc_list'))
	->addRow(_('Preprocessing steps'), $preprocessing);

// Append tabs to form.
$itemTab = (new CTabView())
	->addTab('itemTab', $data['caption'], $itemFormList)
	->addTab('preprocTab', _('Preprocessing'), $item_preproc_list);

if (!hasRequest('form_refresh')) {
	$itemTab->setSelected(0);
}

// Append buttons to form.
if ($data['itemid'] != 0) {
	$buttons = [new CSubmit('clone', _('Clone'))];

	if ($host['status'] == HOST_STATUS_MONITORED || $host['status'] == HOST_STATUS_NOT_MONITORED) {
		$buttons[] = new CButtonQMessage(
			'del_history',
			_('Clear history and trends'),
			_('History clearing can take a long time. Continue?')
		);
	}

	$buttons[] = (new CButtonDelete(_('Delete item?'), url_params(['form', 'itemid', 'hostid'])))
		->setEnabled(!$data['limited']);
	$buttons[] = new CButtonCancel(url_param('hostid'));

	$itemTab->setFooter(makeFormFooter(new CSubmit('update', _('Update')), $buttons));
}
else {
	$itemTab->setFooter(makeFormFooter(
		new CSubmit('add', _('Add')),
		[new CButtonCancel(url_param('hostid'))]
	));
}

$itemForm->addItem($itemTab);
$widget->addItem($itemForm);

require_once dirname(__FILE__).'/js/configuration.item.edit.js.php';

return $widget;
