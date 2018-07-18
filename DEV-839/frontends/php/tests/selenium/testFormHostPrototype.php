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
	protected $discovery_rule = 'Discovery rule 1';

	/**
	 * Discovery rule id used in test.
	 */
	const DISCRULEID = 90001;


	public static function getCreateValidationData() {
		return [
			[
				// Create host prototype with empty name
				[
					'error' => 'Page received incorrect data',
					'error_message' => 'Incorrect value for field "Host name": cannot be empty.',
					'check_db' => false
				]
			],
				// Create host prototype with space in name field
			[
				[
					'name' => ' ',
					'error' => 'Page received incorrect data',
					'error_message' => 'Incorrect value for field "Host name": cannot be empty.',
				]
			],
			[
				[
					'name' => 'Кириллица Прототип хоста {#FSNAME}',
					'error' => 'Cannot add host prototype',
					'error_message' => 'Incorrect characters used for host "Кириллица Прототип хоста {#FSNAME}".',
				]
			],
			[
				[
					'name' => 'Host prototype {#GROUP_EMPTY}',
					'error' => 'Cannot add host prototype',
					'error_message' => 'Host prototype "Host prototype {#GROUP_EMPTY}" cannot be without host group',
				]
			],
			[
				[
					'name' => 'Host prototype without macro in name',
					'error' => 'Cannot add host prototype',
					'error_message' => 'Host name for host prototype "Host prototype without macro in name" must contain macros',
				]
			],
			[
				[
					'name' => 'Host prototype without macro in group prototype',
					'hostgroup' => 'Linux servers',
					'group_prototypes' => [
						'Group prototype'
					],
					'error' => 'Cannot add host prototype',
					'error_message' => 'Host name for host prototype "Host prototype without macro in group prototype" must contain macros',
				]
			],
			[
				[
					'name' => 'Host prototype with / in name',
					'hostgroup' => 'Linux servers',
					'error' => 'Cannot add host prototype',
					'error_message' => 'Incorrect characters used for host "Host prototype with / in name".',
				]
			],
			[
				[
					'name' => '{#HOST} prototype with duplicated Group prototypes',
					'hostgroup' => 'Linux servers',
					'group_prototypes' => [
						'Group prototype',
						'Group prototype'
					],
					'error' => 'Cannot add host prototype',
					'error_message' => 'Duplicate group prototype name "Group prototype" for host prototype "{#HOST} prototype with duplicated Group prototypes".',
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
		$this->zbxTestLogin('host_prototypes.php?parent_discoveryid='.self::DISCRULEID.'&form=create');
		$this->zbxTestCheckHeader('Host prototypes');

		if (array_key_exists('name', $data)) {
			$this->zbxTestInputType('host', $data['name']);
		}

		$this->zbxTestTabSwitch('Groups');

		if (array_key_exists('hostgroup', $data)) {
			$this->zbxTestClickButtonMultiselect('group_links_');
			$this->zbxTestLaunchOverlayDialog('Host groups');
			$this->zbxTestClickLinkText($data['hostgroup']);
		}

		if (array_key_exists('group_prototypes', $data)) {
			foreach ($data['group_prototypes'] as $i => $group) {
				$this->zbxTestInputTypeByXpath('//*[@name="group_prototypes['.$i.'][name]"]', $group);
				$this->zbxTestClick('group_prototype_add');
			}
		}

		$this->zbxTestClick('add');

		// Check the results in frontend.
		$this->zbxTestWaitUntilMessageTextPresent('msg-bad', $data['error']);
		$error = $this->zbxTestGetText('//ul[@class="msg-details-border"]');
		$this->assertContains($data['error_message'], $error);
		$this->zbxTestCheckFatalErrors();

		if (!array_key_exists('check_db', $data) || $data['check_db'] === true ) {
			$this->assertEquals(0, DBcount('SELECT NULL FROM hosts WHERE flags=2 AND name='.zbx_dbstr($data['name'])));
		}
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
	public function testFormHostPrototype_Create1($data) {
		$this->zbxTestLogin('host_prototypes.php?parent_discoveryid='.self::DISCRULEID.'&form=create');
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
			$this->zbxTestClickXpath('//button[contains(@onclick, "add_template")]');
		}

		if (array_key_exists('inventory', $data)) {
			$this->zbxTestTabSwitch('Host inventory');
			$this->zbxTestClickXpath('//label[text()="'.$data['inventory'].'"]');
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
	public function testFormHostPrototype_SimpleUpdate() {
		$prototype_name = 'Host prototype {#1}';
		$sql_hash = 'SELECT * FROM hosts ORDER BY hostid';
		$old_hash = DBhash($sql_hash);

		$this->zbxTestLogin('host_prototypes.php?parent_discoveryid='.self::DISCRULEID);
		$this->zbxTestClickLinkTextWait($prototype_name);
		$this->zbxTestCheckHeader('Host prototypes');
		$this->zbxTestCheckTitle('Configuration of host prototypes');

		$this->zbxTestClick('update');

		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Host prototype updated');
		$this->zbxTestTextPresent($prototype_name);
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
		$this->zbxTestLogin('host_prototypes.php?parent_discoveryid='.self::DISCRULEID);
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
			// zbxTestMultiselectRemove
			$this->zbxTestClickButtonMultiselect('group_links_');
			$this->zbxTestLaunchOverlayDialog('Host groups');
			$this->zbxTestClickLinkAndWaitWindowClose($data['hostgroup']);
			$this->zbxTestClickXpathWait('//button[@class=\'btn-link group-prototype-remove\']');
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
			$this->zbxTestClickXpath('//button[contains(@onclick, \'add_template\')]'); // div[@id='']//button[text()="Add"]
		}

		// Change inventory mode.
		if (array_key_exists('inventory', $data)) {
			$this->zbxTestTabSwitch('Host inventory');
			$this->zbxTestClickXpath('//label[@for=\'inventory_mode_2\']'); // label text
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
			$this->zbxTestAssertElementText('//div[@id="templateTab"]//a', $data['template']);
		}

		if (array_key_exists('inventory', $data)) {
			$this->zbxTestTabSwitch('Host inventory');
			$this->zbxTestAssertElementPresentXpath("//input[@id='inventory_mode_2' and @checked='checked']"); // by text

		}
	}

	public function testFormHostPrototype_Delete() {
		$prototype_name = 'Host prototype {#3}';

		$this->zbxTestLogin('host_prototypes.php?parent_discoveryid='.self::DISCRULEID);
		$this->zbxTestClickLinkTextWait($prototype_name);
		$this->zbxTestCheckHeader('Host prototypes');
		$this->zbxTestCheckTitle('Configuration of host prototypes');

		$this->zbxTestClickAndAcceptAlert('delete');

		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Host prototype deleted');
		$this->zbxTestCheckFatalErrors();

		$this->assertEquals(0, DBcount("SELECT NULL FROM hosts WHERE host = 'Host prototype {#3}'")); // zbxsrt($prototype_name)
	}

	public function testFormHostPrototype_CancelUpdate() {
		$sql_hash = 'SELECT * FROM hosts ORDER BY hostid';
		$old_hash = DBhash($sql_hash);

		$this->zbxTestLogin('hosts.php');
		$this->zbxTestClickLinkTextWait($this->host);
		$this->zbxTestClickLinkTextWait('Discovery rules');
		$this->zbxTestClickLinkTextWait($this->discovery_rule);
		$this->zbxTestClickLinkTextWait('Host prototypes');
		$this->zbxTestClickLinkTextWait('Host prototype {#1}');
		$this->zbxTestClick('cancel');

		$this->assertEquals($old_hash, DBhash($sql_hash));
	}

	public function testFormHostPrototype_Clone() {
		$this->zbxTestLogin('host_prototypes.php?parent_discoveryid='.self::DISCRULEID);
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
