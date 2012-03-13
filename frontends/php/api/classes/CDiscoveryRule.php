<?php
/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/
?>
<?php
/**
 * @package API
 */
class CDiscoveryRule extends CItemGeneral {

	const MIN_LIFETIME = 0;
	const MAX_LIFETIME = 3650;

	protected $tableName = 'items';
	protected $tableAlias = 'i';

	public function __construct() {
		parent::__construct();
	}

	/**
	 * Get DiscoveryRule data
	 */
	public function get($options = array()) {
		$result = array();
		$userType = self::$userData['type'];
		$userid = self::$userData['userid'];

		// allowed columns for sorting
		$sortColumns = array('itemid', 'name', 'key_', 'delay', 'type', 'status');

		// allowed output options for [ select_* ] params
		$subselectsAllowedOutputs = array(API_OUTPUT_REFER, API_OUTPUT_EXTEND, API_OUTPUT_CUSTOM);

		$sqlParts = array(
			'select'	=> array('items' => 'i.itemid'),
			'from'		=> array('items' => 'items i'),
			'where'		=> array('i.flags='.ZBX_FLAG_DISCOVERY),
			'group'		=> array(),
			'order'		=> array(),
			'limit'		=> null
		);

		$defOptions = array(
			'nodeids'					=> null,
			'groupids'					=> null,
			'templateids'				=> null,
			'hostids'					=> null,
			'itemids'					=> null,
			'inherited'					=> null,
			'templated'					=> null,
			'monitored'					=> null,
			'editable'					=> null,
			'nopermissions'				=> null,
			// filter
			'filter'					=> null,
			'search'					=> null,
			'searchByAny'				=> null,
			'startSearch'				=> null,
			'excludeSearch'				=> null,
			'searchWildcardsEnabled'	=> null,
			// output
			'output'					=> API_OUTPUT_REFER,
			'selectHosts'				=> null,
			'selectTriggers'			=> null,
			'selectGraphs'				=> null,
			'selectPrototypes'			=> null,
			'countOutput'				=> null,
			'groupCount'				=> null,
			'preservekeys'				=> null,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null,
			'limitSelects'				=> null
		);
		$options = zbx_array_merge($defOptions, $options);

		if (is_array($options['output'])) {
			unset($sqlParts['select']['items']);

			$dbTable = DB::getSchema('items');
			$sqlParts['select']['itemid'] = 'i.itemid';
			foreach ($options['output'] as $field) {
				if (isset($dbTable['fields'][$field])) {
					$sqlParts['select'][$field] = 'i.'.$field;
				}
			}
			$options['output'] = API_OUTPUT_CUSTOM;
		}

		// editable + PERMISSION CHECK
		if (USER_TYPE_SUPER_ADMIN == $userType || $options['nopermissions']) {
		}
		else {
			$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ_ONLY;

			$sqlParts['from']['hosts_groups'] = 'hosts_groups hg';
			$sqlParts['from']['rights'] = 'rights r';
			$sqlParts['from']['users_groups'] = 'users_groups ug';
			$sqlParts['where'][] = 'hg.hostid=i.hostid';
			$sqlParts['where'][] = 'r.id=hg.groupid ';
			$sqlParts['where'][] = 'r.groupid=ug.usrgrpid';
			$sqlParts['where'][] = 'ug.userid='.$userid;
			$sqlParts['where'][] = 'r.permission>='.$permission;
			$sqlParts['where'][] = 'NOT EXISTS('.
								' SELECT hgg.groupid'.
								' FROM hosts_groups hgg,rights rr,users_groups gg'.
								' WHERE hgg.hostid=hg.hostid'.
									' AND rr.id=hgg.groupid'.
									' AND rr.groupid=gg.usrgrpid'.
									' AND gg.userid='.$userid.
									' AND rr.permission<'.$permission.')';
		}

		// nodeids
		$nodeids = !is_null($options['nodeids']) ? $options['nodeids'] : get_current_nodeid();

		// templateids
		if (!is_null($options['templateids'])) {
			zbx_value2array($options['templateids']);

			if (!is_null($options['hostids'])) {
				zbx_value2array($options['hostids']);
				$options['hostids'] = array_merge($options['hostids'], $options['templateids']);
			}
			else {
				$options['hostids'] = $options['templateids'];
			}
		}

		// hostids
		if (!is_null($options['hostids'])) {
			zbx_value2array($options['hostids']);

			if ($options['output'] != API_OUTPUT_EXTEND) {
				$sqlParts['select']['hostid'] = 'i.hostid';
			}

			$sqlParts['where']['hostid'] = DBcondition('i.hostid', $options['hostids']);

			if (!is_null($options['groupCount'])) {
				$sqlParts['group']['i'] = 'i.hostid';
			}
		}

		// itemids
		if (!is_null($options['itemids'])) {
			zbx_value2array($options['itemids']);

			$sqlParts['where']['itemid'] = DBcondition('i.itemid', $options['itemids']);
		}

		// inherited
		if (!is_null($options['inherited'])) {
			if ($options['inherited']) {
				$sqlParts['where'][] = 'i.templateid IS NOT NULL';
			}
			else {
				$sqlParts['where'][] = 'i.templateid IS NULL';
			}
		}

		// templated
		if (!is_null($options['templated'])) {
			$sqlParts['from']['hosts'] = 'hosts h';
			$sqlParts['where']['hi'] = 'h.hostid=i.hostid';

			if ($options['templated']) {
				$sqlParts['where'][] = 'h.status='.HOST_STATUS_TEMPLATE;
			}
			else {
				$sqlParts['where'][] = 'h.status<>'.HOST_STATUS_TEMPLATE;
			}
		}

		// monitored
		if (!is_null($options['monitored'])) {
			$sqlParts['from']['hosts'] = 'hosts h';
			$sqlParts['where']['hi'] = 'h.hostid=i.hostid';

			if ($options['monitored']) {
				$sqlParts['where'][] = 'h.status='.HOST_STATUS_MONITORED;
				$sqlParts['where'][] = 'i.status='.ITEM_STATUS_ACTIVE;
			}
			else {
				$sqlParts['where'][] = '(h.status<>'.HOST_STATUS_MONITORED.' OR i.status<>'.ITEM_STATUS_ACTIVE.')';
			}
		}

		// search
		if (is_array($options['search'])) {
			zbx_db_search('items i', $options, $sqlParts);
		}

		// filter
		if (is_array($options['filter'])) {
			zbx_db_filter('items i', $options, $sqlParts);

			if (isset($options['filter']['host'])) {
				zbx_value2array($options['filter']['host']);

				$sqlParts['from']['hosts'] = 'hosts h';
				$sqlParts['where']['hi'] = 'h.hostid=i.hostid';
				$sqlParts['where']['h'] = DBcondition('h.host', $options['filter']['host']);
			}
		}

		// output
		if ($options['output'] == API_OUTPUT_EXTEND) {
			$sqlParts['select']['items'] = 'i.*';
		}

		// countOutput
		if (!is_null($options['countOutput'])) {
			$options['sortfield'] = '';
			$sqlParts['select'] = array('count(DISTINCT i.itemid) as rowscount');

			// groupCount
			if (!is_null($options['groupCount'])) {
				foreach ($sqlParts['group'] as $key => $fields) {
					$sqlParts['select'][$key] = $fields;
				}
			}
		}

		// sorting
		zbx_db_sorting($sqlParts, $options, $sortColumns, 'i');

		// limit
		if (zbx_ctype_digit($options['limit']) && $options['limit']) {
			$sqlParts['limit'] = $options['limit'];
		}

		$itemids = array();

		$sqlParts['select'] = array_unique($sqlParts['select']);
		$sqlParts['from'] = array_unique($sqlParts['from']);
		$sqlParts['where'] = array_unique($sqlParts['where']);
		$sqlParts['group'] = array_unique($sqlParts['group']);
		$sqlParts['order'] = array_unique($sqlParts['order']);

		$sqlSelect = '';
		$sqlFrom = '';
		$sqlWhere = '';
		$sqlGroup = '';
		$sqlOrder = '';
		if (!empty($sqlParts['select'])) {
			$sqlSelect .= implode(',', $sqlParts['select']);
		}
		if (!empty($sqlParts['from'])) {
			$sqlFrom .= implode(',', $sqlParts['from']);
		}
		if (!empty($sqlParts['where'])) {
			$sqlWhere .= ' AND '.implode(' AND ', $sqlParts['where']);
		}
		if (!empty($sqlParts['group'])) {
			$sqlWhere .= ' GROUP BY '.implode(',', $sqlParts['group']);
		}
		if (!empty($sqlParts['order'])) {
			$sqlOrder .= ' ORDER BY '.implode(',', $sqlParts['order']);
		}
		$sqlLimit = $sqlParts['limit'];

		$sql = 'SELECT '.zbx_db_distinct($sqlParts).' '.$sqlSelect.
				' FROM '.$sqlFrom.
				' WHERE '.DBin_node('i.itemid', $nodeids).
					$sqlWhere.
				$sqlGroup.
				$sqlOrder;

		$res = DBselect($sql, $sqlLimit);
		while ($item = DBfetch($res)) {
			if (!is_null($options['countOutput'])) {
				if (!is_null($options['groupCount'])) {
					$result[] = $item;
				}
				else {
					$result = $item['rowscount'];
				}
			}
			else {
				$itemids[$item['itemid']] = $item['itemid'];

				if ($options['output'] == API_OUTPUT_SHORTEN) {
					$result[$item['itemid']] = array('itemid' => $item['itemid']);
				}
				else {
					if (!isset($result[$item['itemid']])) {
						$result[$item['itemid']]= array();
					}
					if (!is_null($options['selectHosts']) && !isset($result[$item['itemid']]['hosts'])) {
						$result[$item['itemid']]['hosts'] = array();
					}
					if (!is_null($options['selectTriggers']) && !isset($result[$item['itemid']]['triggers'])) {
						$result[$item['itemid']]['triggers'] = array();
					}
					if (!is_null($options['selectGraphs']) && !isset($result[$item['itemid']]['graphs'])) {
						$result[$item['itemid']]['graphs'] = array();
					}
					if (!is_null($options['selectPrototypes']) && !isset($result[$item['itemid']]['prototypes'])) {
						$result[$item['itemid']]['prototypes'] = array();
					}

					// hostids
					if (isset($item['hostid']) && is_null($options['selectHosts'])) {
						if (!isset($result[$item['itemid']]['hosts'])) {
							$result[$item['itemid']]['hosts'] = array();
						}
						$result[$item['itemid']]['hosts'][] = array('hostid' => $item['hostid']);
					}
					$result[$item['itemid']] += $item;
				}
			}
		}

		if (!is_null($options['countOutput'])) {
			return $result;
		}

		// adding objects
		// adding hosts
		if (!is_null($options['selectHosts'])) {
			if (is_array($options['selectHosts']) || str_in_array($options['selectHosts'], $subselectsAllowedOutputs)) {
				$objParams = array(
					'nodeids' => $nodeids,
					'itemids' => $itemids,
					'templated_hosts' => true,
					'output' => $options['selectHosts'],
					'nopermissions' => true,
					'preservekeys' => true
				);
				$hosts = API::Host()->get($objParams);

				foreach ($hosts as $hostid => $host) {
					$hitems = $host['items'];
					unset($host['items']);
					foreach ($hitems as $item) {
						$result[$item['itemid']]['hosts'][] = $host;
					}
				}

				$templates = API::Template()->get($objParams);
				foreach ($templates as $templateid => $template) {
					$titems = $template['items'];
					unset($template['items']);
					foreach ($titems as $item) {
						$result[$item['itemid']]['hosts'][] = $template;
					}
				}
			}
		}

		// adding triggers
		if (!is_null($options['selectTriggers'])) {
			$objParams = array(
				'nodeids' => $nodeids,
				'discoveryids' => $itemids,
				'preservekeys' => true,
				'filter' => array('flags' => ZBX_FLAG_DISCOVERY_CHILD)
			);

			if (in_array($options['selectTriggers'], $subselectsAllowedOutputs)) {
				$objParams['output'] = $options['selectTriggers'];
				$triggers = API::Trigger()->get($objParams);

				if (!is_null($options['limitSelects'])) order_result($triggers, 'name');
				foreach ($triggers as $triggerid => $trigger) {
					unset($triggers[$triggerid]['items']);
					$count = array();
					foreach ($trigger['items'] as $item) {
						if (!is_null($options['limitSelects'])) {
							if (!isset($count[$item['itemid']])) {
								$count[$item['itemid']] = 0;
							}
							$count[$item['itemid']]++;

							if ($count[$item['itemid']] > $options['limitSelects']) {
								continue;
							}
						}
						$result[$item['itemid']]['triggers'][] = &$triggers[$triggerid];
					}
				}
			}
			elseif (API_OUTPUT_COUNT == $options['selectTriggers']) {
				$objParams['countOutput'] = 1;
				$objParams['groupCount'] = 1;
				$triggers = API::Trigger()->get($objParams);

				$triggers = zbx_toHash($triggers, 'parent_itemid');
				foreach ($result as $itemid => $item) {
					if (isset($triggers[$itemid])) {
						$result[$itemid]['triggers'] = $triggers[$itemid]['rowscount'];
					}
					else {
						$result[$itemid]['triggers'] = 0;
					}
				}
			}
		}

		// adding graphs
		if (!is_null($options['selectGraphs'])) {
			$objParams = array(
				'nodeids' => $nodeids,
				'discoveryids' => $itemids,
				'preservekeys' => true,
				'filter' => array('flags' => ZBX_FLAG_DISCOVERY_CHILD)
			);

			if (in_array($options['selectGraphs'], $subselectsAllowedOutputs)) {
				$objParams['output'] = $options['selectGraphs'];
				$graphs = API::Graph()->get($objParams);

				if (!is_null($options['limitSelects'])) {
					order_result($graphs, 'name');
				}
				foreach ($graphs as $graphid => $graph) {
					unset($graphs[$graphid]['discoveries']);
					$count = array();
					foreach ($graph['discoveries'] as $item) {
						if (!is_null($options['limitSelects'])) {
							if (!isset($count[$item['itemid']])) {
								$count[$item['itemid']] = 0;
							}
							$count[$item['itemid']]++;

							if ($count[$item['itemid']] > $options['limitSelects']) {
								continue;
							}
						}
						$result[$item['itemid']]['graphs'][] = &$graphs[$graphid];
					}
				}
			}
			elseif (API_OUTPUT_COUNT == $options['selectGraphs']) {
				$objParams['countOutput'] = 1;
				$objParams['groupCount'] = 1;
				$graphs = API::Graph()->get($objParams);

				$graphs = zbx_toHash($graphs, 'parent_itemid');
				foreach ($result as $itemid => $item) {
					if (isset($graphs[$itemid])) {
						$result[$itemid]['graphs'] = $graphs[$itemid]['rowscount'];
					}
					else {
						$result[$itemid]['graphs'] = 0;
					}
				}
			}
		}

		// adding prototypes
		if (!is_null($options['selectPrototypes'])) {
			$objParams = array(
				'nodeids' => $nodeids,
				'discoveryids' => $itemids,
				'nopermissions' => true,
				'preservekeys' => true,
				'selectDiscoveryRule' => API_OUTPUT_EXTEND
			);

			if (is_array($options['selectPrototypes']) || str_in_array($options['selectPrototypes'], $subselectsAllowedOutputs)) {
				$objParams['output'] = $options['selectPrototypes'];
				$prototypes = API::Item()->get($objParams);

				if (!is_null($options['limitSelects'])) {
					order_result($prototypes, 'name');
				}

				$count = array();
				foreach ($prototypes as $prototype) {
					$discoveryId = $prototype['discoveryRule']['itemid'];
					if (!isset($count[$discoveryId])) {
						$count[$discoveryId] = 0;
					}
					$count[$discoveryId]++;

					if ($options['limitSelects'] && $options['limitSelects'] > $count[$discoveryId]) {
						continue;
					}

					unset($prototype['discoveryRule']);
					$result[$discoveryId]['prototypes'][] = $prototype;
				}
			}
			elseif (API_OUTPUT_COUNT == $options['selectPrototypes']) {
				$objParams['countOutput'] = 1;
				$objParams['groupCount'] = 1;
				$prototypes = API::Item()->get($objParams);

				$prototypes = zbx_toHash($prototypes, 'parent_itemid');
				foreach ($result as $itemid => $item) {
					if (isset($prototypes[$itemid])) {
						$result[$itemid]['prototypes'] = $prototypes[$itemid]['rowscount'];
					}
					else {
						$result[$itemid]['prototypes'] = 0;
					}
				}
			}
		}

		if (is_null($options['preservekeys'])) {
			$result = zbx_cleanHashes($result);
		}
		return $result;
	}

