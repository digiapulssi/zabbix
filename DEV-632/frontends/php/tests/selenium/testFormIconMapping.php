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

require_once dirname(__FILE__).'/../include/class.cwebtest.php';

class testFormIconMapping extends CWebTest {

	public function testFormIconMapping_backup() {
		DBsave_tables('icon_map');
	}

	public function testFormIconMapping_CheckLayout() {
		$this->zbxTestLogin('adm.iconmapping.php');
		$this->zbxTestClickWait('form');
		$this->zbxTestCheckHeader('Icon mapping');

		$this->zbxTestAssertElementPresentId('iconmap_name');
		$this->zbxTestAssertAttribute("//input[@id='iconmap_name']", 'maxlength', 64);

		$this->zbxTestAssertElementPresentId('iconmap_mappings_new0_expression');
		$this->zbxTestAssertAttribute("//input[@id='iconmap_mappings_new0_expression']", 'maxlength', 64);

		$this->zbxTestDropdownHasOptions('iconmap_mappings_new0_inventory_link', ['Type', 'Type (Full details)', 'Name',
			'Alias', 'OS', 'OS (Full details)', 'OS (Short)', 'Serial number A', 'Serial number B', 'Tag', 'Asset tag',
			'MAC address A', 'MAC address B', 'Hardware', 'Hardware (Full details)', 'Software', 'Software (Full details)',
			'Software application A', 'Software application B', 'Software application C', 'Software application D', 'Software application E',
			'Contact', 'Location', 'Location latitude', 'Location longitude', 'Notes', 'Chassis', 'Model', 'HW architecture',
			'Vendor', 'Contract number', 'Installer name', 'Deployment status', 'URL A', 'URL B', 'URL C', 'Host networks',
			'Host subnet mask', 'Host router', 'OOB IP address', 'OOB subnet mask', 'OOB router', 'Date HW purchased',
			'Date HW installed', 'Date HW maintenance expires', 'Date HW decommissioned', 'Site address A', 'Site address B',
			'Site address C', 'Site city', 'Site state / province', 'Site country', 'Site ZIP / postal', 'Site rack location',
			'Site notes', 'Primary POC name', 'Primary POC email', 'Primary POC phone A', 'Primary POC phone B', 'Primary POC cell',
			'Primary POC screen name', 'Primary POC notes', 'Secondary POC name', 'Secondary POC email', 'Secondary POC phone A',
			'Secondary POC phone B', 'Secondary POC cell', 'Secondary POC screen name', 'Secondary POC notes']);
		$this->zbxTestDropdownHasOptions('iconmap_mappings_new0_iconid', ['Cloud_(24)', 'Cloud_(48)', 'Cloud_(64)',
			'Cloud_(96)', 'Cloud_(128)', 'Crypto-router_(24)', 'Crypto-router_symbol_(24)', 'IP_PBX_(24)', 'Video_terminal_(24)']);
		$this->zbxTestDropdownHasOptions('iconmap_default_iconid', ['Cloud_(24)', 'Cloud_(48)', 'Cloud_(64)',
			'Cloud_(96)', 'Cloud_(128)', 'Crypto-router_(24)', 'Crypto-router_symbol_(24)', 'IP_PBX_(24)', 'Video_terminal_(24)']);

		$this->zbxTestTextPresent(['Name', 'Mappings', 'Default']);
		$this->zbxTestTextPresent(['Inventory field', 'Expression', 'Icon', 'Action']);
	}

