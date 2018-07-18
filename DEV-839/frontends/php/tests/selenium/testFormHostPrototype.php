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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

require_once dirname(__FILE__).'/../include/class.cwebtest.php';

/**
* @backup hosts
*/
class testFormHostPrototype extends CWebTest {

	/**
	 * The name of the test host created in the test data set.
	 *
	 * @var string
	 */
	protected $host = 'Host for host prototype tests';

	/**
	 * The name of the form test discovery rule created in the test data set.
	 *
	 * @var string
	 */
	protected $discoveryRule = 'Discovery rule 1';

	public static function getCreateValidationData() {
		return [
			[
				// Create hot prototype with empty name
				[
					'error' => 'Page received incorrect data',
					'error_message' => 'Incorrect value for field "Host name": cannot be empty.',
				]
			],
				// Create hot prototype with space in name field
			[
				[
					'error' => 'Page received incorrect data',
					'name' => ' ',
					'error_message' => 'Incorrect value for field "Host name": cannot be empty.',
				]
			],
			[
				[
					'error' => 'Cannot add host prototype',
					'name' => 'Host prototype {#GROUP_EMPTY}',
					'error_message' => 'Host prototype "Host prototype {#GROUP_EMPTY}" cannot be without host group',
				]
			]
		];
	}

	/**
	 * Test validation of host prototype creation
	 *
	 * @dataProvider getCreateValidationData
	 */
	public function testFormHostPrototype_CreateValidation($data) {
		$this->zbxTestLogin('hosts.php');
		$this->zbxTestClickLinkTextWait($this->host);
		$this->zbxTestClickLinkTextWait('Discovery rules');
		$this->zbxTestClickLinkTextWait($this->discoveryRule);
		$this->zbxTestClickLinkTextWait('Host prototypes');
		$this->zbxTestContentControlButtonClickTextWait('Create host prototype');
		$this->zbxTestCheckHeader('Host prototypes');
		$this->zbxTestCheckTitle('Configuration of host prototypes');

		if (array_key_exists('name', $data)) {
			$this->zbxTestInputType('host', $data['name']);
		}

		$this->zbxTestClick('add');

		// Check the results in frontend.
		$this->zbxTestWaitUntilMessageTextPresent('msg-bad', $data['error']);
		$error = $this->zbxTestGetText('//ul[@class=\'msg-details-border\']');
		$this->assertContains($data['error_message'], $error);
		$this->zbxTestCheckFatalErrors();
	}

	public static function getCreateData() {
		return [
			[
				[
					'name' => 'Host with minimum fields {#FSNAME}',
					'hostgroup'=> 'Virtual machines'
				]
			],
			[
				[
					'name' => 'Host with all fields {#FSNAME}',
					'visible_name' => 'Host with all fields visible name',
					'hostgroup'=> 'Virtual machines',
					'group_prototype'=> '{#FSNAME}',
					'template'=> 'Form test template',
					'inventory'=> 'Automatic',
					'checkbox' => false
				]
			]
		];
	}

	/**
	 * Test creation of a host prototype with all possible fields and with default values.
	 *
	 * @dataProvider getCreateData
	 */
	public function testFormHostPrototype_Create($data) {

//		$this->zbxTestLogin('host_prototypes.php?parent_discoveryid=90001&form=create');
		$this->zbxTestLogin('hosts.php');
		$this->zbxTestClickLinkTextWait($this->host);
		$this->zbxTestClickLinkTextWait('Discovery rules');
		$this->zbxTestClickLinkTextWait($this->discoveryRule);
		$this->zbxTestClickLinkTextWait('Host prototypes');
		$this->zbxTestContentControlButtonClickTextWait('Create host prototype');
		$this->zbxTestCheckHeader('Host prototypes');
		$this->zbxTestCheckTitle('Configuration of host prototypes');
		$this->zbxTestInputType('host', $data['name']);

		if (array_key_exists('visible_name', $data)) {
			$this->zbxTestInputType('name', $data['visible_name']);
		}

		if (array_key_exists('checkbox', $data)) {
			$this->zbxTestCheckboxSelect('status', $data['checkbox']);
		}

		$this->zbxTestTabSwitch('Groups');
		$this->zbxTestClickButtonMultiselect('group_links_');
		$this->zbxTestLaunchOverlayDialog('Host groups');
		$this->zbxTestClickLinkText($data['hostgroup']);

		if (array_key_exists('group_prototype', $data)) {
			$this->zbxTestInputTypeByXpath('//*[@name=\'group_prototypes[0][name]\']', $data['group_prototype']);
		}

		if (array_key_exists('template', $data)) {
			$this->zbxTestTabSwitch('Templates');
			$this->zbxTestClickButtonMultiselect('add_templates_');
			$this->zbxTestLaunchOverlayDialog('Templates');
			$this->zbxTestClickLinkText($data['template']);
			$this->zbxTestClickXpath('//button[contains(@onclick, \'add_template\')]');
		}

		if (array_key_exists('inventory', $data)) {
			$this->zbxTestTabSwitch('Host inventory');
			$this->zbxTestClickXpath('//label[@for=\'inventory_mode_2\']');
		}

		$this->zbxTestClick('add');

		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Host prototype added');
		$this->zbxTestTextPresent($data['name']);
		$this->zbxTestCheckFatalErrors();

		// Check the results in form
		$this->checkFormFields($data);

		// Check the results in DB
		$this->assertEquals(1, DBcount('SELECT NULL FROM hosts WHERE host = '.zbx_dbstr($data['name'])));
	}

