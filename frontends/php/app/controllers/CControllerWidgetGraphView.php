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


class CControllerWidgetGraphView extends CControllerWidget {

	public function __construct() {
		parent::__construct();

		$this->setType(WIDGET_GRAPH);
		$this->setValidationRules([
			'name' =>				'string',
			'uniqueid' =>			'required|string',
			'initial_load' =>		'in 0,1',
			'edit_mode' =>			'in 0,1',
			'dashboardid' =>		'db dashboard.dashboardid',
			'fields' =>				'json',
			'dynamic_hostid' =>		'db hosts.hostid',
			'content_width' =>		'int32',
			'content_height' =>		'int32',
			'only_footer' =>		'in 1'
		]);
	}

	protected function doAction() {
		if ($this->getInput('only_footer', 0)) {
			$this->setResponse(new CControllerResponseData([
				'only_footer' => true,
				'user' => [
					'debug_mode' => $this->getDebugMode()
				]
			]));
			return;
		}

		$fields = $this->getForm()->getFieldsData();

		$uniqueid = $this->getInput('uniqueid');
		$edit_mode = (int) $this->getInput('edit_mode', 0);

		$width = $this->getInput('content_width', '100');
		$height = $this->getInput('content_height', '100');

		$dataid = 'graph_'.$uniqueid;
		$containerid = 'graph_container_'.$uniqueid;
		$dynamic_hostid = $this->getInput('dynamic_hostid', 0);
		$dashboardid = $this->getInput('dashboardid', 0);
		$resourceid = null;
		$profileIdx = 'web.dashbrd';
		$profileIdx2 = $dashboardid;
		$update_profile = $dashboardid ? UPDATE_PROFILE_ON : UPDATE_PROFILE_OFF;
		$critical_error = null;

		if ($fields['source_type'] == ZBX_WIDGET_FIELD_RESOURCE_GRAPH && $fields['graphid']) {
			$resource_type = SCREEN_RESOURCE_GRAPH;
			$resourceid = $fields['graphid'];
			$graph_dims = getGraphDims($resourceid);
			$graph_dims['graphHeight'] = $height;
			$graph_dims['width'] = $width;
		}
		elseif ($fields['source_type'] == ZBX_WIDGET_FIELD_RESOURCE_SIMPLE_GRAPH && $fields['itemid']) {
			$resource_type = SCREEN_RESOURCE_SIMPLE_GRAPH;
			$resourceid = $fields['itemid'];
			$graph_dims = getGraphDims();
			$graph_dims['graphHeight'] = $height;
			$graph_dims['width'] = $width;
		}
		else {
			$resource_type = null;
			$graph_dims = getGraphDims();
		}

		// Prepare timeline details
		$timeline = calculateTime([
			'profileIdx' => $profileIdx,
			'profileIdx2' => $profileIdx2,
			'updateProfile' => $update_profile,
			'period' => null,
			'stime' => null
		]);

		$time_control_data = [
			'id' => $dataid,
			'containerid' => $containerid,
			'objDims' => $graph_dims,
			'loadSBox' => 0,
			'loadImage' => 1,
			'periodFixed' => CProfile::get($profileIdx.'.timelinefixed', 1),
			'sliderMaximumTimePeriod' => ZBX_MAX_PERIOD,
			'reloadOnAdd' => 1
		];

		// data for flickerscreen
		$fs_data = [
			'id' => $dataid,
			'interval' => CWebUser::getRefresh(),
			'timeline' => $timeline,
			'resourcetype' => $resource_type,
			'profileIdx' => $profileIdx,
			'profileIdx2' => $profileIdx2,
			'updateProfile' => $update_profile
		];

		// Replace graph item by particular host item if dynamic items are used.
		if ($fields['dynamic'] == WIDGET_DYNAMIC_ITEM && $dynamic_hostid && $resourceid) {
			// Find same simple-graph item in selected $dynamic_hostid host.
			if ($fields['source_type'] == ZBX_WIDGET_FIELD_RESOURCE_SIMPLE_GRAPH) {
				$new_itemid = get_same_item_for_host($resourceid, $dynamic_hostid);
				$resourceid = !empty($new_itemid) ? $new_itemid : null;

				if ($resourceid === null) {
					$critical_error = _('No permissions to referred object or it does not exist!');
				}
			}
			// Find requested host and change graph details.
			elseif ($fields['source_type'] == ZBX_WIDGET_FIELD_RESOURCE_GRAPH) {
				// get host
				$hosts = API::Host()->get([
					'hostids' => $dynamic_hostid,
					'output' => ['hostid', 'name']
				]);
				$host = reset($hosts);

				// get graph
				$graph = API::Graph()->get([
					'graphids' => $resourceid,
					'output' => API_OUTPUT_EXTEND,
					'selectHosts' => ['hostid'],
					'selectGraphItems' => API_OUTPUT_EXTEND
				]);
				$graph = reset($graph);

				// if all items are from one host we change them, or set calculated if not exist on that host
				if ($graph && count($graph['hosts']) == 1) {
					if ($graph['ymax_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE && $graph['ymax_itemid']) {
						$new_dynamic = getSameGraphItemsForHost(
							[['itemid' => $graph['ymax_itemid']]],
							$dynamic_hostid,
							false
						);
						$new_dynamic = reset($new_dynamic);

						if ($new_dynamic && array_key_exists('itemid', $new_dynamic) && $new_dynamic['itemid'] > 0) {
							$graph['ymax_itemid'] = $new_dynamic['itemid'];
						}
						else {
							$graph['ymax_type'] = GRAPH_YAXIS_TYPE_CALCULATED;
						}
					}

					if ($graph['ymin_type'] == GRAPH_YAXIS_TYPE_ITEM_VALUE && $graph['ymin_itemid']) {
						$new_dynamic = getSameGraphItemsForHost(
							[['itemid' => $graph['ymin_itemid']]],
							$dynamic_hostid,
							false
						);
						$new_dynamic = reset($new_dynamic);

						if ($new_dynamic && array_key_exists('itemid', $new_dynamic) && $new_dynamic['itemid'] > 0) {
							$graph['ymin_itemid'] = $new_dynamic['itemid'];
						}
						else {
							$graph['ymin_type'] = GRAPH_YAXIS_TYPE_CALCULATED;
						}
					}
				}

				if ($graph) {
					// Search if there are any items available for this dynamic host
					$new_dynamic = getSameGraphItemsForHost(
						$graph['gitems'],
						$dynamic_hostid,
						false
					);
					if (!$new_dynamic) {
						$critical_error = _('No permissions to referred object or it does not exist!');
					}
				}
				else {
					$critical_error = _('No permissions to referred object or it does not exist!');
				}
			}
		}
		else {
			if (!$resourceid) {
				$critical_error = _('No permissions to referred object or it does not exist!');
			}
			elseif ($fields['source_type'] == ZBX_WIDGET_FIELD_RESOURCE_SIMPLE_GRAPH) {
				$item = API::Item()->get([
					'itemids' => $resourceid,
					'output' => null
				]);
				$item = reset($item);
				if (!$item) {
					$critical_error = _('No permissions to referred object or it does not exist!');
				}
			}
			elseif ($fields['source_type'] == ZBX_WIDGET_FIELD_RESOURCE_GRAPH) {
				// get graph, used below
				$graph = API::Graph()->get([
					'graphids' => $resourceid,
					'output' => API_OUTPUT_EXTEND
				]);
				$graph = reset($graph);

				if (!$graph) {
					$critical_error = _('No permissions to referred object or it does not exist!');
				}
			}
		}

		if (!$critical_error) {
			// Build graph action and data source links.
			if ($fields['source_type'] == ZBX_WIDGET_FIELD_RESOURCE_SIMPLE_GRAPH) {
				if (!$edit_mode) {
					$time_control_data['loadSBox'] = 1;
				}

				if ($resourceid) {
					$graph_src = new CUrl('chart.php');
					$graph_src->setArgument('itemids[]', $resourceid);
					$graph_src->setArgument('width', $width);
					$graph_src->setArgument('height', $height);
				}
				else {
					$graph_src = new CUrl('chart3.php');
				}

				$graph_src->setArgument('period', $timeline['period']);
				$graph_src->setArgument('stime', $timeline['stime']);
			}
			elseif ($fields['source_type'] == ZBX_WIDGET_FIELD_RESOURCE_GRAPH) {
				$graph_src = '';

				if ($fields['dynamic'] == WIDGET_DYNAMIC_ITEM && $dynamic_hostid && $resourceid) {
					// TODO miks: why chart7 and chart3 are allowed only if dynamic is set?
					$chart_file = ($graph['graphtype'] == GRAPH_TYPE_PIE || $graph['graphtype'] == GRAPH_TYPE_EXPLODED)
						? 'chart7.php'
						: 'chart3.php';

					$graph_src = new CUrl($chart_file);

					foreach ($graph as $name => $value) {
						if ($name === 'width' || $name === 'height') {
							continue;
						}
						$graph_src->setArgument($name, $value);
					}

					$new_graph_items = getSameGraphItemsForHost($graph['gitems'], $dynamic_hostid, false);
					foreach ($new_graph_items as $new_graph_item) {
						unset($new_graph_item['gitemid'], $new_graph_item['graphid']);

						foreach ($new_graph_item as $name => $value) {
							$graph_src->setArgument('items['.$new_graph_item['itemid'].']['.$name.']', $value);
						}
					}

					$graph_src->setArgument('name', $host['name'].NAME_DELIMITER.$graph['name']);
				}

				if ($graph_dims['graphtype'] == GRAPH_TYPE_PIE || $graph_dims['graphtype'] == GRAPH_TYPE_EXPLODED) {
					if ($fields['dynamic'] == WIDGET_SIMPLE_ITEM || $graph_src === '') {
						$graph_src = new CUrl('chart6.php');
						$graph_src->setArgument('graphid', $resourceid);
					}

					$timeline['starttime'] = date(TIMESTAMP_FORMAT, get_min_itemclock_by_graphid($resourceid));
				}
				else {
					if ($fields['dynamic'] == WIDGET_SIMPLE_ITEM || $graph_src === '') {
						$graph_src = new CUrl('chart2.php');
						$graph_src->setArgument('graphid', $resourceid);
					}

					if (!$edit_mode) {
						$time_control_data['loadSBox'] = 1;
					}
				}

				$graph_src->setArgument('width', $width);
				$graph_src->setArgument('height', $height);
				$graph_src->setArgument('legend', $graph['show_legend']);
				$graph_src->setArgument('period', $timeline['period']);
				$graph_src->setArgument('stime', $timeline['stime']);

				if ($graph_dims['graphtype'] == GRAPH_TYPE_PIE || $graph_dims['graphtype'] == GRAPH_TYPE_EXPLODED) {
					$graph_src->setArgument('graph3d', $graph['show_3d']);
				}
			}

			$graph_src->setArgument('updateProfile', $update_profile);
			$graph_src->setArgument('profileIdx', $profileIdx);
			$graph_src->setArgument('profileIdx2', $profileIdx2);

			if ($graph_dims['graphtype'] != GRAPH_TYPE_PIE && $graph_dims['graphtype'] != GRAPH_TYPE_EXPLODED) {
				$graph_src->setArgument('outer', '1');
			}

			$time_control_data['src'] = $graph_src->getUrl();
		}

		$this->setResponse(new CControllerResponseData([
			'name' => $this->getInput('name', $this->getDefaultHeader()),
			'graph' => [
				'dataid' => $dataid,
				'containerid' => $containerid,
				'timestamp' => time(),
				'critical_error' => $critical_error
			],
			'widget' => [
				'uniqueid' => $uniqueid,
				'initial_load' => (int) $this->getInput('initial_load', 0),
			],
			'time_control_data' => $time_control_data,
			'timeline' => $timeline,
			'fs_data' => $fs_data,
			'dashboardid' => $dashboardid,
			'only_footer' => false,
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		]));
	}
}
