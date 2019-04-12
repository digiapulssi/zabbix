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

require_once dirname(__FILE__).'/../../include/CWebTest.php';

/**
 * Trait for preprocessing related tests.
 */
trait PreprocessingTrait {

	/**
	 * Get descriptors of preprocessing fields.
	 *
	 * @return array
	 */
	private static function getPreprocessingFieldDescriptors() {
		return [
			[
				'name'		=> 'type',
				'selector'	=> 'xpath:.//select[contains(@id, "_type")]',
				'class'		=> CDropdownElement::class,
				'value'		=> ['getText']
			],
			[
				'name'		=> 'type',
				'selector'	=> 'xpath:.//input[contains(@id, "_type_name")]',
				'value'		=> ['getAttribute', 'params' => ['value']]
			],
			[
				'name'		=> 'parameter_1',
				'selector'	=> 'xpath:.//input[contains(@id, "_params_0")]',
				'value'		=> ['getAttribute', 'params' => ['value']]
			],
			[
				'name'		=> 'parameter_2',
				'selector'	=> 'xpath:.//input[contains(@id, "_params_1")]',
				'value'		=> ['getAttribute', 'params' => ['value']]
			],
			[
				'name'		=> 'on_fail',
				'selector'	=> 'xpath:.//input[contains(@id, "_on_fail")]',
				'class'		=> CCheckboxElement::class,
				'value'		=> ['isChecked']
			],
			[
				'name'		=> 'error_handler',
				'selector'	=> 'xpath:.//ul[contains(@id, "_error_handler")]',
				'class'		=> CSegmentedRadioElement::class,
				'value'		=> ['getText']
			],
			[
				'name'		=> 'error_handler_params',
				'selector'	=> 'xpath:.//input[contains(@id, "_error_handler_params")]',
				'value'		=> ['getAttribute', 'params' => ['value']]
			]
		];
	}

	/**
	 * Get preprocessing step field from container and field description.
	 *
	 * @param Element $container    container element
	 * @param array   $field        field description
	 *
	 * @return Element|null
	 */
	private static function getPreprocessingField($container, $field) {
		$query = $container->query($field['selector']);

		if (array_key_exists('class', $field)) {
			$query->cast($field['class']);
		}

		return $query->one(false);
	}

	/**
	 * Add new preprocessing, select preprocessing type and parameters if exist.
	 *
	 * @param array $steps    preprocessing step values
	 */
	private function addPreprocessingSteps($steps) {
		$rows = $this->query('class:preprocessing-list-item')->count() + 1;
		$add = $this->query('id:param_add')->one();
		$fields = self::getPreprocessingFieldDescriptors();

		// Forcing removing of input field descriptor used in items inherited from templates.
		unset($fields[1]);

		foreach ($steps as $options) {
			$add->click();
			$container = $this->query('xpath://li[contains(@class, "preprocessing-list-item")]['.$rows.']')
					->waitUntilPresent()->one();

			foreach ($fields as $field) {
				if (array_key_exists($field['name'], $options)) {
					self::getPreprocessingField($container, $field)->fill($options[$field['name']]);
				}
			}

			$rows++;
		}
	}

	/**
	 * Get input fields of preprocessing steps.
	 *
	 * @param boolean $extended    get preprocessing steps with field descriptors.
	 *
	 * @return array
	 */
	private function getPreprocessingSteps($extended = false) {
		$steps = [];

		$fields = self::getPreprocessingFieldDescriptors();

		foreach ($this->query('class:preprocessing-list-item')->all() as $row) {
			$preprocessing = [];

			foreach ($fields as $field) {
				$key = $field['name'];

				if (isset($preprocessing[$key]) && (!$extended || $preprocessing[$key]['element'] !== null)) {
					continue;
				}

				$element = self::getPreprocessingField($row, $field);

				$preprocessing[$key] = $extended ? ['element' => $element, 'field' => $field] : $element;
			}

			$steps[] = $preprocessing;
		}

		return $steps;
	}

	/**
	 * Check if values of preprocessing step inputs match data from data provider.
	 *
	 * @return array
	 */
	private function assertPreprocessingSteps($data) {
		$steps = $this->getPreprocessingSteps(true);
		$this->assertEquals(count($data), count($steps), 'Preprocessing step count should match step count in data.');

		foreach ($data as $i => $options) {
			foreach ($steps[$i] as $control) {
				$field = $control['field'];

				if (!array_key_exists($field['name'], $options)) {
					continue;
				}

				if ($control['element'] === null) {
					$this->fail('Field "'.$field['name'].'" is not present.');
				}

				$value = call_user_func_array([$control['element'], $field['value'][0]],
						array_key_exists('params', $field['value']) ? $field['value']['params'] : []
				);

				$this->assertEquals($options[$field['name']], $value);
			}
		}

		// Remove field data.
		foreach ($steps as &$step) {
			foreach ($step as &$control) {
				$control = $control['element'];
			}
			unset($control);
		}
		unset($step);

		return $steps;
	}
}
