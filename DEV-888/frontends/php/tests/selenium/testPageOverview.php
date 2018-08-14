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

/**
 * @backup problem
 */
class testPageOverview extends CWebTest {

	// Check that no real host or template names displayed
	public function testPageOverview_NoHostNames() {
		$this->zbxTestLogin('overview.php');
		$this->zbxTestCheckTitle('Overview [refreshed every 30 sec.]');
		$this->zbxTestCheckNoRealHostnames();
	}

	public function getLayoutData() {
		return [
			// Overview check with type = 'Triggers'
			[
				[
					'group' => 'all',
					'type' => 'Triggers',
					'result_hosts' =>
					[
						'Host-map-test-zbx6840', 'ЗАББИКС Сервер', '1_Host_to_check_Monitoring_Overview',
						'3_Host_to_check_Monitoring_Overview', '4_Host_to_check_Monitoring_Overview'
					],
					'result_triggers' =>
					[
						'Trigger-map-test-zbx6840', '1_trigger_Average', '1_trigger_Disaster', '1_trigger_High',
						'1_trigger_Information', '1_trigger_Not_classified', '1_trigger_Warning', '2_trigger_Average',
						'2_trigger_Disaster', '2_trigger_High', '2_trigger_Information', '2_trigger_Not_classified',
						'2_trigger_Warning', '3_trigger_Average'
					]
				]
			],
			[
				[
					'group' => 'Group to check Monitoring-> Overview',
					'type' => 'Triggers',
					'result_hosts' =>
					[
						'1_Host_to_check_Monitoring_Overview', '3_Host_to_check_Monitoring_Overview'
					],
					'result_triggers' =>
					[
						'1_trigger_Not_classified', '1_trigger_Information', '1_trigger_Warning', '1_trigger_Average',
						'1_trigger_High', '1_trigger_Disaster', '2_trigger_Not_classified', '2_trigger_Information',
						'2_trigger_Warning', '2_trigger_Average', '2_trigger_High', '2_trigger_Disaster',
						'3_trigger_Average'
					]
				]
			],
			[
				[
					'group' => 'Group to check Monitoring-> Overview',
					'type' => 'Triggers',
					'show_severity' => 'Information',
					'result_hosts' =>
					[
						'1_Host_to_check_Monitoring_Overview', '3_Host_to_check_Monitoring_Overview'
					],
					'result_triggers' =>
					[
						'1_trigger_Information', '1_trigger_Warning', '1_trigger_Average', '1_trigger_High',
						'1_trigger_Disaster', '2_trigger_Information', '2_trigger_Warning', '2_trigger_Average',
						'2_trigger_High', '2_trigger_Disaster', '3_trigger_Average'
					]
				]
			],
			[
				[
					'group' => 'Group to check Monitoring-> Overview',
					'type' => 'Triggers',
					'show_severity' => 'Warning',
					'result_hosts' =>
					[
						'1_Host_to_check_Monitoring_Overview', '3_Host_to_check_Monitoring_Overview'
					],
					'result_triggers' =>
					[
						'1_trigger_Warning', '1_trigger_Average', '1_trigger_High', '1_trigger_Disaster',
						'2_trigger_Warning', '2_trigger_Average', '2_trigger_High', '2_trigger_Disaster',
						'3_trigger_Average'
					]
				]
			],
			[
				[
					'group' => 'Group to check Monitoring-> Overview',
					'type' => 'Triggers',
					'show_severity' => 'Average',
					'result_hosts' =>
					[
						'1_Host_to_check_Monitoring_Overview', '3_Host_to_check_Monitoring_Overview'
					],
					'result_triggers' =>
					[
						'1_trigger_Average', '1_trigger_High', '1_trigger_Disaster', '2_trigger_Average',
						'2_trigger_High', '2_trigger_Disaster', '3_trigger_Average'
					]
				]
			],
			[
				[
					'group' => 'Group to check Monitoring-> Overview',
					'type' => 'Triggers',
					'show_severity' => 'High',
					'result_hosts' =>
					[
						'1_Host_to_check_Monitoring_Overview'
					],
					'result_triggers' =>
					[
						'1_trigger_High', '1_trigger_Disaster', '2_trigger_High', '2_trigger_Disaster'
					]
				]
			],
			[
				[
					'group' => 'Group to check Monitoring-> Overview',
					'type' => 'Triggers',
					'show_severity' => 'Disaster',
					'result_hosts' =>
					[
						'1_Host_to_check_Monitoring_Overview'
					],
					'result_triggers' =>
					[
						'1_trigger_Disaster', '2_trigger_Disaster'
					]
				]
			],
			[
				[
					'type' => 'Triggers',
					'applications' =>
					[
						'app_group' => 'Group to check Monitoring-> Overview',
						'app_host' => '1_Host_to_check_Monitoring_Overview',
						'application' => '1 application'
					],
					'result_hosts' =>
					[
						'1_Host_to_check_Monitoring_Overview'
					],
					'result_triggers' =>
					[
						'1_trigger_Not_classified', '1_trigger_Information', '1_trigger_Warning', '1_trigger_Average',
						'1_trigger_High', '1_trigger_Disaster'
					]
				]
			],
			[
				[
					'type' => 'Triggers',
					'applications' =>
					[
						'app_group' => 'Group to check Monitoring-> Overview',
						'app_host' => '1_Host_to_check_Monitoring_Overview',
						'application' => '2 application'
					],
					'result_hosts' =>
					[
						'1_Host_to_check_Monitoring_Overview'
					],
					'result_triggers' =>
					[
						'2_trigger_Not_classified', '2_trigger_Information', '2_trigger_Warning', '2_trigger_Average',
						'2_trigger_High', '2_trigger_Disaster'
					]
				]
			],
			[
				[
					'group' => 'Group to check Monitoring-> Overview',
					'type' => 'Triggers',
					'name' => 'Warning',
					'result_hosts' =>
					[
						'1_Host_to_check_Monitoring_Overview'
					],
					'result_triggers' =>
					[
						'1_trigger_Warning', '2_trigger_Warning'
					]
				]
			],
			[
				[
					'group' => 'Group to check Monitoring-> Overview',
					'type' => 'Triggers',
					'name' => '2_',
					'result_hosts' =>
					[
						'1_Host_to_check_Monitoring_Overview'
					],
					'result_triggers' =>
					[
						'2_trigger_Not_classified', '2_trigger_Information', '2_trigger_Warning', '2_trigger_Average',
						'2_trigger_High', '2_trigger_Disaster'
					]
				]
			],
			[
				[
					'group' => 'Group to check Monitoring-> Overview',
					'type' => 'Triggers',
					'name' => '9_',
					'no_result' => 'No data found'
				]
			],
			[
				[
					'group' => 'Group to check Monitoring-> Overview',
					'type' => 'Triggers',
					'ack_status' => 'With last event unacknowledged',
					'result_hosts' =>
					[
						'1_Host_to_check_Monitoring_Overview'
					],
					'result_triggers' =>
					[
						'1_trigger_Not_classified', '1_trigger_Information', '1_trigger_Warning', '1_trigger_Average',
						'1_trigger_High', '1_trigger_Disaster', '2_trigger_Not_classified', '2_trigger_Warning',
						'2_trigger_Average', '2_trigger_High', '2_trigger_Disaster'
					]
				]
			],
			[
				[
					'group' => 'Group to check Monitoring-> Overview',
					'type' => 'Triggers',
					'ack_status' => 'With unacknowledged events',
					'result_hosts' =>
					[
						'1_Host_to_check_Monitoring_Overview'
					],
					'result_triggers' =>
					[
						'1_trigger_Not_classified', '1_trigger_Information', '1_trigger_Warning', '1_trigger_Average',
						'1_trigger_High', '1_trigger_Disaster', '2_trigger_Not_classified', '2_trigger_Warning',
						'2_trigger_Average', '2_trigger_High', '2_trigger_Disaster'
					]
				]
			],
			[
				[
					'group' => 'Another group to check Monitoring-> Overview',
					'type' => 'Triggers',
					'result_hosts' =>
					[
						'4_Host_to_check_Monitoring_Overview'
					],
					'result_triggers' =>
					[
						'4_trigger_Average'
					]
				]
			],
			[
				[
					'group' => 'Another group to check Monitoring-> Overview',
					'type' => 'Triggers',
					'maintenance' => false,
					'no_result' => 'No data found'
				]
			],
			[
				[
					'group' => 'Group to check Monitoring-> Overview',
					'type' => 'Triggers',
					'inventories' =>
					[
						'inventory_field' => 'Notes',
						'inventory_value' => 'Notes'
					],
					'result_hosts' =>
					[
						'3_Host_to_check_Monitoring_Overview'
					],
					'result_triggers' =>
					[
						'3_trigger_Average'
					]
				]
			],
			[
				[
					'group' => 'Group to check Monitoring-> Overview',
					'type' => 'Triggers',
					'age' => '1',
					'no_result' => 'No data found'
				]
			],
			// TODO: after ZBX-14725 will be resolved
//			[
//				[
//					'group' => 'Group to check Monitoring-> Overview',
//					'type' => 'Triggers',
//					'show_triggers' => 'Recent problems',
//					'problem' => 'open',
//					'result_hosts' =>
//					[
//						'1_Host_to_check_Monitoring_Overview', '3_Host_to_check_Monitoring_Overview'
//					],
//					'result_triggers' =>
//					[
//						'1_trigger_Average', '1_trigger_Disaster', '1_trigger_High', '1_trigger_Information',
//						'1_trigger_Not_classified', '1_trigger_Warning', '2_trigger_Average', '2_trigger_Disaster',
//						'2_trigger_High', '2_trigger_Information', '2_trigger_Not_classified', '2_trigger_Warning',
//						'3_trigger_Average', '3_trigger_Disaster'
//					]
//				]
//			],
//			[
//				[
//					'group' => 'Group to check Monitoring-> Overview',
//					'type' => 'Triggers',
//					'show_triggers' => 'Problems',
//					'problem' => 'resolve',
//					'result_hosts' =>
//					[
//						'1_Host_to_check_Monitoring_Overview', '3_Host_to_check_Monitoring_Overview'
//					],
//					'result_triggers' =>
//					[
//						'1_trigger_Average', '1_trigger_Disaster', '1_trigger_High', '1_trigger_Information',
//						'1_trigger_Not_classified', '1_trigger_Warning', '2_trigger_Average', '2_trigger_Disaster',
//						'2_trigger_High', '2_trigger_Information', '2_trigger_Not_classified', '2_trigger_Warning',
//						'3_trigger_Average'
//					]
//				]
//			],
//			[
//				[
//					'group' => 'Group to check Monitoring-> Overview',
//					'type' => 'Triggers',
//					'show_triggers' => 'Any',
//					'result_hosts' =>
//					[
//						'1_Host_to_check_Monitoring_Overview', '3_Host_to_check_Monitoring_Overview'
//					],
//					'result_triggers' =>
//					[
//						'1_trigger_Average', '1_trigger_Disaster', '1_trigger_High', '1_trigger_Information',
//						'1_trigger_Not_classified', '1_trigger_Warning', '2_trigger_Average', '2_trigger_Disaster',
//						'2_trigger_High', '2_trigger_Information', '2_trigger_Not_classified', '2_trigger_Warning',
//						'3_trigger_Average', '3_trigger_Disaster'
//					]
//				]
//			],
			// Overview check with type = 'Data'
			[
				[
					'group' => 'Another group to check Monitoring-> Overview',
					'type' => 'Data',
					'result_hosts' =>
					[
						'4_Host_to_check_Monitoring_Overview'
					],
					'result_items' =>
					[
						'4_item'
					]
				]
			],
			[
				[
					'group' => 'Group to check Monitoring-> Overview',
					'type' => 'Data',
					'result_hosts' =>
					[
						'1_Host_to_check_Monitoring_Overview', '3_Host_to_check_Monitoring_Overview'
					],
					'result_items' =>
					[
						'1_item', '2_item', '3_item'
					]
				]
			],
			[
				[
					'type' => 'Data',
					'applications' =>
					[
						'app_group' => 'Group to check Monitoring-> Overview',
						'app_host' => '1_Host_to_check_Monitoring_Overview',
						'application' => '1 application'
					],
					'result_hosts' =>
					[
						'1_Host_to_check_Monitoring_Overview'
					],
					'result_items' =>
					[
						'1_item'
					]
				]
			],
			[
				[
					'type' => 'Data',
					'applications' =>
					[
						'app_group' => 'Group to check Monitoring-> Overview',
						'app_host' => '3_Host_to_check_Monitoring_Overview',
						'application' => '3 application'
					],
					'result_hosts' =>
					[
						'3_Host_to_check_Monitoring_Overview'
					],
					'result_items' =>
					[
						'3_item'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getLayoutData
	 */
	public function testPageMonitoringOverview_Layout($data) {
		$this->zbxTestLogin('overview.php');
		$this->zbxTestClickButtonText('Reset');

		if (array_key_exists('group', $data)) {
			$this->zbxTestDropdownSelect('groupid', $data['group']);
		}

		if (array_key_exists('type', $data)) {
			$this->zbxTestDropdownSelect('type', $data['type']);
		}

		if (array_key_exists('full_screen', $data) && $data['full_screen'] === true ) {
			$this->zbxTestClickXpathWait('//button[@title="Fullscreen"]');
			$this->zbxTestAssertElementNotPresentXpath('//div[@id="mmenu"][@class="top-nav-container"]');
			$this->zbxTestAssertElementNotPresentXpath('//nav[@class="top-subnav-container"]');
		}
		else {
			if ($this->zbxTestIsElementPresent('//button[@title="Normal view"]')) {
				$this->zbxTestClickXpathWait('//button[@title="Normal view"]');
			}
			$this->zbxTestAssertVisibleXpath('//div[@id="mmenu"][@class="top-nav-container"]');
			$this->zbxTestAssertVisibleXpath('//nav[@class="top-subnav-container"]');
		}

		if (array_key_exists('show', $data)) {
			$this->zbxTestClickXpath('//input[contains(@id,"show_triggers")]/../label[text()="'.$data['show'].'"]');
		}

		if (array_key_exists('ack_status', $data)) {
			$this->zbxTestDropdownSelect('ack_status', $data['ack_status']);
		}

		if (array_key_exists('show_severity', $data)) {
			$this->zbxTestDropdownSelect('show_severity', $data['show_severity']);
		}

		if (array_key_exists('age', $data)) {
			$this->zbxTestCheckboxSelect('status_change');
			$this->zbxTestInputType('status_change_days', $data['age']);
		}

		if (array_key_exists('name', $data)) {
			$this->zbxTestInputType('txt_select', $data['name']);
		}

		if (array_key_exists('applications', $data)){
			$this->zbxTestClick('application_name');
			$this->zbxTestLaunchOverlayDialog('Applications');
			foreach ($data['applications'] as $key => $value) {
				switch ($key) {
					case 'app_group':
						$this->zbxTestDropdownSelect('groupid', $value);
						break;

					case 'app_host':
						$this->zbxTestDropdownSelect('hostid', $value);
						break;

					case 'application':
						$this->zbxTestClickLinkTextWait($value);
						break;
				}
			}
		}

		if (array_key_exists('inventories', $data)) {
			foreach ($data['inventories'] as $key => $value) {
				switch ($key) {

					case 'inventory_field':
						$this->zbxTestDropdownSelect('inventory_0_field', $value);
						break;

					case 'inventory_value':
						$this->zbxTestInputType('inventory_0_value', $value);
						break;
				}
			}
		}

		if (array_key_exists('maintenance', $data) && $data['maintenance'] === true) {
			$this->zbxTestCheckboxSelect('show_maintenance');
		}
		elseif (array_key_exists('maintenance', $data) && $data['maintenance'] === false) {
			$this->zbxTestCheckboxSelect('show_maintenance', false);
		}

		if (array_key_exists('problem', $data) && $data['problem'] === 'open') {
			CTestDbHelper::setTriggerProblem('3_trigger_Disaster', TRIGGER_VALUE_TRUE, ['clock' => 1534231699, 'ns' => 726692807]);
		}
		elseif (array_key_exists('problem', $data) && $data['problem'] === 'resolve') {
			CTestDbHelper::setTriggerProblem('3_trigger_Disaster', TRIGGER_VALUE_FALSE, ['clock' => 1534231699, 'ns' => 726692807]);
		}

		if (array_key_exists('show_triggers', $data)) {
			if ($data['show_triggers'] === 'Recent problems') {
				$this->zbxTestClickXpath('//label[@for="show_triggers_0"]');
			}
			elseif ($data['show_triggers'] === 'Problems') {
				$this->zbxTestClickXpath('//label[@for="show_triggers_1"]');
			}
			else {
				$this->zbxTestClickXpath('//label[@for="show_triggers_2"]');
			}
			$this->putBreak();
		}

		$this->zbxTestClickButtonText('Apply');

		if (array_key_exists('no_result', $data)) {
			$this->zbxTestAssertElementPresentXpath('//tr[@class="nothing-to-show"]/td[text()="No data found."]');
		}
		// Check results for type='Triggers'
		elseif ($this->zbxTestGetSelectedLabel('type') === 'Triggers') {
			$this->zbxTestDropdownSelect('view_style', 'Top');
			// Check output for location='Top'
			$this->zbxTestAssertElementPresentXpath('//th[text()="Triggers"]');
			foreach ($data['result_hosts'] as $host) {
				$this->zbxTestAssertElementPresentXpath('//th[@class="vertical_rotation"][@title="'.$host.'"]');
			}
			foreach ($data['result_triggers'] as $trigger) {
				$this->zbxTestAssertElementPresentXpath('//td[1][text()="'.$trigger.'"]');
			}
			// Check output for location='Left'
			$this->zbxTestDropdownSelect('view_style', 'Left');
			$this->zbxTestAssertElementPresentXpath('//th[text()="Host"]');
			foreach ($data['result_hosts'] as $host) {
				$this->zbxTestAssertElementPresentXpath('//td[1]/a[text()="'.$host.'"]');
			}
			foreach ($data['result_triggers'] as $trigger) {
				$this->zbxTestAssertElementPresentXpath('//div[@class="vertical_rotation_inner"][text()="'.$trigger.'"]');
			}
		}
		// Check results for type='Data'
		elseif ($this->zbxTestGetSelectedLabel('type') === 'Data') {
			// Check output for location='Top'
			if ($this->zbxTestGetSelectedLabel('view_style') === 'Top') {
				$this->zbxTestAssertElementPresentXpath('//th[text()="Items"]');
				foreach ($data['result_hosts'] as $host) {
					$this->zbxTestAssertElementPresentXpath('//th[@class="vertical_rotation"][@title="'.$host.'"]');
				}
				foreach ($data['result_items'] as $item) {
					$this->zbxTestAssertElementPresentXpath('//td[1][text()="'.$item.'"]');
				}
			}
			// Check output for location='Left'
			elseif($this->zbxTestDropdownAssertSelected('view_style', 'Left')) {
				$this->zbxTestAssertElementPresentXpath('//th[text()="Host"]');
				foreach ($data['result_items'] as $item) {
					$this->zbxTestAssertElementPresentXpath('//th[@class="vertical_rotation"][@title="'.$item.'"]');
				}
				foreach ($data['result_hosts'] as $host) {
					$this->zbxTestAssertElementPresentXpath('//td[1][text()="'.$host.'"]');
				}
			}
		}
	}

	public function testPageMonitoringOverview_Links() {
		$this->zbxTestLogin('overview.php');
		$this->zbxTestCheckTitle('Overview [refreshed every 30 sec.]');
		$this->zbxTestCheckHeader('Overview');
		$this->zbxTestClickButtonText('Reset');
		$this->zbxTestDropdownSelect('type', 'Triggers');
		$this->zbxTestClickXpath('//tbody//td[contains(@class, "cursor-pointer")]');

		$this->zbxTestAssertElementPresentXpath('//h3[text()="Trigger"]');
		$this->zbxTestAssertElementPresentXpath('//a[contains(@class, "action-menu-item")][@aria-label="Trigger, Problems"]'
			. '[contains(@href, "zabbix.php?action=problem.view&filter_triggerids")][text()="Problems"]');
		$this->zbxTestAssertElementPresentXpath('//a[contains(@class, "action-menu-item")][@aria-label="Trigger, Acknowledge"]'
			. '[contains(@href, "action=acknowledge.edit&eventids")][text()="Acknowledge"]');
		$this->zbxTestAssertElementPresentXpath('//a[contains(@class, "action-menu-item")][@aria-label="Trigger, Description"]'
			. '[text()="Description"]');
		$this->zbxTestAssertElementPresentXpath('//a[contains(@class, "action-menu-item")][@aria-label="Trigger, Configuration"]'
			. '[contains(@href, "triggers.php?form=update&triggerid")][text()="Configuration"]');
		$this->zbxTestAssertElementPresentXpath('//h3[text()="History"]');
		$this->zbxTestAssertElementPresentXpath('//a[contains(@class, "action-menu-item")][@aria-label="History, 1_item"]'
			. '[contains(@href, "action=showgraph&itemid")]');

		$this->zbxTestDropdownSelect('type', 'Data');
		$this->zbxTestClickXpath('//tbody//td[contains(@class, "cursor-pointer")]');
		$this->zbxTestAssertElementPresentXpath('//h3[text()="History"]');
		$this->zbxTestAssertElementPresentXpath('//a[contains(@class, "action-menu-item")][@aria-label="Last hour graph"]'
			. '[contains(@href, "action=showgraph&period=3600")][text()="Last hour graph"]');
		$this->zbxTestAssertElementPresentXpath('//a[contains(@class, "action-menu-item")][@aria-label="Last week graph"]'
			. '[contains(@href, "action=showgraph&period=604800")][text()="Last week graph"]');
		$this->zbxTestAssertElementPresentXpath('//a[contains(@class, "action-menu-item")][@aria-label="Last month graph"]'
			. '[contains(@href, "action=showgraph&period=2678400")][text()="Last month graph"]');
		$this->zbxTestAssertElementPresentXpath('//a[contains(@class, "action-menu-item")][@aria-label="Latest values"]'
			. '[contains(@href, "action=showvalues&period=3600")][text()="Latest values"]');
	}

	private function getSeverity($host, $trigger) {
		$sql = 'SELECT priority'.
				' FROM triggers'.
				' WHERE description='.$trigger.
				' AND triggerid IN ('.
					'SELECT triggerid'.
					' FROM functions'.
					' WHERE itemid IN ('.
						'SELECT itemid'.
						' FROM items'.
						' WHERE hostid IN ('.
							'SELECT hostid'.
							' FROM hosts'.
							' WHERE name='.$host.
						')'.
					')'.
				')';

		$severity_number = DBfetch(DBselect($sql));
		$severities = ['na', 'info', 'warning', 'average','high', 'disaster'];
		$trigger_severity = $severities[$severity_number].'-bg';

		return $trigger_severity;
	}
}
