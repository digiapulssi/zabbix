<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
require_once(dirname(__FILE__) . '/../include/class.cwebtest.php');

class testFormAction extends CWebTest {


	public static function providerNewActions(){
		$data = array(
			array(array(
				'name' => 'asd',
				'esc_period' => '123',
				'def_shortdata' => 'def_shortdata',
				'def_longdata' => 'def_longdata',
				'operations' => array(
					array(
						'type' => 'Send message',
						'media' => 'Email',

					),
					array(
						'type' => 'Remote command',
						'command' => 'command',
					)
				),
			)),
		);
		return $data;
	}

	/**
	 * @dataProvider providerNewActions
	 */
	public function testActionCreateSimple($action){
		$this->login('actionconf.php?form=1&eventsource=0');
		$this->assertTitle('Configuration of actions');

		$this->type('name', $action['name']);
		$this->type('esc_period', $action['esc_period']);
		$this->type("def_shortdata", $action['def_shortdata']);
		$this->type("def_longdata", $action['def_longdata']);

		$this->click("link=Operations");

		foreach($action['operations'] as $operation){
			$this->click('new_operation');
			$this->wait();
			$this->dropdown_select_wait('new_operation_operationtype', $operation['type']);

			switch($operation['type']){
				case 'Send message':
					$this->click("addusrgrpbtn");
					$this->waitForPopUp("zbx_popup", "30000");
					$this->selectWindow("name=zbx_popup");
					$this->click("all_usrgrps");
					$this->click("select");
					$this->selectWindow("null");

					sleep(1);

					$this->click("adduserbtn");
					$this->waitForPopUp("zbx_popup", "30000");
					$this->selectWindow("name=zbx_popup");
					$this->click('all_users');
					$this->click('select');
					$this->selectWindow("null");

					$this->select('new_operation_opmessage_mediatypeid', $operation['media']);
					break;
				case 'Remote command':
					$this->click("add");
					$this->click("//input[@name='save']");
					$this->type('new_operation_opcommand_command', $operation['command']);
					break;
			}
			$this->click('add_operation');
			$this->wait();
		}
		$this->click('save');

		$this->wait();
		$this->ok('Action added');
	}

	public function testActionCreate(){
		$this->login('actionconf.php?form=1&eventsource=0');
		$this->assertTitle('Configuration of actions');

		$this->type("name", "action test");
		$this->type("esc_period", "123");
		$this->type("def_shortdata", "subject");
		$this->type("def_longdata", "message");

// adding conditions
		$this->click("link=Conditions");
		$this->type("new_condition_value", "trigger");
		$this->click("add_condition");
		$this->wait();
		$this->select("new_condition_conditiontype", "label=Trigger severity");
		$this->wait();
		$this->assertTrue($this->isTextPresent("Trigger name like \"trigger\""));

		$this->select("new_condition_value", "label=Average");
		$this->click("add_condition");
		$this->wait();
		$this->assertTrue($this->isTextPresent("Trigger severity = \"Average\""));

		$this->select("new_condition_conditiontype", "label=Application");
		$this->wait();
		$this->type("new_condition_value", "app");
		$this->click("add_condition");
		$this->wait();
		$this->assertTrue($this->isTextPresent("Application = \"app\""));

//adding operations
		$this->click("link=Operations");
		$this->click("new_operation");
		$this->wait();
		$this->click("addusrgrpbtn");
		$this->waitForPopUp("zbx_popup", "30000");
		$this->selectWindow("name=zbx_popup");
		$this->click("usrgrps_'7'");
		$this->click("usrgrps_'2'");
		$this->click("select");
		$this->selectWindow("null");
		$this->click("adduserbtn");
		$this->waitForPopUp("zbx_popup", "30000");
		$this->selectWindow("name=zbx_popup");
		$this->click("users_'1'");
		$this->click("select");
		$this->selectWindow("null");
		$this->select("new_operation_opmessage_mediatypeid", "label=Jabber");
		$this->click("add_operation");
		$this->wait();
		$this->assertTrue($this->isTextPresent("Send message to Users: Admin"));
		$this->assertTrue($this->isTextPresent("Send message to Groups: Database administrators, Zabbix administrators"));
		$this->click("new_operation");
		$this->wait();
		$this->select("new_operation_operationtype", "label=Remote command");
		$this->wait();
		$this->click("add");
		$this->click("//input[@name='save']");
		$this->click("add");
		$this->select("opCmdTarget", "label=Host");
		$this->click("select");
		$this->waitForPopUp("zbx_popup", "30000");
		$this->selectWindow("name=zbx_popup");
		$this->click("//span[@onclick=concat(\"try{window.opener.document.getElementById('opCmdTargetObjectId').value='10017'; } catch(e){throw(\",'\"Error: Target not found\")}\ntry{window.opener.document.getElementById(',\"'opCmdTargetObjectName').value='Zabbix server'; } catch(e){throw(\",'\"Error: Target not found\")}\n close_window();')]");
		$this->selectWindow("null");
		$this->click("//input[@name='save']");
		$this->click("add");
		$this->select("opCmdTarget", "label=Host group");
		$this->click("select");
		$this->waitForPopUp("zbx_popup", "30000");
		$this->selectWindow("name=zbx_popup");
		$this->click("//span[@onclick=\"javascript: addValues('action.edit.php',{'opCmdTargetObjectId' : '4','opCmdTargetObjectName' : 'Zabbix servers'}); return false;\"]");
		$this->selectWindow("null");
		$this->click("//input[@name='save']");
		$this->type("new_operation_opcommand_command", "command");
		$this->click("add_operation");
		$this->wait();
		$this->assertTrue($this->isTextPresent("Run remote command on current host"));
		$this->assertTrue($this->isTextPresent("Run remote command on hosts: Zabbix server"));
		$this->assertTrue($this->isTextPresent("Run remote command on host groups: Zabbix servers"));
		$this->click("new_operation");
		$this->wait();
		$this->type("new_operation_esc_step_to", "2");
		$this->select("new_operation_operationtype", "label=Remote command");
		$this->wait();
		$this->click("add");
		$this->click("//input[@name='save']");
		$this->select("new_operation_opcommand_type", "label=SSH");
		$this->type("new_operation_opcommand_username", "user");
		$this->type("new_operation_opcommand_password", "pass");
		$this->type("new_operation_opcommand_port", "123");
		$this->type("new_operation_opcommand_command", "command ssh");
		$this->click("add_operation");
		$this->wait();
		$this->assertTrue($this->isTextPresent("Run remote command on current host"));
		$this->click("save");
		$this->wait();
		$this->assertTrue($this->isTextPresent("Action added"));
	}

}

?>
