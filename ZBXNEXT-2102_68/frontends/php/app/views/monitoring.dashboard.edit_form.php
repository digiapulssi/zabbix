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

if (array_key_exists('dashboardid', $data)) {
	$form = (new CForm())
		->setAttribute('id', 'dashboard_form')
		->setName('dashboard_form');

	$multiselect = (new CMultiSelect([
		'name' => 'userid',
		'selectedLimit' => 1,
		'objectName' => 'users',
		'disabled' => (CWebUser::getType() != USER_TYPE_SUPER_ADMIN && CWebUser::getType() != USER_TYPE_ZABBIX_ADMIN),
		'popup' => [
			'parameters' => 'srctbl=users&dstfrm='.$form->getName().'&dstfld1=userid&srcfld1=userid&srcfld2=fullname'
		],
		'callPostEvent' => false
	]))
	->setAttribute('data-default-owner', CJs::encodeJson($data['owner']))
	->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH);

	$form->addItem((new CFormList())
		->addRow(_('Owner'), $multiselect)
		->addRow(_('Name'),
			(new CTextBox('name', $data['name'], false, DB::getFieldLength('dashboard', 'name')))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAttribute('autofocus', 'autofocus')
		)
	);

	$js_scripts = [
		$multiselect->getPostJS()
	];

	if ($data['owner']) {
		$js_scripts[] = 'jQuery("#userid").multiSelect("addData", '.CJs::encodeJson($data['owner']).')';
	}

	// Submit button is needed to enable submit event on Enter on inputs.
	$form->addItem((new CInput('submit', 'dashboard_widget_config_submit'))->addStyle('display: none;'));

	$output = [
		'body' => $form->toString() . get_js(implode("\n", $js_scripts))
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
