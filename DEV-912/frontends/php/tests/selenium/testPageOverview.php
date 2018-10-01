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

class testPageOverview extends CWebTest {

	public function getSuppressedProblemsData() {
		return [
			[
				[
					'group' => 'Group for problem suppression test',
					'type' => 'Triggers',
					'show_suppressed' => true,
					'result_host' => 'Host for suppression',
					'result_trigger' => 'Trigger for suppression'
				]
			],
			[
				[
					'group' => 'Group for problem suppression test',
					'type' => 'Triggers',
					'show_suppressed' => false,
					'no_result' => 'No data found.'

				]
			],
			[
				[
					'group' => 'Group for problem suppression test',
					'type' => 'Data',
					'show_suppressed' => true,
					'result_host' => 'Host for suppression',
					'result_item' => 'Trapper for suppression'
				]
			],
			[
				[
					'group' => 'Group for problem suppression test',
					'type' => 'Data',
					'show_suppressed' => false,
					'result' => 'No data found.',
					'result_host' => 'Host for suppression',
					'result_item' => 'Trapper for suppression'
				]
			]
		];
	}

	/**
	 *
	 * @dataProvider getSuppressedProblemsData
	 */
	public function testPageOverview_SuppressedProblems($data) {
		$this->zbxTestLogin('overview.php');
		$this->zbxTestClickButtonText('Reset');
		$this->zbxTestDropdownSelect('groupid', $data['group']);
		$this->zbxTestDropdownSelect('type', $data['type']);
		$this->zbxTestCheckboxSelect('show_suppressed', $data['show_suppressed']);
		$this->zbxTestClickButtonText('Apply');

		if ($data['type'] == 'Triggers'){

			if ($data['show_suppressed'] == true){
				$this->zbxTestAssertElementPresentXpath('.//th/a[text()="'.$data['result_host'].'"]');
				$this->zbxTestAssertElementPresentXpath('.//div[@class="vertical_rotation_inner"][text()="'.$data['result_trigger'].'"]');
				$this->zbxTestAssertElementPresentXpath('.//table[@class="list-table"]//td[contains(@class, average-bg)]');
			}

			if ($data['show_suppressed'] == false){
				$this->zbxTestAssertElementPresentXpath('.//tr[@class="nothing-to-show"]/td[text()="'.$data['no_result'].'"]');
			}

		} else {

			if ($data['show_suppressed'] == true){
				$this->zbxTestAssertElementPresentXpath('.//th/a[text()="'.$data['result_host'].'"]');
				$this->zbxTestAssertElementPresentXpath('.//div[@class="vertical_rotation_inner"][text()="'.$data['result_item'].'"]');
				$this->zbxTestAssertElementPresentXpath('.//table[@class="list-table"]//td[@class="average-bg cursor-pointer nowrap"]');
			}

			if ($data['show_suppressed'] == false){
				$this->zbxTestAssertElementPresentXpath('.//th/a[text()="'.$data['result_host'].'"]');
				$this->zbxTestAssertElementPresentXpath('.//div[@class="vertical_rotation_inner"][text()="'.$data['result_item'].'"]');
				$this->zbxTestAssertElementPresentXpath('.//table[@class="list-table"]//td[@class="cursor-pointer nowrap"]');
			}
		}
	}

	public function testPageOverview_CheckLayout() {
		$this->zbxTestLogin('overview.php');
		$this->zbxTestCheckTitle('Overview [refreshed every 30 sec.]');
		$this->zbxTestCheckHeader('Overview');
		$this->zbxTestTextPresent(['Group', 'Type', 'Hosts location']);
		$this->zbxTestTextPresent('Filter');
	}

// Check that no real host or template names displayed
	public function testPageOverview_NoHostNames() {
		$this->zbxTestLogin('overview.php');
		$this->zbxTestCheckTitle('Overview [refreshed every 30 sec.]');
		$this->zbxTestCheckNoRealHostnames();
	}
}
