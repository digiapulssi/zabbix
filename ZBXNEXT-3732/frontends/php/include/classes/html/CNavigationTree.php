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


class CNavigationTree extends CDiv {
	private $error;
	private $script_file;
	private $script_run;
	private $widgetId = null;

	public function __construct(array $configData) {
		parent::__construct();

		$this->setId(uniqid());
		$this->addClass(ZBX_STYLE_NAVIGATIONTREE);

		$this->error = null;
		$this->widgetId = $configData['widgetid'];
		$this->script_file = 'js/class.cnavtree.js';
		$this->script_run = '';
	}

	public function setError($value) {
		$this->error = $value;

		return $this;
	}

	public function getScriptFile() {
		return $this->script_file;
	}

	public function getScriptRun() {
		if ($this->error === null) {
			$items = $this->getItems();
			$tree = $this->buildTree($items['rows']);
			$this->script_run = ''
			 . 'jQuery("#tree").zbx_navtree({'
			 .	'tree: '.json_encode($tree).','
			 .	'problems: '.json_encode($items['problems']).','
			 .	'widgetid: '.$this->widgetId.''
			 . '});';
		}

		return $this->script_run;
	}

	protected function getNumberOfProblemsBySysmap(array $mapsId = []) {
		// TODO miks: add severity checks // done
		// TODO miks: map may have several triggers per selement:o
		// TODO miks: create submap counter

		$response = [];
		$sysmaps = API::Map()->get([
				'output' => ['sysmapid', 'severity_min'],
				'sysmapids' => $mapsId,
				'preservekeys' => true,
				'severity_min' => 0,
				'selectSelements' => API_OUTPUT_EXTEND
		]);

		if ($sysmaps) {
			$problems_by_elements = [];
			$problemsPerSeverityTmpl = [
				0 => 0,
				1 => 0,
				2 => 0,
				3 => 0,
				4 => 0,
				5 => 0,
			];

			foreach ($sysmaps as $map) {
				foreach ($map['selements'] as $selement) {
					switch ($selement['elementtype']) {
						case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
							$element = reset($selement['elements']);
							if ($element) {
								$problems_by_elements[SYSMAP_ELEMENT_TYPE_HOST_GROUP][$element['groupid']] = $problemsPerSeverityTmpl;
							}
							break;
						case SYSMAP_ELEMENT_TYPE_TRIGGER:
							$element = reset($selement['elements']);
							if ($element) {
								$problems_by_elements[SYSMAP_ELEMENT_TYPE_TRIGGER][$element['triggerid']] = $problemsPerSeverityTmpl;
							}
							break;
						case SYSMAP_ELEMENT_TYPE_HOST:
							$element = reset($selement['elements']);
							if ($element) {
								$problems_by_elements[SYSMAP_ELEMENT_TYPE_HOST][$element['hostid']] = $problemsPerSeverityTmpl;
							}
							break;
						/*case SYSMAP_ELEMENT_TYPE_MAP:
							$element = reset($selement['elements']);
							if ($element) {
								$selements['sysmaps'][] = $selement['sysmapid'];
							}
							break; */
					}
				}
			}

			$severity_min = min(zbx_objectValues($sysmaps, 'severity_min'));

			if (array_key_exists(SYSMAP_ELEMENT_TYPE_HOST_GROUP, $problems_by_elements)) {
				$triggers = API::Trigger()->get([
					'output' => ['triggerid', 'priority'],
					'groupids' => array_keys($problems_by_elements[SYSMAP_ELEMENT_TYPE_HOST_GROUP]),
					'min_severity' => $severity_min,
					'skipDependent' => true,
					'selectGroups' => ['groupid'],
					'preservekeys' => true
				]);

				$events = API::Event()->get([
					'output' => ['objectid'],
					'source' => EVENT_SOURCE_TRIGGERS,
					'object' => EVENT_OBJECT_TRIGGER,
					'value' => TRIGGER_VALUE_TRUE,
					'objectids' => zbx_objectValues($triggers, 'triggerid')
				]);

				if ($events) {
					foreach ($events as $event) {
						$trigger = $triggers[$event['objectid']];
						$host_group = reset($trigger['groups']);

						if ($host_group) {
							$problems_by_elements[SYSMAP_ELEMENT_TYPE_HOST_GROUP][$host_group['groupid']][$trigger['priority']]++;
						}
					}
				}
			}

			if (array_key_exists(SYSMAP_ELEMENT_TYPE_TRIGGER, $problems_by_elements)) {
				$events = API::Event()->get([
					'output' => ['objectid'],
					'source' => EVENT_SOURCE_TRIGGERS,
					'object' => EVENT_OBJECT_TRIGGER,
					'value' => TRIGGER_VALUE_TRUE,
					'objectids' => array_keys($problems_by_elements[SYSMAP_ELEMENT_TYPE_TRIGGER])
				]);

				if ($events) {
					foreach ($events as $event) {
						$problems_by_elements[SYSMAP_ELEMENT_TYPE_TRIGGER][$event['objectid']][$trigger['priority']]++;
					}
				}
			}

			if (array_key_exists(SYSMAP_ELEMENT_TYPE_HOST, $problems_by_elements)) {
				$triggers = API::Trigger()->get([
					'output' => ['triggerid', 'priority'],
					'selectHosts' => ['hostid'],
					'hostids' => array_keys($problems_by_elements[SYSMAP_ELEMENT_TYPE_HOST]),
					'min_severity' => $severity_min,
					'skipDependent' => true,
					'preservekeys' => true
				]);

				$events = API::Event()->get([
					'output' => ['objectid'],
					'source' => EVENT_SOURCE_TRIGGERS,
					'object' => EVENT_OBJECT_TRIGGER,
					'value' => TRIGGER_VALUE_TRUE,
					'objectids' => zbx_objectValues($triggers, 'triggerid')
				]);

				if ($events) {
					foreach ($events as $event) {
						$trigger = $triggers[$event['objectid']];
						$host = reset($trigger['hosts']);

						if ($host) {
							$problems_by_elements[SYSMAP_ELEMENT_TYPE_HOST][$host['hostid']][$trigger['priority']]++;
						}
					}
				}
			}

			foreach ($sysmaps as $map) {
				$response[$map['sysmapid']] = $problemsPerSeverityTmpl;

				foreach ($map['selements'] as $selement) {
					$element = reset($selement['elements']);
					if ($element) {
						switch ($selement['elementtype']) {
							case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
								$problems = $problems_by_elements[SYSMAP_ELEMENT_TYPE_HOST_GROUP][$element['groupid']];
								break;
							case SYSMAP_ELEMENT_TYPE_TRIGGER:
								$problems = $problems_by_elements[SYSMAP_ELEMENT_TYPE_TRIGGER][$element['triggerid']];
								break;
							case SYSMAP_ELEMENT_TYPE_HOST:
								$problems = $problems_by_elements[SYSMAP_ELEMENT_TYPE_HOST][$element['hostid']];
								break;
							/*case SYSMAP_ELEMENT_TYPE_MAP:
								$element = reset($selement['elements']);
								if ($element) {
									$selements['sysmaps'][] = $selement['sysmapid'];
								}
								break; */
							default:
								$problems = null;
								break;
						}

						if (is_array($problems)) {
							$response[$map['sysmapid']] = array_map(function () {
								return array_sum(func_get_args());
							}, $response[$map['sysmapid']], $problems);
						}
					}
				}
			}
		}

		return $response;
	}

