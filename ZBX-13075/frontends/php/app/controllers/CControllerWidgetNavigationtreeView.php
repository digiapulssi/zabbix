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


require_once dirname(__FILE__).'/../../include/blocks.inc.php';

class CControllerWidgetNavigationtreeView extends CControllerWidget {

	private $problems_per_severity_tpl;

	public function __construct() {
		parent::__construct();

		$this->setType(WIDGET_NAVIGATION_TREE);
		$this->setValidationRules([
			'name' => 'string',
			'uniqueid' => 'required|string',
			'widgetid' => 'db widget.widgetid',
			'initial_load' => 'in 0,1',
			'fields' => 'json'
		]);
	}

	protected function getNumberOfProblemsBySysmap(array $navtree_items = []) {
		$response = [];
		$sysmapids = array_keys(array_flip(zbx_objectValues($navtree_items, 'mapid')));

		$sysmaps = API::Map()->get([
			'sysmapids' => $sysmapids,
			'preservekeys' => true,
			'output' => ['sysmapid', 'severity_min'],
			'selectLinks' => ['linktriggers', 'permission'],
			'selectSelements' => ['elements', 'elementtype', 'permission']
		]);

		if ($sysmaps) {
			$triggers_per_hosts = [];
			$triggers_per_host_groups = [];
			$problems_per_trigger = [];
			$submaps_relations = [];
			$submaps_found = [];
			$host_groups = [];
			$hosts = [];
			$all_triggers = [];

			// Gather submaps from all selected maps.
			foreach ($sysmaps as $map) {
				foreach ($map['selements'] as $selement) {
					if ($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_MAP) {
						if (($element = reset($selement['elements'])) !== false) {
							$submaps_relations[$map['sysmapid']][] = $element['sysmapid'];
							$submaps_found[] = $element['sysmapid'];
						}
					}
				}
			}

			// Gather maps added as submaps for each of map in any depth.
			$sysmaps_resolved = array_keys($sysmaps);
			while ($diff = array_diff($submaps_found, $sysmaps_resolved)) {
				$submaps = API::Map()->get([
					'sysmapids' => $diff,
					'preservekeys' => true,
					'output' => ['sysmapid', 'severity_min'],
					'selectLinks' => ['linktriggers', 'permission'],
					'selectSelements' => ['elements', 'elementtype', 'permission']
				]);

				$sysmaps_resolved = array_merge($sysmaps_resolved, $diff);

				foreach ($submaps as $submap) {
					$sysmaps[$submap['sysmapid']] = $submap;

					foreach ($submap['selements'] as $selement) {
						if ($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_MAP) {
							$element = reset($selement['elements']);
							if ($element) {
								$submaps_relations[$submap['sysmapid']][] = $element['sysmapid'];
								$submaps_found[] = $element['sysmapid'];
							}
						}
					}
				}
			}

			// Gather elements from all maps selected.
			foreach ($sysmaps as &$sysmap) {
				$sysmap['submaps'] = [];

				// Collect triggers from map links.
				foreach ($sysmap['links'] as $link) {
					foreach ($link['linktriggers'] as $linktrigger) {
						$problems_per_trigger[$linktrigger['triggerid']] = $this->problems_per_severity_tpl;
						$all_triggers[$linktrigger['triggerid']] = false;
					}
				}

				// Collect map elements.
				foreach ($sysmap['selements'] as $selement) {
					switch ($selement['elementtype']) {
						case SYSMAP_ELEMENT_TYPE_MAP:
							// Recursively find all submaps in any depth and put them into $sysmaps[][submaps] array.
							$sysmap['submaps'][$selement['elements'][0]['sysmapid']] = false;

							while (array_filter($sysmap['submaps'], function($item) {return !$item;})) {
								foreach ($sysmap['submaps'] as $linked_map => $val) {
									if (!$val) {
										$sysmap['submaps'][$linked_map] = true;

										if (array_key_exists($linked_map, $submaps_relations)) {
											foreach ($submaps_relations[$linked_map] as $submap) {
												if (!array_key_exists($submap, $sysmap['submaps'])) {
													$sysmap['submaps'][$submap] = false;
												}
											}
										}
									}
								}
							}
							break;

						case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
							if (($element = reset($selement['elements'])) !== false) {
								$host_groups[$element['groupid']] = true;
							}
							break;

						case SYSMAP_ELEMENT_TYPE_TRIGGER:
							foreach (zbx_objectValues($selement['elements'], 'triggerid') as $triggerid) {
								$problems_per_trigger[$triggerid] = $this->problems_per_severity_tpl;
								$all_triggers[$triggerid] = false;
							}
							break;

						case SYSMAP_ELEMENT_TYPE_HOST:
							if (($element = reset($selement['elements'])) !== false) {
								$hosts[$element['hostid']] = true;
							}
							break;
					}
				}
			}
			unset($sysmap);

			// Select lowest severity to reduce amount of data returned by API.
			$severity_min = min(zbx_objectValues($sysmaps, 'severity_min'));

			// Get triggers related to host groups.
			if ($host_groups) {
				$triggers = API::Trigger()->get([
					'output' => ['triggerid'],
					'groupids' => array_keys($host_groups),
					'min_severity' => $severity_min,
					'skipDependent' => true,
					'selectGroups' => ['groupid'],
					'preservekeys' => true
				]);

				foreach ($triggers as $trigger) {
					foreach ($trigger['groups'] as $host_group) {
						$triggers_per_host_groups[$host_group['groupid']][$trigger['triggerid']] = true;
					}
					$problems_per_trigger[$trigger['triggerid']] = $this->problems_per_severity_tpl;
					$all_triggers[$trigger['triggerid']] = false;
				}

				unset($host_groups);
			}

			// Get triggers related to hosts.
			if ($hosts) {
				$triggers = API::Trigger()->get([
					'output' => ['triggerid'],
					'selectHosts' => ['hostid'],
					'hostids' => array_keys($hosts),
					'min_severity' => $severity_min,
					'skipDependent' => true,
					'preservekeys' => true
				]);

				foreach ($triggers as $trigger) {
					if (($host = reset($trigger['hosts'])) !== false) {
						$triggers_per_hosts[$host['hostid']][$trigger['triggerid']] = true;
						$problems_per_trigger[$trigger['triggerid']] = $this->problems_per_severity_tpl;
						$all_triggers[$trigger['triggerid']] = false;
					}
				}

				unset($hosts);
			}

			// Count problems per trigger.
			if ($problems_per_trigger) {
				$triggers = API::Trigger()->get([
					'output' => ['triggerid', 'priority'],
					'triggerids' => array_keys($problems_per_trigger),
					'min_severity' => $severity_min,
					'skipDependent' => true,
					'selectGroups' => ['groupid'],
					'preservekeys' => true
				]);

				$problems = API::Problem()->get([
					'output' => ['objectid'],
					'source' => EVENT_SOURCE_TRIGGERS,
					'object' => EVENT_OBJECT_TRIGGER,
					'objectids' => zbx_objectValues($triggers, 'triggerid'),
					'severities' => range($severity_min, TRIGGER_SEVERITY_COUNT - 1),
					'preservekeys' => true
				]);

				if ($problems) {
					foreach ($problems as $problem) {
						$trigger = $triggers[$problem['objectid']];
						$problems_per_trigger[$problem['objectid']][$trigger['priority']]++;
						$all_triggers[$problem['objectid']] = false;
					}
				}
			}

			// Count problems in each submap included in navigation tree:
			foreach ($navtree_items as $itemid => $item_details) {
				$maps_need_to_count_in = $item_details['children_mapids'];
				if ($item_details['mapid']) {
					$maps_need_to_count_in[] = $item_details['mapid'];
				}

				$maps_need_to_count_in = array_keys(array_flip($maps_need_to_count_in));

				$response[$itemid] = $this->problems_per_severity_tpl;
				$problems_counted = $all_triggers;

				foreach ($maps_need_to_count_in as $mapid) {
					if (array_key_exists($mapid, $sysmaps)) {
						$map = $sysmaps[$mapid];

						// Count problems occurred in linked elements.
						foreach ($map['selements'] as $selement) {
							if ($selement['permission'] >= PERM_READ) {
								$problems = $this->getElementProblems($selement, $problems_per_trigger, $sysmaps,
									$problems_counted, $triggers_per_hosts, $triggers_per_host_groups
								);

								// Sum problems.
								foreach ($problems as $sev => $probl) {
									if ($probl != 0 && $sev >= $map['severity_min']) {
										$response[$itemid][$sev] += $probl;
									}
								}
							}
						}

						// Count problems occurred in triggers which are related to links.
						foreach ($map['links'] as $link) {
							foreach ($link['linktriggers'] as $lt) {
								if (!$problems_counted[$lt['triggerid']]) {
									$problems_to_add = $problems_per_trigger[$lt['triggerid']];
									$problems_counted[$lt['triggerid']] = true;

									// Sum problems.
									foreach ($problems_to_add as $sev => $probl) {
										if ($probl != 0 && $sev >= $map['severity_min']) {
											$response[$itemid][$sev] += $probl;
										}
									}
								}
							}
						}
					}
				}
			}
		}

		foreach ($response as &$row) {
			// Reduce the amount of data transferred over Ajax.
			if ($row === $this->problems_per_severity_tpl) {
				$row = 0;
			}
		}
		unset($row);

		return $response;
	}

