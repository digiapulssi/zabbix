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


class CControllerNotificationsMute extends CController {

	protected function checkInput() {
		$fields = [
			'mute' => 'required|int32|in 0,1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			http_response_code(400);
			$this->setResponse(new CControllerResponseData(['main_block' => 'Incorrect request.']));
		}

		return $ret;
	}

	protected function checkPermissions() {
		return ($this->getUserType() >= USER_TYPE_ZABBIX_USER);
	}

	protected function doAction() {
		$msgsettings = getMessageSettings();

		$msgsettings['sounds.mute'] = $this->input['mute'];

		updateMessageSettings($msgsettings);

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($this->input)]));
	}
}

