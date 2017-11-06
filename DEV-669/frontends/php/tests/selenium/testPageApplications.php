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
	* Data for select
	*/
	public static function data() {
		return [
			[
				[
					'groupid' => 4,
					'groupname' => 'Zabbix servers',
					'hostid' => 10084,
					'hostname' => 'ЗАББИКС Сервер',
					'applications' => [349,350,352,354]
				]
			]
		];
	}

	/**
	* select of Applications
	*/
	public function selectApplications($id) {
		foreach ($id as $appid) {
			$this->zbxTestCheckboxSelect('applications_'.$appid);
		}
	}

	/**
	* Test check of redirect Configuration -> Hosts -> Applications.
	* Test check of correct select Host and HostGroup with redirect to link.
	* @dataProvider data
	*/
	public function testPageApplications_CheckLinkSelectHost($data) {
		$this->zbxTestLogin('hosts.php');
		$this->zbxTestClickLinkTextWait($data['hostname']);
		$this->zbxTestClickLinkTextWait('Applications');

		$this->zbxTestWaitForPageToLoad();
		$this->zbxTestDropdownAssertSelected('hostid', $data['hostname']);

		$this->zbxTestLogin('applications.php?groupid='.$data['groupid'].'&hostid='.$data['hostid']);

		$this->zbxTestDropdownAssertSelected('groupid', $data['groupname']);
		$this->zbxTestDropdownAssertSelected('hostid', $data['hostname']);

	}

	/**
	* Check of Applications for selected Host from DataBase.
	* @dataProvider data
	*/
	public function testPageApplications_CheckForSelectHost($data) {
		$this->zbxTestLogin('applications.php?groupid='.$data['groupid'].'&hostid='.$data['hostid']);

		$sqlAllApplications = 'SELECT A.name FROM applications A WHERE A.hostid='.$data['hostid'];
		$result = DBselect($sqlAllApplications);
		while ($row = DBfetch($result)) {
			$this->zbxTestTextPresent($row['name']);
		}
	}

	/**
	* Check: when selected all Hosts, appears button "Create application (select host first)" and it is disabled
	*/
	public function testPageApplications_CheckDisableButton() {
		$this->zbxTestLogin('applications.php?groupid=0&hostid=0');

		$this->zbxTestTextPresent('Create application (select host first)');
		$this->zbxTestAssertAttribute("//button[@id='form']",'disabled','true');
	}

	/**
	* Test check of activate selected Applications.
	* @dataProvider data
	*/
	public function testPageApplications_EnableSelectApp($data) {
		$this->zbxTestLogin('applications.php?groupid='.$data['groupid'].'&hostid='.$data['hostid']);

		$this->selectApplications($data['applications']);
		$this->zbxTestClickButton('application.massenable');
		$this->zbxTestAlertAcceptWait();

		$this->zbxTestCheckTitle('Configuration of applications');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Items enabled');

		$applications = implode(", ", $data['applications']);
		$hostid= $data['hostid'];
		$sql = 'SELECT NULL FROM items I LEFT JOIN items_applications IA USING (itemid)
		WHERE IA.applicationid IN ('.$applications.') && I.hostid='.$hostid.' && I.status='.ITEM_STATUS_DISABLED;

		$this->assertEquals(0, DBcount($sql), 'Chuck Norris: Not all Items for ApplicationsID: '.$applications.' have the status ITEM_STATUS_ACTIVE');
	}

	/**
	* Test check of deactivate selected Applications.
	* @dataProvider data
	*/
	public function testPageApplications_DisableSelectApp($data) {
		$this->zbxTestLogin('applications.php?groupid='.$data['groupid'].'&hostid='.$data['hostid']);

		$this->selectApplications($data['applications']);
		$this->zbxTestClickButton('application.massdisable');
		$this->zbxTestAlertAcceptWait();

		$this->zbxTestCheckTitle('Configuration of applications');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Items disabled');

		$applications = implode(", ", $data['applications']);
		$hostid= $data['hostid'];
		$sql = 'SELECT NULL FROM items I LEFT JOIN items_applications IA USING (itemid)
		WHERE IA.applicationid IN ('.$applications.') && I.flags<>2 && I.hostid='.$hostid.' && I.status='.ITEM_STATUS_ACTIVE;

		$this->assertEquals(0, DBcount($sql), 'Chuck Norris: Not all Items for ApplicationsID: '.$applications.' have the status ITEM_STATUS_DISABLED');
	}

	/**
	* Test check for attempt of delete selected Applications.
	* @dataProvider data
	*/
	public function testPageApplications_AttemptDeleteSelectApp($data) {
		$this->zbxTestLogin('applications.php?groupid='.$data['groupid'].'&hostid='.$data['hostid']);

		$this->selectApplications($data['applications']);
		$this->zbxTestClickButton('application.massdelete');
		$this->zbxTestAlertAcceptWait();

		$this->zbxTestCheckTitle('Configuration of applications');
		$this->zbxTestWaitUntilMessageTextPresent('msg-bad', 'Cannot delete applications');
	}

	/**
	* Test check of activate all Applications for selected Host and HostGroup.
	* @dataProvider data
	*/
	public function testPageApplications_EnableAllApp($data) {
		$this->zbxTestLogin('applications.php?groupid='.$data['groupid'].'&hostid='.$data['hostid']);

		$this->zbxTestCheckboxSelect('all_applications');
		$this->zbxTestClickButton('application.massenable');
		$this->zbxTestAlertAcceptWait();

		$this->zbxTestCheckTitle('Configuration of applications');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Items enabled');

		$sql = 'SELECT NULL FROM items I LEFT JOIN items_applications IA USING (itemid)
		WHERE IA.applicationid>0 && I.flags<>2 && I.hostid='.$data['hostid'].' && I.status='.ITEM_STATUS_DISABLED;

		$this->assertEquals(0, DBcount($sql), 'Chuck Norris: Not all Items for Applications have the status ITEM_STATUS_ACTIVE');
	}

	/**
	* Test check of deactivate all Applications for selected Host and HostGroup.
	* @dataProvider data
	*/
	public function testPageApplications_DisableAllApp($data) {
		$this->zbxTestLogin('applications.php?groupid='.$data['groupid'].'&hostid='.$data['hostid']);

		$this->zbxTestCheckboxSelect('all_applications');
		$this->zbxTestClickButton('application.massdisable');
		$this->zbxTestAlertAcceptWait();

		$this->zbxTestCheckTitle('Configuration of applications');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Items disabled');

		$sql = 'SELECT NULL FROM items I LEFT JOIN items_applications IA USING (itemid)
		WHERE IA.applicationid>0 && I.flags<>2 && I.hostid='.$data['hostid'].' && I.status='.ITEM_STATUS_ACTIVE;

		$this->assertEquals(0, DBcount($sql), 'Chuck Norris: Not all Items for Applications have the status ITEM_STATUS_DISABLED');
	}

	/**
	* Test check for attempt of delete all Applications for selected Host and  HostGroup.
	* @dataProvider data
	*/
	public function testPageApplications_AttempDeleteAllApp($data) {
		$this->zbxTestLogin('applications.php?groupid='.$data['groupid'].'&hostid='.$data['hostid']);

		$this->zbxTestCheckboxSelect('all_applications');
		$this->zbxTestClickButton('application.massdelete');
		$this->zbxTestAlertAcceptWait();

		$this->zbxTestCheckTitle('Configuration of applications');
		$this->zbxTestWaitUntilMessageTextPresent('msg-bad', 'Cannot delete applications');
	}
}