	public function create() {
		return[
			[
				[
					'expected' => TEST_BAD,
					'expression' => 'Create with empty name',
					'error' => 'Invalid parameter "/1/name": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Icon mapping one',
					'expression' => 'Create with existing name',
					'error' => 'Icon map "Icon mapping one" already exists.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Icon mapping create with slash',
					'expression' => '/',
					'error' => 'Invalid parameter "/1/mappings/1/expression": invalid regular expression. '
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Icon mapping create with backslash',
					'expression' => '\\',
					'error' => 'Invalid parameter "/1/mappings/1/expression": invalid regular expression.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Icon mapping create with double slash',
					'expression' => '//',
					'error' => 'Invalid parameter "/1/mappings/1/expression": invalid regular expression. '
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Create with empty expression',
					'error' => 'Invalid parameter "/1/mappings/1/expression": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Icon mapping testForm create default inventory and icons',
					'mappings' => [
						['expression' => '!@#$%^&*()123abc']
					],
					'dbCheck' => true,
					'formCheckDefaultValues' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Icon mapping testForm create',
					'mappings' => [
						['expression' => 'test expression']
					],
					'inventory' => 'Alias',
					'icon' => 'Crypto-router_(96)',
					'default' => 'Firewall_(96)',
					'dbCheck' => true,
					'formCheck' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'LongNameqwertyuioplkjhgfdsazxcvbnmqwertyuioplkjhgfdsazxcvbnmqwerr',
					'expression' => 'Create with long name',
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Икона карты утф-8',
					'mappings' => [
						['expression' => 'Выражение утф-8']
					],
					'inventory' => 'Name',
					'icon' => 'House_(48)',
					'default' => 'Hub_(24)',
					'dbCheck' => true,
					'formCheck' => true
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Icon mapping add two equals expressions',
					'mappings' => [
						['expression' => 'first expression'],
						['expression' => 'first expression']
					],
					'error' => 'Invalid parameter "/1/mappings/2": value (inventory_link, expression)=(1, first expression) already exists.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Icon mapping without expressions',
					'mappings' => [
						['expression' => 'first expression', 'remove' => true]
					],
					'error' => 'Invalid parameter "/1/mappings": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Icon mapping add three expressions',
					'mappings' => [
						['expression' => 'first expression'],
						['expression' => 'second expression'],
						['expression' => 'third expression']
					],
					'dbCheck' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Icon mapping add and remove one expressions',
					'mappings' => [
						['expression' => 'one expression', 'remove' => true],
						['expression' => 'one expression']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Icon mapping add and remove two expressions',
					'mappings' => [
						['expression' => 'first expression', 'remove' => true],
						['expression' => 'second expression', 'remove' => true],
						['expression' => 'first expression'],
						['expression' => 'second expression']
					]
				]
			]
		];
	}

	/**
	 * @dataProvider create
	 */
	public function testFormIconMapping_Create($data) {
		$this->zbxTestLogin('adm.iconmapping.php?form=create');

		if (array_key_exists('name', $data)) {
			$this->zbxTestInputTypeWait('iconmap_name', $data['name']);
		}

		if (array_key_exists('expression', $data)) {
			$this->zbxTestInputTypeWait('iconmap_mappings_new0_expression', $data['expression']);
		}

		if (array_key_exists('inventory', $data)) {
			$this->zbxTestDropdownSelect('iconmap_mappings_new0_inventory_link', $data['inventory']);
		}

		if (array_key_exists('icon', $data)) {
			$this->zbxTestDropdownSelect('iconmap_mappings_new0_iconid', $data['icon']);
		}

		if (array_key_exists('default', $data)) {
			$this->zbxTestDropdownSelect('iconmap_default_iconid', $data['default']);
		}

		if (array_key_exists('mappings', $data)) {
			$expressionCount = 0;

			foreach ($data['mappings'] as $mappingRow) {
				$this->zbxTestInputTypeWait('iconmap_mappings_new'.$expressionCount.'_expression', $mappingRow['expression']);

				if (array_key_exists('remove', $mappingRow)) {
					$this->zbxTestClickXpathWait("//tr[@id='iconmapidRow_new". $expressionCount ."']//button");
				}

				$expressionCount++;
				if (count($data['mappings']) == $expressionCount) {
					break;
				}
				$this->zbxTestClick('addMapping');
			}
		}

		$this->zbxTestClick('add');

		switch ($data['expected']) {
			case TEST_GOOD:
				$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Icon map created');
				$this->zbxTestCheckTitle('Configuration of icon mapping');
				$this->zbxTestCheckHeader('Icon mapping');
				$this->zbxTestCheckFatalErrors();
				break;
			case TEST_BAD:
				$this->zbxTestWaitUntilMessageTextPresent('msg-bad', 'Cannot create icon map');
				$this->zbxTestTextPresent($data['error']);
				$this->zbxTestCheckFatalErrors();
				break;
		}

		if (array_key_exists('dbCheck', $data)) {
			$result = DBselect("SELECT icon_map.name, icon_mapping.expression FROM icon_map LEFT JOIN icon_mapping "
					. "ON icon_map.iconmapid = icon_mapping.iconmapid WHERE icon_map.name = '".$data['name']."'");
			while ($row = DBfetch($result)) {
				$this->assertEquals($row['name'], $data['name']);
				$dbExpression[] = $row['expression'];
			}

			foreach ($data['mappings'] as $key => $options) {
				$this->assertEquals($dbExpression[$key], $options['expression']);
			}
		}

		if (array_key_exists('formCheck', $data)) {
			$this->zbxTestClickLinkTextWait($data['name']);
			$this->zbxTestAssertElementValue('iconmap_name', $data['name']);
			$this->zbxTestAssertElementValue('iconmap_mappings_0_expression', $data['mappings'][0]['expression']);
			$this->zbxTestDropdownAssertSelected('iconmap[mappings][0][inventory_link]', $data['inventory']);
			$this->zbxTestDropdownAssertSelected('iconmap[mappings][0][iconid]', $data['icon']);
			$this->zbxTestDropdownAssertSelected('iconmap[default_iconid]', $data['default']);
		}

		if (array_key_exists('formCheckDefaultValues', $data)) {
			$this->zbxTestClickLinkTextWait($data['name']);
			$this->zbxTestAssertElementValue('iconmap_name', $data['name']);
			$this->zbxTestAssertElementValue('iconmap_mappings_0_expression', $data['mappings'][0]['expression']);
			$this->zbxTestDropdownAssertSelected('iconmap[mappings][0][inventory_link]', 'Type');
			$this->zbxTestDropdownAssertSelected('iconmap[mappings][0][iconid]', 'Cloud_(24)');
			$this->zbxTestDropdownAssertSelected('iconmap[default_iconid]', 'Cloud_(24)');
		}
	}

	public function update(){
		return [
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Icon mapping for update',
					'new_name' => '',
					'expression' => 'Update with empty name',
					'error' => 'Invalid parameter "/1/name": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Icon mapping for update',
					'new_name' => 'Icon mapping one',
					'expression' => 'Update with existing name',
					'error' => 'Icon map "Icon mapping one" already exists.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Icon mapping update expression with slash',
					'new_expression' => '/',
					'error' => 'Invalid parameter "/1/mappings/1/expression": invalid regular expression. '
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Icon mapping update with backslash',
					'new_expression' => '\\',
					'error' => 'Invalid parameter "/1/mappings/1/expression": invalid regular expression.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Icon mapping update with double slash',
					'new_expression' => '//',
					'error' => 'Invalid parameter "/1/mappings/1/expression": invalid regular expression. '
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Update with empty expression',
					'new_expression' => '',
					'error' => 'Invalid parameter "/1/mappings/1/expression": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Icon mapping testForm update without inventory and icons changes',
					'new_expression' => '!@#$%^&*()123updated',
					'dbCheck' => true,
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Icon mapping testForm update',
					'expression' => 'Test expression updated',
					'inventory' => 'Serial number B',
					'icon' => 'Firewall_(96)',
					'default' => 'Crypto-router_(96)',
					'dbCheck' => true,
					'formCheck' => true
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'LongNameqwertyuioplkjhgfdsazxcvbnmqwertyuioplkjhgfdsazxcvbnmqwerr',
					'expression' => 'Update with long name',
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Икона карты обновленна утф-8',
					'expression' => 'Выражение обновленно утф-8',
					'inventory' => 'Name',
					'icon' => 'House_(48)',
					'default' => 'Hub_(24)',
					'dbCheck' => true,
					'formCheck' => true
				]
			]
		];
	}

	/**
	 * @dataProvider update
	 */
	public function testFormIconMapping_ChangeAndUpdate($data) {
		$this->zbxTestLogin('adm.iconmapping.php');
		$this->zbxTestClickLinkTextWait($data['name']);
	}

	public function testFormIconMapping_restore() {
		DBrestore_tables('icon_map');
	}
}
