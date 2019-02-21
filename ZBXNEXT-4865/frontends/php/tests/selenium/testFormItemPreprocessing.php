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

require_once dirname(__FILE__).'/../include/CLegacyWebTest.php';
require_once dirname(__FILE__).'/../../include/items.inc.php';

/**
 * @backup items
 */
class testFormItemPreprocessing extends CLegacyWebTest {

	/**
	 * The name of the test host created in the test data set.
	 *
	 * @var string
	 */
	protected $host = 'Simple form test host';

	public static function getCreateData() {
		return [
			// Custom multiplier.
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Item empty multiplier',
					'key' => 'item-empty-multiplier',
					'preprocessing' => [
						['type' => 'Custom multiplier', 'parameter_1' => ''],
					],
					'error' => 'Incorrect value for field "params": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Item string multiplier',
					'key' => 'item-string-multiplier',
					'preprocessing' => [
						['type' => 'Custom multiplier', 'parameter_1' => 'abc'],
					],
					'error' => 'Incorrect value for field "params": a numeric value is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Item multiplier comma',
					'key' => 'item-comma-multiplier',
					'preprocessing' => [
						['type' => 'Custom multiplier', 'parameter_1' => '0,0'],
					],
					'error' => 'Incorrect value for field "params": a numeric value is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Item multiplier symbol',
					'key' => 'item-symbol-multiplier',
					'preprocessing' => [
						['type' => 'Custom multiplier', 'parameter_1' => '1a!@#$%^&*()-='],
					],
					'error' => 'Incorrect value for field "params": a numeric value is expected.'
				]
			],
			// Empty trim.
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Item right trim',
					'key' => 'item-empty-right-trim',
					'preprocessing' => [
						['type' => 'Right trim', 'parameter_1' => ''],
					],
					'error' => 'Incorrect value for field "params": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Item left trim',
					'key' => 'item-empty-left-trim',
					'preprocessing' => [
						['type' => 'Left trim', 'parameter_1' => ''],
					],
					'error' => 'Incorrect value for field "params": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Item trim',
					'key' => 'item-empty-trim',
					'preprocessing' => [
						['type' => 'Trim', 'parameter_1' => ''],
					],
					'error' => 'Incorrect value for field "params": cannot be empty.'
				]
			],
			// Structured data.
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Item XML XPath',
					'key' => 'item-empty-xpath',
					'preprocessing' => [
						['type' => 'XML XPath', 'parameter_1' => ''],
					],
					'error' => 'Incorrect value for field "params": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Item JSONPath',
					'key' => 'item-empty-jsonpath',
					'preprocessing' => [
						['type' => 'JSONPath', 'parameter_1' => ''],
					],
					'error' => 'Incorrect value for field "params": cannot be empty.'
				]
			],
			// Regular expression.
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Item empty regular expression',
					'key' => 'item-empty-both-parameters',
					'preprocessing' => [
						['type' => 'Regular expression', 'parameter_1' => '', 'parameter_2' => ''],
					],
					'error' => 'Incorrect value for field "params": first parameter is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Item empty regular expression',
					'key' => 'item-empty-first-parameter',
					'preprocessing' => [
						['type' => 'Regular expression', 'parameter_1' => '', 'parameter_2' => 'test output'],
					],
					'error' => 'Incorrect value for field "params": first parameter is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Item empty regular expression',
					'key' => 'item-empty-second-parameter',
					'preprocessing' => [
						['type' => 'Regular expression', 'parameter_1' => 'expression', 'parameter_2' => ''],
					],
					'error' => 'Incorrect value for field "params": second parameter is expected.'
				]
			],
			// Change.
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Item two delta',
					'key' => 'item-two-delta',
					'preprocessing' => [
						['type' => 'Simple change'],
						['type' => 'Simple change']
					],
					'error' => 'Only one change step is allowed.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Item two delta per second',
					'key' => 'item-two-delta-per-second',
					'preprocessing' => [
						['type' => 'Change per second'],
						['type' => 'Change per second']
					],
					'error' => 'Only one change step is allowed.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Item two different delta',
					'key' => 'item-two-different-delta',
					'preprocessing' => [
						['type' => 'Simple change'],
						['type' => 'Change per second']
					],
					'error' => 'Only one change step is allowed.'
				]
			],
			// Validation. In range.
			[
				[
					'expected' => TEST_BAD,
					'name' => 'In range empty',
					'key' => 'in-range-empty',
					'preprocessing' => [
						['type' => 'In range', 'parameter_1' => '', 'parameter_2' => '']
					],
					'error' => 'Incorrect value for field "params": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'In range letters string',
					'key' => 'in-range-letters-string',
					'preprocessing' => [
						['type' => 'In range', 'parameter_1' => 'abc', 'parameter_2' => 'def']
					],
					'error' => 'Incorrect value for field "params": a numeric value is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'In range symbols',
					'key' => 'in-range-symbols',
					'preprocessing' => [
						['type' => 'In range', 'parameter_1' => '1a!@#$%^&*()-=', 'parameter_2' => '2b!@#$%^&*()-=']
					],
					'error' => 'Incorrect value for field "params": a numeric value is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'In range comma',
					'key' => 'in-range-comma',
					'preprocessing' => [
						['type' => 'In range', 'parameter_1' => '1,5', 'parameter_2' => '-3,5']
					],
					'error' => 'Incorrect value for field "params": a numeric value is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'In range wrong interval',
					'key' => 'in-range-wrong-interval',
					'preprocessing' => [
						['type' => 'In range', 'parameter_1' => '8', 'parameter_2' => '-8']
					],
					'error' => 'Incorrect value for field "params": "min" value must be less than or equal to "max" value.'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'In range negative float',
					'key' => 'in-range-negative-float',
					'preprocessing' => [
						['type' => 'In range', 'parameter_1' => '-3.5', 'parameter_2' => '-1.5']
					],
					'error' => 'Incorrect value for field "params": a numeric value is expected.'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'In range zero',
					'key' => 'in-range-zero',
					'preprocessing' => [
						['type' => 'In range', 'parameter_1' => '0', 'parameter_2' => '0']
					]
				]
			],
			// Validation. Regular expressions matching.
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Matches regular expression empty',
					'key' => 'matches-regular-expression-empty',
					'preprocessing' => [
						['type' => 'Matches regular expression', 'parameter_1' => '']
					],
					'error' => 'Incorrect value for field "params": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Does not match regular expression empty',
					'key' => 'does-not-match-regular-expression-empty',
					'preprocessing' => [
						['type' => 'Does not match regular expression', 'parameter_1' => '']
					],
					'error' => 'Incorrect value for field "params": cannot be empty.'
				]
			],
			// Validation. Error in JSON and XML.
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Item error JSON empty',
					'key' => 'item-error-json-empty',
					'preprocessing' => [
						['type' => 'Check for error in JSON', 'parameter_1' => '']
					],
					'error' => 'Incorrect value for field "params": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Item error XML empty',
					'key' => 'item-error-xml-empty',
					'preprocessing' => [
						['type' => 'Check for error in XML', 'parameter_1' => '']
					],
					'error' => 'Incorrect value for field "params": cannot be empty.'
				]
			],
			// Validation. Check error using REGEXP.
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Item error REGEXP both params empty',
					'key' => 'item-error-regexp-both-empty',
					'preprocessing' => [
						['type' => 'Check for error using regular expression', 'parameter_1' => '', 'parameter_2' => '']
					],
					'error' => 'Incorrect value for field "params": first parameter is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Item error REGEXP first parameter empty',
					'key' => 'item-error-regexp-first-empty',
					'preprocessing' => [
						['type' => 'Check for error using regular expression', 'parameter_1' => '', 'parameter_2' => 'test']
					],
					'error' => 'Incorrect value for field "params": first parameter is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Item error REGEXP second parameter empty',
					'key' => 'item-error-regexp-second-empty',
					'preprocessing' => [
						['type' => 'Check for error using regular expression', 'parameter_1' => 'test', 'parameter_2' => '']
					],
					'error' => 'Incorrect value for field "params": second parameter is expected.'
				]
			],
			// Throttling.
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Item two discard uncahnged',
					'key' => 'item-two-discard-uncahnged',
					'preprocessing' => [
						['type' => 'Discard unchanged'],
						['type' => 'Discard unchanged']
					],
					'error' => 'Only one throttling step is allowed.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Item two different throttlings',
					'key' => 'item-two-different-throttlings',
					'preprocessing' => [
						['type' => 'Discard unchanged'],
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '1']
					],
					'error' => 'Only one throttling step is allowed.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Item two equal discard unchanged with heartbeat',
					'key' => 'item-two-equal-discard-uncahnged-with-heartbeat',
					'preprocessing' => [
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '1'],
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '1']
					],
					'error' => 'Only one throttling step is allowed.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Item two different discard unchanged with heartbeat',
					'key' => 'item-two-different-discard-uncahnged-with-heartbeat',
					'preprocessing' => [
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '1'],
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '2']
					],
					'error' => 'Only one throttling step is allowed.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Discard unchanged with heartbeat empty',
					'key' => 'discard-uncahnged-with-heartbeat-empty',
					'preprocessing' => [
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '']
					],
					'error' => 'Invalid parameter "params": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Discard unchanged with heartbeat symbols',
					'key' => 'discard-uncahnged-with-heartbeat-symbols',
					'preprocessing' => [
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '3g!@#$%^&*()-=']
					],
					'error' => 'Invalid parameter "params": a time unit is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Discard unchanged with heartbeat sletters string',
					'key' => 'discard-uncahnged-with-heartbeat-letters-string',
					'preprocessing' => [
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => 'abc']
					],
					'error' => 'Invalid parameter "params": a time unit is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Discard unchanged with heartbeat comma',
					'key' => 'discard-uncahnged-with-heartbeat-comma',
					'preprocessing' => [
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '1,5']
					],
					'error' => 'Invalid parameter "params": a time unit is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Discard unchanged with heartbeat dot',
					'key' => 'discard-uncahnged-with-heartbeat-dot',
					'preprocessing' => [
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '1.5']
					],
					'error' => 'Invalid parameter "params": a time unit is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Discard unchanged with heartbeat negative',
					'key' => 'discard-uncahnged-with-heartbeat-negative',
					'preprocessing' => [
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '-3']
					],
					'error' => 'Invalid parameter "params": value must be one of 1-788400000.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Discard unchanged with heartbeat zero',
					'key' => 'discard-uncahnged-with-heartbeat-zero',
					'preprocessing' => [
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '0']
					],
					'error' => 'Invalid parameter "params": value must be one of 1-788400000.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Discard unchanged with heartbeat maximum',
					'key' => 'discard-uncahnged-with-heartbeat-max',
					'preprocessing' => [
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '788400001']
					],
					'error' => 'Invalid parameter "params": value must be one of 1-788400000.'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Add all preprocessing',
					'key' => 'item.all.preprocessing',
					'preprocessing' => [
						['type' => 'Right trim', 'parameter_1' => 'abc'],
						['type' => 'Left trim', 'parameter_1' => 'def'],
						['type' => 'Trim', 'parameter_1' => '1a2b3c'],
						['type' => 'Custom multiplier', 'parameter_1' => '123'],
						['type' => 'Regular expression', 'parameter_1' => 'expression', 'parameter_2' => 'test output'],
						['type' => 'Boolean to decimal'],
						['type' => 'Octal to decimal'],
						['type' => 'Hexadecimal to decimal'],
						['type' => 'Simple change'],
						['type' => 'In range', 'parameter_1' => '-5', 'parameter_2' => '9.5'],
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '5']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Add symblos preprocessing',
					'key' => 'item.symbols.preprocessing',
					'preprocessing' => [
						['type' => 'Right trim', 'parameter_1' => '1a!@#$%^&*()-='],
						['type' => 'Left trim', 'parameter_1' => '2b!@#$%^&*()-='],
						['type' => 'Trim', 'parameter_1' => '3c!@#$%^&*()-='],
						['type' => 'XML XPath', 'parameter_1' => '3c!@#$%^&*()-='],
						['type' => 'JSONPath', 'parameter_1' => '3c!@#$%^&*()-='],
						['type' => 'Custom multiplier', 'parameter_1' => '4e+10'],
						['type' => 'Regular expression', 'parameter_1' => '5d!@#$%^&*()-=', 'parameter_2' => '6e!@#$%^&*()-='],
						['type' => 'Matches regular expression', 'parameter_1' => '7f!@#$%^&*()-='],
						['type' => 'Does not match regular expression', 'parameter_1' => '8g!@#$%^&*()-='],
						['type' => 'Check for error in JSON', 'parameter_1' => '9h!@#$%^&*()-='],
						['type' => 'Check for error in XML', 'parameter_1' => '0i!@#$%^&*()-='],
						['type' => 'Check for error using regular expression', 'parameter_1' => '1j!@#$%^&*()-=', 'parameter_2' => '2k!@#$%^&*()-=']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Add the same preprocessing',
					'key' => 'item.theSamePpreprocessing',
					'preprocessing' => [
						['type' => 'Change per second'],
						['type' => 'Right trim', 'parameter_1' => 'abc'],
						['type' => 'Right trim', 'parameter_1' => 'abc'],
						['type' => 'Left trim', 'parameter_1' => 'def'],
						['type' => 'Left trim', 'parameter_1' => 'def'],
						['type' => 'Trim', 'parameter_1' => '1a2b3c'],
						['type' => 'Trim', 'parameter_1' => '1a2b3c'],
						['type' => 'XML XPath', 'parameter_1' => '1a2b3c'],
						['type' => 'XML XPath', 'parameter_1' => '1a2b3c'],
						['type' => 'JSONPath', 'parameter_1' => '1a2b3c'],
						['type' => 'JSONPath', 'parameter_1' => '1a2b3c'],
						['type' => 'Custom multiplier', 'parameter_1' => '123'],
						['type' => 'Custom multiplier', 'parameter_1' => '123'],
						['type' => 'Regular expression', 'parameter_1' => 'expression', 'parameter_2' => 'test output'],
						['type' => 'Regular expression', 'parameter_1' => 'expression', 'parameter_2' => 'test output'],
						['type' => 'Boolean to decimal'],
						['type' => 'Boolean to decimal'],
						['type' => 'Octal to decimal'],
						['type' => 'Octal to decimal'],
						['type' => 'Hexadecimal to decimal'],
						['type' => 'Hexadecimal to decimal'],
						['type' => 'In range', 'parameter_1' => '-5.5', 'parameter_2' => '10'],
						['type' => 'In range', 'parameter_1' => '-5.5', 'parameter_2' => '10'],
						['type' => 'Matches regular expression', 'parameter_1' => 'test_expression'],
						['type' => 'Matches regular expression', 'parameter_1' => 'test_expression'],
						['type' => 'Does not match regular expression', 'parameter_1' => 'not_expression'],
						['type' => 'Does not match regular expression', 'parameter_1' => 'not_expression'],
						['type' => 'Check for error in JSON', 'parameter_1' => '/path'],
						['type' => 'Check for error in JSON', 'parameter_1' => '/path'],
						['type' => 'Check for error in XML', 'parameter_1' => '/path/xml'],
						['type' => 'Check for error in XML', 'parameter_1' => '/path/xml'],
						['type' => 'Check for error using regular expression', 'parameter_1' => 'regexp', 'parameter_2' => '\1'],
						['type' => 'Check for error using regular expression', 'parameter_1' => 'regexp', 'parameter_2' => '\1']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Item with preprocessing rule with user macro',
					'key' => 'item-user-macro',
					'preprocessing' => [
						['type' => 'Regular expression', 'parameter_1' => '{$DELIM}(.*)', 'parameter_2' => '\1'],
						['type' => 'Trim', 'parameter_1' => '{$DELIM}'],
						['type' => 'Right trim', 'parameter_1' => '{$MACRO}'],
						['type' => 'Left trim', 'parameter_1' => '{$USER}'],
						['type' => 'XML XPath', 'parameter_1' => 'number(/values/Item/value[../key=\'{$DELIM}\'])'],
						['type' => 'JSONPath', 'parameter_1' => '$.data[\'{$KEY}\']'],
						['type' => 'Custom multiplier', 'parameter_1' => '{$VALUE}'],
						['type' => 'In range', 'parameter_1' => '{$FROM}', 'parameter_2' => '{$TO}'],
						['type' => 'Matches regular expression', 'parameter_1' => '{$EXPRESSION}(.*)'],
						['type' => 'Does not match regular expression', 'parameter_1' => '{$REGEXP}(.+)'],
						['type' => 'Check for error in JSON', 'parameter_1' => '{$USERMACRO}'],
						['type' => 'Check for error in XML', 'parameter_1' => '/tmp/{$PATH}'],
						['type' => 'Check for error using regular expression', 'parameter_1' => '^{$REGEXP}(.+)', 'parameter_2' => '\0'],
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '{$SECONDS}']
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getCreateData
	 */
	public function testFormItemPreprocessing_Create($data) {
		$db_hostid = CDBHelper::getRow('SELECT hostid FROM hosts WHERE host='.zbx_dbstr($this->host));
		$hostid = $db_hostid['hostid'];

		$this->zbxTestLogin('items.php?filter_set=1&filter_hostids[0]='.$hostid);
		$this->zbxTestContentControlButtonClickTextWait('Create item');
		$this->zbxTestCheckTitle('Configuration of items');
		$this->zbxTestCheckHeader('Items');

		$this->zbxTestInputType('name', $data['name']);
		$this->zbxTestInputType('key', $data['key']);
		$this->zbxTestTabSwitch('Preprocessing');

		foreach ($data['preprocessing'] as $step_count => $options) {
			$this->selectTypeAndfillParameters($step_count, $options);
		}

		$this->zbxTestClickWait('add');

		switch ($data['expected']) {
			case TEST_GOOD:
				$this->zbxTestCheckTitle('Configuration of items');
				$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Item added');

				$row_item = CDBHelper::getRow('SELECT name,key_,itemid FROM items where key_ = '.zbx_dbstr($data['key']));
				$this->assertEquals($row_item['name'], $data['name']);
				$this->assertEquals($row_item['key_'], $data['key']);

				$get_rows = CDBHelper::getAll('SELECT * FROM item_preproc where itemid ='.$row_item['itemid'].' ORDER BY step ASC');
				foreach ($get_rows as $row) {
					$type[] = $row['type'];
					$db_params[] = $row['params'];
				}

				// Check results in DB.
				foreach ($data['preprocessing'] as $key => $options) {
					// The array of allowed types must be synced with CItem::$supported_preprocessing_types.
					$db_type = get_preprocessing_types($type[$key], false, [ZBX_PREPROC_REGSUB, ZBX_PREPROC_TRIM,
						ZBX_PREPROC_RTRIM, ZBX_PREPROC_LTRIM, ZBX_PREPROC_XPATH, ZBX_PREPROC_JSONPATH,
						ZBX_PREPROC_MULTIPLIER, ZBX_PREPROC_DELTA_VALUE, ZBX_PREPROC_DELTA_SPEED, ZBX_PREPROC_BOOL2DEC,
						ZBX_PREPROC_OCT2DEC, ZBX_PREPROC_HEX2DEC, ZBX_PREPROC_VALIDATE_RANGE,
						ZBX_PREPROC_VALIDATE_REGEX, ZBX_PREPROC_VALIDATE_NOT_REGEX, ZBX_PREPROC_ERROR_FIELD_JSON,
						ZBX_PREPROC_ERROR_FIELD_XML, ZBX_PREPROC_ERROR_FIELD_REGEX, ZBX_PREPROC_THROTTLE_VALUE,
						ZBX_PREPROC_THROTTLE_TIMED_VALUE, ZBX_PREPROC_PROMETHEUS_PATTERN, ZBX_PREPROC_PROMETHEUS_TO_JSON
					]);
					$this->assertEquals($options['type'], $db_type);

					switch ($options['type']) {
						case 'Custom multiplier':
						case 'Right trim':
						case 'Left trim':
						case 'Trim':
						case 'XML XPath':
						case 'JSONPath':
						case 'Matches regular expression':
						case 'Does not match regular expression':
						case 'Check for error in JSON':
						case 'Check for error in XML':
						case 'Discard unchanged with heartbeat':
							$this->assertEquals($options['parameter_1'], $db_params[$key]);
							break;
						case 'Regular expression':
						case 'In range':
						case 'Check for error using regular expression':
							$params = $options['parameter_1']."\n".$options['parameter_2'];
							$this->assertEquals($params, $db_params[$key]);
							break;
					}
				}
				// Check result in frontend form.
				$this->checkTypeAndParametersFields($data);
				break;

			case TEST_BAD:
				$this->zbxTestCheckTitle('Configuration of items');
				$this->zbxTestWaitUntilMessageTextPresent('msg-bad', 'Cannot add item');
				$this->zbxTestTextPresent($data['error']);
				$this->assertEquals(0, CDBHelper::getCount('SELECT * FROM items where key_ = '.zbx_dbstr($data['key'])));
				break;
		}
	}

	/**
	 * Test copies templated item from one host to another.
	 */
	public function testFormItemPreprocessing_CopyItem() {
		$preprocessing_itemid = 15094;
		$original_hostid = 15001;

		$db_hostid = CDBHelper::getRow('SELECT hostid FROM hosts WHERE host='.zbx_dbstr($this->host));
		$hostid = $db_hostid['hostid'];

		$this->zbxTestLogin('items.php?filter_set=1&filter_hostids[0]='.$original_hostid);
		$this->zbxTestCheckTitle('Configuration of items');
		$this->zbxTestCheckHeader('Items');

		$this->zbxTestCheckboxSelect('group_itemid_'.$preprocessing_itemid);
		$this->zbxTestClickButton('item.masscopyto');

		$this->zbxTestDropdownSelectWait('copy_type', 'Hosts');
		$this->zbxTestDropdownSelectWait('copy_groupid', 'Zabbix servers');
		$this->zbxTestCheckboxSelect('copy_targetid_'.$hostid);
		$this->zbxTestClickWait('copy');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Item copied');

		$this->zbxTestClickLinkTextWait('All hosts');
		$this->zbxTestClickLinkTextWait($this->host);
		$this->zbxTestClickLinkTextWait('Items');
		$this->zbxTestClickLinkTextWait('testInheritanceItemPreprocessing');

		$row_item = CDBHelper::getRow('SELECT * FROM items WHERE itemid='.$preprocessing_itemid);
		$this->zbxTestAssertElementValue('name', $row_item['name']);
		$this->zbxTestAssertElementValue('key', $row_item['key_']);
		$this->zbxTestTabSwitch('Preprocessing');

		// Check preprocessing parameters for each type in form.
		$db_items_preproc = CDBHelper::getAll('SELECT * FROM item_preproc WHERE itemid='.$preprocessing_itemid);
		foreach ($db_items_preproc as $item_preproc) {
			$preprocessing_type = get_preprocessing_types($item_preproc['type'], false, [ZBX_PREPROC_REGSUB,
				ZBX_PREPROC_TRIM, ZBX_PREPROC_RTRIM, ZBX_PREPROC_LTRIM, ZBX_PREPROC_XPATH, ZBX_PREPROC_JSONPATH,
				ZBX_PREPROC_MULTIPLIER, ZBX_PREPROC_DELTA_VALUE, ZBX_PREPROC_DELTA_SPEED, ZBX_PREPROC_BOOL2DEC,
				ZBX_PREPROC_OCT2DEC, ZBX_PREPROC_HEX2DEC, ZBX_PREPROC_VALIDATE_RANGE, ZBX_PREPROC_VALIDATE_REGEX,
				ZBX_PREPROC_VALIDATE_NOT_REGEX, ZBX_PREPROC_ERROR_FIELD_JSON, ZBX_PREPROC_ERROR_FIELD_XML,
				ZBX_PREPROC_ERROR_FIELD_REGEX, ZBX_PREPROC_THROTTLE_VALUE, ZBX_PREPROC_THROTTLE_TIMED_VALUE,
				ZBX_PREPROC_PROMETHEUS_PATTERN, ZBX_PREPROC_PROMETHEUS_TO_JSON
			]);
			$this->zbxTestAssertElementNotPresentXpath('//input[@id="preprocessing_'.($item_preproc['step'] - 1).'_type"][@readonly]');
			$this->zbxTestDropdownAssertSelected('preprocessing['.($item_preproc['step'] - 1).'][type]', $preprocessing_type);
			switch ($item_preproc['type']) {
				case 1:
				case 2:
				case 3:
				case 4:
				case 11:
				case 12:
				case 14:
				case 15:
				case 16:
				case 17:
				case 20:
					$this->zbxTestAssertElementNotPresentXpath('//input[@id="preprocessing_'.($item_preproc['step'] - 1).'_params_0"][@readonly]');
					$this->zbxTestAssertElementValue('preprocessing_'.($item_preproc['step'] - 1).'_params_0', $item_preproc['params']);
					break;
				case 5:
				case 13:
				case 18:
					$parameter = preg_split("/\n/", $item_preproc['params']);
					$this->zbxTestAssertElementNotPresentXpath('//input[@id="preprocessing_'.($item_preproc['step'] - 1).'_params_0"][@readonly]');
					$this->zbxTestAssertElementNotPresentXpath('//input[@id="preprocessing_'.($item_preproc['step'] - 1).'_params_1"][@readonly]');
					$this->zbxTestAssertElementValue('preprocessing_'.($item_preproc['step'] - 1).'_params_0', $parameter[0]);
					$this->zbxTestAssertElementValue('preprocessing_'.($item_preproc['step'] - 1).'_params_1', $parameter[1]);
					break;
			}
		}
	}

	public static function getCustomOnFailData() {
		return [
			[
				[
					'name' => 'Item discard value on fail 1',
					'key' => 'discard_1',
					'preprocessing' => [
						['type' => 'Regular expression', 'parameter_1' => 'expression', 'parameter_2' => 'test output'],
						['type' => 'Trim', 'parameter_1' => 'trim'],
						['type' => 'Right trim', 'parameter_1' => 'right_trim'],
						['type' => 'Left trim', 'parameter_1' => 'left_trim'],
						['type' => 'XML XPath', 'parameter_1' => '/xml/path'],
						['type' => 'JSONPath', 'parameter_1' => '/json/path'],
						['type' => 'Custom multiplier', 'parameter_1' => '5'],
						['type' => 'Simple change'],
						['type' => 'Boolean to decimal'],
						['type' => 'Octal to decimal'],
						['type' => 'Hexadecimal to decimal'],
						['type' => 'In range', 'parameter_1' => '-1', 'parameter_2' => '2'],
						['type' => 'Matches regular expression', 'parameter_1' => 'expression'],
						['type' => 'Does not match regular expression', 'parameter_1' => 'not_expression'],
						['type' => 'Check for error in JSON', 'parameter_1' => '/path/json'],
						['type' => 'Check for error in XML', 'parameter_1' => '/path/xml'],
						['type' => 'Check for error using regular expression', 'parameter_1' => 'reg_exp', 'parameter_2' => '\0'],
						['type' => 'Discard unchanged with heartbeat', 'parameter_1' => '1']
					]
				]
			],
			[
				[
					'name' => 'Item discard value on fail 2',
					'key' => 'discard_2',
					'preprocessing' => [
						['type' => 'Change per second'],
						['type' => 'Discard unchanged'],
					]
				]
			],
		];
	}

	/**
	 * @dataProvider getCustomOnFailData
	 */
	public function testFormItemPreprocessing_CustomOnFailDiscard($data) {
		$this->exectueCustomOnFail('discard_value', $data);
	}

	/**
	 * @dataProvider getCustomOnFailData
	 */
	public function testFormItemPreprocessing_CustomOnFailSetValue($data) {
		$this->exectueCustomOnFail('set_value', $data);
	}

	/**
	 * @dataProvider getCustomOnFailData
	 */
	public function testFormItemPreprocessing_CustomOnFailSetError($data) {
		$this->exectueCustomOnFail('set_error', $data);
	}

	/**
	 * Check Custom on fail checkbox.
	 *
	 * @param array $data test case data from data provider
	 */
	private function exectueCustomOnFail($action, $data) {
		$custom_value = 'test_value';
		$custom_error = 'Test error message';

		$db_hostid = CDBHelper::getRow('SELECT hostid FROM hosts WHERE host='.zbx_dbstr($this->host));
		$hostid = $db_hostid['hostid'];

		$this->zbxTestLogin('items.php?hostid='.$hostid.'&form=create');
		$this->zbxTestCheckTitle('Configuration of items');
		$this->zbxTestCheckHeader('Items');

		$this->zbxTestInputType('name', $data['name']);
		// Generate random item key for each test case.
		$item_key = $data['key'].microtime(true);
		$this->zbxTestInputType('key', $item_key);
		$this->zbxTestTabSwitch('Preprocessing');

		foreach ($data['preprocessing'] as $step_count => $options) {
			$this->selectTypeAndfillParameters($step_count, $options);

			switch ($options['type']) {
				case 'Regular expression':
				case 'XML XPath':
				case 'JSONPath':
				case 'Custom multiplier':
				case 'Simple change':
				case 'Change per second':
				case 'Boolean to decimal':
				case 'Octal to decimal':
				case 'Hexadecimal to decimal':
				case 'In range':
				case 'Matches regular expression':
				case 'Does not match regular expression':
					$this->zbxTestIsEnabled('//input[@id="preprocessing_'.$step_count.'_on_fail"][@type="checkbox"]');
					$this->zbxTestClickWait('preprocessing_'.$step_count.'_on_fail');

					switch ($action) {
						case 'set_value':
							$this->zbxTestClickXpathWait('//label[@for="preprocessing_'.$step_count.'_error_handler_1"]');
							$this->zbxTestInputTypeWait('preprocessing_'.$step_count.'_error_handler_params', $custom_value);
							break;
						case 'set_error':
							$this->zbxTestClickXpathWait('//label[@for="preprocessing_'.$step_count.'_error_handler_2"]');
							$this->zbxTestInputTypeWait('preprocessing_'.$step_count.'_error_handler_params', $custom_error);
							break;
					}
					break;
				case 'Right trim':
				case 'Left trim':
				case 'Trim':
				case 'Check for error in JSON':
				case 'Check for error in XML':
				case 'Check for error using regular expression':
				case 'Discard unchanged':
				case 'Discard unchanged with heartbeat':
					$this->zbxTestAssertElementPresentXpath('//input[@id="preprocessing_'.$step_count.'_on_fail"][@type="checkbox"][@disabled]');
					break;
			}
		}

		$this->zbxTestClickWait('add');

		$this->zbxTestCheckTitle('Configuration of items');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Item added');

		// Get item row from DB.
		$db_item = CDBHelper::getRow('SELECT name,key_,itemid FROM items where key_ = '.zbx_dbstr($item_key));
		$this->assertEquals($db_item['name'], $data['name']);
		$this->assertEquals($db_item['key_'], $item_key);
		$itemid = $db_item['itemid'];

		// Check saved preprocessing.
		$this->zbxTestClickXpathWait('//a[contains(@href, "&itemid='.$itemid.'")]');
		$this->zbxTestTabSwitch('Preprocessing');
		foreach ($data['preprocessing'] as $step_count => $options) {
			// Get preprocessing from DB.
			$row_preproc = CDBHelper::getRow('SELECT * FROM item_preproc WHERE step='.($step_count + 1).' AND itemid = '.$itemid);
			switch ($options['type']) {
				case 'Regular expression':
				case 'XML XPath':
				case 'JSONPath':
				case 'Custom multiplier':
				case 'Simple change':
				case 'Change per second':
				case 'Boolean to decimal':
				case 'Octal to decimal':
				case 'Hexadecimal to decimal':
				case 'In range':
				case 'Matches regular expression':
				case 'Does not match regular expression':
					// Check preprocessing in frontend.
					$this->assertTrue($this->zbxTestCheckboxSelected('preprocessing_'.$step_count.'_on_fail'));
					if ($action === 'discard_value') {
						$this->zbxTestAssertElementPresentXpath('//input[@id="preprocessing_'.$step_count.'_error_handler_0"][@checked]');
						// Check preprocessing in DB, where "Discard value" type is equal 1.
						$this->assertEquals(1, $row_preproc['error_handler']);
					}
					elseif ($action === 'set_value') {
						$this->zbxTestAssertElementPresentXpath('//input[@id="preprocessing_'.$step_count.'_error_handler_1"][@checked]');
						$this->zbxTestAssertElementValue('preprocessing_'.$step_count.'_error_handler_params', $custom_value);
						// Check preprocessing in DB, where "Set value" type is equal 2.
						$this->assertEquals(2, $row_preproc['error_handler']);
						$this->assertEquals($custom_value, $row_preproc['error_handler_params']);
					}
					elseif ($action === 'set_error') {
						$this->zbxTestAssertElementPresentXpath('//input[@id="preprocessing_'.$step_count.'_error_handler_2"][@checked]');
						$this->zbxTestAssertElementValue('preprocessing_'.$step_count.'_error_handler_params', $custom_error);
						// Check preprocessing in DB, where "Set error message" type is equal 3.
						$this->assertEquals(3, $row_preproc['error_handler']);
						$this->assertEquals($custom_error, $row_preproc['error_handler_params']);
					}
					break;
				case 'Right trim':
				case 'Left trim':
				case 'Trim':
				case 'Check for error in JSON':
				case 'Check for error in XML':
				case 'Check for error using regular expression':
				case 'Discard unchanged':
				case 'Discard unchanged with heartbeat':
					// Check preprocessing in DB.
					$this->assertEquals(0, $row_preproc['error_handler']);
					// Check preprocessing in frontend.
					$this->zbxTestAssertElementPresentXpath('//input[@id="preprocessing_'.$step_count.'_on_fail"][@type="checkbox"][@disabled]');
					$this->assertFalse($this->zbxTestCheckboxSelected('preprocessing_'.$step_count.'_on_fail'));
					break;
			}
		}
	}

	public static function getCustomOnFailValidationData() {
		return [
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Set value empty',
					'key' => 'set-value-empty',
					'custom_on_fail' => [
						['option' => 'Set value to', 'input' => '']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Set value number',
					'key' => 'set-value-number',
					'custom_on_fail' => [
						['option' => 'Set value to', 'input' => '500']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Set value string',
					'key' => 'set-value-string',
					'custom_on_fail' => [
						['option' => 'Set error to', 'input' => 'String']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Set value special-symbols',
					'key' => 'set-value-special-symbols',
					'custom_on_fail' => [
						['option' => 'Set value to', 'input' => '!@#$%^&*()_+<>,.\/']
					]
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'name' => 'Set error empty',
					'key' => 'set-error-empty',
					'custom_on_fail' => [
						['option' => 'Set error to', 'input' => '']
					],
					'error' => 'Incorrect value for field "error_handler_params": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Set error string',
					'key' => 'set-error-string',
					'custom_on_fail' => [
						['option' => 'Set error to', 'input' => 'Test error']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Set error number',
					'key' => 'set-error-number',
					'custom_on_fail' => [
						['option' => 'Set error to', 'input' => '999']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'name' => 'Set error special symbols',
					'key' => 'set-error-special-symbols',
					'custom_on_fail' => [
						['option' => 'Set error to', 'input' => '!@#$%^&*()_+<>,.\/']
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getCustomOnFailValidationData
	 */
	public function testFormItemPreprocessing_CustomOnFailValidation($data) {
		$preprocessing = [
			['type' => 'Regular expression', 'parameter_1' => 'expression', 'parameter_2' => 'test output'],
			['type' => 'XML XPath', 'parameter_1' => '/xml/path'],
			['type' => 'JSONPath', 'parameter_1' => '/json/path'],
			['type' => 'Custom multiplier', 'parameter_1' => '5'],
			['type' => 'Simple change'],
			['type' => 'Boolean to decimal'],
			['type' => 'Octal to decimal'],
			['type' => 'Hexadecimal to decimal'],
			['type' => 'In range', 'parameter_1' => '-1', 'parameter_2' => '2'],
			['type' => 'Matches regular expression', 'parameter_1' => 'expression'],
			['type' => 'Does not match regular expression', 'parameter_1' => 'not_expression'],
		];

		$db_hostid = CDBHelper::getRow('SELECT hostid FROM hosts WHERE host='.zbx_dbstr($this->host));
		$hostid = $db_hostid['hostid'];

		$this->zbxTestLogin('items.php?hostid='.$hostid.'&form=create');
		$this->zbxTestCheckTitle('Configuration of items');
		$this->zbxTestCheckHeader('Items');

		$this->zbxTestInputType('name', $data['name']);
		$this->zbxTestInputType('key', $data['key']);
		$this->zbxTestTabSwitch('Preprocessing');

		foreach ($preprocessing as $step_count => $options) {
			$this->selectTypeAndfillParameters($step_count, $options);
			$this->zbxTestClickWait('preprocessing_'.$step_count.'_on_fail');

			foreach ($data['custom_on_fail'] as $error_type) {
				switch ($error_type['option']) {
					case 'Set value to':
						$this->zbxTestClickXpathWait('//label[@for="preprocessing_'.$step_count.'_error_handler_1"]');
						$this->zbxTestInputType('preprocessing_'.$step_count.'_error_handler_params', $error_type['input']);
						break;
					case 'Set error to':
						$this->zbxTestClickXpathWait('//label[@for="preprocessing_'.$step_count.'_error_handler_2"]');
						$this->zbxTestInputType('preprocessing_'.$step_count.'_error_handler_params', $error_type['input']);
						break;
				}
				break;
			}
		}
		$this->zbxTestClickWait('add');

		switch ($data['expected']) {
			case TEST_GOOD:
				$this->zbxTestCheckTitle('Configuration of items');
				$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Item added');
				$this->assertEquals(1, CDBHelper::getCount('SELECT * FROM items where key_ = '.zbx_dbstr($data['key'])));
				break;

			case TEST_BAD:
				$this->zbxTestCheckTitle('Configuration of items');
				$this->zbxTestWaitUntilMessageTextPresent('msg-bad', 'Cannot add item');
				$this->zbxTestTextPresent($data['error']);
				$this->assertEquals(0, CDBHelper::getCount('SELECT * FROM items where key_ = '.zbx_dbstr($data['key'])));
				break;
		}
	}

	/**
	 * Check dropdowns and fields in saved form.
	 *
	 * @param array $data test case data from data provider
	 */
	private function checkTypeAndParametersFields($data) {
		$this->zbxTestClickLinkTextWait($data['name']);
		$this->zbxTestTabSwitch('Preprocessing');
		foreach ($data['preprocessing'] as $step_count => $options) {
			$this->zbxTestDropdownAssertSelected('preprocessing_'.$step_count.'_type', $options['type']);
			if (array_key_exists('parameter_1', $options) && array_key_exists('parameter_2', $options)) {
				$this->zbxTestAssertElementValue('preprocessing_'.$step_count.'_params_0', $options['parameter_1']);
				$this->zbxTestAssertElementValue('preprocessing_'.$step_count.'_params_1', $options['parameter_2']);
			}
			elseif (array_key_exists('parameter_1', $options) && !array_key_exists('parameter_2', $options)) {
				$this->zbxTestAssertElementValue('preprocessing_'.$step_count.'_params_0', $options['parameter_1']);
			}
		}
	}

	/**
	 * Add new preprocessing, select preprocessing type and parameters if exist.
	 */
	private function selectTypeAndfillParameters($step, $options) {
		$this->zbxTestClickWait('param_add');
		$this->zbxTestDropdownSelect('preprocessing_'.$step.'_type', $options['type']);

		if (array_key_exists('parameter_1', $options) && array_key_exists('parameter_2', $options)) {
			$this->zbxTestInputType('preprocessing_'.$step.'_params_0', $options['parameter_1']);
			$this->zbxTestInputType('preprocessing_'.$step.'_params_1', $options['parameter_2']);
		}
		elseif (array_key_exists('parameter_1', $options) && !array_key_exists('parameter_2', $options)) {
			$this->zbxTestInputType('preprocessing_'.$step.'_params_0', $options['parameter_1']);
		}
	}
}
