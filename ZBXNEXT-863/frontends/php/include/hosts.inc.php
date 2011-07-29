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
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
	function setHostGroupInternal($groupids, $internal=ZBX_NOT_INTERNAL_GROUP){
		zbx_value2array($groupids);

		$sql = 'UPDATE groups SET internal='.$internal.' WHERE '.DBcondition('groupid', $groupids);
		$result = DBexecute($sql);
	return $result;
	}

	/**
	 * Get info about what host inventory fields we have, their numbers and names
	 * Example of usage:
	 *      $inventories = getHostInventories();
	 *      echo $inventories[1]['db_field']; // host_networks
	 *      echo $inventories[1]['title']; // Host networks
	 *      echo $inventories[1]['nr']; // 1
	 * @author Konstantin Buravcov
	 * @param bool $orderedByTitle whether an array should be ordered by field title, not by number
	 * @return array
	 */
	function getHostInventories($orderedByTitle=false){
		/**
		 * WARNING! Before modifying this array, make sure changes are synced with C
		 * C analog is located in function DBget_inventory_field() in src/libs/zbxdbhigh/db.c
		 */
		$inventoryFields = array(
			1 => array(
				'nr' => 1,
				'db_field' => 'type',
				'title' => _('Type'),
			),
			2 => array(
				'nr' => 2,
				'db_field' => 'type_full',
				'title' => _('Type (Full details)'),
			),
			3 => array(
				'nr' => 3,
				'db_field' => 'name',
				'title' => _('Name'),
			),
			4 => array(
				'nr' => 4,
				'db_field' => 'alias',
				'title' => _('Alias'),
			),
			5 => array(
				'nr' => 5,
				'db_field' => 'os',
				'title' => _('OS'),
			),
			6 => array(
				'nr' => 6,
				'db_field' => 'os_full',
				'title' => _('OS (Full details)'),
			),
			7 => array(
				'nr' => 7,
				'db_field' => 'os_short',
				'title' => _('OS (Short)'),
			),
			8 => array(
				'nr' => 8,
				'db_field' => 'serialno_a',
				'title' => _('Serial number A'),
			),
			9 => array(
				'nr' => 9,
				'db_field' => 'serialno_b',
				'title' => _('Serial number B'),
			),
			10 => array(
				'nr' => 10,
				'db_field' => 'tag',
				'title' => _('Tag'),
			),
			11 => array(
				'nr' => 11,
				'db_field' => 'asset_tag',
				'title' => _('Asset tag'),
			),
			12 => array(
				'nr' => 12,
				'db_field' => 'macaddress_a',
				'title' => _('MAC address A'),
			),
			13 => array(
				'nr' => 13,
				'db_field' => 'macaddress_b',
				'title' => _('MAC address B'),
			),
			14 => array(
				'nr' => 14,
				'db_field' => 'hardware',
				'title' => _('Hardware'),
			),
			15 => array(
				'nr' => 15,
				'db_field' => 'hardware_full',
				'title' => _('Hardware (Full details)'),
			),
			16 => array(
				'nr' => 16,
				'db_field' => 'software',
				'title' => _('Software'),
			),
			17 => array(
				'nr' => 17,
				'db_field' => 'software_full',
				'title' => _('Software (Full details)'),
			),
			18 => array(
				'nr' => 18,
				'db_field' => 'software_app_a',
				'title' => _('Software application A'),
			),
			19 => array(
				'nr' => 19,
				'db_field' => 'software_app_b',
				'title' => _('Software application B'),
			),
			20 => array(
				'nr' => 20,
				'db_field' => 'software_app_c',
				'title' => _('Software application C'),
			),
			21 => array(
				'nr' => 21,
				'db_field' => 'software_app_d',
				'title' => _('Software application D'),
			),
			22 => array(
				'nr' => 22,
				'db_field' => 'software_app_e',
				'title' => _('Software application E'),
			),
			23 => array(
				'nr' => 23,
				'db_field' => 'contact',
				'title' => _('Contact'),
			),
			24 => array(
				'nr' => 24,
				'db_field' => 'location',
				'title' => _('Location'),
			),
			25 => array(
				'nr' => 25,
				'db_field' => 'location_lat',
				'title' => _('Location latitude'),
			),
			26 => array(
				'nr' => 26,
				'db_field' => 'location_lon',
				'title' => _('Location longitude'),
			),
			27 => array(
				'nr' => 27,
				'db_field' => 'notes',
				'title' => _('Notes'),
			),
			28 => array(
				'nr' => 28,
				'db_field' => 'chassis',
				'title' => _('Chassis'),
			),
			29 => array(
				'nr' => 29,
				'db_field' => 'model',
				'title' => _('Model'),
			),
			30 => array(
				'nr' => 30,
				'db_field' => 'hw_arch',
				'title' => _('HW architecture'),
			),
			31 => array(
				'nr' => 31,
				'db_field' => 'vendor',
				'title' => _('Vendor'),
			),
			32 => array(
				'nr' => 32,
				'db_field' => 'contract_number',
				'title' => _('Contract number'),
			),
			33 => array(
				'nr' => 33,
				'db_field' => 'installer_name',
				'title' => _('Installer name'),
			),
			34 => array(
				'nr' => 34,
				'db_field' => 'deployment_status',
				'title' => _('Deployment status'),
			),
			35 => array(
				'nr' => 35,
				'db_field' => 'url_a',
				'title' => _('URL A'),
			),
			36 => array(
				'nr' => 36,
				'db_field' => 'url_b',
				'title' => _('URL B'),
			),
			37 => array(
				'nr' => 37,
				'db_field' => 'url_c',
				'title' => _('URL C'),
			),
			38 => array(
				'nr' => 38,
				'db_field' => 'host_networks',
				'title' => _('Host networks'),
			),
			39 => array(
				'nr' => 39,
				'db_field' => 'host_netmask',
				'title' => _('Host subnet mask'),
			),
			40 => array(
				'nr' => 40,
				'db_field' => 'host_router',
				'title' => _('Host router'),
			),
			41 => array(
				'nr' => 41,
				'db_field' => 'oob_ip',
				'title' => _('OOB IP address'),
			),
			42 => array(
				'nr' => 42,
				'db_field' => 'oob_netmask',
				'title' => _('OOB subnet mask'),
			),
			43 => array(
				'nr' => 43,
				'db_field' => 'oob_router',
				'title' => _('OOB router'),
			),
			44 => array(
				'nr' => 44,
				'db_field' => 'date_hw_purchase',
				'title' => _('Date HW purchased'),
			),
			45 => array(
				'nr' => 45,
				'db_field' => 'date_hw_install',
				'title' => _('Date HW installed'),
			),
			46 => array(
				'nr' => 46,
				'db_field' => 'date_hw_expiry',
				'title' => _('Date HW maintenance expires'),
			),
			47 => array(
				'nr' => 47,
				'db_field' => 'date_hw_decomm',
				'title' => _('Date hw decommissioned'),
			),
			48 => array(
				'nr' => 48,
				'db_field' => 'site_address_a',
				'title' => _('Site address A'),
			),
			49 => array(
				'nr' => 49,
				'db_field' => 'site_address_b',
				'title' => _('Site address B'),
			),
			50 => array(
				'nr' => 50,
				'db_field' => 'site_address_c',
				'title' => _('Site address C'),
			),
			51 => array(
				'nr' => 51,
				'db_field' => 'site_city',
				'title' => _('Site city'),
			),
			52 => array(
				'nr' => 52,
				'db_field' => 'site_state',
				'title' => _('Site state / province'),
			),
			53 => array(
				'nr' => 53,
				'db_field' => 'site_country',
				'title' => _('Site country'),
			),
			54 => array(
				'nr' => 54,
				'db_field' => 'site_zip',
				'title' => _('Site ZIP / postal'),
			),
			55 => array(
				'nr' => 55,
				'db_field' => 'site_rack',
				'title' => _('Site rack location'),
			),
			56 => array(
				'nr' => 56,
				'db_field' => 'site_notes',
				'title' => _('Site notes'),
			),
			57 => array(
				'nr' => 57,
				'db_field' => 'poc_1_name',
				'title' => _('Primary POC name'),
			),
			58 => array(
				'nr' => 58,
				'db_field' => 'poc_1_email',
				'title' => _('Primary POC email'),
			),
			59 => array(
				'nr' => 59,
				'db_field' => 'poc_1_phone_a',
				'title' => _('Primary POC phone A'),
			),
			60 => array(
				'nr' => 60,
				'db_field' => 'poc_1_phone_b',
				'title' => _('Primary POC phone B'),
			),
			61 => array(
				'nr' => 61,
				'db_field' => 'poc_1_cell',
				'title' => _('Primary POC cell'),
			),
			62 => array(
				'nr' => 62,
				'db_field' => 'poc_1_screen',
				'title' => _('Primary POC screen name'),
			),
			63 => array(
				'nr' => 63,
				'db_field' => 'poc_1_notes',
				'title' => _('Primary POC notes'),
			),
			64 => array(
				'nr' => 64,
				'db_field' => 'poc_2_name',
				'title' => _('Secondary POC name'),
			),
			65 => array(
				'nr' => 65,
				'db_field' => 'poc_2_email',
				'title' => _('Secondary POC email'),
			),
			66 => array(
				'nr' => 66,
				'db_field' => 'poc_2_phone_a',
				'title' => _('Secondary POC phone A'),
			),
			67 => array(
				'nr' => 67,
				'db_field' => 'poc_2_phone_b',
				'title' => _('Secondary POC phone B'),
			),
			68 => array(
				'nr' => 68,
				'db_field' => 'poc_2_cell',
				'title' => _('Secondary POC cell'),
			),
			69 => array(
				'nr' => 69,
				'db_field' => 'poc_2_screen',
				'title' => _('Secondary POC screen name'),
			),
			70 => array(
				'nr' => 70,
				'db_field' => 'poc_2_notes',
				'title' => _('Secondary POC notes'),
			)
		);

		// array is ordered by number by default, should we change that and order by title?
		if($orderedByTitle){
			function sortInventoriesByTitle($a, $b){
				return strcmp($a['title'], $b['title']);
			}
			uasort($inventoryFields, 'sortInventoriesByTitle');
		}

		return $inventoryFields;

		/*
		// code below look at schema and gets field numbers from there
		// we decided to use hard coded array (above) instead of this approach
		// uncomment this block to fall back to schema approach (if needed)

		$inventorySchema = DB::getSchema('host_inventory');
		$inventoryTitles = array(
			'type' => _('Type'),
			'type_full' => _('Type (Full details)'),
			'name' => _('Name'),
			'alias' => _('Alias'),
			'os' => _('OS'),
			'os_full' => _('OS (Full details)'),
			'os_short' => _('OS (Short)'),
			'serialno_a' => _('Serial number A'),
			'serialno_b' => _('Serial number B'),
			'tag' => _('Tag'),
			'asset_tag' => _('Asset tag'),
			'macaddress_a' => _('MAC address A'),
			'macaddress_b' => _('MAC address B'),
			'hardware' => _('Hardware'),
			'hardware_full' => _('Hardware (Full details)'),
			'software' => _('Software'),
			'software_full' => _('Software (Full details)'),
			'software_app_a' => _('Software application A'),
			'software_app_b' => _('Software application B'),
			'software_app_c' => _('Software application C'),
			'software_app_d' => _('Software application D'),
			'software_app_e' => _('Software application E'),
			'contact' => _('Contact'),
			'location' => _('Location'),
			'location_lat' => _('Location latitude'),
			'location_lon' => _('Location longitude'),
			'notes' => _('Notes'),
			'chassis' => _('Chassis'),
			'model' => _('Model'),
			'hw_arch' => _('HW architecture'),
			'vendor' => _('Vendor'),
			'contract_number' => _('Contract number'),
			'installer_name' => _('Installer name'),
			'deployment_status' => _('Deployment status'),
			'url_a' => _('URL A'),
			'url_b' => _('URL B'),
			'url_c' => _('URL C'),
			'host_networks' => _('Host networks'),
			'host_netmask' => _('Host subnet mask'),
			'host_router' => _('Host router'),
			'oob_ip' => _('OOB IP address'),
			'oob_netmask' => _('OOB subnet mask'),
			'oob_router' => _('OOB router'),
			'date_hw_purchase' => _('Date HW purchased'),
			'date_hw_install' => _('Date HW installed'),
			'date_hw_expiry' => _('Date HW maintenance expires'),
			'date_hw_decomm' => _('Date hw decommissioned'),
			'site_address_a' => _('Site address A'),
			'site_address_b' => _('Site address B'),
			'site_address_c' => _('Site address C'),
			'site_city' => _('Site city'),
			'site_state' => _('Site state / province'),
			'site_country' => _('Site country'),
			'site_zip' => _('Site ZIP / postal'),
			'site_rack' => _('Site rack location'),
			'site_notes' => _('Site notes'),
			'poc_1_name' => _('Primary POC name'),
			'poc_1_email' => _('Primary POC email'),
			'poc_1_phone_a' => _('Primary POC phone A'),
			'poc_1_phone_b' => _('Primary POC phone B'),
			'poc_1_cell' => _('Primary POC cell'),
			'poc_1_screen' => _('Primary POC screen name'),
			'poc_1_notes' => _('Primary POC notes'),
			'poc_2_name' => _('Secondary POC name'),
			'poc_2_email' => _('Secondary POC email'),
			'poc_2_phone_a' => _('Secondary POC phone A'),
			'poc_2_phone_b' => _('Secondary POC phone B'),
			'poc_2_cell' => _('Secondary POC cell'),
			'poc_2_screen' => _('Secondary POC screen name'),
			'poc_2_notes' => _('Secondary POC notes'),
		);
		$fieldNo = 0; // field numbering is critical for item linkage with host inventories
		foreach ($inventorySchema['fields'] as $field_name=>$field){
			// we are interested in id field
			if($field['type'] != 'id' && $field_name != 'inventory_mode'){
				$result[$fieldNo] = array(
					'db_field' => $field_name,
					// if no title is defined in getHostInventoryTitles() function for this field, we assume that title is equal to DB field name
					'title' => isset($inventoryTitles[$field_name]) ? $inventoryTitles[$field_name] : $field_name
				);
			}
			$fieldNo++;
		}

		return $result;
		*/

	}


	function get_hostgroup_by_groupid($groupid){
		$result=DBselect("select * from groups where groupid=".$groupid);
		$row=DBfetch($result);
		if($row){
			return $row;
		}
		error(_s('No host groups with groupid "%s".', $groupid));
		return  false;
	}

	function get_host_by_itemid($itemids){
		$res_array = is_array($itemids);
		zbx_value2array($itemids);

		$result = false;
		$hosts = array();

		$sql = 'SELECT i.itemid, h.* '.
				' FROM hosts h, items i '.
				' WHERE i.hostid=h.hostid '.
					' AND '.DBcondition('i.itemid',$itemids);

		$res=DBselect($sql);
		while($row=DBfetch($res)){
			$result = true;
			$hosts[$row['itemid']] = $row;
		}

		if(!$res_array){
			foreach($hosts as $itemid => $host){
				$result = $host;
			}
		}
		else if($result){
			$result = $hosts;
			unset($hosts);
		}

	return $result;
	}

	function get_host_by_hostid($hostid,$no_error_message=0){
		$sql='SELECT * FROM hosts WHERE hostid='.$hostid;
		$result=DBselect($sql);
		$row=DBfetch($result);
		if($row){
			return $row;
		}
		if($no_error_message == 0)
			error(_s('No host with hostid "%s".', $hostid));

	return	false;
	}

	function get_hosts_by_templateid($templateids){
		zbx_value2array($templateids);
		$sql = 'SELECT h.* '.
				' FROM hosts h, hosts_templates ht '.
				' WHERE h.hostid=ht.hostid '.
					' AND '.DBcondition('ht.templateid',$templateids);

	return DBselect($sql);
	}

