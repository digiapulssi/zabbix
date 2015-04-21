<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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

$page['title'] = _('Configuration of trigger displaying options');
$page['file'] = 'adm.triggerdisplayoptions.php';
$page['hist_arg'] = array();

require_once dirname(__FILE__).'/include/page_header.php';

$fields = array(
	'problem_unack_color' =>	array(T_ZBX_CLR, O_OPT, null, null, 'isset({update})',
		_('Unacknowledged PROBLEM events')
	),
	'problem_ack_color' =>		array(T_ZBX_CLR, O_OPT, null, null, 'isset({update})',
		_('Acknowledged PROBLEM events')
	),
	'ok_unack_color' =>			array(T_ZBX_CLR, O_OPT, null, null, 'isset({update})', _('Unacknowledged OK events')),
	'ok_ack_color' =>			array(T_ZBX_CLR, O_OPT, null, null, 'isset({update})', _('Acknowledged OK events')),
	'problem_unack_style' =>	array(T_ZBX_INT, O_OPT, null, IN('1'), null, _('Blinking')),
	'problem_ack_style' =>		array(T_ZBX_INT, O_OPT, null, IN('1'), null, _('Blinking')),
	'ok_unack_style' =>			array(T_ZBX_INT, O_OPT, null, IN('1'), null, _('Blinking')),
	'ok_ack_style' =>			array(T_ZBX_INT, O_OPT, null, IN('1'), null, _('Blinking')),
	'ok_period' =>				array(T_ZBX_INT, O_OPT, null, BETWEEN(0, 999999), 'isset({update})',
		_('Display OK triggers for')
	),
	'blink_period' =>			array(T_ZBX_INT, O_OPT, null, BETWEEN(0, 999999), 'isset({update})',
		_('On status change triggers blink for')
	),
	// actions
	'update'=>					array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, null, null),
	'form_refresh' =>			array(T_ZBX_INT, O_OPT, null, null, null)
);
check_fields($fields);

/*
 * Actions
 */
if (hasRequest('update')) {
	DBstart();
	$result = update_config(array(
		'problem_unack_color' => getRequest('problem_unack_color'),
		'problem_ack_color' => getRequest('problem_ack_color'),
		'ok_unack_color' => getRequest('ok_unack_color'),
		'ok_ack_color' => getRequest('ok_ack_color'),
		'problem_unack_style' => getRequest('problem_unack_style', 0),
		'problem_ack_style' => getRequest('problem_ack_style', 0),
		'ok_unack_style' => getRequest('ok_unack_style', 0),
		'ok_ack_style' => getRequest('ok_ack_style', 0),
		'ok_period' => getRequest('ok_period'),
		'blink_period' => getRequest('blink_period')
	));
	$result = DBend($result);

	show_messages($result, _('Configuration updated'), _('Cannot update configuration'));
}

/*
 * Display
 */
$form = new CForm();
$form->cleanItems();
$form->addItem(new CComboBox('configDropDown', 'adm.triggerdisplayoptions.php',
	'redirect(this.options[this.selectedIndex].value);',
	array(
		'adm.gui.php' => _('GUI'),
		'adm.housekeeper.php' => _('Housekeeping'),
		'adm.images.php' => _('Images'),
		'adm.iconmapping.php' => _('Icon mapping'),
		'adm.regexps.php' => _('Regular expressions'),
		'adm.macros.php' => _('Macros'),
		'adm.valuemapping.php' => _('Value mapping'),
		'adm.workingtime.php' => _('Working time'),
		'adm.triggerseverities.php' => _('Trigger severities'),
		'adm.triggerdisplayoptions.php' => _('Trigger displaying options'),
		'adm.other.php' => _('Other')
	)
));

$cnf_wdgt = new CWidget();
$cnf_wdgt->addPageHeader(_('CONFIGURATION OF ZABBIX'), $form);

$config = select_config();

// form has been submitted
if (hasRequest('form_refresh')) {
	$data = array(
		'problem_unack_color' => getRequest('problem_unack_color', $config['problem_unack_color']),
		'problem_ack_color' => getRequest('problem_ack_color', $config['problem_ack_color']),
		'ok_unack_color' => getRequest('ok_unack_color', $config['ok_unack_color']),
		'ok_ack_color' => getRequest('ok_ack_color', $config['ok_ack_color']),
		'problem_unack_style' => getRequest('problem_unack_style', 0),
		'problem_ack_style' => getRequest('problem_ack_style', 0),
		'ok_unack_style' => getRequest('ok_unack_style', 0),
		'ok_ack_style' => getRequest('ok_ack_style', 0),
		'ok_period' => getRequest('ok_period', $config['ok_period']),
		'blink_period' => getRequest('blink_period', $config['blink_period'])
	);
}
else {
	$data = array(
		'problem_unack_color' => $config['problem_unack_color'],
		'problem_ack_color' => $config['problem_ack_color'],
		'ok_unack_color' => $config['ok_unack_color'],
		'ok_ack_color' => $config['ok_ack_color'],
		'problem_unack_style' => $config['problem_unack_style'],
		'problem_ack_style' => $config['problem_ack_style'],
		'ok_unack_style' => $config['ok_unack_style'],
		'ok_ack_style' => $config['ok_ack_style'],
		'ok_period' => $config['ok_period'],
		'blink_period' => $config['blink_period']
	);
}

$triggerDisplayingForm = new CView('administration.general.triggerDisplayOptions.edit', $data);
$cnf_wdgt->addItem($triggerDisplayingForm->render());
$cnf_wdgt->show();

require_once dirname(__FILE__).'/include/page_footer.php';
