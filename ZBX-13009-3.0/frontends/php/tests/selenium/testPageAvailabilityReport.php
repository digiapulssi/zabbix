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

class testPageAvailabilityReport extends CWebTest {

	/**
	 * SLA calculated events data in format: triggerid, starttime, duration.
	 * Triggers created via import of data/data_test.sql
	 * @var array
	 */
	public static $SLA_events = [
		[16027, '01.01.2017 22:59:00', '+1 hour 59 second'],
		[16027, '02.01.2017 22:50:00', '+1 hour 11 minutes'],
		[16028, '01.01.2017 22:59:10', '+1 hour 5 minutes 59 second'],
		[16028, '10.10.2017 00:00:00', '+1 second'],
		[16029, '30.11.2017 22:59:00', '+1 hour 59 second'],
		[16029, '31.12.2017 22:50:00', '+1 hour 11 minutes']
	];

	/**
	 * Data provider for testPageAvailabilityReportSLA. Array have following schema:
	 * - SLA availability report filter start time
	 * - SLA availability report filter end time
	 * - SLA availability report result table row values. Only 3 first cells of every row are defined.
	 *
	 * @return array
	 */
	public function dataProviderSLA() {
		return [
			['01.01.2017 00:00:00', '02.01.2017 00:01:00', [
				['A trigger', '4.2320%', '95.7680%'],
				['B trigger', '4.2910%', '95.7090%'],
				['C trigger', '', '100.0000%']
			]],
			['10.10.2017 00:00:00', '10.11.2017 23:59:59', [
				['A trigger', '', '100.0000%'],
				['B trigger', '', '100.0000%'],
				['C trigger', '', '100.0000%']
			]],
			['01.01.2017 00:00:00', '01.01.2018 00:00:00', [
				['A trigger', '0.0251%', '99.9749%'],
				['B trigger', '0.0126%', '99.9874%'],
				['C trigger', '0.0249%', '99.9751%']
			]],
		];
	}

	/**
	 * @beforeClass
	 */
	public static function initializeTest() {
		global $DB;

		if (!isset($DB['DB'])) {
			DBconnect($error);
		}

		// Clear events data.
		$triggerids = [];
		foreach (self::$SLA_events as $event) {
			$triggerids[$event[0]] = true;
		}

		DBexecute('DELETE FROM events WHERE source='.EVENT_OBJECT_TRIGGER.' AND '
			.dbConditionInt('objectid', array_keys($triggerids))
		);

		// Generate events data for 'SLA host' trigger items.
		$eventid = get_dbid('events', 'eventid');
		$start_time = 'INSERT INTO events SET eventid=%1$d, objectid=%2$d, clock=%3$d, value='.TRIGGER_VALUE_TRUE;
		$end_time = 'INSERT INTO events SET eventid=%1$d, objectid=%2$d, clock=%3$d, value='.TRIGGER_VALUE_FALSE;

		foreach (self::$SLA_events as $event) {
			array_unshift($event, $eventid);
			// Event start clock.
			$event[2] = strtotime($event[2]);
			DBexecute(vsprintf($start_time, $event));

			// Event end clock.
			$event[0]++;
			$event[2] = strtotime($event[3], $event[2]);
			DBexecute(vsprintf($end_time, $event));

			$eventid = $eventid + 2;
		}

		DBclose();
	}

	/**
	 * @dataProvider dataProviderSLA
	 */
	public function testPageAvailabilityReportSLA($start_time, $end_time, $sla_item_values) {
		$args = [
			'filter_timesince' => date('YmdHis', strtotime($start_time)),
			'filter_timetill' => date('YmdHis', strtotime($end_time)),
			'filter_hostid' => 50009
		];

		$this->zbxTestLogin('report2.php?'.http_build_query($args));
		$table_rows =$this->webDriver->findElements(WebDriverBy::xpath('//table[@class="list-table"]/tbody/tr'));
		if (!$table_rows) {
			$this->fail("Failed to get SLA reports table.");
		}

		foreach ($table_rows as $row) {
			$cells = $row->findElements(WebDriverBy::xpath('td'));
			$cells_values = [];

			foreach ($cells as $cell) {
				$cells_values[] = $cell->getText();
			}

			// Check only first 3 cells in every row: Label, Problem state value, Ok state value.
			$this->assertContains(array_slice($cells_values, 0, 3), $sla_item_values);
		}
	}

	public function testPageAvailabilityReport_ByHost_CheckLayout() {
		$this->zbxTestLogin('report2.php?config=0');
		$this->zbxTestCheckTitle('Availability report');
		$this->zbxTestCheckHeader('Availability report');
		$this->zbxTestTextPresent('Mode');
		$this->zbxTestTextPresent('Filter');
		$this->zbxTestTextPresent(['Host', 'Name', 'Problems', 'Ok', 'Graph']);
	}

// Check that no real host or template names displayed
	public function testPageAvailabilityReport_ByHost_NoHostNames() {
		$this->zbxTestLogin('report2.php?config=0');
		$this->zbxTestCheckTitle('Availability report');
		$this->zbxTestCheckNoRealHostnames();
	}

	public function testPageAvailabilityReport_ByTriggerTemplate_CheckLayout() {
		$this->zbxTestLogin('report2.php?config=1');
		$this->zbxTestCheckTitle('Availability report');
		$this->zbxTestCheckHeader('Availability report');
		$this->zbxTestTextPresent('Mode');
		$this->zbxTestTextPresent('Filter');
		$this->zbxTestTextPresent(['Host', 'Name', 'Problems', 'Ok', 'Graph']);
	}

// Check that no real host or template names displayed
	public function testPageAvailabilityReport_ByTriggerTemplate_NoHostNames() {
		$this->zbxTestLogin('report2.php?config=1');
		$this->zbxTestCheckNoRealHostnames();
	}
}