	public function exists($object) {
		$options = array(
			'filter' => array('key_' => $object['key_']),
			'output' => API_OUTPUT_SHORTEN,
			'nopermissions' => true,
			'limit' => 1
		);

		if (isset($object['hostid'])) {
			$options['hostids'] = $object['hostid'];
		}
		if (isset($object['host'])) {
			$options['filter']['host'] = $object['host'];
		}

		if (isset($object['node'])) {
			$options['nodeids'] = getNodeIdByNodeName($object['node']);
		}
		elseif (isset($object['nodeids'])) {
			$options['nodeids'] = $object['nodeids'];
		}
		$objs = $this->get($options);
		return !empty($objs);
	}

	/**
	 * Add DiscoveryRule
	 *
	 * @param array $items
	 * @return array|boolean
	 */
	public function create($items) {
		$items = zbx_toArray($items);
		$this->checkInput($items);
		$this->createReal($items);
		$this->inherit($items);
		return array('itemids' => zbx_objectValues($items, 'itemid'));
	}

	/**
	 * Update DiscoveryRule
	 *
	 * @param array $items
	 * @return boolean
	 */
	public function update($items) {
		$items = zbx_toArray($items);
		$this->checkInput($items, true);
		$this->updateReal($items);
		$this->inherit($items);
		return array('itemids' => zbx_objectValues($items, 'itemid'));
	}

