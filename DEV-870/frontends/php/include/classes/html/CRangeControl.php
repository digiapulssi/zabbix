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


class CRangeControl extends CTextBox {

	protected $options;

	public function __construct($name, $value = '') {
		parent::__construct();

		$this->options = [];
		$this->setValue($value);
		$this->setId(base_convert(rand(), 10, 36));
		return $this;
	}

	public function setValue($value) {
		$this->setAttribute('value', $value);
		return $this;
	}

	public function setMin($value) {
		$this->options['min'] = $value;
		return $this;
	}

	public function setMax($value) {
		$this->options['max'] = $value;
		return $this;
	}

	public function setStep($value) {
		$this->options['step'] = $value;
		return $this;
	}

	public function toString($destroy = true) {
		$input = parent::toString($destroy);

		return $input.get_js('jQuery("#'.$this->getId().'").rangeControl('.CJs::encodeJson($this->options).')');
	}
}
