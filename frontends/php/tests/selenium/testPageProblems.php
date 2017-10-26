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

class testPageProblems extends CWebTest {

	public function testPageProblems_CheckLayout() {
		$this->zbxTestLogin('zabbix.php?action=problem.view');
		$this->zbxTestCheckTitle('Problems');
		$this->zbxTestCheckHeader('Problems');

		$this->assertTrue($this->zbxTestCheckboxSelected('filter_show_0'));
		$this->zbxTestTextPresent(['Show', 'Host groups', 'Host', 'Application', 'Triggers', 'Problem',
			'Minimum trigger severity', 'Age less than', 'Host inventory', 'Tags', 'Show hosts in maintenance',
			'Show unacknowledged only',
			'Severity', 'Time', 'Recovery time', 'Status', 'Host', 'Problem', 'Duration', 'Ack', 'Actions', 'Tags']);

		$this->zbxTestCheckNoRealHostnames();
	}

	public function testPageProblems_History_CheckLayout() {
		$this->zbxTestLogin('zabbix.php?action=problem.view');
		$this->zbxTestCheckHeader('Problems');

		$this->zbxTestClickXpathWait("//label[@for='filter_show_2']");
		$this->zbxTestClickButtonText('Apply');
		$this->assertTrue($this->zbxTestCheckboxSelected('filter_show_2'));
		$this->zbxTestAssertNotVisibleId('filter_age_state');
		$this->zbxTestAssertElementPresentId('scrollbar_cntr');
		$this->zbxTestTextPresent(['Show', 'Host groups', 'Host', 'Application', 'Triggers', 'Problem',
			'Minimum trigger severity', 'Host inventory', 'Tags', 'Show hosts in maintenance',
			'Show unacknowledged only',
			'Severity', 'Time', 'Recovery time', 'Status', 'Host', 'Problem', 'Duration', 'Ack', 'Actions', 'Tags']);

		$this->zbxTestCheckNoRealHostnames();
	}

	/**
	 * Search problems by "AND" or "OR" tag options
	 */
	public function testPageProblems_FilterByTagsOptionAndOr() {
		$this->zbxTestLogin('zabbix.php?action=problem.view');
		$this->zbxTestCheckHeader('Problems');

		// Check the default tag filter option AND and tag value option Like
		$this->zbxTestClickButtonText('Reset');
		$this->assertTrue($this->zbxTestCheckboxSelected('filter_evaltype_0'));
		$this->assertTrue($this->zbxTestCheckboxSelected('filter_tags_0_operator_0'));

		// Select "AND" option and two tag names with partial "Like" value match
		$this->zbxTestInputType('filter_tags_0_tag', 'Service');
		$this->zbxTestClick('filter_tags_add');
		$this->zbxTestInputTypeWait('filter_tags_1_tag', 'Database');
		$this->zbxTestClickButtonText('Apply');
		$this->zbxTestAssertElementText('//tbody/tr/td[10]/span', 'Test trigger to check tag filter on problem page');
		$this->zbxTestAssertElementText('//div[@class="table-stats"]', 'Displaying 1 of 1 found');
		$this->zbxTestTextNotPresent('Test trigger with tag');

		// Change tags select to "OR" option
		$this->zbxTestClickXpath('//label[@for="filter_evaltype_1"]');
		$this->zbxTestClickButtonText('Apply');
		$this->zbxTestAssertElementText('//tbody/tr[1]/td[10]/span', 'Test trigger with tag');
		$this->zbxTestAssertElementText('//tbody/tr[2]/td[10]/span', 'Test trigger to check tag filter on problem page');
		$this->zbxTestAssertElementText('//div[@class="table-stats"]', 'Displaying 2 of 2 found');
	}

	/**
	 * Search problems by partial or exact tag value match
	 */
	public function testPageProblems_FilterByTagsOptionLikeEqual() {
		$this->zbxTestLogin('zabbix.php?action=problem.view');
		$this->zbxTestCheckHeader('Problems');
		$this->zbxTestClickButtonText('Reset');

		// Search by partial "Like" tag value match
		$this->zbxTestInputType('filter_tags_0_tag', 'service');
		$this->zbxTestInputType('filter_tags_0_value', 'abc');
		$this->zbxTestClickButtonText('Apply');
		$this->zbxTestAssertElementText('//tbody/tr/td[10]/span', 'Test trigger to check tag filter on problem page');
		$this->zbxTestAssertElementText('//div[@class="table-stats"]', 'Displaying 1 of 1 found');
		$this->zbxTestTextNotPresent('Test trigger with tag');

		// Change tag value filter to "Equal"
		$this->zbxTestClickXpath('//label[@for="filter_tags_0_operator_1"]');
		$this->zbxTestClickButtonText('Apply');
		$this->zbxTestAssertElementText('//tbody/tr[@class="nothing-to-show"]/td', 'No data found.');
		$this->zbxTestAssertElementText('//div[@class="table-stats"]', 'Displaying 0 of 0 found');
	}

