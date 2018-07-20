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
	 * Discovery rule id used in test.
	 */
	const DISCRULEID = 90001;

	public static function getCreateValidationData() {
		return [
			[
				// Create host prototype with empty name.
				[
					'error' => 'Page received incorrect data',
					'error_message' => 'Incorrect value for field "Host name": cannot be empty.',
					'check_db' => false
				]
			],
				// Create host prototype with space in name field.
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
		$this->zbxTestCheckTitle('Configuration of host prototypes');

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
		$this->zbxTestTextPresentInMessageDetails($data['error_message']);
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
	public function testFormHostPrototype_Create($data) {
		$this->zbxTestLogin('host_prototypes.php?parent_discoveryid='.self::DISCRULEID.'&form=create');
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
			$this->zbxTestInputTypeByXpath('//*[@name="group_prototypes[0][name]"]', $data['group_prototype']);
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

		if (array_key_exists('visible_name', $data)) {
			$this->zbxTestAssertElementPresentXpath('//a[contains(@href, "form") and text()="'.$data['visible_name'].'"]');
		}else{
			$this->zbxTestAssertElementPresentXpath('//a[contains(@href, "form") and text()="'.$data['name'].'"]');
		}
		$this->zbxTestCheckFatalErrors();

		// Check the results in form.
		$this->checkFormFields($data);

		// Check the results in DB.
		$this->assertEquals(1, DBcount('SELECT NULL FROM hosts WHERE host = '.zbx_dbstr($data['name'])));
	}

	public static function getUpdateValidationData() {
		return [
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
					'error' => 'Cannot update host prototype',
					'error_message' => 'Incorrect characters used for host "Кириллица Прототип хоста {#FSNAME}".',
				]
			],
			[
				[
					'name' => 'Host prototype {#GROUP_EMPTY}',
					'error' => 'Cannot update host prototype',
					'error_message' => 'Host prototype "Host prototype {#GROUP_EMPTY}" cannot be without host group',
				]
			],
			[
				[
					'name' => 'Host prototype without macro in name',
					'error' => 'Cannot update host prototype',
					'error_message' => 'Host name for host prototype "Host prototype without macro in name" must contain macros',
				]
			],
			[
				[
					'name' => 'Host prototype without macro in group prototype',
					'clear_groups' => false,
					'group_prototypes' => [
						'Group prototype'
					],
					'error' => 'Cannot update host prototype',
					'error_message' => 'Host name for host prototype "Host prototype without macro in group prototype" must contain macros',
				]
			],
			[
				[
					'name' => 'Host prototype with / in name',
					'error' => 'Cannot update host prototype',
					'error_message' => 'Incorrect characters used for host "Host prototype with / in name".',
				]
			],
			[
				[
					'name' => '{#HOST} prototype with duplicated Group prototypes',
					'clear_groups' => false,
					'group_prototypes' => [
						'Group prototype',
						'Group prototype'
					],
					'error' => 'Cannot update host prototype',
					'error_message' => 'Duplicate group prototype name "Group prototype" for host prototype "{#HOST} prototype with duplicated Group prototypes".',
				]
			]
		];
	}

	/**
	 * Test update with fields validation of host prototype.
	 * @dataProvider getUpdateValidationData
	 */
	public function testFormHostPrototype_UpdateValidation($data) {
		$old_name = 'Host prototype {#2}';

		$this->zbxTestLogin('host_prototypes.php?parent_discoveryid='.self::DISCRULEID);
		$this->zbxTestClickLinkTextWait($old_name);
		$this->zbxTestCheckHeader('Host prototypes');
		$this->zbxTestInputTypeOverwrite('host', $data['name']);
		$this->zbxTestTabSwitch('Groups');

		if (!array_key_exists('clear_groups', $data) || $data['clear_groups'] === true ) {
			$this->zbxTestMultiselectClear('group_links_');
		}

		if (array_key_exists('group_prototypes', $data)) {
			foreach ($data['group_prototypes'] as $i => $group) {
				$this->zbxTestInputClearAndTypeByXpath('//*[@name="group_prototypes['.$i.'][name]"]', $group);
				$this->zbxTestClick('group_prototype_add');
			}
		}

		$this->zbxTestClick('update');

		// Check the results in frontend.
		$this->zbxTestWaitUntilMessageTextPresent('msg-bad', $data['error']);
		$this->zbxTestTextPresentInMessageDetails($data['error_message']);
		$this->zbxTestCheckFatalErrors();

		$this->assertEquals(0, DBcount('SELECT NULL FROM hosts WHERE flags=2 AND name='.zbx_dbstr($data['name'])));
		$this->assertEquals(1, DBcount('SELECT NULL FROM hosts WHERE flags=2 AND name='.zbx_dbstr($old_name)));
	}

	/**
	 * Test update without any modification of host prototype.
	 */
	public function testFormHostPrototype_SimpleUpdate() {
		$host = 'Host prototype {#2}';
		$sql_hash = 'SELECT * FROM hosts ORDER BY hostid';
		$old_hash = DBhash($sql_hash);

		$this->zbxTestLogin('host_prototypes.php?parent_discoveryid='.self::DISCRULEID);
		$this->zbxTestClickLinkTextWait($host);

		$this->zbxTestClick('update');

		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Host prototype updated');
		$this->zbxTestAssertElementPresentXpath('//a[contains(@href, "form") and text()="'.$host.'"]');
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

		// Change name and visible name.
		if (array_key_exists('name', $data)) {
			$this->zbxTestInputTypeOverwrite('host', $data['name']);
		}
		if (array_key_exists('visible_name', $data)) {
			$this->zbxTestInputTypeOverwrite('name', $data['visible_name']);
		}
		// Change status.
		if (array_key_exists('checkbox', $data)) {
			$this->zbxTestCheckboxSelect('status', $data['checkbox']);
		}

		// Change Host group and Group prototype.
		if (array_key_exists('hostgroup', $data)) {
			$this->zbxTestTabSwitch('Groups');
			$this->zbxTestMultiselectClear('group_links_');
			$this->zbxTestClickButtonMultiselect('group_links_');
			$this->zbxTestLaunchOverlayDialog('Host groups');
			$this->zbxTestClickLinkText($data['hostgroup']);
			$this->zbxTestInputClearAndTypeByXpath('//*[@name="group_prototypes[0][name]"]', $data['group_prototype']);
		}

		// Change template.
		if (array_key_exists('template', $data)) {
			$this->zbxTestTabSwitch('Templates');
			$this->zbxTestClickXpath('//button[contains(@onclick,"unlink")]');
			$this->zbxTestClickButtonMultiselect('add_templates_');
			$this->zbxTestLaunchOverlayDialog('Templates');
			$this->zbxTestClickLinkText($data['template']);
			$this->zbxTestClickXpath('//div[@id="templateTab"]//button[text()="Add"]');
		}

		// Change inventory mode.
		if (array_key_exists('inventory', $data)) {
			$this->zbxTestTabSwitch('Host inventory');
			$this->zbxTestClickXpath('//label[text()="'.$data['inventory'].'"]');
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

	public static function getCloneValidationData() {
		return [
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
					'clear_groups' => false,
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
					'error' => 'Cannot add host prototype',
					'error_message' => 'Incorrect characters used for host "Host prototype with / in name".',
				]
			],
			[
				[
					'name' => '{#HOST} prototype with duplicated Group prototypes',
					'clear_groups' => false,
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
	 * Test clone of a host prototype with fields validation.
	 *
	 * @dataProvider getCloneValidationData
	 */
	public function testFormHostPrototype_CloneValidation($data) {
		$hostname = 'Host prototype {#1}';

		$this->zbxTestLogin('host_prototypes.php?parent_discoveryid='.self::DISCRULEID);
		$this->zbxTestClickLinkTextWait($hostname);
		$this->zbxTestClick('clone');

		$this->zbxTestInputTypeOverwrite('host', $data['name']);
		$this->zbxTestTabSwitch('Groups');

		if (!array_key_exists('clear_groups', $data) || $data['clear_groups'] === true ) {
			$this->zbxTestMultiselectClear('group_links_');
		}

		if (array_key_exists('group_prototypes', $data)) {
			foreach ($data['group_prototypes'] as $i => $group) {
				$this->zbxTestInputClearAndTypeByXpath('//*[@name="group_prototypes['.$i.'][name]"]', $group);
				$this->zbxTestClick('group_prototype_add');
			}
		}

		$this->zbxTestClick('add');

		// Check the results in frontend.
		$this->zbxTestWaitUntilMessageTextPresent('msg-bad', $data['error']);
		$this->zbxTestTextPresentInMessageDetails($data['error_message']);
		$this->zbxTestCheckFatalErrors();

		$this->assertEquals(0, DBcount('SELECT NULL FROM hosts WHERE flags=2 AND name='.zbx_dbstr($data['name'])));
		$this->assertEquals(1, DBcount('SELECT NULL FROM hosts WHERE flags=2 AND name='.zbx_dbstr($hostname)));

		$this->zbxTestClick('add');
	}

	public static function getCloneData() {
		return [
			[
				[
					'name' => 'Clone_1 of Host prototype {#1}',
				]
			],
			[
				[
					'name' => 'Clone_2 of Host prototype {#1}',
					'visible_name' => 'Clone_2 Host prototype visible name',
				]
			],
			[
				[
					'name' => 'Clone_3 of Host prototype {#1}',
					'checkbox' => false
				]
			],
			[
				[
					'name' => 'Clone_4 of Host prototype {#1}',
					'hostgroup' => 'Hypervisors'
				]
			],
			[
				[
					'name' => 'Clone_5 of Host prototype {#1}',
					'group_prototype' => 'Clone group prototype {#MACRO}'
				]
			]
			,
			[
				[
					'name' => 'Clone_6 of Host prototype {#1}',
					'template' => 'Template OS Mac OS X'
				]
			],
			[
				[
					'name' => 'Clone_7 of Host prototype {#1}',
					'inventory' => 'Manual'
				]
			]
		];
	}

	/**
	 * Test clone of a host prototype with update all possible fields.
	 *
	 * @dataProvider getCloneData
	 */
	public function testFormHostPrototype_Clone($data) {
		$hostname = 'Host prototype {#1}';

		$this->zbxTestLogin('host_prototypes.php?parent_discoveryid='.self::DISCRULEID);
		$this->zbxTestClickLinkTextWait($hostname);
		$this->zbxTestClick('clone');
		$this->zbxTestInputTypeOverwrite('host', $data['name']);

		// Change name and visible name.
		if (array_key_exists('name', $data)) {
			$this->zbxTestInputTypeOverwrite('host', $data['name']);
		}
		if (array_key_exists('visible_name', $data)) {
			$this->zbxTestInputType('name', $data['visible_name']);
		}
		// Change status.
		if (array_key_exists('checkbox', $data)) {
			$this->zbxTestCheckboxSelect('status', $data['checkbox']);
		}
		$this->zbxTestTabSwitch('Groups');
		// Change host group.
		if (array_key_exists('hostgroup', $data)) {
			$this->zbxTestClickXpath('//span[@class="subfilter-disable-btn"]');
			$this->zbxTestMultiselectClear('group_links_');
			$this->zbxTestClickButtonMultiselect('group_links_');
			$this->zbxTestLaunchOverlayDialog('Host groups');
			$this->zbxTestClickLinkText($data['hostgroup']);
		}
		// Change host group prototype.
		if (array_key_exists('group_prototype', $data)) {
			$this->zbxTestInputClearAndTypeByXpath('//*[@name="group_prototypes[0][name]"]', $data['group_prototype']);
		}

		// Change template.
		if (array_key_exists('template', $data)) {
			$this->zbxTestTabSwitch('Templates');
			$this->zbxTestClickButtonMultiselect('add_templates_');
			$this->zbxTestLaunchOverlayDialog('Templates');
			$this->zbxTestClickLinkText($data['template']);
			$this->zbxTestClickXpath('//div[@id="templateTab"]//button[text()="Add"]');
		}

		// Change inventory mode.
		if (array_key_exists('inventory', $data)) {
			$this->zbxTestTabSwitch('Host inventory');
			$this->zbxTestClickXpath('//label[text()="'.$data['inventory'].'"]');
		}

		$this->zbxTestClick('add');

		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Host prototype added');
		$this->zbxTestCheckFatalErrors();

		if (array_key_exists('visible_name', $data)) {
			$this->zbxTestAssertElementPresentXpath('//a[contains(@href, "form") and text()="'.$data['visible_name'].'"]');
			$this->zbxTestAssertElementPresentXpath('//a[contains(@href, "form") and text()="'.$data['visible_name'].'"]');
		}else{
			$this->zbxTestAssertElementPresentXpath('//a[contains(@href, "form") and text()="'.$data['name'].'"]');
			$this->zbxTestAssertElementPresentXpath('//a[contains(@href, "form") and text()="'.$hostname.'"]');
		}

		// Check the results in form
		$this->checkFormFields($data);

		$this->assertEquals(1, DBcount('SELECT NULL FROM hosts WHERE host='.zbx_dbstr($data['name'])));
		$this->assertEquals(1, DBcount('SELECT NULL FROM hosts WHERE host='.zbx_dbstr($hostname)));
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
			$this->zbxTestAssertElementPresentXpath('//label[text()="'.$data['inventory'].'"]/../input[@checked]');

		}
	}

	public function testFormHostPrototype_Delete() {
		$prototype_name = 'Host prototype {#3}';

		$this->zbxTestLogin('host_prototypes.php?parent_discoveryid='.self::DISCRULEID);
		$this->zbxTestClickLinkTextWait($prototype_name);

		$this->zbxTestClickAndAcceptAlert('delete');

		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Host prototype deleted');
		$this->zbxTestCheckFatalErrors();

		$this->assertEquals(0, DBcount('SELECT NULL FROM hosts WHERE host='.zbx_dbstr($prototype_name)));
	}

	public function testFormHostPrototype_CancelCreation() {
		$host = 'Host for host prototype tests';
		$discovery_rule = 'Discovery rule 1';
		$name = 'Host prototype {#FSNAME}';
		$group = 'Virtual machines';

		$sql_hash = 'SELECT * FROM hosts ORDER BY hostid';
		$old_hash = DBhash($sql_hash);

		$this->zbxTestLogin('hosts.php');
		$this->zbxTestClickLinkTextWait($host);
		$this->zbxTestClickLinkTextWait('Discovery rules');
		$this->zbxTestClickLinkTextWait($discovery_rule);
		$this->zbxTestClickLinkTextWait('Host prototypes');
		$this->zbxTestContentControlButtonClickText('Create host prototype');

		$this->zbxTestInputType('host', $name);
		$this->zbxTestTabSwitch('Groups');
		$this->zbxTestClickButtonMultiselect('group_links_');
		$this->zbxTestLaunchOverlayDialog('Host groups');
		$this->zbxTestClickLinkText($group);

		$this->zbxTestClick('cancel');

		// Check the results in frontend.
		$this->zbxTestCheckHeader('Host prototypes');
		$this->zbxTestCheckTitle('Configuration of host prototypes');
		$this->zbxTestCheckFatalErrors();
		$this->zbxTestTextNotPresent($name);

		$this->assertEquals($old_hash, DBhash($sql_hash));
	}

	/**
	 * Cancel updating, cloning or deleting of host prototype.
	 */
	private function executeCancelAction($action) {
		$sql_hash = 'SELECT * FROM hosts ORDER BY hostid';
		$old_hash = DBhash($sql_hash);

		$this->zbxTestLogin('host_prototypes.php?parent_discoveryid='.self::DISCRULEID);

		$sql = 'SELECT name'.
						' FROM hosts'.
						' WHERE hostid IN ('.
							'SELECT hostid'.
							' FROM host_discovery'.
							' WHERE parent_itemid='.self::DISCRULEID.
							')'.
						'LIMIT 1';

		foreach (DBdata($sql, false) as $host) {
			$host = $host[0];
			$name = $host['name'];
			$this->zbxTestClickLinkText($name);

			switch ($action) {
				case 'update':
					$name .= ' (updated)';
					$this->zbxTestInputTypeOverwrite('host', $name);
					$this->zbxTestClick('cancel');
					break;

				case 'clone':
					$name .= ' (cloned)';
					$this->zbxTestInputTypeOverwrite('host', $name);
					$this->zbxTestClickWait('clone');
					$this->zbxTestClickWait('cancel');
					break;

				case 'delete':
					$this->zbxTestClickWait('delete');
					$this->webDriver->switchTo()->alert()->dismiss();
					break;
			}

			$this->zbxTestCheckHeader('Host prototypes');
			$this->zbxTestCheckTitle('Configuration of host prototypes');
			$this->zbxTestCheckFatalErrors();

			if ($action !== 'delete') {
				$this->zbxTestTextNotPresent($name);
			}
			else {
				$this->zbxTestTextPresent($name);
			}
		}
		$this->assertEquals($old_hash, DBhash($sql_hash));
	}

	/**
	 * Cancel update of host prototype.
	 */
	public function testFormHostPrototype_CancelUpdating() {
		$this->executeCancelAction('update');
	}

	/**
	 * Cancel cloning of host prototype.
	 */
	public function testFormHostPrototype_CancelCloning() {
		$this->executeCancelAction('clone');
	}

	/**
	 * Cancel deleting of host prototype.
	 */
	public function testFormHostPrototype_CancelDelete() {
		$this->executeCancelAction('delete');
	}
}
