<?php
/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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


class CDecimalValidatorTest extends CValidatorTest {

	public function validParamProvider() {
		return array(
			array(array(
				'maxPrecision' => 3,
				'maxScale' => 2,
				'messageInvalid' => 'Invalid decimal',
				'messagePrecision' => 'Value too long',
				'messageNatural' => 'To many digits before the decimal point',
				'messageScale' => 'To many digits after the decimal point'
			))
		);
	}

	public function validValuesProvider() {
		return array(
			array(array('maxPrecision' => 5, 'maxScale' => 3), 0),
			array(array('maxPrecision' => 5, 'maxScale' => 3), 1),
			array(array('maxPrecision' => 5, 'maxScale' => 3), -1),
			array(array('maxPrecision' => 5, 'maxScale' => 3), 1.0),
			array(array('maxPrecision' => 5, 'maxScale' => 3), '1'),
			array(array('maxPrecision' => 5, 'maxScale' => 3), '-1'),
			array(array('maxPrecision' => 5, 'maxScale' => 3), '1.0'),
			array(array('maxPrecision' => 5, 'maxScale' => 3), 99.999),
			array(array('maxPrecision' => 5, 'maxScale' => 3), '99.999'),
			array(array('maxPrecision' => 5, 'maxScale' => 3), -99.999),
			array(array('maxPrecision' => 5, 'maxScale' => 3), '-99.999'),
			array(array('maxScale' => 3), '1.001'),
		);
	}

	public function invalidValuesProvider() {
		return array(
			array(
				array('messageInvalid' => 'Invalid decimal'),
				'',
				'Invalid decimal'
			),
			array(
				array('messageInvalid' => 'Invalid decimal'),
				'--1.0',
				'Invalid decimal'
			),
			array(
				array('messageInvalid' => 'Invalid decimal'),
				'1E3',
				'Invalid decimal'
			),
			array(
				array('messageInvalid' => 'Invalid decimal "%1$s"'),
				array(),
				'Invalid decimal "array"'
			),
			array(
				array(
					'maxPrecision' => 5,
					'maxScale' => 3,
					'messagePrecision' => 'Value %1$s is too long, it cannot have more than %2$s digits before the decimal point and %3$s after'
				),
				999.999,
				'Value 999.999 is too long, it cannot have more than 2 digits before the decimal point and 3 after'
			),
			array(
				array(
					'maxPrecision' => 5,
					'maxScale' => 3,
					'messageNatural' => 'Value %1$s has to many digits before the decimal point, it cannot have more than %2$s digits'
				),
				999.99,
				'Value 999.99 has to many digits before the decimal point, it cannot have more than 2 digits'
			),
			array(
				array(
					'maxPrecision' => 5,
					'maxScale' => 3,
					'messageScale' => 'Value %1$s has to many digits after the decimal point, it cannot have more than %2$s digits'
				),
				9.9999,
				'Value 9.9999 has to many digits after the decimal point, it cannot have more than 3 digits'
			),
			array(
				array(
					'maxPrecision' => 5,
					'maxScale' => 3,
					'messagePrecision' => 'Value %1$s is too long, it cannot have more than %2$s digits before the decimal point and %3$s after'
				),
				'999.999',
				'Value 999.999 is too long, it cannot have more than 2 digits before the decimal point and 3 after'
			),
			array(
				array(
					'maxPrecision' => 5,
					'maxScale' => 3,
					'messageNatural' => 'Value %1$s has to many digits before the decimal point, it cannot have more than %2$s digits'
				),
				'999.99',
				'Value 999.99 has to many digits before the decimal point, it cannot have more than 2 digits'
			),
			array(
				array(
					'maxPrecision' => 5,
					'maxScale' => 3,
					'messageScale' => 'Value %1$s has to many digits after the decimal point, it cannot have more than %2$s digits'
				),
				'9.9999',
				'Value 9.9999 has to many digits after the decimal point, it cannot have more than 3 digits'
			),
			array(
				array(
					'maxPrecision' => 5,
					'maxScale' => 3,
					'messagePrecision' => 'Value %1$s is too long, it cannot have more than %2$s digits before the decimal point and %3$s after'
				),
				-999.999,
				'Value -999.999 is too long, it cannot have more than 2 digits before the decimal point and 3 after'
			),
			array(
				array(
					'maxPrecision' => 5,
					'maxScale' => 3,
					'messageNatural' => 'Value %1$s has to many digits before the decimal point, it cannot have more than %2$s digits'
				),
				-999.99,
				'Value -999.99 has to many digits before the decimal point, it cannot have more than 2 digits'
			),
			array(
				array(
					'maxPrecision' => 5,
					'maxScale' => 3,
					'messageScale' => 'Value %1$s has to many digits after the decimal point, it cannot have more than %2$s digits'
				),
				-9.9999,
				'Value -9.9999 has to many digits after the decimal point, it cannot have more than 3 digits'
			),
		);
	}

	public function invalidValuesWithObjectsProvider() {
		return array(
			array(
				array('messageInvalid' => 'Invalid decimal value for "%1$s"'),
				'',
				'Invalid decimal value for "object"'
			),
			array(
				array('messageInvalid' => 'Invalid decimal value for "%1$s"'),
				array(),
				'Invalid decimal value for "object"'
			),
			array(
				array('messageInvalid' => 'Invalid decimal value "%2$s" for "%1$s"'),
				'A',
				'Invalid decimal value "A" for "object"'
			),
		);
	}

	protected function createValidator(array $params = array()) {
		return new CDecimalValidator($params);
	}
}
