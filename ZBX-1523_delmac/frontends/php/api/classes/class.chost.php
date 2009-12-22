<?php
/*
** ZABBIX
** Copyright (C) 2000-2009 SIA Zabbix
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
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
/**
 * File containing CHost class for API.
 * @package API
 */
/**
 * Class containing methods for operations with Hosts
 */
class CHost extends CZBXAPI{
/**
 * Get Host data
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param _array $options
 * @param array $options['nodeids'] Node IDs
 * @param array $options['groupids'] HostGroup IDs
 * @param array $options['hostids'] Host IDs
 * @param boolean $options['monitored_hosts'] only monitored Hosts
 * @param boolean $options['templated_hosts'] include templates in result
 * @param boolean $options['with_items'] only with items
 * @param boolean $options['with_monitored_items'] only with monitored items
 * @param boolean $options['with_historical_items'] only with historical items
 * @param boolean $options['with_triggers'] only with triggers
 * @param boolean $options['with_monitored_triggers'] only with monitored triggers
 * @param boolean $options['with_httptests'] only with http tests
 * @param boolean $options['with_monitored_httptests'] only with monitored http tests
 * @param boolean $options['with_graphs'] only with graphs
 * @param boolean $options['editable'] only with read-write permission. Ignored for SuperAdmins
 * @param int $options['extendoutput'] return all fields for Hosts
 * @param boolean $options['select_groups'] select HostGroups
 * @param boolean $options['select_templates'] select Templates
 * @param boolean $options['select_items'] select Items
 * @param boolean $options['select_triggers'] select Triggers
 * @param boolean $options['select_graphs'] select Graphs
 * @param boolean $options['select_applications'] select Applications
 * @param boolean $options['select_macros'] select Macros
 * @param boolean $options['select_profile'] select Profile
 * @param int $options['count'] count Hosts, returned column name is rowscount
 * @param string $options['pattern'] search hosts by pattern in Host name
 * @param string $options['extend_pattern'] search hosts by pattern in Host name, ip and DNS
 * @param int $options['limit'] limit selection
 * @param string $options['sortfield'] field to sort by
 * @param string $options['sortorder'] sort order
 * @return array|boolean Host data as array or false if error
 */
	public static function get($options=array()){
		global $USER_DETAILS;

		$result = array();
		$user_type = $USER_DETAILS['type'];
		$userid = $USER_DETAILS['userid'];

		$sort_columns = array('hostid', 'host', 'status', 'dns', 'ip'); // allowed columns for sorting


		$sql_parts = array(
			'select' => array('hosts' => 'h.hostid'),
			'from' => array('hosts h'),
			'where' => array(),
			'order' => array(),
			'limit' => null);

		$def_options = array(
			'nodeids'					=> null,
			'groupids'					=> null,
			'hostids'					=> null,
			'templateids'				=> null,
			'itemids'					=> null,
			'triggerids'				=> null,
			'graphids'					=> null,
			'monitored_hosts'			=> null,
			'templated_hosts'			=> null,
			'proxy_hosts'				=> null,
			'with_items'				=> null,
			'with_monitored_items'		=> null,
			'with_historical_items'		=> null,
			'with_triggers'				=> null,
			'with_monitored_triggers'	=> null,
			'with_httptests'			=> null,
			'with_monitored_httptests'	=> null,
			'with_graphs'				=> null,
			'editable'					=> null,
			'nopermissions'				=> null,
// filter
			'pattern'					=> '',
			'extend_pattern'			=> null,

// OutPut
			'extendoutput'				=> null,
			'select_groups'				=> null,
			'select_templates'			=> null,
			'select_items'				=> null,
			'select_triggers'			=> null,
			'select_graphs'				=> null,
			'select_applications'		=> null,
			'select_macros'				=> null,
			'select_profile'			=> null,
			'count'						=> null,
			'preservekeys'				=> null,

			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		);

		$options = zbx_array_merge($def_options, $options);

// editable + PERMISSION CHECK
		if(defined('ZBX_API_REQUEST')){
			$options['nopermissions'] = false;
		}

		if((USER_TYPE_SUPER_ADMIN == $user_type) || $options['nopermissions']){
		}
		else{
			$permission = $options['editable'] ? PERM_READ_WRITE : PERM_READ_ONLY;

			$sql_parts['from']['hg'] = 'hosts_groups hg';
			$sql_parts['from']['r'] = 'rights r';
			$sql_parts['from']['ug'] = 'users_groups ug';
			$sql_parts['where']['hgh'] = 'hg.hostid=h.hostid';
			$sql_parts['where'][] = 'r.id=hg.groupid ';
			$sql_parts['where'][] = 'r.groupid=ug.usrgrpid';
			$sql_parts['where'][] = 'ug.userid='.$userid;
			$sql_parts['where'][] = 'r.permission>='.$permission;
			$sql_parts['where'][] = 'NOT EXISTS( '.
									' SELECT hgg.groupid '.
									' FROM hosts_groups hgg, rights rr, users_groups gg '.
									' WHERE hgg.hostid=hg.hostid '.
										' AND rr.id=hgg.groupid '.
										' AND rr.groupid=gg.usrgrpid '.
										' AND gg.userid='.$userid.
										' AND rr.permission<'.$permission.')';
		}

// nodeids
		$nodeids = $options['nodeids'] ? $options['nodeids'] : get_current_nodeid(false);

// groupids
		if(!is_null($options['groupids'])){
			zbx_value2array($options['groupids']);
			if(!is_null($options['extendoutput'])){
				$sql_parts['select']['groupid'] = 'hg.groupid';
			}
			$sql_parts['from']['hg'] = 'hosts_groups hg';
			$sql_parts['where'][] = DBcondition('hg.groupid', $options['groupids']);
			$sql_parts['where']['hgh'] = 'hg.hostid=h.hostid';
		}

// hostids
		if(!is_null($options['hostids'])){
			zbx_value2array($options['hostids']);
			$sql_parts['where'][] = DBcondition('h.hostid', $options['hostids']);
		}

// templateids
		if(!is_null($options['templateids'])){
			zbx_value2array($options['templateids']);
			if(!is_null($options['extendoutput'])){
				$sql_parts['select']['templateid'] = 'ht.templateid';
			}

			$sql_parts['from']['ht'] = 'hosts_templates ht';
			$sql_parts['where'][] = DBcondition('ht.templateid', $options['templateids']);
			$sql_parts['where']['hht'] = 'h.hostid=ht.hostid';
		}

// itemids
		if(!is_null($options['itemids'])){
			zbx_value2array($options['itemids']);
			if(!is_null($options['extendoutput'])){
				$sql_parts['select']['itemid'] = 'i.itemid';
			}

			$sql_parts['from']['i'] = 'items i';
			$sql_parts['where'][] = DBcondition('i.itemid', $options['itemids']);
			$sql_parts['where']['hi'] = 'h.hostid=i.hostid';
		}

// triggerids
		if(!is_null($options['triggerids'])){
			zbx_value2array($options['triggerids']);
			if(!is_null($options['extendoutput'])){
				$sql_parts['select']['triggerid'] = 'f.triggerid';
			}

			$sql_parts['from']['f'] = 'functions f';
			$sql_parts['from']['i'] = 'items i';
			$sql_parts['where'][] = DBcondition('f.triggerid', $options['triggerids']);
			$sql_parts['where']['hi'] = 'h.hostid=i.hostid';
			$sql_parts['where']['fi'] = 'f.itemid=i.itemid';
		}

// graphids
		if(!is_null($options['graphids'])){
			zbx_value2array($options['graphids']);
			if(!is_null($options['extendoutput'])){
				$sql_parts['select']['graphid'] = 'gi.graphid';
			}

			$sql_parts['from']['gi'] = 'graphs_items gi';
			$sql_parts['from']['i'] = 'items i';
			$sql_parts['where'][] = DBcondition('gi.graphid', $options['graphids']);
			$sql_parts['where']['igi'] = 'i.itemid=gi.itemid';
			$sql_parts['where']['hi'] = 'h.hostid=i.hostid';

		}

// monitored_hosts, templated_hosts
		if(!is_null($options['monitored_hosts'])){
			$sql_parts['where'][] = 'h.status='.HOST_STATUS_MONITORED;
		}
		else if(!is_null($options['templated_hosts'])){
			$sql_parts['where'][] = 'h.status IN ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.','.HOST_STATUS_TEMPLATE.')';
		}
		else if(!is_null($options['proxy_hosts'])){
			$sql_parts['where'][] = 'h.status IN ('.HOST_STATUS_PROXY.')';
		}
		else{
			$sql_parts['where'][] = 'h.status IN ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.')';
		}