// Update Host status

	function update_host_status($hostids,$status){
		$res = true;
		zbx_value2array($hostids);

//		$hosts = array();
		$sql = 'SELECT * '.
			' FROM hosts '.
			' WHERE '.DBcondition('hostid', $hostids).
				' AND status IN ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.')';
		$result = DBselect($sql);
		while($host=DBfetch($result)){
			if($status != $host['status']){
//				$hosts[$host['hostid']] = $host['hostid'];
				update_trigger_value_to_unknown_by_hostid($host['hostid']);
				$res = DBexecute('UPDATE hosts SET status='.$status.' WHERE hostid='.$host['hostid']);
				if($res){
					$host_new = $host;//get_host_by_hostid($host['hostid']);
					$host_new['status'] = $status;
					add_audit_ext(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_HOST, $host['hostid'], $host['host'], 'hosts', $host, $host_new);
				}
				info(S_UPDATED_STATUS_OF_HOST.' "'.$host['host'].'"');
			}
		}

/*
		if(!empty($hosts)){
			update_trigger_value_to_unknown_by_hostid($hosts);

			return	DBexecute('UPDATE hosts SET status='.$status.
							' WHERE '.DBcondition('hostid',$hosts).
								' AND status IN ('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.')'
						);
		}
		else{z
			return 1;
		}
//*/
	return $res;
	}

