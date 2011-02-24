<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
// include JS + templates
	include('include/templates/script.js.php');
	include('include/templates/scriptConfirm.js.php');
?>
<?php
	$scriptTab = new CFormList('scriptsTab');
	$frmScr = new CForm();
	$frmScr->setName('scripts');
	$frmScr->addVar('form', get_request('form', 1));

	$from_rfr = get_request('form_refresh',0);
	$frmScr->addVar('form_refresh', $from_rfr+1);


	if(isset($_REQUEST['scriptid'])) $frmScr->addVar('scriptid', $_REQUEST['scriptid']);

	if(!isset($_REQUEST['scriptid']) || isset($_REQUEST['form_refresh'])){
		$name = get_request('name', '');
		$command  = get_request('command', '');
		$description  = get_request('description', '');
		$usrgrpid = get_request('usrgrpid',	0);
		$groupid = get_request('groupid', 0);
		$access = get_request('access',	PERM_READ_ONLY);
		$confirmation = get_request('confirmation',	'');
		$enableConfirmation = get_request('enableConfirmation', false);
	}

	if(isset($_REQUEST['scriptid']) && !isset($_REQUEST['form_refresh'])){
		$frmScr->addVar('form_refresh', get_request('form_refresh',1));

		$options = array(
			'scriptids' => $_REQUEST['scriptid'],
			'output' => API_OUTPUT_EXTEND,
		);
		$script = API::Script()->get($options);
		$script = reset($script);

		$name = $script['name'];
		$command  = $script['command'];
		$description = $script['description'];
		$usrgrpid = $script['usrgrpid'];
		$groupid = $script['groupid'];
		$access = $script['host_access'];
		$confirmation = $script['confirmation'];
		$enableConfirmation = !empty($confirmation);
	}

// NAME
	$nameTB = new CTextBox('name', $name);
	$nameTB->setAttribute('maxlength', 255);
	$nameTB->addStyle('width: 50em');
	$scriptTab->addRow(_('Name'), $nameTB);

// COMMAND
	$commandTB = new CTextBox('command', $command);
	$commandTB->setAttribute('maxlength', 255);
	$commandTB->addStyle('width: 50em');
	$scriptTab->addRow(_('Command'), $commandTB);

// DESCRIPTION
	$description_ta = new CTextArea('description', $description);
	$description_ta->addStyle('width: 50em; padding: 0;');
	$scriptTab->addRow(_('Description'), $description_ta);

// USER GROUPS
	$usr_groups = new CCombobox('usrgrpid', $usrgrpid);
	$usr_groups->addItem(0, _('All'));
	$usrgrps = API::UserGroup()->get(array(
		'output' => API_OUTPUT_EXTEND,
	));
	order_result($usrgrps, 'name');
	foreach($usrgrps as $ugnum => $usr_group){
		$usr_groups->addItem($usr_group['usrgrpid'], $usr_group['name']);
	}
	$scriptTab->addRow(_('User groups'), $usr_groups);

// HOST GROUPS
	$host_groups = new CCombobox('groupid', $groupid);
	$host_groups->addItem(0, _('All'));
	$groups = API::HostGroup()->get(array(
		'output' => API_OUTPUT_EXTEND,
	));
	order_result($groups, 'name');
	foreach($groups as $gnum => $group){
		$host_groups->addItem($group['groupid'], $group['name']);
	}
	$scriptTab->addRow(_('Host groups'), $host_groups);

// PERMISSIONS
	$select_acc = new CCombobox('access', $access);
	$select_acc->addItem(PERM_READ_ONLY, _('Read'));
	$select_acc->addItem(PERM_READ_WRITE, _('Write'));
	$scriptTab->addRow(_('Required host permissions'), $select_acc);

// CONFIRMATION
	$enableQuestCB = new CCheckBox('enableConfirmation', $enableConfirmation);
	$scriptTab->addRow(new CLabel(_('Enable confirmation'), 'enableConfirmation'), array($enableQuestCB, SPACE));

	$confirmationTB = new CTextBox('confirmation', $confirmation);
	$confirmationTB->addStyle('width: 50em;');
	$confirmationTB->setAttribute('maxlength', 255);

	$testLink = new CButton('testConfirmation', _('Test confirmation'), null, 'link_menu');

	$confirmationLabel = new CLabel(_('Confirmation text'), 'confirmation');
	$confirmationLabel->setAttribute('id', 'confirmationLabel');
	$scriptTab->addRow($confirmationLabel, array($confirmationTB, SPACE, $testLink));

	$scriptView = new CTabView();
	$scriptView->addTab('scripts', _('Script'), $scriptTab);
	$frmScr->addItem($scriptView);


// Footer
	$main = array(new CSubmit('save', _('Save')));
	$others = array();
	if(isset($_REQUEST['scriptid'])){
		$others[] = new CButton('clone', _('Clone'));
		$others[] = new CButtonDelete(_('Delete script?'), url_param('form').url_param('scriptid'));
	}
	$others[] = new CButtonCancel();

	$footer = makeFormFooter($main, $others);
	$frmScr->addItem($footer);


	return $frmScr;
?>
