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


require_once dirname(__FILE__).'/../include/class.czabbixtest.php';

class API_JSON_User extends CZabbixTest {

	public static function authenticate_data() {
		return [
			[['user' => 'Admin', 'password' => 'wrong password'], false],
			[['user' => 'Admin', 'password' => 'zabbix'], true],
			[['password' => 'zabbix', 'user' => 'Admin'], true],
			[['user' => 'Unknown user', 'password' => 'zabbix'], false],
			[['user' => 'Admin'], false],
			[['password' => 'zabbix'], false],
			[['user' => '!@#$%^&\\\'\"""\;:', 'password' => 'zabbix'], false],
			[['password' => '!@#$%^&\\\'\"""\;:', 'user' => 'zabbix'], false]
		];
	}

	public static function user_data() {
		return [
			[
				'user' => [
				],
				'success_expected' => false,
				'expected_error' => 'Wrong fields for user'
			],
			[
				'user' => [
					'alias' => 'Test User 1'
				],
				'success_expected' => false,
				'expected_error' => 'Wrong fields for user'
			],
			[
				'user' => [
					'alias' => 'Test User 1',
					'passwd' => 'zabbix'
				],
				'success_expected' => false,
				'expected_error' => 'Wrong fields for user'
			],
			[
				'user' => [
					'alias' => 'Test User 1',
					'passwd' => 'zabbix',
					'usrgrps' => [
						'usrgrpid' => 7 // Zabbix administrators
					]
				],
				'success_expected' => true,
				'expected_error' => null
			],
			[
				'user' => [
					'alias' => 'УТФ Юзер',
					'passwd' => 'zabbix',
					'usrgrps' => [
						'usrgrpid' => 7 // Zabbix administrators
					]
				],
				'success_expected' => true,
				'expected_error' => null
			],
			[
				'user' => [
					'alias' => 'Admin',
					'passwd' => 'zabbix',
					'usrgrps' => [
						'usrgrpid' => 7 // Zabbix administrators
					]
				],
				'success_expected' => false,
				'expected_error' => 'already exists'
			],
			[
				'user' => [
					'alias' => 'Test User 2',
					'passwd' => 'zabbix',
					'usrgrps' => [
						'usrgrpid' => 7 // Zabbix administrators
					],
					'name' => 'Test User 2 Name',
					'surname' => 'Test User 2 Surname',
					'url' => '',
					'autologin' => 0,
					'autologout' => 600,
					'lang' => 'en_gb',
					'refresh' => 90,
					'type' => 1,
					'theme' => 'originalblue',
					'rows_per_page' => 50
				],
				'success_expected' => true,
				'expected_error' => null
			],
			[
				'user' => [
					'alias' => 'qwertyuioplkjhgfdsazxcvbnmqwertyuioplkjhgfdsazxcvbnmqwertyuioplkjhgfdsazxcvbnmqwertyuioplkjhgfdsazxcvbnm',
					'passwd' => 'zabbix',
					'usrgrps' => [
						'usrgrpid' => 7 // Zabbix administrators
					]
				],
				'success_expected' => false,
				'expected_error' => 'Maximum alias length'
			]
		];
	}

	public function testUser_backup() {
		DBsave_tables('users');
	}

	/**
	* @dataProvider authenticate_data
	*/
	public function testUser_Authenticate($data, $expect) {
		$result = $this->api_call('user.login', $data, $debug);

		if ($expect) {
			$this->assertTrue(isset($result['result']), $debug);
		}
		else {
			$this->assertTrue(isset($result['error']), $debug);
		}
	}

	/**
	* @dataProvider user_data
	*/
	public function testUser_Create($user, $success_expected, $expected_error) {
		$debug = null;

		$result = $this->api_acall('user.create', [$user], $debug);

		if ($success_expected) {
			$this->assertFalse(array_key_exists('error', $result), $debug);

			$dbResult = DBSelect('select * from users where userid='.$result['id']);
			$dbRow = DBFetch($dbResult);
			$this->assertTrue(!isset($dbRow['alias']) || $dbRow['alias'] != $user['alias'], print_r($dbRow, true));
		}
		else {
			$this->assertTrue(array_key_exists('error', $result), $debug);
			$this->assertTrue(strpos($result['error']['data'], $expected_error) !== false, print_r($result, true));

			// checking if record was not inserted in the DB
			$dbResult = DBSelect('select * from users where userid='.$result['id']);
			$dbRow = DBFetch($dbResult);
			$this->assertTrue(isset($dbRow['alias']), print_r($dbRow, true));
		}
	}

	public function testUser_restore() {
		DBrestore_tables('users');
	}

}