	/**
	 * Test update without any modification of host prototype.
	 */
	public function testFormHostPrototype_SimpleUpade() {
		$sql_hash = 'SELECT * FROM hosts ORDER BY hostid';
		$old_hash = DBhash($sql_hash);

		$this->zbxTestLogin('hosts.php');
		$this->zbxTestClickLinkTextWait($this->host);
		$this->zbxTestClickLinkTextWait('Discovery rules');
		$this->zbxTestClickLinkTextWait($this->discoveryRule);
		$this->zbxTestClickLinkTextWait('Host prototypes');
		$this->zbxTestClickLinkTextWait('Host prototype {#1}');
		$this->zbxTestCheckHeader('Host prototypes');
		$this->zbxTestCheckTitle('Configuration of host prototypes');

		$this->zbxTestClick('update');

		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Host prototype updated');
		$this->zbxTestTextPresent('Host prototype {#1}');
		$this->zbxTestCheckFatalErrors();

		$this->assertEquals($old_hash, DBhash($sql_hash));
	}


	public static function getUpdateData() {
		return [
			[
				[
					'old_name' => 'Host prototype {#2}',
					'name' => 'New Host prototype {#2}',
					'checkbox' => true,
					'hostgroup'=> 'Virtual machines',
					'group_prototype'=> 'New test {#MACRO}',
					'template' => 'Template OS Windows',
					'inventory' => 'Automatic',
				]
			],
			[
				[
					'old_name' => 'Host prototype visible name',
					'old_visible_name' => 'Host prototype visible name',
					'visible_name' => 'New prototype visible name',
				]
			]
		];
	}

	/**
	 * Test update of a host prototype with all possible fields.
	 *
	 * @dataProvider getUpdateData
	 */
	public function testFormHostPrototype_UpdateAll($data) {
		$this->zbxTestLogin('hosts.php');
		$this->zbxTestClickLinkTextWait($this->host);
		$this->zbxTestClickLinkTextWait('Discovery rules');
		$this->zbxTestClickLinkTextWait($this->discoveryRule);
		$this->zbxTestClickLinkTextWait('Host prototypes');
		$this->zbxTestClickLinkTextWait($data['old_name']);
		$this->zbxTestCheckHeader('Host prototypes');
		$this->zbxTestCheckTitle('Configuration of host prototypes');

		// Change name and visible name.
		if (array_key_exists('name', $data)) {
			$this->zbxTestInputTypeOverwrite('host', $data['name']);
		}
		if (array_key_exists('visible_name', $data)) {
			$this->zbxTestInputTypeOverwrite('name', $data['visible_name']);
		}
		// Change status
		if (array_key_exists('checkbox', $data)) {
			$this->zbxTestCheckboxSelect('status', $data['checkbox']);
		}

		// Change Host group and Group prototype.
		if (array_key_exists('hostgroup', $data)) {
			$this->zbxTestTabSwitch('Groups');
			$this->zbxTestClickXpath('//span[@class=\'subfilter-disable-btn\']');
			$this->zbxTestClickButtonMultiselect('group_links_');
			$this->zbxTestLaunchOverlayDialog('Host groups');
			$this->zbxTestClickLinkAndWaitWindowClose($data['hostgroup']);
			$this->zbxTestClickXpath('//button[@class=\'btn-link group-prototype-remove\']');
			$this->zbxTestClick('group_prototype_add');
			$this->zbxTestInputTypeByXpath('//*[@name=\'group_prototypes[1][name]\']', $data['group_prototype']);
		}

		// Change template.
		if (array_key_exists('template', $data)) {
			$this->zbxTestTabSwitch('Templates');
			$this->zbxTestClickXpath('//button[contains(@onclick, \'unlink\')]');
			$this->zbxTestClickButtonMultiselect('add_templates_');
			$this->zbxTestLaunchOverlayDialog('Templates');
			$this->zbxTestClickLinkAndWaitWindowClose($data['template']);
			$this->zbxTestClickXpath('//button[contains(@onclick, \'add_template\')]');
		}

		// Change inventory mode.
		if (array_key_exists('inventory', $data)) {
			$this->zbxTestTabSwitch('Host inventory');
			$this->zbxTestClickXpath('//label[@for=\'inventory_mode_2\']');
		}

		$this->zbxTestClick('update');

		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Host prototype updated');
		if (array_key_exists('visible_name', $data)) {
			$this->zbxTestTextPresent($data['visible_name']);
		}
		if (array_key_exists('name', $data)) {
			$this->zbxTestTextPresent($data['name']);
		}
		$this->zbxTestCheckFatalErrors();

		// Check the results in form
		$this->checkFormFields($data);

		if (array_key_exists('name', $data)) {
			$this->assertEquals(1, DBcount('SELECT NULL FROM hosts WHERE host = '.zbx_dbstr($data['name'])));
			$this->assertEquals(0, DBcount('SELECT NULL FROM hosts WHERE host = '.zbx_dbstr($data['old_name'])));
		}

		if (array_key_exists('visible_name', $data)) {
			$this->assertEquals(1, DBcount('SELECT NULL FROM hosts WHERE name = '.zbx_dbstr($data['visible_name'])));
			$this->assertEquals(0, DBcount('SELECT NULL FROM hosts WHERE name = '.zbx_dbstr($data['old_visible_name'])));
		}
	}

