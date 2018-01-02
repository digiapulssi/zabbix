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

require_once dirname(__FILE__) . '/../include/class.cwebtest.php';

/**
 * @backup applications
 */
class testPageApplications extends CWebTest {

	/**
	 * Test application list when select hosts and groups.
	 */
	public function testPageApplications_CheckApplicationList() {
		// Open hosts page.
		$this->zbxTestLogin('hosts.php');
		$this->zbxTestClickLinkTextWait('ЗАББИКС Сервер');

		// Navigate to host applications.
		$this->zbxTestClickLinkTextWait('Applications');
		$this->zbxTestCheckTitle('Configuration of applications');
		$this->zbxTestCheckHeader('Applications');

		// Check selected host and group.
		$this->zbxTestDropdownAssertSelected('hostid', 'ЗАББИКС Сервер');
		$this->zbxTestDropdownAssertSelected('groupid', 'all');

		// Check the application list in frontend for 'ЗАББИКС Сервер' host.
		$sqlAllApplications = 'SELECT name FROM applications WHERE hostid=10084';
		$result = DBselect($sqlAllApplications);
		while ($row = DBfetch($result)) {
			$hosAllApp[] = $row['name'];
		}
		$this->zbxTestTextPresent($hosAllApp);

		// Select another host 'Template App Apache Tomcat JMX'.
		$this->zbxTestDropdownSelectWait('hostid', 'Template App Apache Tomcat JMX');

		// Check the application list in frontend
		$sqlAllApplications = 'SELECT name FROM applications WHERE hostid=10168';
		$result = DBselect($sqlAllApplications);
		while ($row = DBfetch($result)) {
			$templateAllApp[] = $row['name'];
		}
		$this->zbxTestTextPresent($templateAllApp);

		// Select all hosts and 'Templates/Applications' group.
		$this->zbxTestDropdownSelectWait('hostid', 'all');
		$this->zbxTestDropdownSelectWait('groupid', 'Templates/Applications');

		// Check the application list in frontend
		$sqlAllApplications = 'SELECT a.name FROM hosts_groups hg LEFT JOIN applications a ON hg.hostid=a.hostid WHERE hg.groupid=12';
		$result = DBselect($sqlAllApplications);
		while ($row = DBfetch($result)) {
			$groupAllApp[] = $row['name'];
		}
		$this->zbxTestTextPresent($groupAllApp);
		$this->zbxTestCheckFatalErrors();
	}

	/**
	 * Test disabled creation button of application, if selected all hosts and all groups.
	 */
	public function testPageApplications_CheckDisabledButton() {
		$this->zbxTestLogin('applications.php?groupid=0&hostid=0');
		$this->zbxTestDropdownAssertSelected('groupid', 'all');
		$this->zbxTestDropdownAssertSelected('hostid', 'all');

		$this->zbxTestAssertElementText("//button[@id='form']", 'Create application (select host first)');
		$this->zbxTestAssertAttribute("//button[@id='form']",'disabled','true');
		$this->zbxTestAssertElementNotPresentXpath("//ul[@class='object-group']");
	}

	/**
	 * Test deactivation of selected applications.
	 */
	public function testPageApplications_DisableSelected() {
		$this->zbxTestLogin('applications.php?groupid=4&hostid=10084');
		$this->zbxTestWaitForPageToLoad();

		// Select two applications and press disable button.
		$this->zbxTestCheckboxSelect('applications_348');
		$this->zbxTestCheckboxSelect('applications_351');
		$this->zbxTestClickButtonText('Disable');
		$this->zbxTestAlertAcceptWait();

		// Check the result in frontend.
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Items disabled');
		$this->zbxTestCheckFatalErrors();

		// Check the results in DB, that selected application items disabled.
		$sql='SELECT NULL FROM items i LEFT JOIN items_applications ia ON ia.itemid=i.itemid '
				. 'WHERE (ia.applicationid=348 OR ia.applicationid=351) AND i.status='.ITEM_STATUS_ACTIVE;
		$this->assertEquals(0, DBcount($sql));
	}