// with_items, with_monitored_items, with_historical_items
		if(!is_null($options['with_items'])){
			$sql_parts['where'][] = 'EXISTS (SELECT i.hostid FROM items i WHERE h.hostid=i.hostid )';
		}
		else if(!is_null($options['with_monitored_items'])){
			$sql_parts['where'][] = 'EXISTS (SELECT i.hostid FROM items i WHERE h.hostid=i.hostid AND i.status='.ITEM_STATUS_ACTIVE.')';
		}
		else if(!is_null($options['with_historical_items'])){
			$sql_parts['where'][] = 'EXISTS (SELECT i.hostid FROM items i WHERE h.hostid=i.hostid AND (i.status='.ITEM_STATUS_ACTIVE.' OR i.status='.ITEM_STATUS_NOTSUPPORTED.') AND i.lastvalue IS NOT NULL)';
		}

// with_triggers, with_monitored_triggers
		if(!is_null($options['with_triggers'])){
			$sql_parts['where'][] = 'EXISTS( '.
					' SELECT i.itemid '.
					' FROM items i, functions f, triggers t '.
					' WHERE i.hostid=h.hostid '.
						' AND i.itemid=f.itemid '.
						' AND f.triggerid=t.triggerid)';
		}
		else if(!is_null($options['with_monitored_triggers'])){
			$sql_parts['where'][] = 'EXISTS( '.
					' SELECT i.itemid '.
					' FROM items i, functions f, triggers t '.
					' WHERE i.hostid=h.hostid '.
						' AND i.status='.ITEM_STATUS_ACTIVE.
						' AND i.itemid=f.itemid '.
						' AND f.triggerid=t.triggerid '.
						' AND t.status='.TRIGGER_STATUS_ENABLED.')';
		}

// with_httptests, with_monitored_httptests
		if(!is_null($options['with_httptests'])){
			$sql_parts['where'][] = 'EXISTS( '.
					' SELECT a.applicationid '.
					' FROM applications a, httptest ht '.
					' WHERE a.hostid=h.hostid '.
						' AND ht.applicationid=a.applicationid)';
		}
		else if(!is_null($options['with_monitored_httptests'])){
			$sql_parts['where'][] = 'EXISTS( '.
					' SELECT a.applicationid '.
					' FROM applications a, httptest ht '.
					' WHERE a.hostid=h.hostid '.
						' AND ht.applicationid=a.applicationid '.
						' AND ht.status='.HTTPTEST_STATUS_ACTIVE.')';
		}

