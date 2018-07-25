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

require_once dirname(__FILE__).'/../include/class.cwebtest.php';

class testPageReportsTriggerTop extends CWebTest {

	public function testPageReportsTriggerTop_FilterLayout() {
		$this->zbxTestLogin('toptriggers.php');
		$this->zbxTestExpandFilterTab('Filter');
		$this->zbxTestCheckTitle('100 busiest triggers');
		$this->zbxTestCheckHeader('100 busiest triggers');
		$this->zbxTestTextPresent('Host groups', 'Hosts', 'Severity', 'Filter', 'From', 'Till');
		$this->zbxTestClickXpathWait('//button[text()="Reset"]');

		// Check selected severities
		$severities = ['Not classified', 'Warning', 'High', 'Information', 'Average', 'Disaster'];
		foreach ($severities as $severity) {
			$severity_id = $this->zbxTestGetAttributeValue('//label[text()=\''.$severity.'\']', 'for');
			$this->assertTrue($this->zbxTestCheckboxSelected($severity_id));
		}

		// Check closed filter
		$this->zbxTestClickXpathWait('//a[contains(@class,\'filter-trigger\')]');
		$this->zbxTestAssertNotVisibleId('groupids_');

		// Check opened filter
		$this->zbxTestClickXpathWait('//a[contains(@class,\'filter-trigger\')]');
		$this->zbxTestAssertVisibleId('groupids_');
	}

	public static function getFilterData() {
		return [
			[
				[
					'host_group' => 'Zabbix servers',
					'date' => [
						'from' => 'now/d',
						'to' => 'now/d'
					]
				]
			],
			[
				[
					'host_group' => 'Zabbix servers',
					'date' => [
						'from' => '2017-10-23 00:00'
					],
					'result' => [
						'Test trigger to check tag filter on problem page',
						'Test trigger with tag'
					]
				]
			],
			[
				[
					'host_group' => 'Zabbix servers',
					'host' => 'Host ZBX6663',
					'date' => [
						'from' => 'now/d',
						'to' => 'now/d'
					],
				]
			],
			[
				[
					'host_group' => 'Zabbix servers',
					'host' => 'ЗАББИКС Сервер',
					'date' => [
						'from' => '2017-10-23 14:00'
					],
					'result' => [
						'Test trigger with tag'
					]
				]
			],
			[
				[
					'host_group' => 'Zabbix servers',
					'host' => 'ЗАББИКС Сервер',
					'date' => [
						'from' => '2018-01-01 00:00'
					],
				]
			],
			[
				[
					'host_group' => 'Zabbix servers',
					'host' => 'ЗАББИКС Сервер',
					'date' => [
						'from' => '2017-10-22 01:01',
						'to' => '2017-10-24 01:01'
					],
					'result' => [
						'Test trigger to check tag filter on problem page',
						'Test trigger with tag'
					]
				]
			],
			[
				[
					'date' => [
						'from' => '2017-10-23 12:35',
						'to' => '2017-10-23 12:36'
					],
					'result' => [
						'Trigger for tag permissions MySQL'
					]
				]
			],
			[
				[
					'date' => [
						'from' => '2017-10-23 12:33',
						'to' => '2017-10-23 12:36'
					],
					'result' => [
						'Test trigger to check tag filter on problem page',
						'Trigger for tag permissions MySQL'
					]
				]
			],
			[
				[
					'date' => [
						'from' => '2017-10-22 00:00'
					],
					'severities' => [
						'Not classified',
						'Information',
						'Warning'
					],
					'result' => [
						'Test trigger to check tag filter on problem page'
					]
				]
			],
			[
				[
					'date' => [
						'from' => '2017-10-22 00:00'
					],
					'severities' => [
						'Not classified',
						'Warning',
						'Information',
						'Average'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getFilterData
	 */
	public function testPageReportsTriggerTop_CheckFilter($data) {
		$this->zbxTestLogin('toptriggers.php');
		$this->zbxTestExpandFilterTab('Filter');
		$this->zbxTestClickButtonText('Reset');

		if (array_key_exists('host_group', $data)) {
			$this->zbxTestClickButtonMultiselect('groupids_');
			$this->zbxTestLaunchOverlayDialog('Host groups');
			$this->zbxTestClickLinkTextWait($data['host_group']);
			$this->zbxTestWaitUntilElementNotVisible(WebDriverBy::xpath("//div[@id='overlay_dialogue']"));
			$this->zbxTestMultiselectAssertSelected('groupids_', $data['host_group']);
		}

		if (array_key_exists('host', $data)) {
			$this->zbxTestClickButtonMultiselect('hostids_');
			$this->zbxTestLaunchOverlayDialog('Hosts');
			$this->zbxTestDropdownHasOptions('groupid', ['Host group for tag permissions', 'Zabbix servers',
				'ZBX6648 All Triggers', 'ZBX6648 Disabled Triggers', 'ZBX6648 Enabled Triggers']
			);
			$this->zbxTestDropdownSelect('groupid', 'Zabbix servers');
			$this->zbxTestClickXpathWait('//a[contains(@id,"spanid")][text()="'.$data['host'].'"]');
			$this->zbxTestWaitUntilElementNotVisible(WebDriverBy::xpath("//div[@id='overlay_dialogue']"));
			$this->zbxTestMultiselectAssertSelected('hostids_', $data['host']);
		}

		if (array_key_exists('severities', $data)) {
			foreach ($data['severities'] as $severity) {
				$severity_id = $this->zbxTestGetAttributeValue('//label[text()=\''.$severity.'\']', 'for');
				$this->zbxTestClick($severity_id);
			}
		}

		$this->zbxTestClickXpathWait('//button[@name="filter_set"][text()="Apply"]');

		// Fill in the date in filter
		if (array_key_exists('date', $data)) {
			$this->zbxTestExpandFilterTab('Time');
			foreach ($data['date'] as $i => $full_date) {
				$this->zbxTestInputTypeOverwrite($i, $full_date);
			}
			$this->zbxTestClickXpathWait('//button[@id="apply"][text()="Apply"]');
		}

		$this->zbxTestWaitForPageToLoad();
		if (array_key_exists('result', $data)) {
			$this->zbxTestTextPresent($data['result']);
		}
		else {
			$this->zbxTestAssertElementText('//tr[@class=\'nothing-to-show\']/td', 'No data found.');
		}
	}
}
