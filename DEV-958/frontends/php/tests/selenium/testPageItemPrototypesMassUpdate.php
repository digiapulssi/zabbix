<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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

require_once dirname(__FILE__) . '/../include/CWebTest.php';

class testPageItemPrototypesMassUpdate extends CWebTest{

	const DISCOVERY_RULE_ID = 90001;

	public function getItemMassUpdateData() {
		return [
			[
				[
					'Type' => 'Zabbix agent',
					'Host Interface' => '127.0.0.2 : 10099',
					'Type of information'=> 'Numeric (float)',
					'Units'=> '$',
					'' => ''
				]
			]
		];
	}

	/**
	 * Test mass updating
	 *
	 * @dataProvider getItemMassUpdateData
	 */
	public function testPageItemPrototypesMassUpdate_ChangeItemType($data) {
		$this->page->login()->open('disc_prototypes.php?parent_discoveryid='.self::DISCOVERY_RULE_ID);
		// Get item table.
		$table = $this->query('xpath://form[@name="items"]/table[@class="list-table"]')->asTable()->one();
		// Select all rows.
		$table->getRows()->select();
		// Open mass update form.
		$this->query('button:Mass update')->one()->click();
		// Wait until page is ready.
		$this->page->waitUntilReady();
		// Get mass update form.
		$form = $this->query('name:item_prototype_form')->asForm()->one();

		foreach ($data as $field => $value) {
			// Click on a label to show input control.
			$form->getLabel($field)->click();
			// Set field value.
			$form->getField($field)->fill($value);
		}
	}
}