// with_graphs
		if(!is_null($options['with_graphs'])){
			$sql_parts['where'][] = 'EXISTS( '.
					' SELECT DISTINCT i.itemid '.
					' FROM items i, graphs_items gi '.
					' WHERE i.hostid=h.hostid '.
						' AND i.itemid=gi.itemid)';
		}

// extendoutput
		if(!is_null($options['extendoutput'])){
			$sql_parts['select']['hosts'] = 'h.*';
		}

// count
		if(!is_null($options['count'])){
			$options['sortfield'] = '';

			$sql_parts['select'] = array('count(DISTINCT h.hostid) as rowscount');
		}

// pattern
		if(!zbx_empty($options['pattern'])){
			if($options['extend_pattern']){
				$sql_parts['where'][] = ' ( '.
											'UPPER(h.host) LIKE '.zbx_dbstr('%'.strtoupper($options['pattern']).'%').' OR '.
											'h.ip LIKE '.zbx_dbstr('%'.$options['pattern'].'%').' OR '.
											'UPPER(h.dns) LIKE '.zbx_dbstr('%'.strtoupper($options['pattern']).'%').
										' ) ';
			}
			else{
				$sql_parts['where'][] = ' UPPER(h.host) LIKE '.zbx_dbstr('%'.strtoupper($options['pattern']).'%');
			}
		}

// order
// restrict not allowed columns for sorting
		$options['sortfield'] = str_in_array($options['sortfield'], $sort_columns) ? $options['sortfield'] : '';
		if(!zbx_empty($options['sortfield'])){
			$sortorder = ($options['sortorder'] == ZBX_SORT_DOWN)?ZBX_SORT_DOWN:ZBX_SORT_UP;

			$sql_parts['order'][] = 'h.'.$options['sortfield'].' '.$sortorder;

			if(!str_in_array('h.'.$options['sortfield'], $sql_parts['select']) && !str_in_array('h.*', $sql_parts['select'])){
				$sql_parts['select'][] = 'h.'.$options['sortfield'];
			}
		}

// limit
		if(zbx_ctype_digit($options['limit']) && $options['limit']){
			$sql_parts['limit'] = $options['limit'];
		}
//-------

		$hostids = array();

		$sql_parts['select'] = array_unique($sql_parts['select']);
		$sql_parts['from'] = array_unique($sql_parts['from']);
		$sql_parts['where'] = array_unique($sql_parts['where']);
		$sql_parts['order'] = array_unique($sql_parts['order']);

		$sql_select = '';
		$sql_from = '';
		$sql_where = '';
		$sql_order = '';
		if(!empty($sql_parts['select']))	$sql_select.= implode(',',$sql_parts['select']);
		if(!empty($sql_parts['from']))		$sql_from.= implode(',',$sql_parts['from']);
		if(!empty($sql_parts['where']))		$sql_where.= ' AND '.implode(' AND ',$sql_parts['where']);
		if(!empty($sql_parts['order']))		$sql_order.= ' ORDER BY '.implode(',',$sql_parts['order']);
		$sql_limit = $sql_parts['limit'];

		$sql = 'SELECT '.$sql_select.'
				FROM '.$sql_from.'
				WHERE '.DBin_node('h.hostid', $nodeids).
					$sql_where.
				$sql_order;
		$res = DBselect($sql, $sql_limit);
		while($host = DBfetch($res)){
			if($options['count'])
				$result = $host;
			else{
				$hostids[$host['hostid']] = $host['hostid'];

				if(is_null($options['extendoutput'])){
					$result[$host['hostid']] = array('hostid' => $host['hostid']);
				}
				else{
					if(!isset($result[$host['hostid']])) $result[$host['hostid']]= array();

					if($options['select_groups'] && !isset($result[$host['hostid']]['groups'])){
						$result[$host['hostid']]['groups'] = array();
					}

					if($options['select_templates'] && !isset($result[$host['hostid']]['templates'])){
						$result[$host['hostid']]['templates'] = array();
					}

					if($options['select_items'] && !isset($result[$host['hostid']]['items'])){
						$result[$host['hostid']]['items'] = array();
					}
					if($options['select_profile'] && !isset($result[$host['hostid']]['profile'])){
						$result[$host['hostid']]['profile'] = array();
						$result[$host['hostid']]['profile_ext'] = array();
					}

					if($options['select_triggers'] && !isset($result[$host['hostid']]['triggers'])){
						$result[$host['hostid']]['triggers'] = array();
					}

					if($options['select_graphs'] && !isset($result[$host['hostid']]['graphs'])){
						$result[$host['hostid']]['graphs'] = array();
					}

					if($options['select_applications'] && !isset($result[$host['hostid']]['applications'])){
						$result[$host['hostid']]['applications'] = array();
					}

					if($options['select_macros'] && !isset($result[$host['hostid']]['macros'])){
						$result[$host['hostid']]['macros'] = array();
					}

// groupids
					if(isset($host['groupid'])){
						if(!isset($result[$host['hostid']]['groups']))
							$result[$host['hostid']]['groups'] = array();

						$result[$host['hostid']]['groups'][$host['groupid']] = array('groupid' => $host['groupid']);
						unset($host['groupid']);
					}

// templateids
					if(isset($host['templateid'])){
						if(!isset($result[$host['hostid']]['templates']))
							$result[$host['hostid']]['templates'] = array();

						$result[$host['hostid']]['templates'][$host['templateid']] = array('templateid' => $host['templateid']);
						unset($host['templateid']);
					}

// triggerids
					if(isset($host['triggerid'])){
						if(!isset($result[$host['hostid']]['triggers']))
							$result[$host['hostid']]['triggers'] = array();

						$result[$host['hostid']]['triggers'][$host['triggerid']] = array('triggerid' => $host['triggerid']);
						unset($host['triggerid']);
					}

// itemids
					if(isset($host['itemid'])){
						if(!isset($result[$host['hostid']]['items']))
							$result[$host['hostid']]['items'] = array();

						$result[$host['hostid']]['items'][$host['itemid']] = array('itemid' => $host['itemid']);
						unset($host['itemid']);
					}

// graphids
					if(isset($host['graphid'])){
						if(!isset($result[$host['hostid']]['graphs']))
							$result[$host['hostid']]['graphs'] = array();

						$result[$host['hostid']]['graphs'][$host['graphid']] = array('graphid' => $host['graphid']);
						unset($host['graphid']);
					}

					$result[$host['hostid']] += $host;
				}
			}
		}

		if(is_null($options['extendoutput']) || !is_null($options['count'])){
			if(is_null($options['preservekeys'])) $result = zbx_cleanHashes($result);
			return $result;
		}

