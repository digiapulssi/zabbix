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


require_once dirname(__FILE__).'/../include/class.czabbixtest.php';

class testDependentItems extends CZabbixTest {

	public static function dataProvider() {
		return [
			[
				'error' => 'Incorrect value for field "master_itemid": maximum number of dependency levels reached.',
				'request_data' => [
					'itemid' => 40554,
					'type' => ITEM_TYPE_DEPENDENT,
					'master_itemid' => 40553
				]
			],
			[
				'error' => 'Incorrect value for field "master_itemid": circular item dependency is not allowed.',
				'request_data' => [
					'itemid' => 40569,
					'master_itemid' => 40573
				]
			]
		];
	}

	/**
	* @dataProvider dataProvider
	*/
	public function testUpdate($error, $request_data) {
		$result = $this->api_acall('item.update', $request_data, $debug);
		$message = array_key_exists('error', $result) ? json_encode($result['error']) : '';

		if ($error) {
			$this->assertArrayHasKey('error', $result, json_encode($result));
			$this->assertArrayHasKey('data', $result['error'], $message);
			$this->assertSame($error, $result['error']['data']);
		}
		else {
			$this->assertArrayNotHasKey('error', $result, $message);
		}
	}
}