/*
 * Function: get_templates_by_hostid
 *
 * Description:
 *     Retrieve templates for specified host
 *
 * Author:
 *		Eugene Grigorjev (eugene.grigorjev@zabbix.com)
 *
 * Comments:
 *		mod by Aly
 */
	function get_templates_by_hostid($hostid){
		$result = array();
		$db_templates = DBselect('SELECT DISTINCT h.hostid,h.host '.
				' FROM hosts_templates ht '.
					' LEFT JOIN hosts h ON h.hostid=ht.templateid '.
				' WHERE ht.hostid='.$hostid.
				' ORDER BY h.host');

		while($template_data = DBfetch($db_templates)){
			$result[$template_data['hostid']] = $template_data['host'];
		}

	return $result;
	}

/*
 * Function: get_viewed_groups
 *
 * Description:
 *     Retrieve groups for dropdown
 *
 * Author:
 *		Artem "Aly" Suharev
 *
 * Comments:
 *
 */
function get_viewed_groups($perm, $options=array(), $nodeid=null, $sql=array()){
	global $USER_DETAILS;
	global $page;

	$def_sql = array(
				'select' =>	array('g.groupid','g.name'),
				'from' =>	array('groups g'),
				'where' =>	array(),
				'order' =>	array(),
			);

	$def_options = array(
				'deny_all' =>						0,
				'allow_all' =>						0,
				'select_first_group'=>				0,
				'select_first_group_if_empty'=>		0,
				'do_not_select' =>					0,
				'do_not_select_if_empty' =>			0,
				'monitored_hosts' =>				0,
				'templated_hosts' =>				0,
				'real_hosts' =>						0,
				'not_proxy_hosts' =>				0,
				'with_items' =>						0,
				'with_monitored_items' =>			0,
				'with_historical_items'=>			0,
				'with_triggers' =>					0,
				'with_monitored_triggers'=>			0,
				'with_httptests' =>					0,
				'with_monitored_httptests'=>		0,
				'with_graphs'=>						0,
				'only_current_node' =>				0
			);
	$def_options = zbx_array_merge($def_options, $options);

	$config = select_config();

	$dd_first_entry = $config['dropdown_first_entry'];
//	if($page['menu'] == 'config') $dd_first_entry = ZBX_DROPDOWN_FIRST_NONE;
	if($def_options['allow_all']) $dd_first_entry = ZBX_DROPDOWN_FIRST_ALL;
	if($def_options['deny_all']) $dd_first_entry = ZBX_DROPDOWN_FIRST_NONE;

	$result = array('original'=> -1, 'selected'=>0, 'groups'=> array(), 'groupids'=> array());
	$groups = &$result['groups'];
	$groupids = &$result['groupids'];

	$first_entry = ($dd_first_entry == ZBX_DROPDOWN_FIRST_NONE)?S_NOT_SELECTED_SMALL:S_ALL_SMALL;
	$groups['0'] = $first_entry;

	$_REQUEST['groupid'] = $result['original'] = get_request('groupid', -1);
	$_REQUEST['hostid'] = get_request('hostid', -1);
//-----
	if(is_null($nodeid)){
		if(!$def_options['only_current_node']) $nodeid = get_current_nodeid();
		else $nodeid = get_current_nodeid(false);
	}
//	$nodeid = is_null($nodeid)?get_current_nodeid(!$def_options['only_current_node']):$nodeid;
	$available_groups = get_accessible_groups_by_user($USER_DETAILS,$perm,PERM_RES_IDS_ARRAY,$nodeid,AVAILABLE_NOCACHE);

// nodes
	if(ZBX_DISTRIBUTED){
		$def_sql['select'][] = 'n.name as node_name';
		$def_sql['from'][] = 'nodes n';
		$def_sql['where'][] = 'n.nodeid='.DBid2nodeid('g.groupid');
		$def_sql['order'][] = 'node_name';
	}

// hosts
	if($def_options['monitored_hosts'])
		$def_sql['where'][] = 'h.status='.HOST_STATUS_MONITORED;
	else if($def_options['real_hosts'])
		$def_sql['where'][] = 'h.status IN('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.')';
	else if($def_options['templated_hosts'])
		$def_sql['where'][] = 'h.status='.HOST_STATUS_TEMPLATE;
	else if($def_options['not_proxy_hosts'])
		$def_sql['where'][] = 'h.status<>'.HOST_STATUS_PROXY;
	else
		$in_hosts = false;

	if(!isset($in_hosts)){
		$def_sql['from'][] = 'hosts_groups hg';
		$def_sql['from'][] = 'hosts h';
		$def_sql['where'][] = 'hg.groupid=g.groupid';
		$def_sql['where'][] = 'h.hostid=hg.hostid';
	}

// items
	if($def_options['with_items']){
		$def_sql['from'][] = 'hosts_groups hg';

		$def_sql['where'][] = 'hg.groupid=g.groupid';
		$def_sql['where'][] = 'EXISTS (SELECT i.hostid FROM items i WHERE hg.hostid=i.hostid )';
	}
	else if($def_options['with_monitored_items']){
		$def_sql['from'][] = 'hosts_groups hg';

		$def_sql['where'][] = 'hg.groupid=g.groupid';
		$def_sql['where'][] = 'EXISTS (SELECT i.hostid FROM items i WHERE hg.hostid=i.hostid AND i.status='.ITEM_STATUS_ACTIVE.')';
	}
	else if($def_options['with_historical_items']){
		$def_sql['from'][] = 'hosts_groups hg';

		$def_sql['where'][] = 'hg.groupid=g.groupid';
		$def_sql['where'][] = 'EXISTS (SELECT i.hostid FROM items i WHERE hg.hostid=i.hostid AND (i.status='.ITEM_STATUS_ACTIVE.' OR i.status='.ITEM_STATUS_NOTSUPPORTED.') AND i.lastvalue IS NOT NULL)';
	}

// triggers
	if($def_options['with_triggers']){
		$def_sql['from'][] = 'hosts_groups hg';

		$def_sql['where'][] = 'hg.groupid=g.groupid';
		$def_sql['where'][] = 'EXISTS( SELECT t.triggerid '.
									' FROM items i, functions f, triggers t'.
									' WHERE i.hostid=hg.hostid '.
										' AND f.itemid=i.itemid '.
										' AND t.triggerid=f.triggerid)';
	}
	else if($def_options['with_monitored_triggers']){
		$def_sql['from'][] = 'hosts_groups hg';

		$def_sql['where'][] = 'hg.groupid=g.groupid';
		$def_sql['where'][] = 'EXISTS( SELECT t.triggerid '.
									' FROM items i, functions f, triggers t'.
									' WHERE i.hostid=hg.hostid '.
										' AND i.status='.ITEM_STATUS_ACTIVE.
										' AND i.itemid=f.itemid '.
										' AND f.triggerid=t.triggerid '.
										' AND t.status='.TRIGGER_STATUS_ENABLED.')';
	}

// httptests
	if($def_options['with_httptests']){
		$def_sql['from'][] = 'hosts_groups hg';

		$def_sql['where'][] = 'hg.groupid=g.groupid';
		$def_sql['where'][] = 'EXISTS( SELECT a.applicationid '.
								' FROM applications a, httptest ht '.
								' WHERE a.hostid=hg.hostid '.
									' AND ht.applicationid=a.applicationid)';
	}
	else if($def_options['with_monitored_httptests']){
		$def_sql['from'][] = 'hosts_groups hg';

		$def_sql['where'][] = 'hg.groupid=g.groupid';
		$def_sql['where'][] = 'EXISTS( '.
								' SELECT a.applicationid '.
								' FROM applications a, httptest ht '.
								' WHERE a.hostid=hg.hostid '.
									' AND ht.applicationid=a.applicationid '.
									' AND ht.status='.HTTPTEST_STATUS_ACTIVE.')';
	}

// graphs
	if($def_options['with_graphs']){
		$def_sql['from'][] = 'hosts_groups hg';

		$def_sql['where'][] = 'hg.groupid=g.groupid';
		$def_sql['where'][] = 'EXISTS( SELECT DISTINCT i.itemid '.
									' FROM items i, graphs_items gi '.
									' WHERE i.hostid=hg.hostid '.
										' AND i.itemid=gi.itemid)';
	}

//-----
	$def_sql['order'][] = 'g.name';

	foreach($sql as $key => $value){
		zbx_value2array($value);

		if(isset($def_sql[$key])) $def_sql[$key] = zbx_array_merge($def_sql[$key], $value);
		else $def_sql[$key] = $value;
	}

	$def_sql['select'] = array_unique($def_sql['select']);
	$def_sql['from'] = array_unique($def_sql['from']);
	$def_sql['where'] = array_unique($def_sql['where']);
	$def_sql['order'] = array_unique($def_sql['order']);

	$sql_select = '';
	$sql_from = '';
	$sql_where = '';
	$sql_order = '';
	if(!empty($def_sql['select'])) $sql_select.= implode(',',$def_sql['select']);
	if(!empty($def_sql['from'])) $sql_from.= implode(',',$def_sql['from']);
	if(!empty($def_sql['where'])) $sql_where.= ' AND '.implode(' AND ',$def_sql['where']);
	if(!empty($def_sql['order'])) $sql_order.= implode(',',$def_sql['order']);

	$sql = 'SELECT DISTINCT '.$sql_select.
			' FROM '.$sql_from.
			' WHERE '.DBcondition('g.groupid',$available_groups).
				$sql_where.
			' ORDER BY '.$sql_order;
//SDI($sql);
	$res = DBselect($sql);
	while($group = DBfetch($res)){
		$groups[$group['groupid']] = $group['name'];
		$groupids[$group['groupid']] = $group['groupid'];

		if(bccomp($_REQUEST['groupid'],$group['groupid']) == 0) $result['selected'] = $group['groupid'];
	}

	$profile_groupid = CProfile::get('web.'.$page['menu'].'.groupid');
//-----
	if($def_options['do_not_select']){
		$result['selected'] = $_REQUEST['groupid'] = 0;
	}
	else if($def_options['do_not_select_if_empty'] && ($_REQUEST['groupid'] == -1)){
		$result['selected'] = $_REQUEST['groupid'] = 0;
	}
	else if(($def_options['select_first_group']) ||
			($def_options['select_first_group_if_empty'] && ($_REQUEST['groupid'] == -1) && is_null($profile_groupid)))
	{
		$first_groupid = next($groupids);
		reset($groupids);

		if($first_groupid !== FALSE)
			$_REQUEST['groupid'] = $result['selected'] = $first_groupid;
		else
			$_REQUEST['groupid'] = $result['selected'] = 0;
	}
	else{
		if($config['dropdown_first_remember']){
			if($_REQUEST['groupid'] == -1) $_REQUEST['groupid'] = is_null($profile_groupid)? '0':$profile_groupid;
			if(isset($groupids[$_REQUEST['groupid']])){
				$result['selected'] = $_REQUEST['groupid'];
			}
			else{
				$_REQUEST['groupid'] = $result['selected'];
			}
		}
		else{
			$_REQUEST['groupid'] = $result['selected'];
		}
	}

return $result;
}