	protected function getElementProblems(array $selement, array $problems_per_trigger, array $sysmaps,
			array &$problems_counted = [], array $triggers_per_hosts = [], array $triggers_per_host_groups = []) {
		$problems = $this->problems_per_severity_tpl;

		switch ($selement['elementtype']) {
			case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
				if (($element = reset($selement['elements'])) !== false) {
					if (array_key_exists($element['groupid'], $triggers_per_host_groups)) {
						foreach ($triggers_per_host_groups[$element['groupid']] as $triggerid => $val) {
							if (!$problems_counted[$triggerid]) {
								$problems_counted[$triggerid] = true;

								foreach ($problems_per_trigger[$triggerid] as $sev => $probl) {
									if ($probl != 0) {
										$problems[$sev] += $probl;
									}
								}
							}
						}
					}
				}
				break;

			case SYSMAP_ELEMENT_TYPE_TRIGGER:
				foreach (zbx_objectValues($selement['elements'], 'triggerid') as $triggerid) {
					if (!$problems_counted[$triggerid]) {
						$problems_counted[$triggerid] = true;

						foreach ($problems_per_trigger[$triggerid] as $sev => $probl) {
							if ($probl != 0) {
								$problems[$sev] += $probl;
							}
						}
					}
				}
				break;

			case SYSMAP_ELEMENT_TYPE_HOST:
				if (($element = reset($selement['elements'])) !== false) {
					if (array_key_exists($element['hostid'], $triggers_per_hosts)) {
						foreach ($triggers_per_hosts[$element['hostid']] as $triggerid => $val) {
							if (!$problems_counted[$triggerid]) {
								$problems_counted[$triggerid] = true;

								foreach ($problems_per_trigger[$triggerid] as $sev => $probl) {
									if ($probl != 0) {
										$problems[$sev] += $probl;
									}
								}
							}
						}
					}
				}
				break;

			case SYSMAP_ELEMENT_TYPE_MAP:
				$maps_to_process = $sysmaps[$selement['elements'][0]['sysmapid']]['submaps'];
				$maps_to_process[$selement['elements'][0]['sysmapid']] = true;

				// Count problems in each of selected submap.
				foreach ($maps_to_process as $sysmapid => $val) {
					// Count problems in elements assigned to selements.
					foreach ($sysmaps[$sysmapid]['selements'] as $submap_selement) {
						if ($submap_selement['permission'] >= PERM_READ) {
							$problems_in_submap = $this->getElementProblems($submap_selement,
								$problems_per_trigger, $sysmaps, $problems_counted, $triggers_per_hosts,
								$triggers_per_host_groups
							);

							foreach ($problems_in_submap as $sev => $probl) {
								if ($probl != 0) {
									$problems[$sev] += $probl;
								}
							}
						}
					}

					// Count problems in triggers assigned to linked.
					foreach ($sysmaps[$sysmapid]['links'] as $link) {
						if ($link['permission'] >= PERM_READ) {
							foreach ($link['linktriggers'] as $lt) {
								if (!$problems_counted[$lt['triggerid']]) {
									$problems_counted[$lt['triggerid']] = true;

									// Sum problems.
									foreach ($problems_per_trigger[$lt['triggerid']] as $sev => $probl) {
										if ($probl != 0) {
											$problems[$sev] += $probl;
										}
									}
								}
							}
						}
					}
				}
				break;
		}

		return $problems;
	}