// Adding Objects
// Adding Groups
		if($options['select_groups']){
			$obj_params = array('extendoutput' => 1, 'hostids' => $hostids, 'preservekeys' => 1);
			$groups = CHostgroup::get($obj_params);
			foreach($groups as $groupid => $group){
				foreach($group['hosts'] as $num => $host){
					$result[$host['hostid']]['groups'][$groupid] = $group;
				}
			}
		}

// Adding Profiles
		if($options['select_profile']){
			$sql = 'SELECT hp.*
				FROM hosts_profiles hp
				WHERE '.DBcondition('hp.hostid', $hostids);
			$db_profile = DBselect($sql);
			while($profile = DBfetch($db_profile))
				$result[$profile['hostid']]['profile'] = $profile;


			$sql = 'SELECT hpe.*
				FROM hosts_profiles_ext hpe
				WHERE '.DBcondition('hpe.hostid', $hostids);
			$db_profile_ext = DBselect($sql);
			while($profile_ext = DBfetch($db_profile_ext))
				$result[$profile_ext['hostid']]['profile_ext'] = $profile_ext;
		}

// Adding Templates
		if($options['select_templates']){
			$obj_params = array('extendoutput' => 1, 'hostids' => $hostids, 'preservekeys' => 1);
			$templates = CTemplate::get($obj_params);
			foreach($templates as $templateid => $template){
				foreach($template['hosts'] as $num => $host){
					$result[$host['hostid']]['templates'][$templateid] = $template;
				}
			}
		}

// Adding Items
		if($options['select_items']){
			$obj_params = array('extendoutput' => 1, 'hostids' => $hostids, 'nopermissions' => 1, 'preservekeys' => 1);
			$items = CItem::get($obj_params);
			foreach($items as $itemid => $item){
				foreach($item['hosts'] as $num => $host){
					$result[$host['hostid']]['items'][$itemid] = $item;
				}
			}
		}

// Adding triggers
		if($options['select_triggers']){
			$obj_params = array('extendoutput' => 1, 'hostids' => $hostids, 'preservekeys' => 1);
			$triggers = CTrigger::get($obj_params);
			foreach($triggers as $triggerid => $trigger){
				foreach($trigger['hosts'] as $num => $host){
					$result[$host['hostid']]['triggers'][$triggerid] = $trigger;
				}
			}
		}

// Adding graphs
		if($options['select_graphs']){
			$obj_params = array('extendoutput' => 1, 'hostids' => $hostids, 'preservekeys' => 1);
			$graphs = CGraph::get($obj_params);
			foreach($graphs as $graphid => $graph){
				foreach($graph['hosts'] as $num => $host){
					$result[$host['hostid']]['graphs'][$graphid] = $graph;
				}
			}
		}

// Adding applications
		if($options['select_applications']){
			$obj_params = array('extendoutput' => 1, 'hostids' => $hostids, 'preservekeys' => 1);
			$applications = CApplication::get($obj_params);
			foreach($applications as $applicationid => $application){
				foreach($application['hosts'] as $num => $host){
					$result[$host['hostid']]['applications'][$applicationid] = $application;
				}
			}
		}

// Adding macros
		if($options['select_macros']){
			$obj_params = array('extendoutput' => 1, 'hostids' => $hostids, 'preservekeys' => 1);
			$macros = CUserMacro::get($obj_params);
			foreach($macros as $macroid => $macro){
				foreach($macro['hosts'] as $num => $host){
					$result[$host['hostid']]['macros'][$macroid] = $macro;
				}
			}
		}

// removing keys (hash -> array)
		if(is_null($options['preservekeys'])){
			$result = zbx_cleanHashes($result);
		}

	return $result;
	}

/**
 * Get Host ID by Host name
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param _array $host_data
 * @param string $host_data['host']
 * @return int|boolean
 */
	public static function getObjects($host_data){
		$result = array();
		$hostids = array();

		$sql = 'SELECT hostid '.
				' FROM hosts '.
				' WHERE host='.zbx_dbstr($host_data['host']).
					' AND '.DBin_node('hostid', false);

		$res = DBselect($sql);
		while($host = DBfetch($res)){
			$hostids[$host['hostid']] = $host['hostid'];
		}

		if(!empty($hostids))
			$result = self::get(array('hostids' => $hostids, 'extendoutput' => 1));

	return $result;
	}