/*
 * Function: get_viewed_hosts
 *
 * Description:
 *     Retrieve groups for dropdown
 *
 * Author:
 *		Artem "Aly" Suharev
 *
 * Comments:
 *
 */
function get_viewed_hosts($perm, $groupid=0, $options=array(), $nodeid=null, $sql=array()){
	global $USER_DETAILS;
	global $page;

	$userid = $USER_DETAILS['userid'];

	$def_sql = array(
				// hostname to avoid confusion with node name
				'select' =>	array('h.hostid','h.name as hostname'),
				'from' =>	array('hosts h'),
				'where' =>	array(),
				'order' =>	array(),
			);

	$def_options = array(
				'deny_all' =>				0,
				'allow_all' =>				0,
				'select_first_host' =>			0,
				'select_first_host_if_empty' =>		0,
				'select_host_on_group_switch' =>	0,
				'do_not_select' =>			0,
				'do_not_select_if_empty' =>		0,
				'monitored_hosts' =>			0,
				'templated_hosts' =>			0,
				'real_hosts' =>				0,
				'not_proxy_hosts' =>			0,
				'with_items' =>				0,
				'with_monitored_items' =>		0,
				'with_historical_items' =>		0,
				'with_triggers' =>			0,
				'with_monitored_triggers' =>		0,
				'with_httptests' =>			0,
				'with_monitored_httptests' =>		0,
				'with_graphs' =>			0,
				'only_current_node' =>			0
			);

	$def_options = zbx_array_merge($def_options, $options);

	$config = select_config();

	$dd_first_entry = $config['dropdown_first_entry'];
	if($def_options['allow_all']) $dd_first_entry = ZBX_DROPDOWN_FIRST_ALL;
	if($def_options['deny_all']) $dd_first_entry = ZBX_DROPDOWN_FIRST_NONE;
	if($dd_first_entry == ZBX_DROPDOWN_FIRST_ALL) $def_options['select_host_on_group_switch'] = 1;

	$result = array('original'=> -1, 'selected'=>0, 'hosts'=> array(), 'hostids'=> array());
	$hosts = &$result['hosts'];
	$hostids = &$result['hostids'];

	$first_entry = ($dd_first_entry == ZBX_DROPDOWN_FIRST_NONE)?S_NOT_SELECTED_SMALL:S_ALL_SMALL;
	$hosts['0'] = $first_entry;

	if(!is_array($groupid) && ($groupid == 0)){
		if($dd_first_entry == ZBX_DROPDOWN_FIRST_NONE){
			return $result;
		}
	}
	else{
		zbx_value2array($groupid);

		$def_sql['from'][] = 'hosts_groups hg';
		$def_sql['where'][] = DBcondition('hg.groupid',$groupid);
		$def_sql['where'][] = 'hg.hostid=h.hostid';
	}

	$_REQUEST['hostid'] = $result['original'] = get_request('hostid', -1);
//-----
	if(is_null($nodeid)){
		if(!$def_options['only_current_node']) $nodeid = get_current_nodeid();
		else $nodeid = get_current_nodeid(false);
	}

//$nodeid = is_null($nodeid)?get_current_nodeid($opt):$nodeid;
//$available_hosts = get_accessible_hosts_by_user($USER_DETAILS,$perm,PERM_RES_IDS_ARRAY,$nodeid,AVAILABLE_NOCACHE);

	if(USER_TYPE_SUPER_ADMIN != $USER_DETAILS['type']){
			$def_sql['from']['hg'] = 'hosts_groups hg';
			$def_sql['from']['r'] = 'rights r';
			$def_sql['from']['ug'] = 'users_groups ug';
			$def_sql['where']['hgh'] = 'hg.hostid=h.hostid';
			$def_sql['where'][] = 'r.id=hg.groupid ';
			$def_sql['where'][] = 'r.groupid=ug.usrgrpid';
			$def_sql['where'][] = 'ug.userid='.$userid;
			$def_sql['where'][] = 'r.permission>='.$perm;
			$def_sql['where'][] = 'NOT EXISTS( '.
									' SELECT hgg.groupid '.
									' FROM hosts_groups hgg, rights rr, users_groups gg '.
									' WHERE hgg.hostid=hg.hostid '.
										' AND rr.id=hgg.groupid '.
										' AND rr.groupid=gg.usrgrpid '.
										' AND gg.userid='.$userid.
										' AND rr.permission<'.$perm.')';
	}

// nodes
	if(ZBX_DISTRIBUTED){
		$def_sql['select'][] = 'n.name';
		$def_sql['from'][] = 'nodes n';
		$def_sql['where'][] = 'n.nodeid='.DBid2nodeid('h.hostid');
		$def_sql['order'][] = 'n.name';
	}

// hosts
	if($def_options['monitored_hosts'])
		$def_sql['where'][] = 'h.status='.HOST_STATUS_MONITORED;
	else if($def_options['real_hosts'])
		$def_sql['where'][] = 'h.status IN('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.')';
	else if($def_options['templated_hosts'])
		$def_sql['where'][] = 'h.status='.HOST_STATUS_TEMPLATE;
	else if($def_options['not_proxy_hosts'])
		$def_sql['where'][] = 'h.status<>'.HOST_STATUS_PROXY;


// items
	if($def_options['with_items']){
		$def_sql['where'][] = 'EXISTS (SELECT i.hostid FROM items i WHERE h.hostid=i.hostid )';
	}
	else if($def_options['with_monitored_items']){
		$def_sql['where'][] = 'EXISTS (SELECT i.hostid FROM items i WHERE h.hostid=i.hostid AND i.status='.ITEM_STATUS_ACTIVE.')';
	}
	else if($def_options['with_historical_items']){
		$def_sql['where'][] = 'EXISTS (SELECT i.hostid FROM items i WHERE h.hostid=i.hostid AND (i.status='.ITEM_STATUS_ACTIVE.' OR i.status='.ITEM_STATUS_NOTSUPPORTED.') AND i.lastvalue IS NOT NULL)';
	}


// triggers
	if($def_options['with_triggers']){
		$def_sql['where'][] = 'EXISTS( SELECT i.itemid '.
									' FROM items i, functions f, triggers t'.
									' WHERE i.hostid=h.hostid '.
										' AND i.itemid=f.itemid '.
										' AND f.triggerid=t.triggerid)';
	}
	else if($def_options['with_monitored_triggers']){
		$def_sql['where'][] = 'EXISTS( SELECT i.itemid '.
									' FROM items i, functions f, triggers t'.
									' WHERE i.hostid=h.hostid '.
										' AND i.status='.ITEM_STATUS_ACTIVE.
										' AND i.itemid=f.itemid '.
										' AND f.triggerid=t.triggerid '.
										' AND t.status='.TRIGGER_STATUS_ENABLED.')';
	}

// httptests
	if($def_options['with_httptests']){
		$def_sql['where'][] = 'EXISTS( SELECT a.applicationid '.
								' FROM applications a, httptest ht '.
								' WHERE a.hostid=h.hostid '.
									' AND ht.applicationid=a.applicationid)';
	}
	else if($def_options['with_monitored_httptests']){
		$def_sql['where'][] = 'EXISTS( SELECT a.applicationid '.
								' FROM applications a, httptest ht '.
								' WHERE a.hostid=h.hostid '.
									' AND ht.applicationid=a.applicationid '.
									' AND ht.status='.HTTPTEST_STATUS_ACTIVE.')';
	}

// graphs
	if($def_options['with_graphs']){
		$def_sql['where'][] = 'EXISTS( SELECT DISTINCT i.itemid '.
									' FROM items i, graphs_items gi '.
									' WHERE i.hostid=h.hostid '.
										' AND i.itemid=gi.itemid)';
	}
//------
	$def_sql['order'][] = 'h.name';

	foreach($sql as $key => $value){
		zbx_value2array($value);

		if(isset($def_sql[$key])) $def_sql[$key] = zbx_array_merge($def_sql[$key], $value);
		else $def_sql[$key] = $value;
	}

	$def_sql['select'] = array_unique($def_sql['select']);
	$def_sql['from'] = array_unique($def_sql['from']);
	$def_sql['where'] = array_unique($def_sql['where']);
	$def_sql['order'] = array_unique($def_sql['order']);

	$sql_select = '';
	$sql_from = '';
	$sql_where = '';
	$sql_order = '';
	if(!empty($def_sql['select'])) $sql_select.= implode(',',$def_sql['select']);
	if(!empty($def_sql['from'])) $sql_from.= implode(',',$def_sql['from']);
	if(!empty($def_sql['where'])) $sql_where.= ' AND '.implode(' AND ',$def_sql['where']);
	if(!empty($def_sql['order'])) $sql_order.= implode(',',$def_sql['order']);

	$sql = 'SELECT DISTINCT '.$sql_select.
			' FROM '.$sql_from.
			' WHERE '.DBin_node('h.hostid', $nodeid).
				$sql_where.
			' ORDER BY '.$sql_order;
	$res = DBselect($sql);
	while($host = DBfetch($res)){
		$hosts[$host['hostid']] = $host['hostname'];
		$hostids[$host['hostid']] = $host['hostid'];

		if(bccomp($_REQUEST['hostid'],$host['hostid']) == 0) $result['selected'] = $host['hostid'];
	}

	$profile_hostid = CProfile::get('web.'.$page['menu'].'.hostid');

//-----
	if($def_options['do_not_select']){
		$_REQUEST['hostid'] = $result['selected'] = 0;
	}
	else if($def_options['do_not_select_if_empty'] && ($_REQUEST['hostid'] == -1)){
		$_REQUEST['hostid'] = $result['selected'] = 0;
	}
	else if(($def_options['select_first_host']) ||
			($def_options['select_first_host_if_empty'] && ($_REQUEST['hostid'] == -1) && is_null($profile_hostid)) ||
			($def_options['select_host_on_group_switch'] && ($_REQUEST['hostid'] != -1) && (bccomp($_REQUEST['hostid'],$result['selected']) != 0)))
	{
		$first_hostid = next($hostids);
		reset($hostids);

		if($first_hostid !== FALSE)
			$_REQUEST['hostid'] = $result['selected'] = $first_hostid;
		else
			$_REQUEST['hostid'] = $result['selected'] = 0;
	}
	else{
		if($config['dropdown_first_remember']){
			if($_REQUEST['hostid'] == -1) $_REQUEST['hostid'] = is_null($profile_hostid)? '0':$profile_hostid;

			if(isset($hostids[$_REQUEST['hostid']])){
				$result['selected'] = $_REQUEST['hostid'];
			}
			else{
				$_REQUEST['hostid'] = $result['selected'];
			}
		}
		else{
			$_REQUEST['hostid'] = $result['selected'];
		}
	}
return $result;
}