	/**
	 * Test activation of selected applications.
	 */
	public function testPageApplications_EnableSelected() {
		$this->zbxTestLogin('applications.php?groupid=4&hostid=10084');
		$this->zbxTestWaitForPageToLoad();

		// Select two applications and press enable button.
		$this->zbxTestCheckboxSelect('applications_348');
		$this->zbxTestCheckboxSelect('applications_351');
		$this->zbxTestClickButtonText('Enable');
		$this->zbxTestAlertAcceptWait();

		// Check the result in frontend.
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Items enabled');
		$this->zbxTestCheckFatalErrors();

		// Check the results in DB, that selected application items enabled.
		$sql='SELECT NULL FROM items i LEFT JOIN items_applications ia ON ia.itemid=i.itemid '
				. 'WHERE (ia.applicationid=348 OR ia.applicationid=351) AND i.status='.ITEM_STATUS_DISABLED;
		$this->assertEquals(0, DBcount($sql));
	}

	/**
	 * Test deactivation of all applications in host.
	 */
	public function testPageApplications_DisableAll() {
		$this->zbxTestLogin('applications.php?groupid=4&hostid=10084');
		$this->zbxTestWaitForPageToLoad();

		// Select all applications and press disable button.
		$this->zbxTestCheckboxSelect('all_applications');
		$this->zbxTestClickButtonText('Disable');
		$this->zbxTestAlertAcceptWait();

		// Check the result in frontend.
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Items disabled');
		$this->zbxTestCheckFatalErrors();

		// Check the results in DB, that all application items disabled.
		$sql = 'SELECT NULL FROM items i LEFT JOIN items_applications ia ON ia.itemid=i.itemid '
				. 'WHERE i.hostid=10084 AND i.flags=0 AND i.status='.ITEM_STATUS_ACTIVE;
		$this->assertEquals(0, DBcount($sql));
	}

	/**
	 * Test activation of all applications in host.
	 */
	public function testPageApplications_EnableAll() {
		$this->zbxTestLogin('applications.php?groupid=4&hostid=10084');
		$this->zbxTestCheckboxSelect('all_applications');

		// Select all applications and press disable button.
		$this->zbxTestClickButtonText('Enable');
		$this->zbxTestAlertAcceptWait();

		// Check the result in frontend.
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Items enabled');
		$this->zbxTestCheckFatalErrors();

		// Check the results in DB, that all application items enabled.
		$sql = 'SELECT NULL FROM items i LEFT JOIN items_applications ia ON ia.itemid=i.itemid '
				. 'WHERE i.hostid=10084 AND i.flags=0 AND i.status='.ITEM_STATUS_DISABLED;
		$this->assertEquals(0, DBcount($sql));
	}

	/**
	 * Test impossible deleting of templated application.
	 */
	public function testPageApplications_CannotDdelete() {
		$sql_hash = 'SELECT * FROM applications ORDER BY applicationid';
		$old_hash = DBhash($sql_hash);

		$this->zbxTestLogin('applications.php?groupid=4&hostid=10084');
		$this->zbxTestWaitForPageToLoad();

		$this->zbxTestCheckboxSelect('all_applications');
		$this->zbxTestClickButtonText('Delete');
		$this->zbxTestAlertAcceptWait();

		$this->zbxTestWaitUntilMessageTextPresent('msg-bad', 'Cannot delete applications');
		$this->zbxTestTextPresent('Cannot delete templated application.');
		$this->zbxTestCheckFatalErrors();

		$this->assertEquals($old_hash, DBhash($sql_hash));
	}

	/**
	 * Test deleting of application.
	 */
	public function testPageApplications_DeleteSelected() {
		$this->zbxTestLogin('applications.php?groupid=4&hostid=10084');
		$this->zbxTestWaitForPageToLoad();

		// Delete an application.
		$this->zbxTestCheckboxSelect('applications_99000');
		$this->zbxTestClickButtonText('Delete');
		$this->zbxTestAlertAcceptWait();

		// Check the result in frontend.
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Application deleted');
		$this->zbxTestCheckFatalErrors();

		// Check the result in DB.
		$this->assertEquals(0, DBcount('SELECT NULL FROM applications WHERE applicationid=99000'));
		$this->assertEquals(0, DBcount('SELECT NULL FROM items_applications WHERE itemappid=99000'));
		$this->assertEquals(1, DBcount('SELECT NULL FROM items WHERE itemid=99000'));
	}
}
