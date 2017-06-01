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

require_once dirname(__FILE__).'/../../include/classes/validators/CHtmlUrlValidator.php';

class CHtmlUrlValidatorTest extends PHPUnit_Framework_TestCase {
	public static function providerSanitizeURL() {
		return array(
			array('',						false),
			array('javascript:alert()',		false),
			array('http://zabbix.com',		true),
			array('https://zabbix.com',		true),
			array('zabbix.php?a=1',			true),
			array('adm.images.php?a=1',		true),
			array('chart_bar.php?a=1&b=2',	true),
			array('/chart_bar.php?a=1&b=2',	false),
			array('vbscript:msgbox()',		false),
			array('../././not_so_zabbix',	false)
		);
	}

	/**
	* @dataProvider providerSanitizeURL
	*/
	public function test_sanitizeURL($url, $expected) {
		$this->assertEquals(CHtmlUrlValidator::validate($url), $expected);
	}
}