/*
 * Function: validate_group_with_host
 *
 * Description:
 *     Check available groups and host by user permission
 *     and check current group an host relations
 *
 * Author:
 *		Aly
 *
 * Comments:
 *
 */
	function validate_group_with_host(&$PAGE_GROUPS, &$PAGE_HOSTS, $reset_host=true){
		global $page;

		$config = select_config();

		$dd_first_entry = $config['dropdown_first_entry'];

		$group_var = 'web.latest.groupid';
		$host_var = 'web.latest.hostid';

		$_REQUEST['groupid']    = get_request('groupid', CProfile::get($group_var, -1));
		$_REQUEST['hostid']     = get_request('hostid', CProfile::get($host_var, -1));

		if($_REQUEST['groupid'] > 0){
			if($_REQUEST['hostid'] > 0){
				$sql = 'SELECT groupid FROM hosts_groups WHERE hostid='.$_REQUEST['hostid'].' AND groupid='.$_REQUEST['groupid'];
				if(!DBfetch(DBselect($sql))){
					$_REQUEST['hostid'] = 0;
				}
			}
			else if($reset_host){
				$_REQUEST['hostid'] = 0;
			}
		}
		else{
			$_REQUEST['groupid'] = 0;

			if($reset_host && ($dd_first_entry == ZBX_DROPDOWN_FIRST_NONE)){
				$_REQUEST['hostid'] = 0;
			}
		}

		$PAGE_GROUPS['selected'] = $_REQUEST['groupid'];
		$PAGE_HOSTS['selected'] = $_REQUEST['hostid'];

		if(($PAGE_GROUPS['selected'] == 0) && ($dd_first_entry == ZBX_DROPDOWN_FIRST_NONE) && $reset_host){
			$PAGE_GROUPS['groupids'] = array();
		}

		if(($PAGE_HOSTS['selected'] == 0) && ($dd_first_entry == ZBX_DROPDOWN_FIRST_NONE) && $reset_host){
			$PAGE_HOSTS['hostids'] = array();
		}

		if($PAGE_GROUPS['original'] > -1)
			CProfile::update('web.'.$page['menu'].'.groupid', $_REQUEST['groupid'], PROFILE_TYPE_ID);

		if($PAGE_HOSTS['original'] > -1)
			CProfile::update('web.'.$page['menu'].'.hostid', $_REQUEST['hostid'], PROFILE_TYPE_ID);

		CProfile::update($group_var, $_REQUEST['groupid'], PROFILE_TYPE_ID);
		CProfile::update($host_var, $_REQUEST['hostid'], PROFILE_TYPE_ID);
	}