	protected function doAction() {
		$fields = $this->getForm()->getFieldsData();
		$error = null;

		// Get list of sysmapids.
		$navtree_items = [];
		foreach ($fields as $field_key => $field_value) {
			if (is_numeric($field_value)) {
				preg_match('/^map\.parent\.(\d+)$/', $field_key, $field_details);
				if ($field_details) {
					$fieldid = $field_details[1];
					$navtree_items[$fieldid] = [
						'parent' => $field_value,
						'mapid' => array_key_exists('mapid.'.$fieldid, $fields) ? $fields['mapid.'.$fieldid] : 0,
						'children_mapids' => []
					];
				}
			}
		}

		// Find and fix circular dependencies.
		foreach ($navtree_items as $fieldid => $field_details) {
			if ($field_details['parent'] != 0) {
				$parent = $navtree_items[$field_details['parent']];
				while ($parent['parent'] != 0) {
					if ($parent['parent'] == $fieldid) {
						$navtree_items[$fieldid]['parent'] = 0;
						break;
					}
					elseif (array_key_exists($parent['parent'], $navtree_items)) {
						$parent = $navtree_items[$parent['parent']];
					}
					else {
						break;
					}
				}
			}
		}

		// Propagate item mapids to all its parent items.
		foreach ($navtree_items as $field_details) {
			$parentid = $field_details['parent'];
			if ($field_details['parent'] != 0 && array_key_exists($parentid, $navtree_items)) {
				while (array_key_exists($parentid, $navtree_items)) {
					if ($field_details['mapid'] != 0) {
						$navtree_items[$parentid]['children_mapids'][] = $field_details['mapid'];
					}
					$parentid = $navtree_items[$parentid]['parent'];
				}
			}
		}

		// Get severity levels and colors and select list of sysmapids to count problems per maps.
		$this->problems_per_severity_tpl = [];
		$config = select_config();
		$severity_config = [];

		$sysmapids = array_keys(array_flip(zbx_objectValues($navtree_items, 'mapid')));
		$maps_accessible = API::Map()->get([
			'output' => ['sysmapid'],
			'sysmapids' => $sysmapids,
			'preservekeys' => true
		]);

		$maps_accessible = array_keys($maps_accessible);

		foreach (range(TRIGGER_SEVERITY_NOT_CLASSIFIED, TRIGGER_SEVERITY_COUNT - 1) as $severity) {
			$this->problems_per_severity_tpl[$severity] = 0;
			$severity_config[$severity] = [
				'color' => $config['severity_color_'.$severity],
				'name' => $config['severity_name_'.$severity],
			];
		}

		$widgetid = $this->getInput('widgetid', 0);
		$navtree_item_selected = 0;
		$navtree_items_opened = [];
		if ($widgetid) {
			$navtree_items_opened = CProfile::findByIdxPattern('web.dashbrd.navtree-%.toggle', $widgetid);
			// Keep only numerical value from idx key name.
			foreach ($navtree_items_opened as &$item_opened) {
				$item_opened = substr($item_opened, 20, -7);
			}
			unset($item_opened);
			$navtree_item_selected = CProfile::get('web.dashbrd.navtree.item.selected', 0, $widgetid);
		}

		$this->setResponse(new CControllerResponseData([
			'name' => $this->getInput('name', $this->getDefaultHeader()),
			'uniqueid' => $this->getInput('uniqueid'),
			'navtree_item_selected' => $navtree_item_selected,
			'navtree_items_opened' => $navtree_items_opened,
			'problems' => $this->getNumberOfProblemsBySysmap($navtree_items),
			'show_unavailable' => array_key_exists('show_unavailable', $fields) ? $fields['show_unavailable'] : 0,
			'maps_accessible' => $maps_accessible,
			'severity_config' => $severity_config,
			'initial_load' => $this->getInput('initial_load', 0),
			'error' => $error,
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		]));
	}
}
