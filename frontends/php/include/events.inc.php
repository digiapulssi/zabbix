<?php
/*
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
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
	function	event_source2str($sourceid)
	{
		switch($sourceid)
		{
			case EVENT_SOURCE_TRIGGERS:	return S_TRIGGERS;
			case EVENT_SOURCE_DISCOVERY:	return S_DISCOVERY;
			default:			return S_UNKNOWN;
		}
	}

	function	get_history_of_triggers_events($start,$num, $groupid=0, $hostid=0)
	{
		global $USER_DETAILS;
		
		$show_unknown = get_profile('web.events.show_unknown',0);
		
		$sql_from = $sql_cond = "";

	        $availiable_groups= get_accessible_groups_by_user($USER_DETAILS,PERM_READ_LIST, null, null, get_current_nodeid());
	        $availiable_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_LIST, null, null, get_current_nodeid());
		
		if($hostid > 0)
		{
			$sql_cond = " and h.hostid=".$hostid;
		}
		elseif($groupid > 0)
		{
			$sql_from = ", hosts_groups hg ";
			$sql_cond = " and h.hostid=hg.hostid and hg.groupid=".$groupid;
		}
		else
		{
			$sql_from = ", hosts_groups hg ";
			$sql_cond = " and h.hostid in (".$availiable_hosts.") ";
		}

		if($show_unknown == 0){
			$sql_cond.= ' AND e.value<>'.TRIGGER_VALUE_UNKNOWN.' ';
		}
	
		$result = DBselect('SELECT DISTINCT t.triggerid,t.priority,t.description,t.expression,h.host,e.clock,e.value,t.type '.
			' FROM events e, triggers t, functions f, items i, hosts h '.$sql_from.
			' WHERE '.DBin_node('t.triggerid').
				' AND e.objectid=t.triggerid and e.object='.EVENT_OBJECT_TRIGGER.
				' AND t.triggerid=f.triggerid and f.itemid=i.itemid '.
				' AND i.hostid=h.hostid '.$sql_cond.' and h.status='.HOST_STATUS_MONITORED.
			' ORDER BY e.clock DESC,h.host,t.priority,t.description,t.triggerid ',10*($start+$num)
			);
       
		$table = new CTableInfo(S_NO_EVENTS_FOUND); 
		$table->SetHeader(array(
				S_TIME,
				is_show_subnodes() ? S_NODE : null,
				$hostid == 0 ? S_HOST : null,
				S_DESCRIPTION,
				S_VALUE,
				S_SEVERITY
				));
		
		$accessible_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_ONLY);
		
		$col=0;
		$skip = $start;

		while(($row=DBfetch($result)) && ($col<$num)){
			
			if($skip > 0){
				$skip--;
				continue;
			}
			
			if($row["value"] == 0)
			{
				$value=new CCol(S_OFF,"off");
			}
			elseif($row["value"] == 1)
			{
				$value=new CCol(S_ON,"on");
			}
			else
			{
				$value=new CCol(S_UNKNOWN_BIG,"unknown");
			}	
			if(($show_unknown == 0) && (!event_initial_time($row,$show_unknown))) continue;
				
			$table->AddRow(array(
				date("Y.M.d H:i:s",$row["clock"]),
				get_node_name_by_elid($row['triggerid']),
				$hostid == 0 ? $row['host'] : null,
				new CLink(
					expand_trigger_description_by_data($row, ZBX_FLAG_EVENT),
					"tr_events.php?triggerid=".$row["triggerid"],"action"
					),
				$value,
				new CCol(get_severity_description($row["priority"]), get_severity_style($row["priority"]))));
			$col++;
		}
		return $table;
	}

	function	get_history_of_discovery_events($start,$num)
	{
		$db_events = DBselect('select distinct e.source,e.object,e.objectid,e.clock,e.value from events e'.
			' where e.source='.EVENT_SOURCE_DISCOVERY.' order by e.clock desc',
			10*($start+$num)
			);
       
		$table = new CTableInfo(S_NO_EVENTS_FOUND); 
		$table->SetHeader(array(S_TIME, S_IP, S_DESCRIPTION, S_STATUS));
		$col=0;
		
		$skip = $start;
		while(($event_data = DBfetch($db_events))&&($col<$num))
		{
			if($skip > 0) 
			{
				$skip--;
				continue;
			}

			if($event_data["value"] == 0)
			{
				$value=new CCol(S_UP,"off");
			}
			elseif($event_data["value"] == 1)
			{
				$value=new CCol(S_DOWN,"on");
			}
			else
			{
				$value=new CCol(S_UNKNOWN_BIG,"unknown");
			}


			switch($event_data['object'])
			{
				case EVENT_OBJECT_DHOST:
					$object_data = DBfetch(DBselect('select ip from dhosts where dhostid='.$event_data['objectid']));
					$description = SPACE;
					break;
				case EVENT_OBJECT_DSERVICE:
					$object_data = DBfetch(DBselect('select h.ip,s.type,s.port from dhosts h,dservices s '.
						' where h.dhostid=s.dhostid and s.dserviceid='.$event_data['objectid']));
					$description = S_SERVICE.': '.discovery_check_type2str($object_data['type']).'; '.
						S_PORT.': '.$object_data['port'];
					break;
				default:
					continue;
			}

			if(!$object_data) continue;


			$table->AddRow(array(
				date("Y.M.d H:i:s",$event_data["clock"]),
				$object_data['ip'],
				$description,
				$value));

			$col++;
		}
		return $table;
	}


/* function:
 *     event_initial_time
 *
 * description:
 *     returs 'true' if event is initial, otherwise false; 
 *
 * author: Aly
 */