/* APPLICATIONS */


	function get_application_by_applicationid($applicationid,$no_error_message=0){
		$result = DBselect("select * from applications where applicationid=".$applicationid);
		$row=DBfetch($result);
		if($row)
		{
			return $row;
		}
		if($no_error_message == 0)
			error(S_NO_APPLICATION_WITH." id=[$applicationid]");
		return	false;

	}

	function get_applications_by_templateid($applicationid){
		return DBselect("select * from applications where templateid=".$applicationid);
	}

	function get_realhost_by_applicationid($applicationid){
		$application = get_application_by_applicationid($applicationid);
		if($application["templateid"] > 0)
			return get_realhost_by_applicationid($application["templateid"]);

		return get_host_by_applicationid($applicationid);
	}

	function get_host_by_applicationid($applicationid){
		$sql="select h.* from hosts h, applications a where a.hostid=h.hostid and a.applicationid=$applicationid";
		$result=DBselect($sql);
		$row=DBfetch($result);
		if($row)
		{
			return $row;
		}
		error(S_NO_HOST_WITH." applicationid=[$applicationid]");
		return	false;
	}

	function get_applications_by_hostid($hostid){
		return DBselect('select * from applications where hostid='.$hostid);
	}

	/*
	 * Function: validate_templates
	 *
	 * Description:
	 *     Check collisions between templates
	 *
	 * Author:
	 *     Eugene Grigorjev (eugene.grigorjev@zabbix.com)
	 *
	 * Comments:
	 *           $templateid_list can be numeric or numeric array
	 *
	 */
	function validate_templates($templateid_list){
		if(is_numeric($templateid_list))return true;
		if(!is_array($templateid_list))	return false;
		if(count($templateid_list)<2)	return true;

		$result = true;

		$sql = 'SELECT key_,count(*) as cnt '.
			' FROM items '.
			' WHERE '.DBcondition('hostid',$templateid_list).
			' GROUP BY key_ '.
			' ORDER BY cnt DESC';
		$res = DBselect($sql);
		while($db_cnt = DBfetch($res)){
			if($db_cnt['cnt']>1){
				$result &= false;
				error(S_TEMPLATE_WITH_ITEM_KEY.SPACE.'['.htmlspecialchars($db_cnt['key_']).']'.SPACE.S_ALREADY_LINKED_TO_HOST_SMALL);
			}
		}


		$sql = 'SELECT name,count(*) as cnt '.
			' FROM applications '.
			' WHERE '.DBcondition('hostid',$templateid_list).
			' GROUP BY name '.
			' ORDER BY cnt DESC';
		$res = DBselect($sql);
		while($db_cnt = DBfetch($res)){
			if($db_cnt['cnt']>1){
				$result &= false;
				error(S_TEMPLATE_WITH_APPLICATION.SPACE.'['.htmlspecialchars($db_cnt['name']).']'.SPACE.S_ALREADY_LINKED_TO_HOST_SMALL);
			}
		}

	return $result;
	}

	function getUnlinkableHosts($groupids=null,$hostids=null){
		zbx_value2array($groupids);
		zbx_value2array($hostids);

		$unlnk_hostids = array();

		$sql_where = '';
		if(!is_null($hostids)){
			$sql_where.= ' AND '.DBcondition('hg.hostid', $hostids);
		}

		if(!is_null($groupids)){
			$sql_where.= ' AND EXISTS ('.
							' SELECT hostid '.
							' FROM hosts_groups hgg '.
							' WHERE hgg.hostid = hg.hostid'.
								' AND '.DBcondition('hgg.groupid', $groupids).')';
		}

		$sql = 'SELECT hg.hostid, count(hg.groupid) as grp_count '.
				' FROM hosts_groups hg '.
				' WHERE hostgroupid>0 '.
				$sql_where.
				' GROUP BY hg.hostid '.
				' HAVING count(hg.groupid) > 1';
		$res = DBselect($sql);
		while($host = DBfetch($res)){
			$unlnk_hostids[$host['hostid']] = $host['hostid'];
		}
	return $unlnk_hostids;
	}

	function getDeletableHostGroups($groupids=null){
		zbx_value2array($groupids);

		$dlt_groupids = array();
		$hostids = getUnlinkableHosts($groupids);

		$sql_where = '';
		if(!is_null($groupids))
			$sql_where.= ' AND '.DBcondition('g.groupid', $groupids);

		$sql = 'SELECT DISTINCT g.groupid '.
				' FROM groups g '.
				' WHERE g.internal='.ZBX_NOT_INTERNAL_GROUP.
					$sql_where.
					' AND NOT EXISTS ('.
						'SELECT hg.groupid '.
						' FROM hosts_groups hg '.
						' WHERE g.groupid=hg.groupid '.
							(!empty($hostids) ? ' AND '.DBcondition('hg.hostid', $hostids, true) : '').
						')';
		$res = DBselect($sql);
		while($group = DBfetch($res)){
			$dlt_groupids[$group['groupid']] = $group['groupid'];
		}

	return $dlt_groupids;
	}

?>
