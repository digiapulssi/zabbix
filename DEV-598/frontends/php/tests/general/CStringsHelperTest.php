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

require_once dirname(__FILE__).'/../../include/classes/helpers/CStringsHelper.php';

class CStringsHelperTest extends PHPUnit_Framework_TestCase {
	public static function providerSanitizeURL() {
		return array(
			array('',						'http://'),
			array('javascript:alert()',		'http://javascript:alert()'),
			array('http://zabbix.com',		'http://zabbix.com'),
			array('https://zabbix.com',		'https://zabbix.com'),
			array('zabbix.php?a=1',			'zabbix.php?a=1'),
			array('adm.images.php?a=1',		'adm.images.php?a=1'),
			array('chart_bar.php?a=1&b=2',	'chart_bar.php?a=1&b=2'),
			array('/chart_bar.php?a=1&b=2',	'http://'.'/chart_bar.php?a=1&b=2'),
			array('vbscript:msgbox()',		'http://vbscript:msgbox()'),
			array('../././not_so_zabbix',	'http://../././not_so_zabbix')
		);
	}

	/**
	* @dataProvider providerSanitizeURL
	*/
	public function test_sanitizeURL($source, $expected) {
		$sanitized = CStringsHelper::sanitizeURL($source);

		$this->assertEquals($sanitized, $expected);
	}
}