function event_initial_time($row,$show_unknown=0){
	$sql_cond=($show_unknown == 0)?' AND value<>2 ':'';

	$events = array();
	$res = DBselect('SELECT MAX(e.clock) as clock, e.value '.
					' FROM events e'.
					' WHERE e.objectid='.$row['triggerid'].$sql_cond.
						' AND e.clock < '.$row['clock'].
						' AND e.object='.EVENT_OBJECT_TRIGGER.
					' GROUP BY e.value '.
					' ORDER BY e.clock DESC');
					
	while($rows = DBfetch($res)){
		$events[] = $rows;
	}
	if(!empty($events) && 
		($events[0]['value'] == $row['value']) && 
		($row['type'] == TRIGGER_MULT_EVENT_ENABLED) && 	
		($row['value'] == TRIGGER_VALUE_TRUE))
	{
		return true;
	}
	if(!empty($events) && ($events[0]['value'] == $row['value'])){
		return false;
	}
	return true;
}


function first_initial_eventid($row,$show_unknown=0){
	$sql_cond=($show_unknown == 0)?' AND value<>2 ':'';
	
	$events = array();

	$sql = 'SELECT MAX(eventid) as eventid,MAX(clock) as clock, value '.
					' FROM events as e '.
					' WHERE objectid='.$row['triggerid'].
						' AND clock < '.$row['lastchange'].$sql_cond.
						' AND object='.EVENT_OBJECT_TRIGGER.
					' GROUP BY value '.
					' ORDER BY clock DESC';
	$res = DBselect($sql);

	while($rows = DBfetch($res)){
		$events[] = $rows;
	}

	if(empty($events)){
		$sql = 'SELECT eventid,clock '.
				' FROM events as e '.
				' WHERE objectid='.$row['triggerid'].$sql_cond.
					' AND object='.EVENT_OBJECT_TRIGGER.
				' ORDER BY clock ASC';
		$res = DBselect($sql,1);
		
		while($rows = DBfetch($res)) return $rows['eventid'];
	}
	else if(!empty($events) && ($events[0]['value'] != $row['value'])){
		$clock = $events[0]['clock'];
		$sql = 'SELECT eventid,clock '.
				' FROM events as e '.
				' WHERE clock > '.$clock.
					' AND objectid='.$row['triggerid'].$sql_cond.
					' AND object='.EVENT_OBJECT_TRIGGER.
				' ORDER BY clock ASC';
				
		$res = DBselect($sql,1);
		
		while($rows = DBfetch($res)){
			return $rows['eventid'];
		}
		
		$row['lastchange'] = $clock;
		$row['value'] = $events[0]['value'];
		return first_initial_eventid($row,$show_unknown=0);
	}
	else if(!empty($events) && ($events[0]['value'] == $row['value'])){

		$clock = (count($events) > 1)?($events[1]['clock']):(0);

		$sql = 'SELECT eventid,clock '.
				' FROM events as e '.
				' WHERE clock > '.$clock.
					' AND objectid='.$row['triggerid'].$sql_cond.
					' AND object='.EVENT_OBJECT_TRIGGER.
					' AND value='.$row['value'].
				' ORDER BY clock ASC';

		$res = DBselect($sql,1);
		
		$rows = DBfetch($res);
		return $rows['eventid'];
	}
return false;
}

function not_ack_event($eventid){
	$sql = 'SELECT COUNT(*) as events '.
			' FROM events '.
			' WHERE eventid='.$eventid.
			  ' AND acknowledged=0';
	$row = DBfetch(DbSelect($sql));
	if($row['events'] == 1) return true;
return false;
}
?>
