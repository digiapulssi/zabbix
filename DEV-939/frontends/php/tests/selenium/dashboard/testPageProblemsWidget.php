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

require_once dirname(__FILE__) . '/../../include/CWebTest.php';

/**
 * @backup dashboard
 */
class testProblemsWidget extends CWebTest {

	/**
	 * Check "Problems" widget.
	 */
	public function testProblemsWidget_checkProblemsWidget() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid=101');
		$dashboard = CDashboardElement::find()->one();
		$widget = $dashboard->getWidget('Problems');
		// Check refresh interval of widget.
		$this->assertEquals(60, $widget->getRefreshInterval());
		$table = $widget->getContent()->asTable();
		$this->assertSame(['Time', '', '', 'Info', 'Host', 'Problem • Severity', 'Duration', 'Ack', 'Actions', 'Tags'],
			$table->getHeadersText());

		// Expected table values.
		$expected = [
			'Fourth test trigger with tag priority'	=>
			[
				'Time' => '2018-08-17 11:47:08',
				'Host' => 'ЗАББИКС Сервер',
				'Ack' => 'No',
				'Tags' => 'Delta: tEta: eGamma: g'
			],
		];

		$data = $table->index('Problem • Severity');

		foreach ($expected as $problem => $details) {
			// Get table row by problem name.
			$row = $data[$problem];
			// Check the value in table.
			foreach ($details as $column => $value) {
				$this->assertEquals($value, $row[$column]);
			}
		}
	}

	public function getProblemsWidgetTagsData() {
		return [
			[
				[
					'show_tags' => 'None',
					'tag_name' => 'None'
				]
			],
			[
				[
					'show_tags' => '3',
					'tag_name' => 'Full'
				]
			]
		];
	}

	/**
	 * @dataProvider getProblemsWidgetTagsData
	 */
	public function testProblemWidget_checkProblemWidgetTags($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid=101');
		$dashboard = CDashboardElement::find()->one()->edit();
		$widget = $dashboard->getWidget('Problems');
		$this->assertEquals(true, $widget->isEditable());
		// Open widget edit form and get the form element.
		$form = $widget->edit();

		// Get field container.
		$count = $form->getFieldContainer('Show tags');
		// Find segmented radio element by id within container and set value to '3'.
		$count->query('id:show_tags')->asSegmentedRadio()->one()->select($data['show_tags']);
		$names = $form->getFieldContainer('Tag name');
		// Find another segmented radio element by id within container and set value to 'None'.
		$names->query('id:tag_name_format')->asSegmentedRadio()->one()->select($data['tag_name']);

		if (array_key_exists('show_tags', $data)) {
			if ($data['show_tags'] === 'None') {
				$this->assertEquals(0, $names->query('id:tag_name_format')->asSegmentedRadio()->one()->getLabels()
						->filter(CElementQuery::ATTRIBUTES_NOT_PRESENT, ['disabled'])->count()
				);

				$this->assertFalse($form->query('id:tag_priority')->one()->isClickable());
			}
			else {
				$this->assertEquals(3, $names->query('id:tag_name_format')->asSegmentedRadio()->one()->getLabels()
						->filter(CElementQuery::CLICKABLE)->count()
				);
				$this->assertTrue($form->query('id:tag_priority')->one()->isClickable());
			}
		}

		// Uncheck checkbox 'Show timeline'.
		$form->query('id:show_timeline')->asCheckbox()->one()->uncheck();

		// Submit the form.
		$form->submit();

		$widget = $dashboard->getWidget('Problems');
		$table = $widget->getContent()->asTable();
		if (array_key_exists('show_tags', $data)) {
			if ($data['show_tags'] !== 'None') {
				foreach ($table->getRows() as $row) {
					$this->assertLessThanOrEqual($data['show_tags'], $row->getColumn('Tags')
						->query('xpath:./span[@class="tag"]')->count());
				}
			}
			else {
				$this->assertSame(['Time', 'Info', 'Host', 'Problem • Severity', 'Duration', 'Ack', 'Actions'],
					$table->getHeadersText());
			}
		}

		$dashboard->cancelEditing();
	}

	public function getProblemsWidgetCreateData() {
		return [
			[
				[
					'name' => 'Problem widget to check host groups',
					'refresh' => '10 seconds',
					'refresh_in_seconds' => '10',
					'host_groups' => ['Zabbix servers', 'Another group to check Overview'],
				]
			],
			[
				[
					'name' => 'Problem widget to check excluded host groups',
					'exclude_host_groups' => ['Group to check Overview']
				]
			],
			[
				[
					'name' => 'Problem widget to check hosts',
					'hosts' => ['1_Host_to_check_Monitoring_Overview', 'Host for tag permissions']
				]
			],
			[
				[
					'name' => 'Problem widget with severity filter - Disaster',
					'severities' => [
						'High' => '4',
						'Disaster' => '5'
					]
				]
			],
			[
				[
					'name' => 'Problem widget with specific problems',
					'problems' => [
						'trigger_Average'
					]
				]
			],
			[
				[
					'name' => 'Problem widget to check suppressed problems',
					'suppressed' => true
				]
			],
			[
				[
					'name' => 'Problem widget with unacknowledged problems only',
					'unacknowledged' => true
				]
			]
		];
	}

	/**
	 * @dataProvider getProblemsWidgetCreateData
	 */
	public function testProblemsWidget_checkProblemsWidgetCreate($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid=101');
		$dashboard = CDashboardElement::find()->one()->edit();

		// Add widget.
		$overlay = $dashboard->addWidget();
		$form = $overlay->asForm();
		// Set type to "Problem".
		$form->getField('Type')->asDropdown()->select('Problems');
		// Wait until form is reloaded.
		$form->waitUntilReloaded();
		// Set name of widget.
		$form->getField('Name')->type($data['name']);
		// Set refresh interval.
		if (array_key_exists('refresh', $data)) {
			$form->getField('Refresh interval')->asDropdown()->select($data['refresh']);
		}

		// Set host groups.
		if (array_key_exists('host_groups', $data)) {
			foreach ($data['host_groups'] as $group) {
				$groups = $form->getField('Host groups');
				$groups->select($group);
			}
		}

		// Set exlude host groups.
		if (array_key_exists('exclude_host_groups', $data)) {
			foreach ($data['exclude_host_groups'] as $exclude) {
				$groups = $form->getField('Exclude host groups');
				$groups->select($exclude);
			}
		}

		if (array_key_exists('hosts', $data)) {
			foreach ($data['hosts'] as $host) {
				$groups = $form->getField('Hosts')->type($host);
			}
		}

		if (array_key_exists('problems', $data)) {
			foreach ($data['problems'] as $problem) {
				$groups = $form->getField('Problem')->type($problem);
			}
		}

		if (array_key_exists('severities', $data)) {
			$data['severity_numbers']=[];
			$severities = $form->getField('Severity');
			foreach ($data['severities'] as $field => $value) {
				$severities->check($field);
				$data['severity_numbers'] = $value;
			}
		}

		$function_parameters =[];
		// Set checkbox 'Show suppressed problems'.
		if (array_key_exists('suppressed', $data) && $data['suppressed'] === TRUE) {
			$form->query('id:show_suppressed')->asCheckbox()->one()->check();
			$function_parameters = '$suppress = true';
		}
		else {
			$form->query('id:show_suppressed')->asCheckbox()->one()->uncheck();
		}

		// Set checkbox 'Show unacknowledged only'.
		if (array_key_exists('unacknowledged', $data) && $data['unacknowledged'] === TRUE) {
			$form->query('id:unacknowledged')->asCheckbox()->one()->check();
			$function_parameters = '$unacknowledged = true';
		}
		else {
			$form->query('id:unacknowledged')->asCheckbox()->one()->uncheck();
		}

		// Uncheck checkbox 'Show timeline'.
		$form->query('id:show_timeline')->asCheckbox()->one()->uncheck();

		$form->submit();

		// Check if widget was added.
		$widget = $dashboard->getWidget($data['name']);
		$this->assertTrue($widget->isVisible());

		// Save dashboard.
		$dashboard->save();
		$this->page->waitUntilReady();

		// Get global message.
		$message = CMessageElement::find()->one();
		// Check if message is positive.
		$this->assertTrue($message->isGood());
		// Check message title.
		$this->assertEquals('Dashboard updated', $message->getTitle());

		// Check widget refresh interval
		if (array_key_exists('refresh', $data)) {
			$this->assertEquals($data['refresh_in_seconds'], $widget->getRefreshInterval());
		}
		else {
			$this->assertEquals(60, $widget->getRefreshInterval());
		}

		// Check widget host groups.
		if (array_key_exists('host_groups', $data) || array_key_exists('exclude_host_groups', $data)) {
			$table = $widget->getContent()->asTable();
			// Get table data as array where values from column 'Problem • Severity' are used as array keys.
			$rows = $table->index('Problem • Severity');

			// If dataset doesn't contain host groups or exclude host groups, than replace it with empty array.
			$empty_array = [];
			$variable_list = ['host_groups', 'exclude_host_groups', 'severity_numbers', 'problems', 'hosts'];
			foreach ($variable_list as $variable){
				if (!array_key_exists($variable, $data)) {
					$data[$variable] = $empty_array;
				}
			}

			if ($function_parameters) {
				$db_data = $this->getProblems($data['host_groups'], $data['exclude_host_groups'], $data['severity_numbers'],
					$data['problems'], $data['hosts'], implode(',', $function_parameters));
			}
			else {
				$db_data = $this->getProblems($data['host_groups'], $data['exclude_host_groups'], $data['severity_numbers'],
					$data['problems'], $data['hosts']);
			}

			// Check db hostname equal to hostname from widget selected by problem name.
			foreach ($db_data as $db_problem) {
				$this->assertEquals($db_problem['hostname'], $rows[$db_problem['name']]['Host']);
			}
		}
	}

	public function testProblemsWidget_checkProblemsWidgetDelete() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid=101');
		$dashboard = CDashboardElement::find()->one()->edit();
		$widget = $dashboard->getWidget('Problems');
		$this->assertEquals(true, $widget->isEditable());
		$widget->delete();
		// Check if widget deleted.
		$this->assertTrue($dashboard->getWidget('Problems', false) === null);
	}

	/**
	* Get problems with hostid and host name by name of host group.
	*
	* @param array $groups names of host groups
	* @param array $exclude names of exclude host groups
	* @param array $severities contain severity numbers
	* @param boolean $suppress include suppress problems
	* @param boolean $unacknowledged exclude acknowledged problems
	*
	* @return array
	*/
	public function getProblems($groups, $exclude, $severities, $problems, $selected_hosts, $suppress = false, $unacknowledged = false) {
		$hosts = [];
		$triggers = [];

		array_walk($groups, function (&$value) {
			$value = zbx_dbstr($value);
		});

		array_walk($exclude, function (&$value) {
			$value = zbx_dbstr($value);
		});

		$criteria = [];
		$criteria_hosts = [];
		if ($groups) {
			$criteria[] = ' name IN ('.implode(',', $groups).')';
		}

		if ($exclude) {
			$criteria[] = ' name NOT IN ('.implode(',', $exclude).')';
		}
		if (($criteria) && ($selected_hosts)) {
			$criteria_hosts = ' AND host IN ('.implode(',', $selected_hosts).')';
		}
		elseif ($selected_hosts) {
			$criteria_hosts = ' WHERE host IN ('.implode(',', $selected_hosts).')';
		}

		if (($criteria)) {
			$data = CDBHelper::getAll(
					'SELECT hostid, name FROM hosts WHERE hostid IN ('.
						'SELECT hostid FROM hosts_groups WHERE groupid IN ('.
							'SELECT groupid FROM hstgrp WHERE' . implode(' AND', $criteria).
						')'.
					')'. implode(',', $criteria_hosts)
			);
		}
		else {
			$data = CDBHelper::getAll('SELECT hostid, name FROM hosts'. implode(',', $criteria_hosts));
		}

		if (!$data) {
			return [];
		}

		foreach ($data as $row) {
			$hosts[$row['hostid']] = $row['name'];
		}
		unset($data);

		$data = CDBHelper::getAll(
			'SELECT DISTINCT triggerid, hostid FROM functions, items WHERE functions.itemid=items.itemid'.
				' AND functions.itemid IN ('.
				'SELECT itemid FROM items WHERE flags IN (0,4) AND hostid IN ('.implode(',', array_keys($hosts)).')'.
			')'
		);

		if (!$data) {
			return [];
		}

		foreach ($data as $row) {
			$triggers[$row['triggerid']] = [
				'hostid' => $row['hostid'],
				'hostname' => $hosts[$row['hostid']]
			];
		}
		unset($data);
		unset($hosts);

		$condition = [];
		if ($suppress === false) {
			$condition = 'AND problem.eventid NOT IN (SELECT eventid FROM event_suppress)';
		}
		if ($unacknowledged) {
			$condition = 'AND problem.acknowledged != "1"';
		}
		if ($severities) {
			$condition = 'AND problem.severity IN ('.implode(',', $severity).')';
		}
		if ($problems) {
			$condition = 'AND problem.name LIKE '.implode(',', $severity).')';
		}

		$problems = CDBHelper::getAll(
			'SELECT DISTINCT problem.*'.
			' FROM problem, triggers'.
			' WHERE source IN (0,3) AND triggers.flags IN (0, 4) AND r_eventid IS NULL AND object=0'.
			' AND objectid=triggerid AND objectid IN ('.
				implode(',', array_keys($triggers)).') '.$condition
		);

		foreach ($problems as &$problem) {
			$problem = array_merge($problem, $triggers[$problem['objectid']]);
		}
		unset($problem);
		unset($triggers);

		return $problems;
	}
}
