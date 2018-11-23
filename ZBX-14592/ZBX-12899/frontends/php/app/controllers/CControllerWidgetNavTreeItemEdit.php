<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

require_once dirname(__FILE__).'/../../include/blocks.inc.php';

class CControllerWidgetNavTreeItemEdit extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'name' => 'required|string',
			'sysmapid' => 'db sysmaps.sysmapid',
			'depth' => 'ge 0|le '.WIDGET_NAVIGATION_TREE_MAX_DEPTH
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseData(['main_block' => CJs::encodeJson('')]));
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->getUserType() >= USER_TYPE_ZABBIX_USER);
	}

	protected function doAction() {
		$sysmapid = $this->getInput('sysmapid', 0);
		$depth = $this->getInput('depth', 1);

		// build form
		$form = (new CForm('post'))
			->cleanItems()
			->setId('widget_dialogue_form')
			->setName('widget_dialogue_form')
			->addItem((new CInput('submit', 'submit'))->addStyle('display: none;'));

		$formList = new CFormList();
		$formList->addRow(_('Name'),
			(new CTextBox('name', $this->getInput('name', '')))
				->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH)
				->setAttribute('autofocus', 'autofocus')
		);

		$sysmap = ['sysmapid' => 0, 'name' => ''];

		if ($sysmapid != 0) {
			$sysmaps = API::Map()->get([
				'sysmapids' => [$sysmapid],
				'output' => ['name', 'sysmapid']
			]);

			if ($sysmaps) {
				$sysmap = $sysmaps[0];
			}
			else {
				$sysmap['name'] = _('Inaccessible map');
			}
		}

		$formList->addVar('sysmapid', $sysmap['sysmapid']);
		$formList->addRow(_('Linked map'), [
			(new CTextBox('sysmapname', $sysmap['name'], true))
				->setAttribute('onChange',
					'javascript: if(jQuery("#'.$form->getName().' input[type=text]:first").val() === ""){'.
						'jQuery("#widget_dialogue_form input[type=text]:first").val(this.value);}')
				->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			(new CButton('select', _('Select')))
				->addClass(ZBX_STYLE_BTN_GREY)
				->onClick('return PopUp("popup.generic",'.
					CJs::encodeJson([
						'srctbl' => 'sysmaps',
						'srcfld1' => 'sysmapid',
						'srcfld2' => 'name',
						'dstfrm' => $form->getName(),
						'dstfld1' => 'sysmapid',
						'dstfld2' => 'sysmapname'
					]).', null, this);'
				)
		]);

		if ($depth >= WIDGET_NAVIGATION_TREE_MAX_DEPTH) {
			$formList->addRow(null, _('Cannot add submaps. Max depth reached.'));
		}
		else {
			$formList->addRow(null, [
				new CCheckBox('add_submaps', 1),
				new CLabel(_('Add submaps'), 'add_submaps')
			]);
		}

		$form->addItem($formList);

		// prepare output
		$output = [
			'body' => $form->toString()
		];

		if (($messages = getMessages()) !== null) {
			$output['messages'] = $messages->toString();
		}

		if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
			CProfiler::getInstance()->stop();
			$output['debug'] = CProfiler::getInstance()->make()->toString();
		}

		echo (new CJson())->encode($output);
	}
}