/**
 * Add Host
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param _array $hosts multidimensional array with Hosts data
 * @param string $hosts['host'] Host name.
 * @param array $hosts['groups'] array of HostGroup objects with IDs add Host to.
 * @param int $hosts['port'] Port. OPTIONAL
 * @param int $hosts['status'] Host Status. OPTIONAL
 * @param int $hosts['useip'] Use IP. OPTIONAL
 * @param string $hosts['dns'] DNS. OPTIONAL
 * @param string $hosts['ip'] IP. OPTIONAL
 * @param int $hosts['proxy_hostid'] Proxy Host ID. OPTIONAL
 * @param int $hosts['useipmi'] Use IPMI. OPTIONAL
 * @param string $hosts['ipmi_ip'] IPMAI IP. OPTIONAL
 * @param int $hosts['ipmi_port'] IPMI port. OPTIONAL
 * @param int $hosts['ipmi_authtype'] IPMI authentication type. OPTIONAL
 * @param int $hosts['ipmi_privilege'] IPMI privilege. OPTIONAL
 * @param string $hosts['ipmi_username'] IPMI username. OPTIONAL
 * @param string $hosts['ipmi_password'] IPMI password. OPTIONAL
 * @return boolean
 */
	public static function create($hosts){
		$errors = array();
		$hosts = zbx_toArray($hosts);
		$hostids = array();
		$groupids = array();
		$result = false;

// CHECK IF HOSTS HAVE AT LEAST 1 GROUP {{{
		foreach($hosts as $hnum => $host){
			if(empty($host['groups'])){
				self::setError(__METHOD__, ZBX_API_ERROR_PARAMETERS, 'No groups for host [ '.$host['host'].' ]');
				return false;
			}
			$hosts[$hnum]['groups'] = zbx_toArray($hosts[$hnum]['groups']);

			foreach($hosts[$hnum]['groups'] as $gnum => $group){
				$groupids[$group['groupid']] = $group['groupid'];
			}
		}
// }}} CHECK IF HOSTS HAVE AT LEAST 1 GROUP


// PERMISSIONS {{{
		$upd_groups = CHostGroup::get(array(
			'groupids' => $groupids,
			'editable' => 1,
			'preservekeys' => 1));
		foreach($groupids as $gnum => $groupid){
			if(!isset($upd_groups[$groupid])){
				self::setError(__METHOD__, ZBX_API_ERROR_PERMISSIONS, 'You do not have enough rights for operation');
				return false;
			}
		}
// }}} PERMISSIONS

		self::BeginTransaction(__METHOD__);
		foreach($hosts as $num => $host){
			$host_db_fields = array(
				'host' => null,
				'port' => 0,
				'status' => 0,
				'useip' => 0,
				'dns' => '',
				'ip' => '0.0.0.0',
				'proxy_hostid' => 0,
				'useipmi' => 0,
				'ipmi_ip' => '',
				'ipmi_port' => 623,
				'ipmi_authtype' => 0,
				'ipmi_privilege' => 0,
				'ipmi_username' => '',
				'ipmi_password' => '',
			);

			if(!check_db_fields($host_db_fields, $host)){
				$result = false;
				$errors[] = array('errno' => ZBX_API_ERROR_PARAMETERS, 'error' => 'Wrong fields for host [ '.$host['host'].' ]');
				break;
			}

			if(!preg_match('/^'.ZBX_PREG_HOST_FORMAT.'$/i', $host['host'])){
				$result = false;
				$errors[] = array('errno' => ZBX_API_ERROR_PARAMETERS, 'error' => 'Incorrect characters used for Hostname [ '.$host['host'].' ]');
				break;
			}
			if(!empty($dns) && !preg_match('/^'.ZBX_PREG_DNS_FORMAT.'$/i', $host['dns'])){
				$result = false;
				$errors[] = array('errno' => ZBX_API_ERROR_PARAMETERS, 'error' => 'Incorrect characters used for DNS [ '.$host['dns'].' ]');
				break;
			}

			$host_exists = self::getObjects(array('host' => $host['host']));
			if(!empty($host_exists)){
				$result = false;
				$errors[] = array('errno' => ZBX_API_ERROR_PARAMETERS, 'error' => S_HOST.' [ '.$host['host'].' ] '.S_ALREADY_EXISTS_SMALL);
				break;
			}

			$hostid = get_dbid('hosts', 'hostid');
			$hostids[] = $hostid;
			$result = DBexecute('INSERT INTO hosts (hostid, proxy_hostid, host, port, status, useip, dns, ip, disable_until, available,'.
				'useipmi,ipmi_port,ipmi_authtype,ipmi_privilege,ipmi_username,ipmi_password,ipmi_ip) VALUES ('.
				$hostid.','.
				$host['proxy_hostid'].','.
				zbx_dbstr($host['host']).','.
				$host['port'].','.
				$host['status'].','.
				$host['useip'].','.
				zbx_dbstr($host['dns']).','.
				zbx_dbstr($host['ip']).
				',0,'.
				HOST_AVAILABLE_UNKNOWN.','.
				$host['useipmi'].','.
				$host['ipmi_port'].','.
				$host['ipmi_authtype'].','.
				$host['ipmi_privilege'].','.
				zbx_dbstr($host['ipmi_username']).','.
				zbx_dbstr($host['ipmi_password']).','.
				zbx_dbstr($host['ipmi_ip']).')'
			);
			if(!$result){
				break;
			}


			$host['hostid'] = $hostid;
			$options = array();
			$options['hosts'] = $host;
			$options['groups'] = $host['groups'];
			if(isset($host['templates']) && !is_null($host['templates']))
				$options['templates'] = $host['templates'];
			if(isset($host['macros']) && !is_null($host['macros']))
				$options['macros'] = $host['macros'];

			$result &= CHost::massAdd($options);

		}

		$result = self::EndTransaction($result, __METHOD__);

		if($result){
			$new_hosts = self::get(array('hostids' => $hostids, 'editable' => 1, 'extendoutput' => 1, 'nopermissions' => 1));
			return $new_hosts;
		}
		else{
			self::setMethodErrors(__METHOD__, $errors);
			return false;
		}
	}

