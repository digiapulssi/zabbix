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

$widgetConfig = new CWidgetConfig();
$formFields = $data['dialogue']['fields'];
$widgetId = $data['dialogue']['widgetid'];
$widgetType = $formFields['type'];

// TODO miks: Dashboard ID is needed to specify from wich dashboards tree widget should be picked in Map Widget's
// configuration window.
// BUT do not leave it here hardcoded:
$dashboardId = 1;

$form = (new CForm('post'))
	->cleanItems()
	->setId('widget_dialogue_form')
	->setName('widget_dialogue_form');

$formList = (new CFormList())
	->addRow(_('Type'), new CComboBox('type', $widgetType, 'updateConfigDialogue()', $data['known_widget_types_w_names']));

/*
 * Screen item: Clock
 */
if ($widgetType == WIDGET_CLOCK) {

	$time_type = array_key_exists('time_type', $formFields) ? $formFields['time_type'] : TIME_TYPE_LOCAL;
	$caption = array_key_exists('caption', $formFields) ? $formFields['caption'] : '';
	$itemId = array_key_exists('itemid', $formFields) ? $formFields['itemid'] : 0;

	if ($caption === '' && $time_type === TIME_TYPE_HOST && $itemId > 0) {
		$items = API::Item()->get([
			'output' => ['itemid', 'hostid', 'key_', 'name'],
			'selectHosts' => ['name'],
			'itemids' => $itemId,
			'webitems' => true
		]);

		if ($items) {
			$items = CMacrosResolverHelper::resolveItemNames($items);

			$item = reset($items);
			$host = reset($item['hosts']);
			$caption = $host['name'].NAME_DELIMITER.$item['name_expanded'];
		}
	}

	$formList->addRow(_('Time type'), new CComboBox('time_type', $time_type, 'updateConfigDialogue()', [
		TIME_TYPE_LOCAL => _('Local time'),
		TIME_TYPE_SERVER => _('Server time'),
		TIME_TYPE_HOST => _('Host time')
	]));

	if ($time_type == TIME_TYPE_HOST) {
		$form->addVar('itemid', $itemId);

		$selectButton = (new CButton('select', _('Select')))
				->addClass(ZBX_STYLE_BTN_GREY)
				->onClick("javascript: return PopUp('popup.php?dstfrm=".$form->getName().'&dstfld1=itemid'.
					"&dstfld2=caption&srctbl=items&srcfld1=itemid&srcfld2=name&real_hosts=1');");
		$cell = (new CDiv([
			(new CTextBox('caption', $caption, true))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			$selectButton
		]))->addStyle('display: flex;'); // TODO VM: move style to scss
		$formList->addRow(_('Item'), $cell);
	}
}

/*
 * Screen item: Sysmap
 * Not the best place to build custom config forms. Must discuss with Valdis.
 */
