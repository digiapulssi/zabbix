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

class testPageApplications extends CWebTest {

	public static function allHosts() {
		return [
			[
				[
					// "Template OS Linux"
					'hostid' => 10001,
					'status' => HOST_STATUS_TEMPLATE
				]
			],
			[
				[
					// "Test host" ("Zabbix server")
					'hostid' => 10084,
					'status' => HOST_STATUS_MONITORED
				]
			]
		];
	}

	/**
	* @dataProvider allHosts
	*/
	public function testPageApplications_CheckLayout($data) {
		$this->zbxTestLogin('applications.php?groupid=0&hostid='.$data['hostid']);

		$this->zbxTestCheckTitle('Configuration of applications');
		$this->zbxTestCheckHeader('Applications');
		$this->zbxTestTextPresent('Displaying');
		$this->zbxTestTextPresent($data['status'] == HOST_STATUS_TEMPLATE ? 'All templates' : 'All hosts');

		$this->zbxTestTextPresent(['Applications', 'Items']);
		$this->zbxTestTextPresent(['Enable selected', 'Disable selected', 'Delete selected']);
		$this->zbxTestTextPresent(
				[
					'CPU',
					'Filesystems',
					'General',
					'Memory',
					'Network interfaces',
					'OS',
					'Performance',
					'Processes',
					'Security'
				]
		);
	}

	/**
	* select Host and HostGroup
	*/
	public static function selectHostGroup() {
		return [
			[
				[
					'groupid' => 4,
					'groupname' => 'Zabbix servers',
					'hostid' => 10084,
					'hostname' => 'ЗАББИКС Сервер',
				]
			]
		];
	}

	/**
	* select Application for operations
	*/
	public function selectApp() {
		$this->zbxTestCheckboxSelect('applications_349');
		$this->zbxTestCheckboxSelect('applications_350');
		$this->zbxTestCheckboxSelect('applications_352');
		$this->zbxTestCheckboxSelect('applications_354');
	}

	/**
	* Test check of redirect Configuration -> Hosts -> Applications.
	* @dataProvider selectHostGroup
	*/
	public function testPageApplications_CheckSelectHost($data) {
		$this->zbxTestLogin('hosts.php');
		$this->zbxTestClickLinkText($data['hostname']);
		$this->zbxTestClickLinkText('Applications');

		$this->zbxTestDropdownAssertSelected('hostid', $data['hostname']);
	}

	/**
	* Test check of correct select Host and HostGroup.
	* @dataProvider selectHostGroup
	*/
	public function testPageApplications_CheckSelectGroupAndHost($data) {
		$this->zbxTestLogin('applications.php?groupid='.$data['groupid'].'&hostid='.$data['hostid']);

		$this->zbxTestDropdownAssertSelected('groupid', $data['groupname']);
		$this->zbxTestDropdownAssertSelected('hostid', $data['hostname']);
	}

	/**
	* Test check of activate selected Applications.
	* @dataProvider selectHostGroup
	*/
	public function testPageApplications_EnableSelectApp($data) {
		$this->zbxTestLogin('applications.php?groupid='.$data['groupid'].'&hostid='.$data['hostid']);

		$this->selectApp();
		$this->zbxTestClickButton('application.massenable');
		$this->webDriver->switchTo()->alert()->accept();

		$this->zbxTestCheckTitle('Configuration of applications');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Items enabled');
	}

	/**
	* Test check of deactivate selected Applications.
	* @dataProvider selectHostGroup
	*/
	public function testPageApplications_DisableSelectApp($data) {
		$this->zbxTestLogin('applications.php?groupid='.$data['groupid'].'&hostid='.$data['hostid']);

		$this->selectApp();
		$this->zbxTestClickButton('application.massdisable');
		$this->webDriver->switchTo()->alert()->accept();

		$this->zbxTestCheckTitle('Configuration of applications');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Items disabled');
	}

	/**
	* Test check for attempt of delete selected Applications.
	* @dataProvider selectHostGroup
	*/
	public function testPageApplications_AttemptDeleteSelectApp($data) {
		$this->zbxTestLogin('applications.php?groupid='.$data['groupid'].'&hostid='.$data['hostid']);

		$this->selectApp();
		$this->zbxTestClickButton('application.massdelete');
		$this->webDriver->switchTo()->alert()->accept();

		$this->zbxTestCheckTitle('Configuration of applications');
		$this->zbxTestWaitUntilMessageTextPresent('msg-bad', 'Cannot delete applications');
	}

	/**
	* Test check of activate all Applications for selected Host and HostGroup.
	* @dataProvider selectHostGroup
	*/
	public function testPageApplications_EnableAllApp($data) {
		$this->zbxTestLogin('applications.php?groupid='.$data['groupid'].'&hostid='.$data['hostid']);

		$this->zbxTestCheckboxSelect('all_applications');
		$this->zbxTestClickButton('application.massenable');
		$this->webDriver->switchTo()->alert()->accept();

		$this->zbxTestCheckTitle('Configuration of applications');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Items enabled');
	}

	/**
	 * Test check of deactivate all Applications for selected Host and HostGroup.
	* @dataProvider selectHostGroup
	*/
	public function testPageApplications_DisableAllApp($data) {
		$this->zbxTestLogin('applications.php?groupid='.$data['groupid'].'&hostid='.$data['hostid']);

		$this->zbxTestCheckboxSelect('all_applications');
		$this->zbxTestClickButton('application.massdisable');
		$this->webDriver->switchTo()->alert()->accept();

		$this->zbxTestCheckTitle('Configuration of applications');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Items disabled');
	}

	/**
	* Test check for attempt of delete all Applications for selected Host and HostGroup.
	* @dataProvider selectHostGroup
	*/
	public function testPageApplications_AttempDeleteAllApp($data) {
		$this->zbxTestLogin('applications.php?groupid='.$data['groupid'].'&hostid='.$data['hostid']);

		$this->zbxTestCheckboxSelect('all_applications');
		$this->zbxTestClickButton('application.massdelete');
		$this->webDriver->switchTo()->alert()->accept();

		$this->zbxTestCheckTitle('Configuration of applications');
		$this->zbxTestWaitUntilMessageTextPresent('msg-bad', 'Cannot delete applications');
	}
}
