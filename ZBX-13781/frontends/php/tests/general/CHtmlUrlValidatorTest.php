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

require_once dirname(__FILE__).'/../../include/defines.inc.php';
require_once dirname(__FILE__).'/../../include/classes/validators/CHtmlUrlValidator.php';

class CHtmlUrlValidatorTest extends PHPUnit_Framework_TestCase {

	public static function providerValidateURL() {
		return array(
			array('http://zabbix.com',				true),
			array('https://zabbix.com',				true),
			array('zabbix.php?a=1',					true),
			array('adm.images.php?a=1',				true),
			array('chart_bar.php?a=1&b=2',			true),
			array('mailto:example@example.com',		true),
			array('file://localhost/path',			true),
			array('ftp://user@host:21',				true),
			array('tel:1-111-111-1111',				true),
			array('ssh://username@hostname:/path ',	true),
			array('',								false),
			array('javascript:alert()',				false),
			array('/chart_bar.php?a=1&b=2',			false),
			array('ftp://user@host:port',			false),
			array('vbscript:msgbox()',				false),
			array('../././not_so_zabbix',			false),
			array('jav&#x09;ascript:alert(1);', 	false)
		);
	}

	/**
	 * @dataProvider providerValidateURL
	 */
	public function test_validateURL($url, $expected) {
		$this->assertEquals(CHtmlUrlValidator::validate($url), $expected);
	}
}