	/**
	 * Delete DiscoveryRules
	 *
	 * @param array $ruleids
	 * @return
	 */
	public function delete($ruleids, $nopermissions = false) {
		if (empty($ruleids)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Empty input parameter.'));
		}
		$ruleids = zbx_toHash($ruleids);

		$options = array(
			'output' => API_OUTPUT_EXTEND,
			'itemids' => $ruleids,
			'editable' => true,
			'preservekeys' => true,
			'selectHosts' => array('name')
		);
		$delRules = $this->get($options);

		// TODO: remove $nopermissions hack
		if (!$nopermissions) {
			foreach ($ruleids as $ruleid) {
				if (!isset($delRules[$ruleid])) {
					self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
				}
				if ($delRules[$ruleid]['templateid'] != 0) {
					self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot delete templated items.'));
				}
			}
		}

		// get child discovery rules
		$parentItemids = $ruleids;
		$childTuleids = array();
		do {
			$dbItems = DBselect('SELECT i.itemid FROM items i WHERE '.DBcondition('i.templateid', $parentItemids));
			$parentItemids = array();
			while ($dbItem = DBfetch($dbItems)) {
				$parentItemids[$dbItem['itemid']] = $dbItem['itemid'];
				$childTuleids[$dbItem['itemid']] = $dbItem['itemid'];
			}
		} while (!empty($parentItemids));

		$options = array(
			'output' => API_OUTPUT_EXTEND,
			'itemids' => $childTuleids,
			'nopermissions' => true,
			'preservekeys' => true,
			'selectHosts' => array('name')
		);
		$delRulesChilds = $this->get($options);

		$delRules = array_merge($delRules, $delRulesChilds);
		$ruleids = array_merge($ruleids, $childTuleids);

		$iprototypeids = array();
		$dbItems = DBselect(
			'SELECT i.itemid'.
			' FROM item_discovery id,items i'.
			' WHERE i.itemid=id.itemid'.
				' AND '.DBcondition('parent_itemid', $ruleids)
		);
		while ($item = DBfetch($dbItems)) {
			$iprototypeids[$item['itemid']] = $item['itemid'];
		}
		if (!empty($iprototypeids)) {
			if (!API::Itemprototype()->delete($iprototypeids, true)) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot delete discovery rule'));
			}
		}
		DB::delete('items', array('itemid' => $ruleids));

