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

require_once dirname(__FILE__) . '/../include/class.cwebtest.php';

/**
 * @backup drules
 */
class testFormConfigDiscovery extends CWebTest {
	public static function getCreateData() {
		return [
			[
				[
					'proxy' => 'Active proxy 1',
					'range' => '192.168.0.1-25',
					'delay' => '1m',
					'checks' => [
						['check_action' => 'add', 'type' => 'HTTP', 'ports' => '7555']
					],
					'error' => 'Incorrect value for field "name": cannot be empty.',
					'check_db' => false
				]
			],
			[
				[
					'name' => ' ',
					'proxy' => 'Active proxy 1',
					'range' => '192.168.0.1-25',
					'delay' => '1m',
					'checks' => [
						['check_action' => 'add', 'type' => 'HTTP', 'ports' => '7555']
					],
					'error' => 'Incorrect value for field "name": cannot be empty.'
				]
			],
			[
				[
					'name' => 'Discovery rule with empty IP range',
					'proxy' => 'Active proxy 1',
					'range' => ' ',
					'delay' => '1m',
					'checks' => [
						['check_action' => 'add', 'type' => 'HTTP', 'ports' => '7555']
					],
					'error' => 'Incorrect value for field "iprange": cannot be empty.'
				]
			],
			[
				[
					'name' => 'Discovery rule with incorrect IP range',
					'proxy' => 'Active proxy 1',
					'range' => 'text',
					'delay' => '1m',
					'checks' => [
						['check_action' => 'add', 'type' => 'HTTP', 'ports' => '7555']
					],
					'error' => 'Incorrect value for field "iprange": invalid address range "text".'
				]
			],
			[
				[
					'name' => 'Discovery rule with incorrect update interval',
					'proxy' => 'Active proxy 1',
					'delay' => '1G',
					'checks' => [
						['check_action' => 'add', 'type' => 'HTTP', 'ports' => '7555']
					],
					'error' => 'Field "Update interval" is not correct: a time unit is expected'
				]
			],
			[
				[
					'name' => 'Discovery rule without checks',
					'proxy' => 'Active proxy 3',
					'range' => '192.168.0.1-25',
					'delay' => '1m',
					'error' => 'Cannot save discovery rule without checks.',
					'check_db' => false
				]
			],
			[
				[
					'name' => 'Local network',
					'checks' => [
						['check_action' => 'add', 'type' => 'HTTPS', 'ports' => '447']
					],
					'error' => 'Discovery rule "Local network" already exists.',
					'check_db' => false
				]
			],
			[
				[
					'name' => 'Discovery rule with incorrect port range',
					'checks' => [
						['check_action' => 'add', 'type' => 'POP', 'ports' => 'abc']
					],
					'error_in_checks' => true
				]
			],
			[
				[
					'name' => 'Discovery rule 1',
					'proxy' => 'Active proxy 1',
					'range' => '192.168.0.1-25',
					'delay' => '1m',
					'checks' => [
						[ 'check_action' => 'add', 'type' => 'HTTP', 'ports' => '7555']
					]
				]
			],
			[
				[
					'name' => 'Discovery rule with many checks',
					'proxy' => 'Active proxy 1',
					'range' => '192.168.0.1-25',
					'delay' => '1m',
					'checks' => [
						[ 'check_action' => 'add', 'type' => 'ICMP ping'],
						[ 'check_action' => 'add', 'type' => 'IMAP', 'ports' => '144'],
						[
							'check_action' => 'add',
							'type' => 'SNMPv1 agent',
							'port' => '156',
							'community' => '1',
							'snmp_oid' => '1'
						],
						[
							'check_action' => 'add',
							'type' => 'SNMPv3 agent',
							'port' => '157',
							'snmp_oid' => '1',
							'context_name' => '1',
							'security_name' => '1',
							'security_level' => 'noAuthNoPriv'
						],
						[
							'check_action' => 'add',
							'type' => 'SNMPv3 agent',
							'port' => '158',
							'snmp_oid' => '2',
							'context_name' => '2',
							'security_name' => '2',
							'security_level' => 'authNoPriv',
							'auth_protocol' => 'SHA',
							'auth_passphrase' => '2'
						],
						[
							'check_action' => 'add',
							'type' => 'SNMPv3 agent',
							'port' => '159',
							'snmp_oid' => '3',
							'context_name' => '3',
							'security_name' => '3',
							'security_level' => 'authPriv',
							'auth_protocol' => 'MD5',
							'auth_passphrase' => '3',
							'priv_protocol' => 'AES',
							'priv_passphrase' => '3']
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getCreateData
	 */
	public function testFormConfigDiscovery_Create($data) {
		$this->zbxTestLogin('discoveryconf.php');
		$this->zbxTestClickButtonText('Create discovery rule');
		$this->FillInFields($data);

		if (array_key_exists('error_in_checks', $data) && $data['error_in_checks'] === true) {
			$this->zbxTestAssertElementPresentXpath('//div[@class="overlay-dialogue-body"]//span[text()="Incorrect port range."]');
			$this->zbxTestClickXpath('//div[@class="overlay-dialogue-footer"]/button[text()="Cancel"]');
			return;
		}

		$this->zbxTestClick('add');
		if (array_key_exists('name', $data)) {
			$sql = 'SELECT NULL FROM drules WHERE name='.zbx_dbstr($data['name']);
		}

		if (array_key_exists('error', $data)) {
			$this->zbxTestWaitUntilMessageTextPresent('msg-bad', $data['error']);
			if (!array_key_exists('check_db', $data) || $data['check_db'] === true) {
					$this->assertEquals(0, DBcount($sql));
			}
		}
		else {
			$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Discovery rule created');
			$this->assertEquals(1, DBcount($sql));
		}
	}

	public static function getUpdateData() {
		return [
			[
				[
					'old_name' => 'Discovery rule for update',
					'name' => ' ',
					'error' => 'Incorrect value for field "name": cannot be empty.'
				]
			],
			[
				[
					'old_name' => 'Discovery rule for update',
					'range' => 'text',
					'error' => 'Incorrect value for field "iprange": invalid address range "text".'
				]
			],
			[
				[
					'old_name' => 'Discovery rule for update',
					'delay' => 'text',
					'error' => 'Field "Update interval" is not correct: a time unit is expected'
				]
			],
			[
				[
					'old_name' => 'Discovery rule for update',
					'checks' => [
						['check_action' => 'remove']
					],
					'error' => 'Cannot save discovery rule without checks.'
				]
			],
			[
				[
					'old_name' => 'Discovery rule for update',
					'status' => 'Disabled'
				]
			],
			[
				[
					'old_name' => 'Disabled discovery rule for update',
					'status' => 'Enabled'
				]
			],
			[
				[
					'old_name' => 'Local network',
					'criteria' => 'Zabbix agent "system.uname"'
				]
			],
			[
				[
					'old_name' => 'Local network',
					'checks' => [
						['check_action' => 'add', 'type' => 'POP', 'ports' => '111']
					],
				]
			],
			[
				[
					'old_name' => 'Discovery rule for update',
					'name' => 'Update name',
					'proxy' => 'Active proxy 3',
					'range' => '1.1.0.1-25',
					'delay' => '30s',
					'checks' => [
						['check_action' => 'update', 'type' => 'TCP', 'ports' => '9']
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getUpdateData
	 */
	public function testFormConfigDiscovery_Update($data) {
		$this->zbxTestLogin('discoveryconf.php');
		$this->zbxTestClickLinkText($data['old_name']);
		$this->FillInFields($data);

		// Counter of rows at discovery page.
		$dchecks_page = count($this->webDriver->findElements(WebDriverBy::xpath('//div[@id="dcheckList"]//tr'))) - 1;

		$this->zbxTestClick('update');

		if (array_key_exists('error', $data)) {
			$this->zbxTestWaitUntilMessageTextPresent('msg-bad', $data['error']);
		}
		else {
			$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Discovery rule updated');
			$this->zbxTestCheckTitle('Configuration of discovery rules');
			$this->zbxTestCheckHeader('Discovery rules');
			$this->zbxTestCheckFatalErrors();
			// DB check table drules
			if (!array_key_exists('name', $data)) {
				$data['name'] = $data['old_name'];
			}

			$proxy = DBfetch(DBselect('SELECT proxy_hostid FROM drules WHERE name='.zbx_dbstr($data['name'])));
			if ($proxy['proxy_hostid']) {
				$drules_after = DBdata('SELECT druleid, hosts.host AS proxy_name, drules.name, iprange, delay, nextcheck, drules.status'.
						' FROM drules'.
						' JOIN hosts ON drules.proxy_hostid=hostid'.
						' WHERE drules.name='.zbx_dbstr($data['name'])
					, false);
			}
			else {
				$drules_after = DBdata('SELECT druleid, name, iprange, delay, nextcheck, status'.
						' FROM drules'.
						' WHERE name='.zbx_dbstr($data['name'])
					,false);
			}

			$drules_after = $drules_after[0][0];

			$fields = [
				'name' => 'name',
				'proxy' => 'proxy_name',
				'range' => 'iprange',
				'delay' => 'delay'
			];

			foreach ($fields as $data_key => $db_key) {
				if (array_key_exists($data_key, $data)) {
					$this->assertEquals($data[$data_key], $drules_after[$db_key]);
				}
			}

			// DB check table dchecks.
			$dchecks_db = DBcount('SELECT dcheckid FROM dchecks WHERE druleid IN ( SELECT druleid FROM drules WHERE name='
					.zbx_dbstr($data['name']).')');
			$this->assertEquals($dchecks_db, $dchecks_page);
		}
	}

	private function FillInFields($data) {
		if (array_key_exists('name', $data)) {
			$this->zbxTestInputTypeOverwrite('name', $data['name']);
		}

		if (array_key_exists('proxy', $data)) {
			$this->zbxTestDropdownSelect('proxy_hostid', $data['proxy']);
		}

		if (array_key_exists('range', $data)) {
			$this->zbxTestInputTypeOverwrite('iprange', $data['range']);
		}

		if (array_key_exists('delay', $data)) {
			$this->zbxTestInputTypeOverwrite('delay', $data['delay']);
		}

		if (array_key_exists('checks', $data)) {

			foreach ($data['checks'] as $check) {
				foreach ($check as $key => $value) {
					switch ($key) {

						case 'check_action':
							$action = $value;
							if ($value === 'add') {
								$this->zbxTestClick('newCheck');
							}
							elseif ($value === 'update') {
								$this->zbxTestClickButtonText('Edit');
							}
							else {
								$this->zbxTestClickButtonText('Remove');
							}
							break;

						case 'type':
							$this->zbxTestDropdownSelectWait('type', $value);
							break;

						case 'ports':
							$this->zbxTestInputTypeOverwrite('ports', $value);
							break;

						case 'key':
							$this->zbxTestInputTypeOverwrite('key_', $value);
							break;

						case 'community':
							$this->zbxTestInputTypeOverwrite('snmp_community', $value);
							break;

						case 'snmp_oid':
							$this->zbxTestInputTypeOverwrite('snmp_oid', $value);
							break;

						case 'context_name':
							$this->zbxTestInputTypeOverwrite('snmpv3_contextname', $value);
							break;

						case 'security_name':
							$this->zbxTestInputTypeOverwrite('snmpv3_securityname', $value);
							break;

						case 'security_level':
							$this->zbxTestDropdownSelect('snmpv3_securitylevel', $value);
							break;

						case 'auth_protocol':
							$this->zbxTestClickXpathWait('//input[@name="snmpv3_authprotocol"]/../label[text()="'.$value.'"]');
							break;

						case 'auth_passphrase':
							$this->zbxTestInputTypeOverwrite('snmpv3_authpassphrase', $value);
							break;

						case 'priv_protocol':
							$this->zbxTestClickXpathWait('//input[@name="snmpv3_privprotocol"]/../label[text()="'.$value.'"]');
							break;

						case 'priv_passphrase':
							$this->zbxTestInputTypeOverwrite('snmpv3_privpassphrase', $value);
							break;
					}
				}
			}
			if ($action === 'add' || $action === 'update') {
				$this->zbxTestClick('add_new_dcheck');
			}
		}

		if (array_key_exists('status', $data) && $data['status'] === 'Disabled') {
			if ($this->zbxTestCheckboxSelected('status')) {
				$this->zbxTestCheckboxSelect('status', false);
			}
		}
		elseif (array_key_exists('status', $data) && $data['status'] === 'Enabled') {
			if (!$this->zbxTestCheckboxSelected('status')) {
				$this->zbxTestCheckboxSelect('status', true);
			}
		}

		if (array_key_exists('criteria', $data)) {
			$this->zbxTestClickXpath('//label[text()=\''.$data['criteria'].'\']');
		}
	}

	public function testFormConfigDiscovery_Delete() {
		$name='Discovery rule to check delete';
		$this->zbxTestLogin('discoveryconf.php');
		$this->zbxTestClickLinkTextWait($name);
		$this->zbxTestWaitForPageToLoad();
		$this->zbxTestClickAndAcceptAlert('delete');
		// Check the results in frontend.
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Discovery rule deleted');
		$this->zbxTestCheckFatalErrors();

		// Check the results in DB.
		$sql = 'SELECT * FROM drules WHERE name='.zbx_dbstr($name);
		$this->assertEquals(0, DBcount($sql));
	}

	public function testFormConfigDiscovery_Clone() {
		$this->zbxTestLogin('discoveryconf.php');
		foreach (DBdata("SELECT name FROM drules WHERE druleid IN (2,3)", false) as $drule) {
			$drule = $drule[0];
			$this->zbxTestClickLinkTextWait($drule['name']);
			$this->zbxTestWaitForPageToLoad();
			$this->zbxTestClickWait('clone');
			$this->zbxTestInputType('name','CLONE: '.$drule['name']);
			$this->zbxTestClickWait('add');

			$sql_drules = [];
			$sql_dchecks = [];

			$names=[($drule['name']),'CLONE: '.$drule['name']];
			foreach ($names as $name) {
				$sql_drules[] = DBhash('SELECT proxy_hostid, iprange, delay, nextcheck, status'.
						' FROM drules'.
						' WHERE name='.zbx_dbstr($name).
						' ORDER BY druleid'
				);

				$sql_dchecks[] = DBhash('SELECT type,key_, snmp_community, ports, snmpv3_securityname, snmpv3_securitylevel'.
						' snmpv3_authpassphrase, uniq, snmpv3_authprotocol, snmpv3_privprotocol, snmpv3_contextname'.
						' FROM dchecks'.
						' WHERE druleid IN ('.
							'SELECT druleid'.
							' FROM drules'.
							' WHERE name='.zbx_dbstr($name).
						')'.
						' ORDER BY type, key_'
				);
			}

			$this->assertEquals($sql_drules[0], $sql_drules[1]);
			$this->assertEquals($sql_dchecks[0], $sql_dchecks[1]);
		}
	}

	/**
	 * Function check cancel functionality.
	 * array $actions contain button names in discovery form.
	 * 'update' - simple update, 'create' - cancel creation, 'cancel' - cancel update, 'delete' - cancel delete,
	 * 'clone' - cancel clone
	 */
	public function testFormConfigDiscovery_Cancel() {
		$actions = ['update', 'create', 'cancel', 'delete', 'clone'];
			foreach ($actions as $action) {
				$this->Cancel($action);
			}
	}

	private function Cancel($action) {
		$sql_drules = 'SELECT * FROM drules ORDER BY druleid';
		$old_drules = DBhash($sql_drules);
		$sql_dchecks = 'SELECT * FROM dchecks ORDER BY druleid, dcheckid';
		$old_dchecks = DBhash($sql_dchecks);

		$this->zbxTestLogin('discoveryconf.php');
		if ($action === 'create') {
			$this->zbxTestClickButtonText('Create discovery rule');
			$this->zbxTestInputType('name','New discovery rule to check cancel creation');
			$this->zbxTestClick('newCheck');
			$this->zbxTestDropdownSelect('type', 'IMAP');
			$this->zbxTestClickWait('cancel');
		}
		else {
			foreach (DBdata("SELECT name FROM drules", false) as $drule) {
				$drule = $drule[0];
				$this->zbxTestClickLinkTextWait($drule['name']);
				$this->zbxTestWaitForPageToLoad();
				$this->zbxTestClickWait($action);
				if ($action === 'update') {
					$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Discovery rule updated');
				}
				elseif ($action === 'delete') {
					$this->webDriver->switchTo()->alert()->dismiss();
					$this->zbxTestClickWait('cancel');
				}
				elseif ($action === 'clone') {
					$this->zbxTestClickWait('cancel');
				}
			}
		}

		$this->assertEquals($old_drules, DBhash($sql_drules));
		$this->assertEquals($old_dchecks, DBhash($sql_dchecks));
	}
}