if ($widgetType == WIDGET_SYSMAP) {
	// widget name
	$formList->addRow(
		_('Name'),
		new CTextBox('widget_name', $formFields['widget_name'])
	);

	// source type
	$formList->addRow(
		_('Source type'),
		(new CRadioButtonList('source_type', $formFields['source_type']))
			->addValue(_('Filter'), WIDGET_NAVIGATION_TREE)
			->addValue(_('Map'), WIDGET_SYSMAP)
			->setModern(true)
	);

	// source - filter
	$filter_id = 0;
	$filter_caption = '';

	if (array_key_exists('filter_id', $formFields) && $formFields['source_type'] === WIDGET_NAVIGATION_TREE) {
		// TODO miks: make validation
		// Need API first
		/*
		$maps = API::Map()->get([
			'sysmapids' => $config_values['filter_id'],
			'output' => API_OUTPUT_EXTEND
		]);

		if (($map = reset($maps)) !== false) {
			$filter_caption = $map['name'];
			$filter_id = $map['sysmapid'];
		}
		*/

		// hardcoded
		$filter_id = $formFields['filter_id'];
		$filter_caption = 'Map Navigation Tree Widget';
	}

	$formList->addVar('filter_id', $filter_id);
	$div1 = (new CDiv)->addItem((new CLabel(_('Source'), 'filter_caption')))->addClass(ZBX_STYLE_TABLE_FORMS_TD_LEFT);
	$div2 = (new CDiv)->addItem([
			(new CTextBox('filter_caption', $filter_caption, true))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			(new CButton('select', _('Select')))
				->addClass(ZBX_STYLE_BTN_GREY)
				->onClick('javascript: return PopUp("popup.php?srctbl=filter_widgets&srcfld1=id&srcfld2=name'.
				'&dstfrm='.$form->getName().'&dstfld1=filter_id&dstfld2=filter_caption&dashboardid='.$dashboardId.'");'
			)
		])->addClass(ZBX_STYLE_TABLE_FORMS_TD_RIGHT);

	$filterRow = (new CListItem(null))
		->addItem($div1)
		->addItem($div2)
		->setAttribute('id', 'source-filter-row');

	if ($formFields['source_type'] !== WIDGET_NAVIGATION_TREE) {
		$filterRow->addStyle('display: none;');
	}

	$formList->addItem($filterRow);

	// source - map
	$sysmap_id = 0;
	$sysmap_caption = '';

	if (array_key_exists('sysmap_id', $formFields) && $formFields['sysmap_id']) {
		$maps = API::Map()->get([
			'sysmapids' => $formFields['sysmap_id'],
			'output' => API_OUTPUT_EXTEND
		]);

		if (($map = reset($maps)) !== false) {
			$sysmap_caption = $map['name'];
			$sysmap_id = $map['sysmapid'];
		}
	}

	$formList->addVar('sysmap_id', $sysmap_id);
	$div1 = (new CDiv)->addItem((new CLabel(_('Map'), 'sysmap_caption')))->addClass(ZBX_STYLE_TABLE_FORMS_TD_LEFT);
	$div2 = (new CDiv)->addItem([
			(new CTextBox('sysmap_caption', $sysmap_caption, true))->setWidth(ZBX_TEXTAREA_MEDIUM_WIDTH),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			(new CButton('select', _('Select')))
				->addClass(ZBX_STYLE_BTN_GREY)
				->onClick('javascript: return PopUp("popup.php?srctbl=sysmaps&srcfld1=sysmapid&srcfld2=name'.
				'&dstfrm='.$form->getName().'&dstfld1=sysmap_id&dstfld2=sysmap_caption");'
			)
		])->addClass(ZBX_STYLE_TABLE_FORMS_TD_RIGHT);

	$mapRow = (new CListItem(null))
		->addItem($div1)
		->addItem($div2)
		->setAttribute('id', 'source-map-row');

	$formList->addItem($mapRow);

	// add Javascript
	$js = '<script type="text/javascript">'
		. 'jQuery("[name=source_type]").change(function(){'
		. '	if(jQuery(this).val() == "navigationtree"){'
		. '		jQuery("#source-filter-row").show();'
		. '		jQuery("#source-map-row").show();'
		. '	} else {'
		. '		jQuery("#source-filter-row").hide();'
		. '		jQuery("#source-map-row").show();'
		. '	}'
		. '});'
		. '</script>';
	$formList->addItem((new CJsScript($js)));
}

/*
 * Screen item: Map navigation tree
 */
if ($widgetType == WIDGET_NAVIGATION_TREE) {
	// widget name
	$formList->addRow(
		_('Name'),
		new CTextBox('widget_name', $formFields['widget_name'])
	);
}

// URL field
if (in_array($widgetType, [WIDGET_URL])) {
	$url = array_key_exists('url', $formFields) ? $formFields['url'] : '';
	$formList->addRow(_('URL'), (new CTextBox('url', $url))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH));
}

// Width and height fields
if (in_array($widgetType, [WIDGET_CLOCK, WIDGET_URL])) {
	$width = array_key_exists('inner_width', $formFields) ? $formFields['inner_width'] : 0;
	$height = array_key_exists('inner_height', $formFields) ? $formFields['inner_height'] : 0;
	$formList->addRow(_('Width'), (new CNumericBox('inner_width', $width, 5))->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH));
	$formList->addRow(_('Height'), (new CNumericBox('inner_height', $height, 5))->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH));
}

$form->addItem($formList);

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
