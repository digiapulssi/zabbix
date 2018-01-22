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
		/**
		 * In this function two types of submaps are possible:
		 *  - First, each navigation tree item could have sub-items with it's own maps linked to it. This type of
		 *	  submaps are stored as an array in $navtree_items[][children_mapids];
		 *  - Each map that is linked to navigation tree item could have its own sub-maps (like for any map in Zabbix).
		 *	  This type of submaps are stored in $map_submaps.
		 */
		$response = [];
		$map_submaps = [];
		$sysmapids = [];

		// Collect all sysmap IDs that are added as map navigation tree child items in any depth.
		foreach ($navtree_items as $navtree_item) {
			$sysmapids[$navtree_item['mapid']] = true;
			foreach ($navtree_item['children_mapids'] as $submapid) {
				$sysmapids[$submapid] = true;
			}
		}

		// Collect all sysmap IDs that are added as submaps to $navtree_items maps in any depth.
		$navtree_item_sysmaps = API::Map()->get([
			'output' => [],
			'sysmapids' => array_keys($sysmapids),
			'selectSelements' => ['elements', 'elementtype', 'permission'],
			'preservekeys' => true,
		]);

		$submaps_found = [];
		foreach ($navtree_item_sysmaps as $map) {
			foreach ($map['selements'] as $selement) {
				if ($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_MAP && $selement['permission'] >= PERM_READ) {
					$map_submaps[$map['sysmapid']][] = $selement['elements'][0]['sysmapid'];
					$sysmapids[$selement['elements'][0]['sysmapid']] = true;
					$submaps_found[] = $selement['elements'][0]['sysmapid'];
				}
			}
		}

		$sysmaps_resolved = array_keys($navtree_item_sysmaps);
		while ($diff = array_diff($submaps_found, $sysmaps_resolved)) {
			$diff_submaps = API::Map()->get([
				'output' => [],
				'sysmapids' => $diff,
				'selectSelements' => ['elements', 'elementtype', 'permission'],
				'preservekeys' => true
			]);

			$sysmaps_resolved = array_merge($sysmaps_resolved, $diff);

			foreach ($diff_submaps as $submap) {
				$sysmaps[$submap['sysmapid']] = $submap;

				foreach ($submap['selements'] as $selement) {
					if ($selement['elementtype'] == SYSMAP_ELEMENT_TYPE_MAP && $selement['permission'] >= PERM_READ) {
						$map_submaps[$map['sysmapid']][] = $selement['elements'][0]['sysmapid'];
						$sysmapids[$selement['elements'][0]['sysmapid']] = true;
						$submaps_found[] = $selement['elements'][0]['sysmapid'];
					}
				}
			}
		}

		// Select all sysmaps that are linked to any navigation tree item in any depth.
		$sysmaps = API::Map()->get([
			'sysmapids' => array_keys($sysmapids),
			'preservekeys' => true,
			'output' => ['sysmapid', 'severity_min', 'show_unack'],
			'selectLinks' => ['linktriggers', 'permission'],
			'selectSelements' => ['elements', 'elementtype', 'permission']
		]);

		if ($sysmaps) {
			$triggers_per_hosts = [];
			$triggers_per_host_groups = [];

			/**
			 * $problems_per_trigger holds a list of triggers and severity of which problem caused by particular trigger
			 * is detected. If problem is not detected, triggerid is still appended to array, but -1 is used for
			 * severity.
			 */
			$problems_per_trigger = [];
			$host_groups = [];
			$hosts = [];

			// Gather elements from all maps selected.
			foreach ($sysmaps as &$sysmap) {
				// Collect triggers from map links.
				foreach ($sysmap['links'] as $link) {
					foreach ($link['linktriggers'] as $linktrigger) {
						$problems_per_trigger[$linktrigger['triggerid']] = -1;
					}
				}

				// Collect map elements.
				foreach ($sysmap['selements'] as $selement) {
					switch ($selement['elementtype']) {
						case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
							if (($element = reset($selement['elements'])) !== false) {
								$host_groups[$element['groupid']] = true;
							}
							break;

						case SYSMAP_ELEMENT_TYPE_TRIGGER:
							foreach (zbx_objectValues($selement['elements'], 'triggerid') as $triggerid) {
								$problems_per_trigger[$triggerid] = -1;
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
					$problems_per_trigger[$trigger['triggerid']] = -1;
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
						$problems_per_trigger[$trigger['triggerid']] = -1;
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
					'selectAcknowledges' => API_OUTPUT_COUNT,
					'preservekeys' => true
				]);

				if ($problems) {
					foreach ($problems as $problem) {
						$trigger = $triggers[$problem['objectid']];
						$problems_per_trigger[$problem['objectid']] = [
							'unack' => !$problem['acknowledges'],
							'sev' => $trigger['priority']
						];
					}
				}
			}

			// Count problems related to each item in navigation tree:
			foreach ($navtree_items as $itemid => $item_details) {
				/**
				 * Aggregated problem number should contain following problems:
				 *  - map's own problems;
				 *  - problems from map submaps in any depth;
				 *  - problems from map that is linked to navigation tree item or their submaps in any depth.
				 */
				$maps_need_to_count_in = [];

				// Add linked map and its submaps.
				if ($item_details['mapid']) {
					if (array_key_exists($item_details['mapid'], $sysmaps)) {
						// Use map's original min severity.
						$maps_need_to_count_in[$item_details['mapid']]
							= $sysmaps[$item_details['mapid']]['severity_min'];
					}
					$maps_to_resolve = [$item_details['mapid']];
					$maps_resolved = [];

					while ($diff = array_diff($maps_to_resolve, $maps_resolved)) {
						foreach ($diff as $mapid) {
							$maps_resolved[] = $mapid;

							if (array_key_exists($mapid, $map_submaps)) {
								foreach ($map_submaps[$mapid] as $submapid) {
									if (array_key_exists($submapid, $sysmaps)) {
										// Use highest severity.
										$maps_need_to_count_in[$submapid] = max([
											$sysmaps[$item_details['mapid']]['severity_min'],
											$sysmaps[$submapid]['severity_min']
										]);
									}
									$maps_to_resolve[] = $submapid;
								}
							}
						}
					}
				}

				// Add map that are linked to navtree child items. Submaps are expected to be already included.
				foreach ($item_details['children_mapids'] as $mapid) {
					if (array_key_exists($mapid, $sysmaps)) {
						// Use each map's min severity.
						$maps_need_to_count_in[$mapid] = $sysmaps[$mapid]['severity_min'];
					}
				}

				$response[$itemid] = $this->problems_per_severity_tpl;

				/**
				 * $problems_per_trigger_clone is a clone of $problems_per_trigger which holds severity of detected
				 * problems. Since each problem in each navtree item must be counted only once, number of problems in
				 * $problems_per_trigger_clone are reset to -1 to control that same problem is not counted multiple
				 * times just because same trigger is reused.
				 */
				$problems_per_trigger_clone = $problems_per_trigger;

				foreach ($maps_need_to_count_in as $mapid => $severity_min) {
					if (array_key_exists($mapid, $sysmaps)) {
						// Count problems occurred in linked elements.
						foreach ($sysmaps[$mapid]['selements'] as $sel) {
							// If no permission to see element related info, jump to next element.
							if ($sel['permission'] < PERM_READ) {
								continue;
							}

							switch ($sel['elementtype']) {
								case SYSMAP_ELEMENT_TYPE_HOST_GROUP:
									$groupid = $sel['elements'][0]['groupid'];
									if (array_key_exists($groupid, $triggers_per_host_groups)) {
										foreach ($triggers_per_host_groups[$groupid] as $triggerid => $val) {
											if ($problems_per_trigger_clone[$triggerid]['sev'] >= $severity_min
													&& ($sysmaps[$mapid]['show_unack'] != EXTACK_OPTION_UNACK
														|| $problems_per_trigger_clone[$triggerid]['unack']
													)) {
												$response[$itemid][$problems_per_trigger_clone[$triggerid]['sev']]++;
												$problems_per_trigger_clone[$triggerid]['sev'] = -1;
											}
										}
									}
									break;

								case SYSMAP_ELEMENT_TYPE_TRIGGER:
									foreach (zbx_objectValues($sel['elements'], 'triggerid') as $triggerid) {
										if ($problems_per_trigger_clone[$triggerid]['sev'] >= $severity_min
												&& ($sysmaps[$mapid]['show_unack'] != EXTACK_OPTION_UNACK
													|| $problems_per_trigger_clone[$triggerid]['unack']
												)) {
											$response[$itemid][$problems_per_trigger_clone[$triggerid]['sev']]++;
											$problems_per_trigger_clone[$triggerid]['sev'] = -1;
										}
									}
									break;

								case SYSMAP_ELEMENT_TYPE_HOST:
									if (($element = reset($sel['elements'])) !== false) {
										if (array_key_exists($element['hostid'], $triggers_per_hosts)) {
											foreach ($triggers_per_hosts[$element['hostid']] as $triggerid => $val) {
												if ($problems_per_trigger_clone[$triggerid]['sev'] >= $severity_min
														&& ($sysmaps[$mapid]['show_unack'] != EXTACK_OPTION_UNACK
															|| $problems_per_trigger_clone[$triggerid]['unack']
														)) {
													$response[$itemid][$problems_per_trigger_clone[$triggerid]['sev']]++;
													$problems_per_trigger_clone[$triggerid]['sev'] = -1;
												}
											}
										}
									}
									break;
							}
						}

						// Count problems occurred in triggers which are related to links.
						foreach ($sysmaps[$mapid]['links'] as $link) {
							foreach ($link['linktriggers'] as $lt) {
								if ($problems_per_trigger_clone[$lt['triggerid']]['sev'] >= $severity_min
										&& ($sysmaps[$mapid]['show_unack'] != EXTACK_OPTION_UNACK
											|| $problems_per_trigger_clone[$triggerid]['unack']
										)) {
									$response[$itemid][$problems_per_trigger_clone[$lt['triggerid']]['sev']]++;
									$problems_per_trigger_clone[$lt['triggerid']]['sev'] = -1;
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
			'output' => [],
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
