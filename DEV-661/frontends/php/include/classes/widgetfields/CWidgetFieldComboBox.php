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


/**
 * A class to create a select drowdown type field in widget.
 */
class CWidgetFieldComboBox extends CWidgetField {

	/**
	 * Combo box values.
	 *
	 * @var array
	 */
	private $values;

	/**
	 * Combo box widget field. Can use both, string and integer type keys.
	 *
	 * @param string $name    Field name in form
	 * @param string $label   Label for the field in form
	 * @param array  $values  Key/value pairs of combo box values. Key - saved in DB. Value - visible to user.
	 */
	public function __construct($name, $label, $values) {
		parent::__construct($name, $label);

		$this->setSaveType(ZBX_WIDGET_FIELD_TYPE_INT32);
		$this->values = $values;
		$this->setExValidationRules(['in' => implode(',', array_keys($this->values))]);
	}

	/**
	 * Set field value forcing it to be integer.
	 *
	 * @param string $value  Field value to set.
	 *
	 * @return CWidgetFieldComboBox
	 */
	public function setValue($value) {
		return parent::setValue((int) $value);
	}

	/**
	 * Get field values.
	 *
	 * @return array
	 */
	public function getValues() {
		return $this->values;
	}
}
