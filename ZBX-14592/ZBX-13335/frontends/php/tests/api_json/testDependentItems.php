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

/**
 * @backup items
 */
class testDependentItems extends CZabbixTest {

	public static function getUpdateData() {
		return [
			[
				'error' => 'Incorrect value for field "master_itemid": maximum number of dependency levels reached.',
				'method' => 'item.update',
				'request_data' => [
					'itemid' => 40554,
					'type' => ITEM_TYPE_DEPENDENT,
					'master_itemid' => 40553
				]
			],
			[
				'error' => 'Incorrect value for field "master_itemid": circular item dependency is not allowed.',
				'method' => 'item.update',
				'request_data' => [
					'itemid' => 40569,
					'master_itemid' => 40573
				]
			],
			[
				'error' => 'Incorrect value for field "master_itemid": should be empty.',
				'method' => 'item.update',
				'request_data' => [
					'itemid' => 40575,
					'master_itemid' => 40574
				]
			],
			[
				'error' => 'Incorrect value for field "master_itemid": maximum number of dependency levels reached.',
				'method' => 'item.update',
				'request_data' => [
					'itemid' => 40575,
					'type' => ITEM_TYPE_DEPENDENT,
					'master_itemid' => 40574
				]
			],
			[
				'error' => 'Incorrect value for field "master_itemid": maximum number of dependency levels reached.',
				'method' => 'template.update',
				'request_data' => [
					'templateid' => 99009,
					'hosts' => [
						['hostid' => 99008]
					]
				]
			]
		];
	}

	/**
	* @dataProvider getUpdateData
	*/
	public function testDependentItems_Update($error, $method, $request_data) {
		$result = $this->api_acall($method, $request_data, $debug);
		$message = array_key_exists('error', $result) ? json_encode($result['error']) : '';

		if ($error) {
			$this->assertArrayHasKey('error', $result, json_encode($result));
			$this->assertArrayHasKey('data', $result['error'], $message);
			$this->assertEquals($error, $result['error']['data']);
		}
		else {
			$this->assertArrayNotHasKey('error', $result, $message);
		}
	}

	public static function getCreateData() {
		$items = [];

		for ($index = 3; $index < 1000; $index++) {
			$items[] = [
				'name' => 'dependent_'.$index,
				'key_' => 'dependent_'.$index,
				'hostid' => 99009,
				'interfaceid' => null,
				'type' => ITEM_TYPE_DEPENDENT,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'delay' => 0,
				'history' => '90d',
				'status' => ITEM_STATUS_ACTIVE,
				'params' => '',
				'description' => '',
				'flags' => 0,
				'master_itemid' => 40581
			];
		}

		return [
			[
				'error' => 'Incorrect value for field "master_itemid": maximum dependent items count reached.',
				'method' => 'item.create',
				'request_data' => $items
			],
			[
				'error' => false,
				'method' => 'item.create',
				'request_data' => array_slice($items, 1)
			]
		];
	}

	/**
	* @dataProvider getCreateData
	*/
	public function testDependentItems_Create($error, $method, $request_data) {
		$result = $this->api_acall($method, $request_data, $debug);
		$message = array_key_exists('error', $result) ? json_encode($result['error']) : '';

		if ($error) {
			$this->assertArrayHasKey('error', $result, json_encode($result));
			$this->assertArrayHasKey('data', $result['error'], $message);
			$this->assertEquals($error, $result['error']['data']);
		}
		else {
			$this->assertArrayNotHasKey('error', $result, $message);
		}
	}
}
