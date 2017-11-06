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

require_once dirname(__FILE__) . '/../include/class.cwebtest.php';

class testPageIconMapping extends CWebTest {
	public $mappingName1 = "Icon mapping one for testPage";
	public $mappingExpressionRow0 = "Type: expresssion one ⇒ Cloud_(24)";
	public $mappingExpressionRow1 = "Type: expresssion two ⇒ Cloud_(24)";
	public $mappingName2 = "Icon mapping testForm update expression";
	public $mappingExpression2 = "OS: one more expression ⇒ Crypto-router_(128)";
	public $mappingName3 = "Icon mapping to check delete functionality";
	public $mappingExpressionRow3_1 = "Type: expresssion 1 ⇒ Cloud_(24)";
	public $mappingExpressionRow3_2 = "Type: expresssion 2 ⇒ Cloud_(24)";
	public $mappingExpressionRow3_3 = "Type: expresssion 3 ⇒ Cloud_(24)";
	public $mappingExpressionRow3_4 = "Type: expresssion 4 ⇒ Cloud_(24)";
	public $mappingName4 = "Icon mapping two for testPage";
	public $mappingExpression4 = "Alias: !@#$%^&*()-= ⇒ Cloud_(96)";

	public function testPageIconMapping_CheckLayout(){
		$this->zbxTestLogin('adm.gui.php');
		$this->zbxTestDropdownSelectWait('configDropDown', 'Icon mapping');
		$this->zbxTestCheckHeader('Icon mapping');
		$this->zbxTestAssertElementText("//tbody/tr[1]/td[1]/a", $this->mappingName1);
		$this->zbxTestAssertElementText("//tbody/tr[2]/td[1]/a", $this->mappingName2);
		$this->zbxTestAssertElementText("//tbody/tr[3]/td[1]/a", $this->mappingName3);
		$this->zbxTestAssertElementText("//tbody/tr[4]/td[1]/a", $this->mappingName4);
		$get_iconMap = $this->zbxTestGetText("//tbody/tr[1]/td[2]");
		$iconMapping = preg_replace( "/\r|\n/", "", $get_iconMap);
		$this->assertEquals($iconMapping, $this->mappingExpressionRow0 . $this->mappingExpressionRow1);
		$get_iconMap = $this->zbxTestGetText("//tbody/tr[3]/td[2]");
		$iconMapping = preg_replace( "/\r|\n/", "", $get_iconMap);
		$this->assertEquals($iconMapping, $this->mappingExpressionRow3_1 . $this->mappingExpressionRow3_2 . $this->mappingExpressionRow3_3 . $this->mappingExpressionRow3_4);
		$this->zbxTestAssertElementText("//tbody/tr[2]/td[2]", $this->mappingExpression2);
		$this->zbxTestAssertElementText("//tbody/tr[4]/td[2]", $this->mappingExpression4);
	}
}
