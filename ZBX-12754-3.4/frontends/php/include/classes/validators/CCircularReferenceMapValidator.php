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


class CCircularReferenceMapValidator extends CValidator {

	/**
	 * Should non existing maps to be reported as valid.
	 */
	const IGNORE_NON_EXISTING = 0x1;

	/**
	 * Validation options.
	 */
	public $options = 0;

	/**
	 * @var CMap
	 */
	public $mapsProvider;

	/**
	 * If circular reference is found will contain array of map labels from root to map having circular reference.
	 */
	protected $recursion_path = [];

	/**
	 * Maps to be checked for circular reference.
	 */
	protected $maps = [];

	public function validate($maps) {
		$this->maps = zbx_toHash($maps, 'name');

		foreach ($this->maps as $name => $map) {
			if (!array_key_exists('selements', $map) || !$map['selements']) {
				continue;
			}
			$this->recursion_path = [$name];

			foreach ($map['selements'] as $selement) {
				if (!$this->validateRecursive($selement)) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Recursive function for searching for circular map references.
	 *
	 * @param array $selement   Map element to inspect on current recursive loop.
	 *
	 * @return bool
	 */
	protected function validateRecursive(array $selement) {
		// If element is not a map element, recursive reference cannot happen.
		if ($selement['elementtype'] != SYSMAP_ELEMENT_TYPE_MAP) {
			return true;
		}

		$map_name = array_key_exists('name', $selement['elements'][0]) ? $selement['elements'][0]['name'] : null;

		// Use mapsProvider service if exists.
		if ($map_name === null || (!array_key_exists($map_name, $this->maps) && $this->mapsProvider)) {
			$maps = $this->mapsProvider->get([
				'output' => ['name'],
				'sysmapids' => $selement['elements'][0]['sysmapid'],
				'selectSelements' => ['elementtype', 'name', 'sysmapid', 'elements']
			]);

			if ($maps) {
				$map_name = ($map_name === null) ? $maps[0]['name'] : $map_name;
				$this->maps[$map_name] = $maps[0];
			}
		}

		if ($map_name === null || !array_key_exists($map_name, $this->maps)) {
			// For non existing map return true if IGNORE_NON_EXISTING options is set, false otherwise.
			return ($this->options & self::IGNORE_NON_EXISTING);
		}

		// If current element map name is already in list of checked map names, circular reference exists.
		if (in_array($map_name, $this->recursion_path)) {
			$this->recursion_path[] = $map_name;
			return false;
		}

		// Find maps that reference the current element, and if one has selements, check all of them recursively.
		if (array_key_exists('selements', $this->maps[$map_name])) {
			$this->recursion_path[] = $map_name;

			foreach ($this->maps[$map_name]['selements'] as $selement) {
				if (!$this->validateRecursive($selement)) {
					return false;
				}
			}

			array_pop($this->recursion_path);
		}

		return true;
	}

	/**
	 * Getter for circular reference map names path.
	 *
	 * @return string
	 */
	public function getCircularMapsString() {
		return implode(' - ', $this->recursion_path);
	}
}
