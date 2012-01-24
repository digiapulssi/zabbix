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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/
?>
<?php
require_once dirname(__FILE__).'/../include/class.cwebtest.php';

class testGeneric extends CWebTest {
	public static function provider() {
		// List of URLs to test
		// URL, expected page Title
		return array(

			// Monitoring
			array('index.php',		'Dashboard'),
			array('dashboard.php',	'Dashboard'),
			array('dashconf.php',	'Dashboard configuration'),

			array('overview.php',	'Overview \[refreshed every 30 sec\]'),
			array('overview.php?form_refresh=1&groupid=0&type=1',				'Overview \[refreshed every 30 sec\]'),
			array('overview.php?form_refresh=1&groupid=0&type=1&view_style=0',	'Overview \[refreshed every 30 sec\]'),
			array('overview.php?form_refresh=1&groupid=0&type=1&view_style=1',	'Overview \[refreshed every 30 sec\]'),

			array('httpmon.php',	'Status of Web monitoring \[refreshed every 30 sec\]'),
			array('latest.php',		'Latest data \[refreshed every 30 sec\]'),
			array('tr_status.php',	'Status of triggers \[refreshed every 30 sec\]'),

			array('events.php',				'Latest events \[refreshed every 30 sec\]'),
			array('events.php?source=0',	'Latest events \[refreshed every 30 sec\]'),
			array('events.php?source=1',	'Latest events \[refreshed every 30 sec\]'),

			array('charts.php',		'Custom graphs \[refreshed every 30 sec\]'),
			array('screens.php',	'Custom screens \[refreshed every 30 sec\]'),
			array('slides.php',		'Custom slides'),
			array('maps.php',		'Network maps \[refreshed every 30 sec\]'),
			array('srv_status.php',	'IT services \[refreshed every 30 sec\]'),

			// Configuration
			array('hostgroups.php',		'Host groups'),
			array('templates.php',		'Templates'),
			array('hosts.php',			'Hosts'),
			array('maintenance.php',	'Maintenance'),
			array('httpconf.php',		'Configuration of Web monitoring'),

			array('actionconf.php',					'Configuration of actions'),
			array('actionconf.php?eventsource=0',	'Configuration of actions'),
			array('actionconf.php?eventsource=1',	'Configuration of actions'),
			array('actionconf.php?eventsource=2',	'Configuration of actions'),

			array('screenconf.php',		'Configuration of screens'),
			array('slideconf.php',		'Configuration of slide shows'),
			array('sysmaps.php',		'Network maps'),
			array('discovery.php',		'Status of discovery'),
			array('services.php',		'Configuration of IT services'),
			array('discoveryconf.php',	'Configuration of discovery'),

			// Inventory
			array('hostinventories.php',	'Host inventories'),

			// Reports
			// TODO Does not work under SQLite well. Extremely slow!
//			array('report2.php',	'Availability report'),

//			array('report2.php?config=0',	'Availability report'),
//			array('report2.php?config=1',	'Availability report'),

			array('report5.php',				'Most busy triggers top 100'),
			array('report5.php?period=day',		'Most busy triggers top 100'),
			array('report5.php?period=week',	'Most busy triggers top 100'),
			array('report5.php?period=month',	'Most busy triggers top 100'),
			array('report5.php?period=year',	'Most busy triggers top 100'),
			array('report6.php',				'Bar reports'),

			// Administration
			array('adm.gui.php', 'Configuration of Zabbix'),
			array('adm.housekeeper.php', 'Configuration of Zabbix'),
			array('adm.images.php', 'Configuration of Zabbix'),
			array('adm.iconmapping.php', 'Configuration of Zabbix'),
			array('adm.regexps.php', 'Configuration of Zabbix'),
			array('adm.macros.php', 'Configuration of Zabbix'),
			array('adm.valuemapping.php', 'Configuration of Zabbix'),
			array('adm.workingtime.php', 'Configuration of Zabbix'),
			array('adm.triggerseverities.php', 'Configuration of Zabbix'),
			array('adm.triggerdisplayingoptions.php', 'Configuration of Zabbix'),
			array('adm.other.php', 'Configuration of Zabbix'),

			array('nodes.php',	'Nodes'),
			array('proxies.php',	'Proxies'),
			array('authentication.php','Authentication to Zabbix'),
			array('usergrps.php',	'User groups'),
			array('users.php',	'Users'),
			array('media_types.php','Media types'),
			array('scripts.php',	'Scripts'),
			array('auditlogs.php',	'Audit'),
			array('auditacts.php',	'Audit'),

			array('queue.php',	'Queue \[refreshed every 30 sec\]'),
			array('queue.php?config=0',	'Queue \[refreshed every 30 sec\]'),
			array('queue.php?config=1',	'Queue \[refreshed every 30 sec\]'),
			array('queue.php?config=2',	'Queue \[refreshed every 30 sec\]'),

			array('report4.php',	'Notification report'),
			array('instal.php',	'Installation'),

			// Misc
			array('search.php?search=server',	'Search'),
			array('profile.php',	'User profile')
		);
	}

	/**
	* @dataProvider provider
	*/
	public function testTitles($a, $b) {
		$this->login();
		$this->open($a);
		$this->assertTitle($b);
	}

	/**
	* @dataProvider provider
	*/
	public function testNotExists($a, $b) {
		$this->login();
		$this->open($a);
		foreach ($this->failIfExists as $str) {
			$this->nok($str, 'assertTextNotPresent('.$a.','.$str.')');
		}
	}

	/**
	* @dataProvider provider
	*/
	public function testExists($a, $b) {
		$this->login();
		$this->open($a);
		foreach ($this->failIfNotExists as $str) {
			$this->ok($str, 'assertTextPresent('.$a.','.$str.')');
		}
	}
}
?>