	private function checkFormFields($data) {
		if (array_key_exists('name', $data) && !array_key_exists('visible_name', $data)) {
			$this->zbxTestClickLinkTextWait($data['name']);
			$this->zbxTestAssertElementValue('host', $data['name']);
		}

		if (array_key_exists('visible_name', $data)) {
			$this->zbxTestClickLinkTextWait($data['visible_name']);
			$this->zbxTestAssertElementValue('name', $data['visible_name']);
		}
		// Should be uncommented when ZBX-14618 is resolved
		// if (array_key_exists('checkbox', $data)) {
		// $this->assertEquals($data['checkbox'], $this->zbxTestCheckboxSelected('status'));
		// }

		if (array_key_exists('hostgroup', $data)) {
			$this->zbxTestTabSwitch('Groups');
			$this->zbxTestMultiselectAssertSelected('group_links_', $data['hostgroup']);
			if (array_key_exists('group_prototype', $data)) {
				$this->assertEquals($data['group_prototype'], $this->zbxTestGetValue('//input[@name="group_prototypes[0][name]"]'));
			}
		}

		if (array_key_exists('template', $data)) {
			$this->zbxTestTabSwitch('Templates');
			$this->zbxTestTextPresent($data['template']);
		}

		if (array_key_exists('inventory', $data)) {
			$this->zbxTestTabSwitch('Host inventory');
			$this->zbxTestAssertElementPresentXpath("//input[@id='inventory_mode_2' and @checked='checked']");

		}
	}

	public function testFormHostPrototype_Delete() {
		$this->zbxTestLogin('hosts.php');
		$this->zbxTestClickLinkTextWait($this->host);
		$this->zbxTestClickLinkTextWait('Discovery rules');
		$this->zbxTestClickLinkTextWait($this->discoveryRule);
		$this->zbxTestClickLinkTextWait('Host prototypes');
		$this->zbxTestClickLinkTextWait('Host prototype {#3}');
		$this->zbxTestCheckHeader('Host prototypes');
		$this->zbxTestCheckTitle('Configuration of host prototypes');

		$this->zbxTestClickAndAcceptAlert('delete');

		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Host prototype deleted');
		$this->zbxTestCheckFatalErrors();

		$this->assertEquals(0, DBcount("SELECT NULL FROM hosts WHERE host = 'Host prototype {#3}'"));
	}

	public function testFormHostPrototype_CancelUpdate() {
		$sql_hash = 'SELECT * FROM hosts ORDER BY hostid';
		$old_hash = DBhash($sql_hash);

		$this->zbxTestLogin('hosts.php');
		$this->zbxTestClickLinkTextWait($this->host);
		$this->zbxTestClickLinkTextWait('Discovery rules');
		$this->zbxTestClickLinkTextWait($this->discoveryRule);
		$this->zbxTestClickLinkTextWait('Host prototypes');
		$this->zbxTestClickLinkTextWait('Host prototype {#1}');
		$this->zbxTestClick('cancel');

		$this->assertEquals($old_hash, DBhash($sql_hash));
	}

	public function testFormHostPrototype_Clone() {
		$this->zbxTestLogin('hosts.php');
		$this->zbxTestClickLinkTextWait($this->host);
		$this->zbxTestClickLinkTextWait('Discovery rules');
		$this->zbxTestClickLinkTextWait($this->discoveryRule);
		$this->zbxTestClickLinkTextWait('Host prototypes');
		$this->zbxTestClickLinkTextWait('Host prototype {#1}');
		$this->zbxTestClick('clone');
		$this->zbxTestInputTypeOverwrite('host', 'Clone of Host prototype {#1}');
		$this->zbxTestClick('add');

		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Host prototype added');
		$this->zbxTestTextPresent('Host prototype {#1}');
		$this->zbxTestCheckFatalErrors();

		$this->assertEquals(1, DBcount("SELECT NULL FROM hosts WHERE host = 'Host prototype {#1}'"));
		$this->assertEquals(1, DBcount("SELECT NULL FROM hosts WHERE host = 'Clone of Host prototype {#1}'"));
	}
}
