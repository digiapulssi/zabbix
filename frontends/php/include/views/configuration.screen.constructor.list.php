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


$screenWidget = (new CWidget())->setTitle(_('Screens'))->addHeader($this->data['screen']['name']);
if (!empty($this->data['screen']['templateid'])) {
	$screenWidget->addItem(get_header_host_table('screens', $this->data['screen']['templateid']));
}
$screenWidget->addItem(BR());

$screenBuilder = new CScreenBuilder([
	'isFlickerfree' => false,
	'screen' => $this->data['screen'],
	'mode' => SCREEN_MODE_EDIT,
	'updateProfile' => false
]);
$screenWidget->addItem($screenBuilder->show());

$screenBuilder->insertInitScreenJs($this->data['screenid']);
$screenBuilder->insertProcessObjectsJs();

return $screenWidget;