/**
 * Update Host
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param _array $hosts multidimensional array with Hosts data
 * @param string $hosts['host'] Host name.
 * @param int $hosts['port'] Port. OPTIONAL
 * @param int $hosts['status'] Host Status. OPTIONAL
 * @param int $hosts['useip'] Use IP. OPTIONAL
 * @param string $hosts['dns'] DNS. OPTIONAL
 * @param string $hosts['ip'] IP. OPTIONAL
 * @param int $hosts['proxy_hostid'] Proxy Host ID. OPTIONAL
 * @param int $hosts['useipmi'] Use IPMI. OPTIONAL
 * @param string $hosts['ipmi_ip'] IPMAI IP. OPTIONAL
 * @param int $hosts['ipmi_port'] IPMI port. OPTIONAL
 * @param int $hosts['ipmi_authtype'] IPMI authentication type. OPTIONAL
 * @param int $hosts['ipmi_privilege'] IPMI privilege. OPTIONAL
 * @param string $hosts['ipmi_username'] IPMI username. OPTIONAL
 * @param string $hosts['ipmi_password'] IPMI password. OPTIONAL
 * @param string $hosts['groups'] groups
 * @return boolean
 */
	public static function update($hosts){
		$errors = array();
		$result = true;
	
		$hosts = zbx_toArray($hosts);
		$hostids = zbx_objectValues($hosts, 'hostid');


		$options = array(
			'hostids' => $hostids, 
			'editable' => 1, 
			'preservekeys' => 1
		);
		$upd_hosts = self::get($options);
		foreach($hosts as $gnum => $host){
			if(!isset($upd_hosts[$host['hostid']])){
				self::setError(__METHOD__, ZBX_API_ERROR_PERMISSIONS, 'You do not have enough rights for operation');
				return false;
			}
		}

		self::BeginTransaction(__METHOD__);

		foreach($hosts as $num => $host){	
			$host['hosts'] = $host;

			$result = self::massUpdate($host);
			if(!$result) break;
		}

		$result = self::EndTransaction($result, __METHOD__);

		if($result){
			$upd_hosts = self::get(array('hostids' => $hostids, 'extendoutput' => 1, 'nopermissions' => 1));
			return $upd_hosts;
		}
		else{
			self::setMethodErrors(__METHOD__, $errors);
			return false;
		}
	}

