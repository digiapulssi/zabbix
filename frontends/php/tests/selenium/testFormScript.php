<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/
?>
<?php
require_once dirname(__FILE__).'/../include/class.cwebtest.php';

class testFormScript extends CWebTest {
	// Data provider
	public static function providerScripts() {
		// data - values for form inputs
		// saveResult - if save action should be successful
		// dbValues - values which should be in db if saveResult is true
		$data = array(
			array(
				array(
					array('name' => 'name', 'value' => 'script', 'type' => 'text'),
					array('name' => 'command', 'value' => 'run', 'type' => 'text')
				),
				true,
				array(
					'name' => 'script',
					'command' => 'run'
				)
			),
			array(
				array(
					array('name' => 'name', 'value' => 'script', 'type' => 'text'),
					array('name' => 'command', 'value' => 'run', 'type' => 'text'),
					array('name' => 'type', 'value' => 'IPMI', 'type' => 'select')
				),
				true,
				array(
					'name' => 'script',
					'command' => 'run',
					'type' => 1
				)
			),
			array(
				array(
					array('name' => 'name', 'value' => 'script', 'type' => 'text'),
					array('name' => 'command', 'value' => 'run', 'type' => 'text'),
					array('name' => 'enableConfirmation', 'type' => 'check')
				),
				false,
				array()
			),
			array(
				array(
					array('name' => 'name', 'value' => 'script', 'type' => 'text'),
					array('name' => 'command', 'value' => '', 'type' => 'text')
				),
				false,
				array()
			)
		);
		return $data;
	}


	public function testLayout() {
		$this->login('scripts.php?form=1');
		$this->assertTitle('Scripts');

		$this->ok('Script');

		$this->ok(array('Name'));
		$this->assertElementPresent('name');

		$this->ok(array('Type'));
		$this->assertElementPresent('type');
		$this->assertSelectHasOption('type', 'IPMI');
		$this->assertSelectHasOption('type', 'Script');

		$this->ok(array('Execute on', 'Zabbix agent', 'Zabbix server'));
		$this->assertElementPresent('execute_on_1');
		$this->assertElementPresent('execute_on_2');

		$this->ok(array('Command'));
		$this->assertElementPresent('command');

		$this->ok(array('Description'));
		$this->assertElementPresent('description');

		$this->ok(array('User groups'));
		$this->assertElementPresent('usrgrpid');

		$this->ok(array('Host groups'));
		$this->assertElementPresent('groupid');

		$this->ok(array('Required host permissions'));
		$this->assertElementPresent('access');
		$this->assertSelectHasOption('access', 'Read');
		$this->assertSelectHasOption('access', 'Write');

		$this->ok(array('Enable confirmation'));
		$this->assertElementPresent('enableConfirmation');
		$this->assertNotChecked('enableConfirmation');

		$this->ok(array('Confirmation text'));
		$this->assertElementPresent('confirmation');
	}

	/**
	 * @dataProvider providerScripts
	 */
	public function testCreate($data, $resultSave, $DBvalues) {

		DBsave_tables('scripts');

		$this->login('scripts.php?form=1');

		foreach ($data as $field) {
			switch ($field['type']) {
				case 'text':
					$this->input_type($field['name'], $field['value']);
					break;
				case 'select':
					$this->select($field['name'], $field['value']);
					break;
				case 'check':
					$this->check($field['name']);
					break;
			}

			if ($field['name'] == 'name') {
				$keyField = $field['value'];
			}
		}

		$sql = 'SELECT '.implode(', ', array_keys($DBvalues)).' FROM scripts';
		if ($resultSave && isset($keyField))
			$sql .= ' WHERE name='.zbx_dbstr($keyField);

		if (!$resultSave) {
			$sql = 'SELECT * FROM scripts';
			$DBhash = DBhash($sql);
		}

		$this->click('save');
		$this->wait();


		if ($resultSave) {
			$this->ok('Script added');

			$dbres = DBfetch(DBselect($sql));
			foreach ($dbres as $field => $value) {
				$this->assertEquals($value, $DBvalues[$field], "Value for '$field' was not updated.");
			}
		}
		else {
			$this->ok('ERROR:');
			$this->assertEquals($DBhash, DBhash($sql), "DB fields changed after unsuccessful save.");
		}

		DBrestore_tables('scripts');
	}

}
?>