		// housekeeper
		$itemDataTables = array(
			'trends',
			'trends_uint',
			'history_text',
			'history_log',
			'history_uint',
			'history_str',
			'history'
		);
		$insert = array();
		foreach ($ruleids as $ruleid) {
			foreach ($itemDataTables as $table) {
				$insert[] = array(
					'tablename' => $table,
					'field' => 'itemid',
					'value' => $ruleid
				);
			}
		}
		DB::insert('housekeeper', $insert);

		// TODO: remove info from API
		foreach ($delRules as $item) {
			$host = reset($item['hosts']);
			info(_s('Deleted: Discovery rule "%1$s" on "%2$s".', $item['name'], $host['name']));
		}
		return array('ruleids' => $ruleids);
	}

	/**
	 * Copies the given discovery rules to the specified hosts.
	 *
	 * @throws APIException if no discovery rule IDs or host IDs are given or
	 * the user doesn't have the necessary permissions.
	 *
	 * @param array $data
	 * @param array $data['discoveryruleids'] An array of item ids to be cloned
	 * @param array $data['hostids']          An array of host ids were the items should be cloned to
	 */
	public function copy(array $data) {
		// validate data
		if (!isset($data['discoveryids']) || !$data['discoveryids']) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('No discovery rule IDs given.'));
		}
		if (!isset($data['hostids']) || !$data['hostids']) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('No host IDs given.'));
		}

		// check if all hosts exist and are writable
		if (!API::Host()->isWritable($data['hostids'])) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		// check if the given discovery rules exist
		if (!$this->isReadable($data['discoveryids'])) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		// copy
		foreach ($data['discoveryids'] as $discoveryid) {
			foreach ($data['hostids'] as $hostid) {
				$this->copyDiscoveryRule($discoveryid, $hostid);
			}
		}

		return true;
	}

	public function syncTemplates($data) {
		$data['templateids'] = zbx_toArray($data['templateids']);
		$data['hostids'] = zbx_toArray($data['hostids']);

		if (!API::Host()->isWritable($data['hostids'])) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}
		if (!API::Template()->isReadable($data['templateids'])) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		$selectFields = array('flags');
		foreach ($this->fieldRules as $key => $rules) {
			if (!isset($rules['system']) && !isset($rules['host'])) {
				$selectFields[] = $key;
			}
		}

		$options = array(
			'hostids' => $data['templateids'],
			'preservekeys' => true,
			'output' => $selectFields
		);
		$items = $this->get($options);

		$this->inherit($items, $data['hostids']);

		return true;
	}

	/**
	 * Returns true if the given discovery rules exists and are available for
	 * reading.
	 *
	 * @param array     $ids  An array if item IDs
	 * @return boolean
	 */
	public function isReadable($ids) {
		if (!is_array($ids)) {
			return false;
		}
		elseif (empty($ids)) {
			return true;
		}

		$ids = array_unique($ids);

		$count = $this->get(array(
			'nodeids' => get_current_nodeid(true),
			'itemids' => $ids,
			'output' => API_OUTPUT_SHORTEN,
			'countOutput' => true
		));

		return (count($ids) == $count);
	}

	/**
	 * Returns true if the given discovery rules exists and are available for
	 * writing.
	 *
	 * @param array     $ids  An array if item IDs
	 * @return boolean
	 */
	public function isWritable($ids) {
		if (!is_array($ids)) {
			return false;
		}
		elseif (empty($ids)) {
			return true;
		}

		$ids = array_unique($ids);

		$count = $this->get(array(
			'nodeids' => get_current_nodeid(true),
			'itemids' => $ids,
			'output' => API_OUTPUT_SHORTEN,
			'editable' => true,
			'countOutput' => true
		));

		return (count($ids) == $count);
	}

	/**
	 * Copies all of the triggers from the source discovery to the target discovery rule.
	 *
	 * @throws APIException if trigger saving fails
	 *
	 * @param array $srcDiscovery    The source discovery rule to copy from
	 * @param array $dstDiscovery    The target discovery rule to copy to
	 * @param array $srcHost         The host the source discovery belongs to
	 * @param array $dstHost         The host the target discovery belongs to
	 *
	 * @return array
	 */
	protected function copyDiscoveryTriggers(array $srcDiscovery, array $dstDiscovery, array $srcHost, array $dstHost) {
		$srcTriggers = API::TriggerPrototype()->get(array(
			'discoveryids' => $srcDiscovery['itemid'],
			'output' => API_OUTPUT_EXTEND,
			'selectHosts' => API_OUTPUT_EXTEND,
			'selectItems' => API_OUTPUT_EXTEND,
			'selectDiscoveryRule' => API_OUTPUT_EXTEND,
			'selectFunctions' => API_OUTPUT_EXTEND
		));

		if (!$srcTriggers) {
			return array();
		}

		$itemKeys = array();
		foreach ($srcTriggers as $trigger) {
			foreach ($trigger['items'] as $item) {
				$itemKeys[] = $item['key_'];
			}
		}
		array_unique($itemKeys);

		// fetch newly created items
		$items = API::Item()->get(array(
			'hostids' => $dstDiscovery['hostid'],
			'filter' => array(
				'key_' => $itemKeys
			),
			'output' => API_OUTPUT_EXTEND
		));
		$dstItems = array();
		foreach ($items as $item) {
			$dstItems[$item['key_']] = $item;
		}

		// save new triggers
		$dstTriggers = $srcTriggers;
		foreach ($dstTriggers as &$trigger) {
			unset($trigger['triggerid']);

			// update expression
			$trigger['expression'] = explode_exp($trigger['expression'], false, false, $srcHost['host'], $dstHost['host']);

		}

		$rs = API::TriggerPrototype()->create($dstTriggers);
		if (!$rs) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot clone trigger prototypes.'));
		}

		return $rs;
	}

	protected function createReal(&$items) {
		$itemids = DB::insert('items', $items);

		$itemApplications = array();
		foreach ($items as $key => $item) {
			$items[$key]['itemid'] = $itemids[$key];

			if (!isset($item['applications'])) {
				continue;
			}

			foreach ($item['applications'] as $appid) {
				if ($appid == 0) {
					continue;
				}
				$itemApplications[] = array(
					'applicationid' => $appid,
					'itemid' => $items[$key]['itemid']
				);
			}
		}

		if (!empty($itemApplications)) {
			DB::insert('items_applications', $itemApplications);
		}

		// TODO: REMOVE info
		$itemHosts = $this->get(array(
			'itemids' => $itemids,
			'output' => array('key_', 'name'),
			'selectHosts' => array('name'),
			'nopermissions' => true
		));
		foreach ($itemHosts as $item) {
			$host = reset($item['hosts']);
			info(_s('Created: Discovery rule "%1$s" on "%2$s".', $item['name'], $host['name']));
		}
	}

	protected function updateReal($items) {
		$items = zbx_toArray($items);

		$data = array();
		foreach ($items as $item) {
			$data[] = array('values' => $item, 'where'=> array('itemid' => $item['itemid']));
		}
		$result = DB::update('items', $data);
		if (!$result) self::exception(ZBX_API_ERROR_PARAMETERS, 'DBerror');

		$itemids = array();
		$itemApplications = array();
		foreach ($items as $key => $item) {
			$itemids[] = $item['itemid'];

			if (!isset($item['applications'])) {
				continue;
			}
			foreach ($item['applications'] as $appid) {
				$itemApplications[] = array(
					'applicationid' => $appid,
					'itemid' => $item['itemid']
				);
			}
		}

		if (!empty($itemids)) {
			DB::delete('items_applications', array('itemid' => $itemids));
			DB::insert('items_applications', $itemApplications);
		}

		// TODO: REMOVE info
		$itemHosts = $this->get(array(
			'itemids' => $itemids,
			'output' => array('key_', 'name'),
			'selectHosts' => array('name'),
			'nopermissions' => true
		));
		foreach ($itemHosts as $item) {
			$host = reset($item['hosts']);
			info(_s('Updated: Discovery rule "%1$s" on "%2$s".', $item['name'], $host['name']));
		}
	}

	/**
	 * Check item data and set missing default values.
	 *
	 * @throws APIException
	 *
	 * @param array $items passed by reference
	 * @param bool  $update
	 *
	 * @return void
	 */
	protected function checkInput(array &$items, $update = false) {
		foreach ($items as &$item) {
			$item['flags'] = ZBX_FLAG_DISCOVERY;
			$item['value_type'] = ITEM_VALUE_TYPE_TEXT;
		}
		parent::checkInput($items, $update);
	}

	protected function checkSpecificFields(array $item) {
		if (isset($item['lifetime']) && !$this->validateLifetime($item['lifetime'])) {
			self::exception(ZBX_API_ERROR_PARAMETERS,
				_s('Discovery rule "%1$s:%2$s" has incorrect lifetime: "%3$s". (min: %4$d, max: %5$d, user macro allowed)',
					$item['name'], $item['key_'], $item['lifetime'], self::MIN_LIFETIME, self::MAX_LIFETIME)
			);
		}
	}

	protected function inherit(array $items, array $hostids = null) {
		if (empty($items)) {
			return true;
		}

		// prepare the child items
		$newItems = $this->prepareInheritedItems($items, $hostids, array(
			'exists' => _('Discovery rule "%1$s" already exists on "%2$s", inherited from another template.')
		));
		if (!$newItems) {
			return true;
		}

		$insertItems = array();
		$updateItems = array();
		foreach ($newItems as $newItem) {
			if (isset($newItem['itemid'])) {
				$updateItems[] = $newItem;
			}
			else {
				$newItem['flags'] = ZBX_FLAG_DISCOVERY;
				$insertItems[] = $newItem;
			}
		}

		// save the new items
		$this->createReal($insertItems);
		$this->updateReal($updateItems);

		// propagate the inheritance to the children
		return $this->inherit(array_merge($updateItems, $insertItems));
	}

	/**
	 * Copies the given discovery rule to the specified host.
	 *
	 * @throws APIException if the discovery rule interfaces could not be mapped
	 * to the new host interfaces.
	 *
	 * @param type $discoveryid  The ID of the discovery rule to be copied
	 * @param type $hostid       Destination host id
	 */
	protected function copyDiscoveryRule($discoveryid, $hostid) {
		// fetch discovery to clone
		$srcDiscovery = $this->get(array(
			'itemids' => $discoveryid,
			'output' => API_OUTPUT_EXTEND
		));
		$srcDiscovery = $srcDiscovery[0];

		// fetch source and destination hosts
		$hosts = API::Host()->get(array(
			'hostids' => array($srcDiscovery['hostid'], $hostid),
			'output' => API_OUTPUT_EXTEND,
			'selectInterfaces' => API_OUTPUT_EXTEND,
			'templated_hosts' => true,
			'preservekeys' => true
		));
		$srcHost = $hosts[$srcDiscovery['hostid']];
		$dstHost = $hosts[$hostid];

		$dstDiscovery = $srcDiscovery;
		$dstDiscovery['hostid'] = $hostid;

		// if this is a plain host, map discovery interfaces
		if ($srcHost['status'] != HOST_STATUS_TEMPLATE) {
			// find a matching interface
			$interface = self::findInterfaceForItem($dstDiscovery, $dstHost['interfaces']);
			if ($interface) {
				$dstDiscovery['interfaceid'] = $interface['interfaceid'];
			}
			// no matching interface found, throw an error
			elseif ($interface !== false) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _s('Cannot find host interface on "%1$s" for item key "%2$s".', $dstHost['name'], $dstDiscovery['key_']));
			}
		}

		// save new discovery
		$newDiscovery = $this->create(array($dstDiscovery));
		$dstDiscovery['itemid'] = $newDiscovery['itemids'][0];

		// copy prototypes
		$newPrototypes = $this->copyDiscoveryPrototypes($srcDiscovery, $dstDiscovery, $dstHost);

		// if there were prototypes defined, clone everything else
		if ($newPrototypes) {
			// fetch new prototypes
			$newPrototypes = API::ItemPrototype()->get(array(
				'itemids' => $newPrototypes['itemids'],
				'output' => API_OUTPUT_EXTEND
			));
			$dstDiscovery['prototypes'] = $newPrototypes;

			// copy graphs
			$this->copyDiscoveryGraphs($srcDiscovery, $dstDiscovery);

			// copy triggers
			$this->copyDiscoveryTriggers($srcDiscovery, $dstDiscovery, $srcHost, $dstHost);
		}
		return true;
	}

	/**
	 * Copies all of the item prototypes from the source discovery to the target
	 * discovery rule.
	 *
	 * @throws APIException if prototype saving fails
	 *
	 * @param array $srcDiscovery   The source discovery rule to copy from
	 * @param array $dstDiscovery   The target discovery rule to copy to
	 * @param array $dstHost        The target host to copy the deiscovery rule to
	 *
	 * @return array
	 */
	protected function copyDiscoveryPrototypes(array $srcDiscovery, array $dstDiscovery, array $dstHost) {
		$prototypes = API::ItemPrototype()->get(array(
			'discoveryids' => $srcDiscovery['itemid'],
			'output' => API_OUTPUT_EXTEND
		));

		$rs = array();
		if ($prototypes) {
			foreach ($prototypes as $key => $prototype) {
				$prototype['ruleid'] = $dstDiscovery['itemid'];
				$prototype['hostid'] = $dstDiscovery['hostid'];

				// map prototype interfaces
				if ($dstHost['status'] != HOST_STATUS_TEMPLATE) {
					// find a matching interface
					$interface = self::findInterfaceForItem($prototype, $dstHost['interfaces']);
					if ($interface) {
						$prototype['interfaceid'] = $interface['interfaceid'];
					}
					// no matching interface found, throw an error
					elseif ($interface !== false) {
						self::exception(ZBX_API_ERROR_PARAMETERS, _s('Cannot find host interface on host "%1$s" for item key "%2$s".', $dstHost['name'], $prototype['key_']));
					}
				}

				$prototypes[$key] = $prototype;
			}

			$rs = API::ItemPrototype()->create($prototypes);
			if (!$rs) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot clone item prototypes.'));
			}
		}
		return $rs;
	}

	/**
	 * Copies all of the graphs from the source discovery to the target discovery rule.
	 *
	 * @throws APIException if graph saving fails
	 *
	 * @param array $srcDiscovery    The source discovery rule to copy from
	 * @param array $dstDiscovery    The target discovery rule to copy to
	 *
	 * @return array
	 */
	protected function copyDiscoveryGraphs(array $srcDiscovery, array $dstDiscovery) {
		// fetch source graphs
		$srcGraphs = API::GraphPrototype()->get(array(
			'discoveryids' => $srcDiscovery['itemid'],
			'output' => API_OUTPUT_EXTEND,
			'selectGraphItems' => API_OUTPUT_EXTEND,
			'selectHosts' => API_OUTPUT_REFER
		));

		if (!$srcGraphs) {
			return array();
		}

		$srcItemids = array();
		$itemKeys = array();
		foreach ($srcGraphs as $key => $graph) {
			// skip graphs with items from multiple hosts
			if (count($graph['hosts']) > 1) {
				unset($srcGraphs[$key]);
				continue;
			}

			// skip graphs with http items
			if (httpItemExists($graph['gitems'])) {
				unset($srcGraphs[$key]);
				continue;
			}

			foreach ($graph['gitems'] as $item) {
				$srcItemids[] = $item['itemid'];
			}
		}

		// fetch source items
		$items = API::Item()->get(array(
			'itemids' => $srcItemids,
			'output' => API_OUTPUT_EXTEND
		));
		$srcItems = array();
		$itemKeys = array();
		foreach ($items as $item) {
			$srcItems[$item['itemid']] = $item;
			$itemKeys[] = $item['key_'];
		}
		$itemKeys = array_unique($itemKeys);

		// fetch newly cloned items
		$items = array_merge($dstDiscovery['prototypes'], API::Item()->get(array(
			'hostids' => $dstDiscovery['hostid'],
			'filter' => array(
				'key_' => $itemKeys
			),
			'output' => API_OUTPUT_EXTEND
		)));
		$dstItems = array();
		foreach ($items as $item) {
			$dstItems[$item['key_']] = $item;
		}

		$dstGraphs = $srcGraphs;
		foreach ($dstGraphs as &$graph) {
			unset($graph['graphid']);

			foreach ($graph['gitems'] as $key => &$gitem) {
				// replace the old item with the new one with the same key
				$item = $srcItems[$gitem['itemid']];
				$gitem['itemid'] = $dstItems[$item['key_']]['itemid'];

				unset($gitem['gitemid'], $gitem['graphid']);
			}
		}

		// save graphs
		$rs = API::Graph()->create($dstGraphs);
		if (!$rs) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Cannot clone graph prototypes.'));
		}
		return $rs;
	}

	private function validateLifetime($lifetime) {
		return (validateNumber($lifetime, self::MIN_LIFETIME, self::MAX_LIFETIME) || validateUserMacro($lifetime));
	}
}
?>