/**
 * Mass update hosts
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param _array $hosts multidimensional array with Hosts data
 * @param array $hosts['hosts'] Array of Host objects to update
 * @param string $hosts['fields']['host'] Host name.
 * @param array $hosts['fields']['groupids'] HostGroup IDs add Host to.
 * @param int $hosts['fields']['port'] Port. OPTIONAL
 * @param int $hosts['fields']['status'] Host Status. OPTIONAL
 * @param int $hosts['fields']['useip'] Use IP. OPTIONAL
 * @param string $hosts['fields']['dns'] DNS. OPTIONAL
 * @param string $hosts['fields']['ip'] IP. OPTIONAL
 * @param int $hosts['fields']['proxy_hostid'] Proxy Host ID. OPTIONAL
 * @param int $hosts['fields']['useipmi'] Use IPMI. OPTIONAL
 * @param string $hosts['fields']['ipmi_ip'] IPMAI IP. OPTIONAL
 * @param int $hosts['fields']['ipmi_port'] IPMI port. OPTIONAL
 * @param int $hosts['fields']['ipmi_authtype'] IPMI authentication type. OPTIONAL
 * @param int $hosts['fields']['ipmi_privilege'] IPMI privilege. OPTIONAL
 * @param string $hosts['fields']['ipmi_username'] IPMI username. OPTIONAL
 * @param string $hosts['fields']['ipmi_password'] IPMI password. OPTIONAL
 * @return boolean
 */
	public static function massUpdate($data){
		$transaction = false;

		$hosts = zbx_toArray($data['hosts']);
		$hostids = zbx_objectValues($hosts, 'hostid');
		
		try{
			$options = array(
				'hostids' => $hostids,
				'editable' => 1,
				'extendoutput' => 1,
				'preservekeys' => 1,
			);
			$upd_hosts = self::get($options);
		
			foreach($hosts as $hnum => $host){
				if(!isset($upd_hosts[$host['hostid']])){
					throw new APIException(ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
				}
			}
			
// CHECK IF HOSTS HAVE AT LEAST 1 GROUP {{{	
			if(isset($data['groups']) && empty($data['groups'])){
				throw new APIException(ZBX_API_ERROR_PARAMETERS, 'No groups for hosts');
			}
			$data['groups'] = zbx_toArray($data['groups']);	
// }}} CHECK IF HOSTS HAVE AT LEAST 1 GROUP

			$transaction = self::BeginTransaction(__METHOD__);

// UPDATE HOSTS PROPERTIES {{{
			if(isset($data['host'])){
				if(count($hosts) > 1){
					$error = array('errno' => ZBX_API_ERROR_PARAMETERS, 'error' => 'Wrong fields');
					throw new APIException($error);
				}
				
				$host_exists = self::getObjects(array('host' => $data['host']));
				$host_exists = reset($host_exists);
				$cur_host = reset($hosts);
				
				if(!empty($host_exists) && ($host_exists['hostid'] != $cur_host['hostid'])){
					$error = array('errno' => ZBX_API_ERROR_PARAMETERS, 'error' => S_HOST.' [ '.$data['host'].' ] '.S_ALREADY_EXISTS_SMALL);
					throw new APIException($error);
				}			
			}
			
			if(isset($data['host']) && !preg_match('/^'.ZBX_PREG_HOST_FORMAT.'$/i', $data['host'])){
				throw new APIException(ZBX_API_ERROR_PARAMETERS, 'Incorrect characters used for Hostname [ '.$data['host'].' ]');
			}
			if(isset($data['dns']) && !empty($dns) && !preg_match('/^'.ZBX_PREG_DNS_FORMAT.'$/i', $data['dns'])){
				throw new APIException(ZBX_API_ERROR_PARAMETERS, 'Incorrect characters used for DNS [ '.$data['dns'].' ]');
			}
				
			$sql_set = array();
			if(isset($data['proxy_hostid'])) $sql_set[] = 'proxy_hostid='.$data['proxy_hostid'];
			if(isset($data['host'])) $sql_set[] = 'host='.zbx_dbstr($data['host']);
			if(isset($data['port'])) $sql_set[] = 'port='.$data['port'];
			if(isset($data['status'])) $sql_set[] = 'status='.$data['status'];
			if(isset($data['useip'])) $sql_set[] = 'useip='.$data['useip'];
			if(isset($data['dns'])) $sql_set[] = 'dns='.zbx_dbstr($data['dns']);
			if(isset($data['ip'])) $sql_set[] = 'ip='.zbx_dbstr($data['ip']);
			if(isset($data['useipmi'])) $sql_set[] = 'useipmi='.$data['useipmi'];
			if(isset($data['ipmi_port'])) $sql_set[] = 'ipmi_port='.$data['ipmi_port'];
			if(isset($data['ipmi_authtype'])) $sql_set[] = 'ipmi_authtype='.$data['ipmi_authtype'];
			if(isset($data['ipmi_privilege'])) $sql_set[] = 'ipmi_privilege='.$data['ipmi_privilege'];
			if(isset($data['ipmi_username'])) $sql_set[] = 'ipmi_username='.zbx_dbstr($data['ipmi_username']);
			if(isset($data['ipmi_password'])) $sql_set[] = 'ipmi_password='.zbx_dbstr($data['ipmi_password']);
			if(isset($data['ipmi_ip'])) $sql_set[] = 'ipmi_ip='.zbx_dbstr($data['ipmi_ip']);

			if(!empty($sql_set)){
				$sql = 'UPDATE hosts SET ' . implode(', ', $sql_set) . ' WHERE '.DBcondition('hostid', $hostids);
				$result = DBexecute($sql);
				
				update_host_status($hostids, $host['status']);
			}
// }}} UPDATE HOSTS PROPERTIES


// UPDATE HOSTGROUPS LINKAGE {{{	
			if(isset($data['groups']) && !is_null($data['groups'])){
				$host_groups = CHostGroup::get(array('hostids' => $hostids));
				$host_groupids = zbx_objectValues($host_groups, 'groupid');
				$new_groupids = zbx_objectValues($data['groups'], 'groupid');

				$groups_to_add = array_diff($new_groupids, $host_groupids);

				if(!empty($groups_to_add)){
					$result = self::massAdd(array('hosts' => $hosts, 'groups' => $groups_to_add));
					if(!$result){
						throw new APIException(ZBX_API_ERROR_PARAMETERS, 'Cant add grooup');
					}
				}

				$groups_to_del = array_diff($host_groupids, $new_groupids);

				if(!empty($groups_to_del)){
					$result = self::massRemove(array('hosts' => $hosts, 'groups' => $groups_to_del));
					if(!$result){
						throw new APIException(ZBX_API_ERROR_PARAMETERS, 'Cant remove group');
					}
				}
			}
// }}} UPDATE HOSTGROUPS LINKAGE


			$data['templates_clear'] = isset($data['templates_clear']) ? zbx_toArray($data['templates_clear']) : array();
			$cleared_templateids = array();
			foreach($hostids as $hostid){
				foreach($data['templates_clear'] as $tpl){
					$result = unlink_template($hostid, $tpl['templateid'], false);
					if(!$result){
						throw new APIException(ZBX_API_ERROR_PARAMETERS, 'Cannot unlink template [ '.$tpl['templateid'].' ]');
					}
					$cleared_templateids[] = $tpl['templateid'];
				}
			}

		
// UPDATE TEMPLATE LINKAGE {{{
			if(isset($data['templates']) && !is_null($data['templates'])){
				$host_templates = CTemplate::get(array('hostids' => $hostids));
				$host_templateids = zbx_objectValues($host_templates, 'templateid');
				$new_templateids = zbx_objectValues($data['templates'], 'templateid');
				
				$templates_to_add = array_diff($new_templateids, $host_templateids);
				if(!empty($templates_to_add)){
					$result = self::massAdd(array('hosts' => $hosts, 'templates' => $templates_to_add));
					if(!$result){
						throw new APIException(ZBX_API_ERROR_PARAMETERS, 'Cant link template');
					}
				}

				$templates_to_del = array_diff($host_templateids, $new_templateids);
				$templates_to_del = array_diff($templates_to_del, $cleared_templateids);
				
				if(!empty($templates_to_del)){
					$result = self::massRemove(array('hosts' => $hosts, 'templates' => $templates_to_del));
					if(!$result){
						throw new APIException(ZBX_API_ERROR_PARAMETERS, 'Cant unlink template');
					}
				}
			}
// }}} UPDATE TEMPLATE LINKAGE


// UPDATE MACROS {{{	
	
			if(isset($data['macros']) && !is_null($data['macros'])){
				$host_macros = CUserMacro::get(array('hostids' => $hostids, 'extendoutput' => 1));

				$result = self::massAdd(array('hosts' => $hosts, 'macros' => $data['macros']));
				if(!$result){
					throw new APIException(ZBX_API_ERROR_PARAMETERS, 'Cant add macro');
				}
			
				$macros_to_del = array();
				foreach($host_macros as $hmacro){
					$del = true;
					foreach($data['macros'] as $nmacro){
						if($hmacro['macro'] == $nmacro['macro']){
							$del = false;
							break;
						}
					}
					if($del){
						$macros_to_del[] = $hmacro;
					}
				}

				if(!empty($macros_to_del)){
					$result = self::massRemove(array('hosts' => $hosts, 'macros' => $macros_to_del));
					if(!$result){
						throw new APIException(ZBX_API_ERROR_PARAMETERS, 'Cant remove macro');
					}
				}		
			}
			
			self::EndTransaction(true, __METHOD__);
// }}} UPDATE MACROS

			$upd_hosts = self::get(array('hostids' => $hostids, 'extendoutput' => 1, 'nopermissions' => 1));
			return $upd_hosts;

		}
		catch(APIException $e){
			if($transaction) self::EndTransaction(false, __METHOD__);
			
			$error = $e->getErrors();
			$error = reset($error);
			self::setError(__METHOD__, $e->getCode(), $error);
			return false;
		}
	}

/**
 * Add Hosts to HostGroups. All Hosts are added to all HostGroups.
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param array $data
 * @param array $data['groups']
 * @param array $data['hosts']
 * @return boolean
 */
	public static function massAdd($data){
		$errors = array();
		$result = true;
		
		$hosts = isset($data['hosts']) ? zbx_toArray($data['hosts']) : null;
		$hostids = is_null($hosts) ? array() : zbx_objectValues($hosts, 'hostid');
		
		self::BeginTransaction(__METHOD__);
		
		if(isset($data['groups'])){
			$options = array('groups' => zbx_toArray($data['groups']), 'hosts' => zbx_toArray($data['hosts']));
			$result = CHostGroup::massAdd($options);
		}

		if(isset($data['templates'])){
			$options = array('hosts' => zbx_toArray($data['hosts']), 'templates' => zbx_toArray($data['templates']));
			$result = CTemplate::massAdd($options);
		}
		
		if(isset($data['macros'])){
			$options = array('hosts' => zbx_toArray($data['hosts']), 'macros' => $data['macros']);
			$result = CUserMacro::massAdd($options);
		}
		

		$result = self::EndTransaction($result, __METHOD__);

		
		if($result !== false){
			return $result;
		}
		else{
			self::setMethodErrors(__METHOD__, $errors);
			return false;
		}
	}

/**
 * remove Hosts to HostGroups. All Hosts are added to all HostGroups.
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param array $data
 * @param array $data['groups']
 * @param array $data['hosts']
 * @return boolean
 */
	public static function massRemove($data){
		$errors = array();
		$result = true;
		
		$hosts = isset($data['hosts']) ? zbx_toArray($data['hosts']) : null;
		$hostids = is_null($hosts) ? array() : zbx_objectValues($hosts, 'hostid');
		
		self::BeginTransaction(__METHOD__);
		
		if(isset($data['groups'])){
			$options = array('groups' => zbx_toArray($data['groups']), 'hosts' => zbx_toArray($data['hosts']));
			$result = CHostGroup::massRemove($options);
		}
		
		if(isset($data['templates'])){
			$options = array('hosts' => zbx_toArray($data['hosts']), 'templates' => zbx_toArray($data['templates']));
			$result = CTemplate::massRemove($options);
		}
		
		if(isset($data['macros'])){
			$options = array('hosts' => zbx_toArray($data['hosts']), 'macros' => $data['macros']);
			$result = CUserMacro::massRemove($options);
		}
		

		$result = self::EndTransaction($result, __METHOD__);

		
		if($result !== false){
			return $result;
		}
		else{
			self::setMethodErrors(__METHOD__, $errors);
			return false;
		}	
	}

/**
 * Delete Host
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param array $hosts
 * @param array $hosts[0, ...]['hostid'] Host ID to delete
 * @return array|boolean
 */
	public static function delete($hosts){
		$hosts = zbx_toArray($hosts);
		$hostids = array();

		$del_hosts = self::get(array('hostids'=> zbx_objectValues($hosts, 'hostid'),
									'editable'=>1,
									'extendoutput'=>1,
									'preservekeys'=>1));
		if(empty($del_hosts)){
			self::setError(__METHOD__, ZBX_API_ERROR_PERMISSIONS, 'Host does not exist');
			return false;
		}

		foreach($hosts as $num => $host){
			if(!isset($del_hosts[$host['hostid']])){
				self::setError(__METHOD__, ZBX_API_ERROR_PERMISSIONS, S_NO_PERMISSION);
				return false;
			}

			$hostids[] = $host['hostid'];
			//add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_HOST, 'Host ['.$host['host'].']');
		}

		self::BeginTransaction(__METHOD__);
		if(!empty($hostids)){
			$result = delete_host($hostids, false);
		}
		else{
			self::setError(__METHOD__, ZBX_API_ERROR_PARAMETERS, 'Empty input parameter');
			$result = false;
		}

		$result = self::EndTransaction($result, __METHOD__);

		if($result){
			return zbx_cleanHashes($del_hosts);
		}
		else{
			self::setError(__METHOD__);
			return false;
		}
	}





}
?>
