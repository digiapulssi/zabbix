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

// TODO miks: check permissions to avoid nonauthorised users to see the tree.

$configData = [
	'source_type' => WIDGET_NAVIGATION_TREE,
	'filter_id' => 1,
	'sysmap_id' => 0,
	'widgetid' => 0,
	'severity_min' => 0,
	'fullscreen' => 0
];

$post_fields = getRequest('fields', []);
if (array_key_exists('sysmap_id', $post_fields) && $post_fields['sysmap_id']) {
	$configData['sysmap_id'] = $post_fields['sysmap_id'];
}
if (array_key_exists('filter_id', $post_fields) && $post_fields['filter_id']) {
	$configData['filter_id'] = $post_fields['filter_id'];
}
if (array_key_exists('source_type', $post_fields) && $post_fields['source_type']) {
	$configData['source_type'] = $post_fields['source_type'];
}
if (isset($_REQUEST['widgetid']) && $_REQUEST['widgetid']) {
	$configData['widgetid'] = $_REQUEST['widgetid'];
}
if (hasRequest('widgetid')) {
	$configData['widgetid'] = getRequest('widgetid');
}

$item = (new CSysmap($configData));

if ($data['sysmap']['error'] !== null) {
	$item->setError($data['sysmap']['error']);
}

$output = [
	'header' => $data['sysmap']['title']?:_('Map widget'),
	'body' => $item->toString(),
	'footer' => (new CList([_s('Updated: %s', zbx_date2str(TIME_FORMAT_SECONDS))]))->toString(),
	'script_file' => $item->getScriptFile(),
	'script_inline' => $item->getScriptRun()
];

if (($messages = getMessages()) !== null) {
	$output['messages'] = $messages->toString();
}

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo (new CJson())->encode($output);
