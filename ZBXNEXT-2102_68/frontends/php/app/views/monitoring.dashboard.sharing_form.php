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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


if ($data['dashboardid']) {
	$form = (new CForm('post', (new CUrl('zabbix.php'))
		->setArgument('action', 'dashboard.update')
		->getUrl()
	))
	->setAttribute('id', 'dashboard_sharing_form')
	// indicator to help delete all users
	->addItem(new CInput('hidden', 'users['.CControllerDashboardUpdate::EMPTY_USER.']', '1'))
	// indicator to help delete all user groups
	->addItem(new CInput('hidden', 'userGroups['.CControllerDashboardUpdate::EMPTY_GROUP.']', '1'));

	// Create table and put a header on it.
	$table_user_groups = (new CTable())
		->setHeader([_('User groups'), _('Permissions'), _('Action')])
		->addStyle('width: 100%;');

	// Add user groups to the list.
	foreach ($data['userGroups'] as $user_groups) {
		$table_user_groups
			->addRow((new CRow([
				new CCol([
					(new CTextBox('userGroups['.$user_groups['usrgrpid'].'][usrgrpid]', $user_groups['usrgrpid']))->setAttribute('type', 'hidden'),
					$user_groups['name']
				]),
				new CCol(
					$a = (new CRadioButtonList('userGroups['.$user_groups['usrgrpid'].'][permission]', (integer) $user_groups['permission']))
						->addValue(_('Read-only'), PERM_READ, 'user_group_'.$user_groups['usrgrpid'].'_permission_'.PERM_READ)
						->addValue(_('Read-write'), PERM_READ_WRITE, 'user_group_'.$user_groups['usrgrpid'].'_permission_'.PERM_READ_WRITE)
						->setModern(true)
				),
				(new CCol(
					(new CButton('remove', _('Remove')))->addClass(ZBX_STYLE_BTN_LINK)
				))->addClass(ZBX_STYLE_NOWRAP)
			]))
			->setId('user_group_shares_'.$user_groups['usrgrpid'])
		);
	}

	// Add footer row.
	$table_user_groups
		->addRow(
			(new CRow(
				(new CCol(
					(new CButton(null, _('Add')))
						->onClick("return PopUp('popup.php?dstfrm=".$form->getName().
							"&srctbl=usrgrp&srcfld1=usrgrpid&srcfld2=name&multiselect=1')"
						)
						->addClass(ZBX_STYLE_BTN_LINK)
				))->setColSpan(3)
			))
			->setId('user_group_list_footer')
		);

	// Create table and put a header on it.
	$table_users = (new CTable())
		->setHeader([_('Users'), _('Permissions'), _('Action')])
		->addStyle('width: 100%;');

	// Add users to the list.
	foreach ($data['users'] as $user) {
		$table_users->addRow((new CRow([
				new CCol([
					(new CTextBox('users['.$user['userid'].'][userid]', $user['userid']))->setAttribute('type', 'hidden'),
					$user['name']
				]),
				new CCol(
					(new CRadioButtonList('users['.$user['userid'].'][permission]', (integer) $user['permission']))
						->addValue(_('Read-only'), PERM_READ, 'user_'.$user['userid'].'_permission_'.PERM_READ)
						->addValue(_('Read-write'), PERM_READ_WRITE, 'user_'.$user['userid'].'_permission_'.PERM_READ_WRITE)
						->setModern(true)
				),
				(new CCol(
					(new CButton('remove', _('Remove')))->addClass(ZBX_STYLE_BTN_LINK)
				))->addClass(ZBX_STYLE_NOWRAP)
			]))->setId('user_shares_'.$user['userid'])
		);
	}

	// Add footer row.
	$table_users
		->addRow(
			(new CRow(
				(new CCol(
					(new CButton(null, _('Add')))
						->onClick("return PopUp('popup.php?dstfrm=".$form->getName().
							"&srctbl=users&srcfld1=userid&srcfld2=fullname&multiselect=1')"
						)
						->addClass(ZBX_STYLE_BTN_LINK)
				))->setColSpan(3)
			))->setId('user_list_footer')
		);

	$form
		->addItem(new CInput('hidden', 'dashboardid', $data['dashboardid']))
		->addItem((new CFormList('sharing_form'))
			->addRow(_('Type'),
				(new CRadioButtonList('private', (integer) $data['private']))
					->addValue(_('Private'), PRIVATE_SHARING)
					->addValue(_('Public'), PUBLIC_SHARING)
					->setModern(true)
			)
			->addRow(_('List of user group shares'),
				(new CDiv($table_user_groups))
					->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
					->addStyle('min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
			)
			->addRow(_('List of user shares'),
				(new CDiv($table_users))
					->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
					->addStyle('min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;')
			)
		);

	$output = [
		'body' => $form->toString()
	];
}
else {
	$output = [];
}

if (($messages = getMessages()) !== null) {
	$output['messages'] = $messages->toString();
}

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo (new CJson())->encode($output);