	public function buildTree(array $rows, $parentId = 0) {
    if (!$rows) return null;

		$tree = [];
    foreach ($rows as $elementId => $element) {
			if ($element['parent'] === $parentId) {
				$children = $this->buildTree($rows, $elementId);
				if ($children) {
					$element['children'] = $children;
				}
				$tree[$elementId] = $element;
			}
    }

    return $tree;
	}
	
	/**
	 * Ugly function to read a tree items. 
	 * Hoping for some API in the future.
	 */
	public function getItems() {
		$rows = [];

		$query = ''
			. 'SELECT widget_fieldid AS id, type, value_str, sysmapid, value_int, name '
			. 'FROM widget_field '
			. 'WHERE '
			. '	widgetid = '.$this->widgetId
			. '';

		$data = DBselect($query);
		while ($row = DBfetch($data)) {
			$row['name'] = explode('.', $row['name']);
			$itemId = array_pop($row['name']);

			if (!array_key_exists($itemId, $rows)) {
				$rows[$itemId] = [
					'name' => '',
					'parent' => 0
				];
			}

			switch ($row['type']) {
				case 1:
					$row['value'] = $row['value_str'];
					break;
				case 2:
					$row['value'] = $row['sysmapid'];
					break;
				case 3:
					$row['value'] = $row['value_int'];
					break;
				default:
					$row['value'] = null;
					break;
			}

			if ($row['name'][0] === 'map' && $row['name'][1] === 'name') {
				$rows[$itemId]['name'] = $row['value'];
			}
			elseif ($row['name'][0] === 'map' && $row['name'][1] === 'parent') {
				$rows[$itemId]['parent'] = (int)$row['value'];
			}
			elseif ($row['name'][0] === 'mapid') {
				$rows[$itemId]['mapid'] = (int)$row['value'];
			}
		}

		return [
			'problems' => $this->getNumberOfProblemsBySysmap(zbx_objectValues($rows, 'mapid')),
			'rows' => $rows
		];
	}

	private function build() {
		if ($this->error !== null) {
			$span->addClass(ZBX_STYLE_DISABLED);
		}

		$treeDiv = (new CDiv())
			->addClass('tree')
			->setAttribute('id', 'tree');

		$this->addItem($treeDiv);
	}

	public function toString($destroy = true) {
		$this->build();

		return parent::toString($destroy);
	}
}
