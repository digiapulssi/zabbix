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
 * @backup graphs
 */
class testPageGraphPrototypes extends CWebTest {
	/**
	 * Item prototype id used in test.
	 */
	const DISCRULEID = 33800;

	/**
	 * Item prototype id used in test.
	 */
	const ITEMPROTOID = 23804;

	/**
	 * Get text of elements by xpath.
	 *
	 * @param string $xpath	xpath selector
	 *
	 * @return array
	 */
	private function getTextOfElements($xpath) {
		$result = [];
		$elements = $this->webDriver->findElements(WebDriverBy::xpath($xpath));
		foreach ($elements as $element) {
			$result[] = $element->getText();
		}
		return $result;
	}

	private function getDbColumn($sql) {
		$result = [];
		foreach (DBfetchArray(DBSelect($sql)) as $row) {
			$result[] = reset($row);
		}
		return $result;
	}

	private $sql_graph_prototypes =
		'SELECT name'.
			' FROM graphs'.
			' WHERE graphid IN ('.
				'SELECT graphid'.
				' FROM graphs_items'.
				' WHERE itemid='.self::ITEMPROTOID.
			')';

	public function testPageGraphPrototypes_CheckLayout() {

		$this->zbxTestLogin('graphs.php?parent_discoveryid='.self::DISCRULEID);
		$this->zbxTestCheckTitle('Configuration of graph prototypes');
		$this->zbxTestCheckHeader('Graph prototypes');
		// Check "Create correlation" button.
		$this->zbxTestAssertElementText('//button[contains(@data-url, "form")]', 'Create graph prototype');

		// Check table headers.
		$this->assertEquals(['', 'Name', 'Width', 'Height', 'Graph type'],
				$this->getTextOfElements("//thead/tr/th")
		);

		// Check the correlation names in frontend
		$graph_prototypes = $this->getDbColumn($this->sql_graph_prototypes);
		$this->zbxTestTextPresent($graph_prototypes);

		// Check table footer to make sure that results are found
		$i = count($graph_prototypes);
		$this->zbxTestAssertElementText("//div[@class='table-stats']", 'Displaying '.$i.' of '.$i.' found');
		$this->zbxTestTextNotPresent('Displaying 0 of 0 found');
		$this->zbxTestAssertElementText("//span[@id='selected_count']", '0 selected');
	}

	// Returns graph prototype ids
	public static function getSimpleDeleteData() {
		return DBdata(
			'SELECT graphid'.
				' FROM graphs_items'.
				' WHERE itemid='.self::ITEMPROTOID.
				' LIMIT 2'
		);
	}

	/**
	 * @dataProvider getSimpleDeleteData
	 */
	public function testPageGraphPrototypes_SimpleDelete($data) {
		$graphid = $data['graphid'];

		$this->zbxTestLogin('graphs.php?parent_discoveryid='.self::DISCRULEID);
		$this->zbxTestCheckTitle('Configuration of graph prototypes');

		$this->zbxTestCheckboxSelect('group_graphid_'.$graphid);
		$this->zbxTestClickButton('graph.massdelete');

		$this->zbxTestAcceptAlert();

		$this->zbxTestCheckTitle('Configuration of graph prototypes');
		$this->zbxTestCheckHeader('Graph prototypes');
		$this->zbxTestTextPresent('Graph prototypes deleted');

		$sql = 'SELECT NULL FROM graphs_items WHERE graphid='.$graphid;
		$this->assertEquals(0, DBcount($sql));
	}

	public function testPageGraphPrototypes_MassDelete() {
		$this->zbxTestLogin('graphs.php?parent_discoveryid='.self::DISCRULEID);
		$this->zbxTestCheckTitle('Configuration of graph prototypes');

		$this->zbxTestCheckboxSelect('all_graphs');
		$this->zbxTestClickButton('graph.massdelete');

		$this->zbxTestAcceptAlert();

		$this->zbxTestCheckTitle('Configuration of graph prototypes');
		$this->zbxTestCheckHeader('Graph prototypes');
		$this->zbxTestTextPresent('Graph prototypes deleted');

		$this->assertEquals(0, DBcount($this->sql_graph_prototypes));
	}

	/**
	 * Test impossible deleting of templated graph.
	 */
	public function testPageGraphPrototypes_CannotDelete() {
		$item_id = 15026;
		$parent_discovery_id = 15016;

		$sql_hash =
			'SELECT *'.
				' FROM graphs'.
				' WHERE graphid IN ('.
					'SELECT graphid'.
					' FROM graphs_items'.
					' WHERE itemid='.$item_id.
				')';
		$old_hash = DBhash($sql_hash);

		$this->zbxTestLogin('graphs.php?parent_discoveryid='.$parent_discovery_id);
		$this->zbxTestCheckboxSelect('all_graphs');
		$this->zbxTestClickButton('graph.massdelete');
		$this->zbxTestAcceptAlert();
		$this->zbxTestWaitUntilMessageTextPresent('msg-bad', 'Cannot delete graph prototypes');
		$this->zbxTestTextPresentInMessageDetails('Cannot delete templated graphs.');
		$this->zbxTestCheckFatalErrors();

		$this->assertEquals($old_hash, DBhash($sql_hash));
	}
}