	/**
	 * Search problems by partial and exact tag value match and then remove one
	 */
	public function testPageProblems_FilterByTagsOptionLikeEqualAndRemoveOne() {
		$this->zbxTestLogin('zabbix.php?action=problem.view');
		$this->zbxTestCheckHeader('Problems');
		$this->zbxTestClickButtonText('Reset');

		// Select tag option "OR" and exact "Equal" tag value match
		$this->zbxTestClickXpath('//label[@for="filter_evaltype_1"]');
		$this->zbxTestClickXpath('//label[@for="filter_tags_0_operator_1"]');

		// Filter by two tags
		$this->zbxTestInputType('filter_tags_0_tag', 'Service');
		$this->zbxTestInputType('filter_tags_0_value', 'abc');
		$this->zbxTestClick('filter_tags_add');
		$this->zbxTestInputTypeWait('filter_tags_1_tag', 'service');
		$this->zbxTestInputType('filter_tags_0_value', 'abc');

		// Search and check result
		$this->zbxTestClickButtonText('Apply');
		$this->zbxTestAssertElementText('//tbody/tr[1]/td[10]/span', 'Test trigger with tag');
		$this->zbxTestAssertElementText('//tbody/tr[2]/td[10]/span', 'Test trigger to check tag filter on problem page');
		$this->zbxTestAssertElementText('//div[@class="table-stats"]', 'Displaying 2 of 2 found');

		// Remove first tag option
		$this->zbxTestClick('filter_tags_0_remove');
		$this->zbxTestClickButtonText('Apply');
		$this->zbxTestAssertElementText('//tbody/tr/td[10]/span', 'Test trigger to check tag filter on problem page');
		$this->zbxTestAssertElementText('//div[@class="table-stats"]', 'Displaying 1 of 1 found');
	}

	/**
	 * Search by all options in filter
	 */
	public function testPageProblems_FilterByAllOptions() {
		$this->zbxTestLogin('zabbix.php?action=problem.view');
		$this->zbxTestCheckHeader('Problems');
		$this->zbxTestClickButtonText('Reset');

		// Select host group
		$this->zbxTestClickXpath('//div[@id="filter_groupids_"]/..//button');
		$this->zbxTestWaitWindowAndSwitchToIt('zbx_popup');
		$this->zbxTestCheckboxSelect('hostGroups_4');
		$this->zbxTestClick('select');
		$this->zbxTestWaitWindowClose();

		// Select host
		$this->zbxTestClickXpath('//div[@id="filter_hostids_"]/..//button');
		$this->zbxTestWaitWindowAndSwitchToIt('zbx_popup');
		$this->zbxTestClickWait('spanid10084');
		$this->zbxTestWaitWindowClose();

		// Type application
		$this->zbxTestInputType('filter_application', 'Processes');

		// Select trigger
		$this->zbxTestClickAndSwitchToNewWindow('//div[@id="filter_triggerids_"]/..//button');
		$this->zbxTestDropdownSelectWait('hostid', 'ЗАББИКС Сервер');
		$this->zbxTestCheckboxSelect("triggers_'99250'");
		$this->zbxTestCheckboxSelect("triggers_'99251'");
		$this->zbxTestClick('select');
		$this->zbxTestWaitWindowClose();

		// Type problem name
		$this->zbxTestInputType('filter_problem', 'Test trigger');

		// Change minimum severity to Average
		$this->zbxTestDropdownSelect('filter_severity', 'Average');
		// Chrck Age less than
		$this->zbxTestCheckboxSelect('filter_age_state');
		// Add tag
		$this->zbxTestInputType('filter_tags_0_tag', 'service');
		$this->zbxTestInputType('filter_tags_0_value', 'abc');
		// Check Show unacknowledged only
		$this->zbxTestCheckboxSelect('filter_unacknowledged');
		// Check Show details
		$this->zbxTestCheckboxSelect('filter_details');

		// Apply filter and check result
		$this->zbxTestClickButtonText('Apply');
		$this->zbxTestAssertElementText('//tbody/tr/td[10]/span', 'Test trigger to check tag filter on problem page');
		$this->zbxTestAssertElementText('//div[@class="table-stats"]', 'Displaying 1 of 1 found');
		$this->zbxTestClickButtonText('Reset');
	}
}
