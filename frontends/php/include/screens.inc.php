<?php
/*
** ZABBIX
** Copyright (C) 2000-2010 SIA Zabbix
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
require_once('include/events.inc.php');
require_once('include/actions.inc.php');
require_once('include/js.inc.php');

?>
<?php
require_once('include/events.inc.php');
require_once('include/actions.inc.php');
require_once('include/js.inc.php');
?>
<?php
	function screen_accessible($screenid,$perm){
		global $USER_DETAILS;

		$result = false;

		if(DBfetch(DBselect('SELECT screenid FROM screens WHERE screenid='.$screenid.' AND '.DBin_node('screenid', get_current_nodeid($perm))))){
			$result = true;

			$db_result = DBselect('SELECT * FROM screens_items WHERE screenid='.$screenid);
			while(($ac_data = DBfetch($db_result)) && $result){
				if($ac_data['resourceid'] == 0) continue;

				switch($ac_data['resourcetype']){
					case SCREEN_RESOURCE_GRAPH:
						if(!isset($graphids)){
							$options = array();
							$options['nodeids'] = get_current_nodeid(true);

							if($perm == PERM_READ_WRITE) $options['editable'] = 1;
							$graphids = CGraph::get($options);
							$graphids = zbx_objectValues($graphids, 'graphid');
							$graphids = zbx_toHash($graphids, 'graphid');
						}

						$result &= isset($graphids[$ac_data['resourceid']]);
					break;
					case SCREEN_RESOURCE_SIMPLE_GRAPH:
						// break; /* use same processing as items */
					case SCREEN_RESOURCE_PLAIN_TEXT:
						if(!isset($itemid))
							$itemid = $ac_data['resourceid'];

						$options = array(
							'countOutput' => 1,
							'itemids' => $itemid,
							'webitems' => 1,
							'nodeids' => get_current_nodeid(true)
						);

						if($perm == PERM_READ_WRITE) $options['editable'] = 1;

						$items = CItem::get($options);
						if($items == 0) $result = false;

						unset($itemid);
						break;
					case SCREEN_RESOURCE_MAP:
						$result &= sysmap_accessible($ac_data['resourceid'], PERM_READ_ONLY);
						break;
					case SCREEN_RESOURCE_SCREEN:
						$result &= screen_accessible($ac_data['resourceid'], PERM_READ_ONLY);
						break;
					case SCREEN_RESOURCE_SERVER_INFO:
					case SCREEN_RESOURCE_HOSTS_INFO:
					case SCREEN_RESOURCE_TRIGGERS_INFO:
					case SCREEN_RESOURCE_TRIGGERS_OVERVIEW:
					case SCREEN_RESOURCE_HOSTGROUP_TRIGGERS:
					case SCREEN_RESOURCE_HOST_TRIGGERS:
					case SCREEN_RESOURCE_DATA_OVERVIEW:
					case SCREEN_RESOURCE_CLOCK:
					case SCREEN_RESOURCE_URL:
					case SCREEN_RESOURCE_ACTIONS:
					case SCREEN_RESOURCE_EVENTS:
						/* skip */
						break;
				}
			}
		}
		return $result;
	}

/*
	function add_screen($name,$hsize,$vsize){
		$screenid=get_dbid("screens","screenid");
		$sql='INSERT INTO screens (screenid,name,hsize,vsize) '.
				" VALUES ($screenid,".zbx_dbstr($name).",$hsize,$vsize)";
		$result=DBexecute($sql);

		if(!$result)
			return $result;

	return $screenid;
	}

	function update_screen($screenid,$name,$hsize,$vsize){
		$sql="update screens set name=".zbx_dbstr($name).",hsize=$hsize,vsize=$vsize where screenid=$screenid";
	return  DBexecute($sql);
	}
*/
	function delete_screen($screenid){
		$result=DBexecute('DELETE FROM screens_items where screenid='.$screenid);
		$result&=DBexecute('DELETE FROM screens_items where resourceid='.$screenid.' and resourcetype='.SCREEN_RESOURCE_SCREEN);
		$result&=DBexecute('DELETE FROM slides where screenid='.$screenid);
		$result&=DBexecute("DELETE FROM profiles WHERE idx='web.favorite.screenids' AND source='screenid' AND value_id=$screenid");
		$result&=DBexecute('DELETE FROM screens where screenid='.$screenid);
	return	$result;
	}

	function add_screen_item($resourcetype,$screenid,$x,$y,$resourceid,$width,$height,$colspan,$rowspan,$elements,$valign,$halign,$style,$url,$dynamic){
		$sql='DELETE FROM screens_items WHERE screenid='.$screenid.' and x='.$x.' and y='.$y;
		DBexecute($sql);

		$screenitemid=get_dbid("screens_items","screenitemid");
		$result=DBexecute('INSERT INTO screens_items '.
							'(screenitemid,resourcetype,screenid,x,y,resourceid,width,height,'.
							' colspan,rowspan,elements,valign,halign,style,url,dynamic) '.
						' VALUES '.
							"($screenitemid,$resourcetype,$screenid,$x,$y,$resourceid,$width,$height,$colspan,".
							"$rowspan,$elements,$valign,$halign,$style,".zbx_dbstr($url).",$dynamic)");

		if(!$result) return $result;
	return $screenitemid;
	}

	function update_screen_item($screenitemid,$resourcetype,$resourceid,$width,$height,$colspan,$rowspan,$elements,$valign,$halign,$style,$url,$dynamic){
		return  DBexecute("UPDATE screens_items SET ".
							"resourcetype=$resourcetype,"."resourceid=$resourceid,"."width=$width,".
							"height=$height,colspan=$colspan,rowspan=$rowspan,elements=$elements,".
							"valign=$valign,halign=$halign,style=$style,url=".zbx_dbstr($url).",dynamic=$dynamic".
						" WHERE screenitemid=$screenitemid");
	}

	function delete_screen_item($screenitemid){
		$sql="DELETE FROM screens_items where screenitemid=$screenitemid";
	return  DBexecute($sql);
	}

	function get_screen_by_screenid($screenid){
		$result = DBselect("select * from screens where screenid=$screenid");
		$row=DBfetch($result);
		if($row){
			return	$row;
		}
		// error("No screen with screenid=[$screenid]");
	return FALSE;
	}

	function check_screen_recursion($mother_screenid, $child_screenid){
		if((bccomp($mother_screenid , $child_screenid)==0))	return TRUE;

		$db_scr_items = DBselect("select resourceid from screens_items where".
			" screenid=$child_screenid and resourcetype=".SCREEN_RESOURCE_SCREEN);
		while($scr_item = DBfetch($db_scr_items)){
			if(check_screen_recursion($mother_screenid,$scr_item["resourceid"]))
				return TRUE;
		}
	return FALSE;
	}

	function get_slideshow($slideshowid, $step, $effectiveperiod=NULL){
		$sql = 'SELECT min(step) as min_step, max(step) as max_step '.
				' FROM slides '.
				' WHERE slideshowid='.$slideshowid;
		$slide_data = DBfetch(DBselect($sql));
		if(!$slide_data || is_null($slide_data['min_step'])){
			return false;
		}

		$step = $step % ($slide_data['max_step']+1);
		if(!isset($step) || $step < $slide_data['min_step'] || $step > $slide_data['max_step']){
			$curr_step = $slide_data['min_step'];
		}
		else{
			$curr_step = $step;
		}

		$sql = 'SELECT sl.* '.
				' FROM slides sl, slideshows ss '.
				' WHERE ss.slideshowid='.$slideshowid.
					' and sl.slideshowid=ss.slideshowid '.
					' and sl.step='.$curr_step;
		$slide_data = DBfetch(DBselect($sql));

	return $slide_data;
	}


	function slideshow_accessible($slideshowid, $perm){
		$result = false;

		$sql = 'SELECT slideshowid '.
				' FROM slideshows '.
				' WHERE slideshowid='.$slideshowid.
					' AND '.DBin_node('slideshowid', get_current_nodeid(null,$perm));
		if(DBselect($sql)){
			$result = true;

			$screenids = array();
			$sql = 'SELECT DISTINCT screenid '.
					' FROM slides '.
					' WHERE slideshowid='.$slideshowid;
			$db_screens = DBselect($sql);
			while($slide_data = DBfetch($db_screens)){
				$screenids[$slide_data['screenid']] = $slide_data['screenid'];
			}

			$options = array(
					'screenids' => $screenids
				);
			if($perm == PERM_READ_WRITE) $options['editable'] = 1;

			$screens = CScreen::get($options);
			$screens = zbx_toHash($screens, 'screenid');

			foreach($screenids as $snum => $screenid){
				if(!isset($screens[$screenid])) return false;
			}
		}

	return $result;
	}

	function get_slideshow_by_slideshowid($slideshowid){
		return DBfetch(DBselect('select * from slideshows where slideshowid='.$slideshowid));
	}

	function validate_slide(&$slide){
		if(!screen_accessible($slide['screenid'], PERM_READ_ONLY)) return false;
		if(!isset($slide['delay'])) $slide['delay'] = 0;

	return true;
	}

	function add_slideshow($name, $delay, $slides){
		foreach($slides as $slide){
			if(!validate_slide($slide)) return false;
		}

		$slideshowid = get_dbid('slideshows','slideshowid');
		$result = DBexecute('INSERT INTO slideshows (slideshowid,name,delay) '.
							' VALUES ('.$slideshowid.','.zbx_dbstr($name).','.$delay.')');

		$i = 0;
		foreach($slides as $num => $slide){
			$slideid = get_dbid('slides','slideid');

// TODO: resulve conflict about regression of delay per slide
			$result = DBexecute('INSERT INTO slides (slideid,slideshowid,screenid,step,delay) '.
								' VALUES ('.$slideid.','.$slideshowid.','.$slide['screenid'].','.($i++).','.$slide['delay'].')');
			if(!$result) return false;
		}

	return $slideshowid;
	}

	function update_slideshow($slideshowid, $name, $delay, $slides){
		foreach($slides as $slide){
			if(!validate_slide($slide))
				return false;
		}

		if(!$result = DBexecute('UPDATE slideshows SET name='.zbx_dbstr($name).',delay='.$delay.' WHERE slideshowid='.$slideshowid))
			return false;

		DBexecute('DELETE FROM slides where slideshowid='.$slideshowid);

		$i = 0;
		foreach($slides as $slide){
			$slideid = get_dbid('slides','slideid');
			if(!isset($slide['delay'])) $slide['delay'] = $delay;
			$result = DBexecute('INSERT INTO slides (slideid,slideshowid,screenid,step,delay) '.
				' VALUES ('.$slideid.','.$slideshowid.','.$slide['screenid'].','.($i++).','.$slide['delay'].')');
			if(!$result){
				return false;
			}
		}

	return true;
	}

	function delete_slideshow($slideshowid){

		$result = DBexecute('DELETE FROM slideshows where slideshowid='.$slideshowid);
		$result &= DBexecute('DELETE FROM slides where slideshowid='.$slideshowid);
		$result &= DBexecute("DELETE FROM profiles WHERE idx='web.favorite.screenids' AND source='slideshowid' AND value_id=$slideshowid");

		return $result;
	}


//Show screen cell containing plain text values
	function get_screen_plaintext($itemid,$elements,$style=0){

		if($itemid == 0){
			$table = new CTableInfo(S_ITEM_DOES_NOT_EXIST);
			$table->setHeader(array(S_TIMESTAMP,S_ITEM));
			return $table;
		}

		global $DB;

		$item=get_item_by_itemid($itemid);
		switch($item['value_type']){
			case ITEM_VALUE_TYPE_FLOAT:
				$history_table = 'history';
				$order_field = 'clock';
				break;
			case ITEM_VALUE_TYPE_UINT64:
				$history_table = 'history_uint';
				$order_field = 'clock';
				break;
			case ITEM_VALUE_TYPE_TEXT:
				$history_table = 'history_text';
				$order_field = 'id';
				break;
			case ITEM_VALUE_TYPE_LOG:
				$history_table = 'history_log';
				$order_field = 'id';
				break;
			default:
				$history_table = 'history_str';
				$order_field = 'clock';
				break;
		}

		$host = get_host_by_itemid($itemid);

		$table = new CTableInfo();
		$table->setHeader(array(S_TIMESTAMP,$host['host'].': '.item_description($item)));

		$sql='SELECT h.clock,h.value,i.valuemapid '.
				' FROM '.$history_table.' h, items i '.
				' WHERE h.itemid=i.itemid '.
					' AND i.itemid='.$itemid.
				' ORDER BY h.'.$order_field.' DESC';
		$result=DBselect($sql,$elements);
		while($row=DBfetch($result)){
			switch($item['value_type']){
				case ITEM_VALUE_TYPE_TEXT:
					if($DB['TYPE'] == 'ORACLE'){
						if(!isset($row['value'])){
							$row['value'] = '';
						}
					}
					/* do not use break */
				case ITEM_VALUE_TYPE_STR:
					if($style){
						$value = new CJSscript($row['value']);
					}
					else{
						$value = htmlspecialchars($row['value']);
//						$value = zbx_nl2br($value);
					}
					break;
				case ITEM_VALUE_TYPE_LOG:
					if($style){
						$value = new CJSscript($row['value']);
					}
					else{
						$value = htmlspecialchars($row['value']);
//						$value = zbx_nl2br($value);
					}
					break;
				default:
					$value = $row['value'];
					break;
			}

			if($row['valuemapid'] > 0)
				$value = replace_value_by_map($value, $row['valuemapid']);

			$pre = new CTag('pre', true);
			$pre->addItem($value);

			$table->addRow(array(zbx_date2str(S_SCREENS_PLAIN_TEXT_DATE_FORMAT,$row['clock']),	$pre));
		}

	return $table;
	}

/*
* Function:
*		check_dynamic_items
*
* Description:
*		Check whether there are dynamic items in the screen, if so return TRUE, else FALSE
*
* Author:
*		Aly
*/

	function check_dynamic_items($elid, $config=0){
		if($config == 0){
			$sql = 'SELECT screenitemid '.
			' FROM screens_items '.
			' WHERE screenid='.$elid.
				' AND dynamic='.SCREEN_DYNAMIC_ITEM;
		}
		else{
			$sql = 'SELECT si.screenitemid '.
			' FROM slides s, screens_items si '.
			' WHERE s.slideshowid='.$elid.
				' AND si.screenid=s.screenid'.
				' AND si.dynamic='.SCREEN_DYNAMIC_ITEM;
		}

		if(DBfetch(DBselect($sql,1))) return TRUE;
	return FALSE;
	}

	function get_screen_item_form(){
		global $USER_DETAILS;
		$available_hosts = get_accessible_hosts_by_user($USER_DETAILS,PERM_READ_ONLY,PERM_RES_IDS_ARRAY,get_current_nodeid(true));

		$form = new CFormTable(S_SCREEN_CELL_CONFIGURATION,'screenedit.php?screenid='.$_REQUEST['screenid']);
		$form->SetHelp('web.screenedit.cell.php');

		if(isset($_REQUEST['screenitemid'])){
			$iresult=DBSelect('SELECT * FROM screens_items'.
							' WHERE screenid='.$_REQUEST['screenid'].
								' AND screenitemid='.$_REQUEST['screenitemid']
							);

			$form->addVar('screenitemid',$_REQUEST['screenitemid']);
		}
		else{
			$form->addVar('x',$_REQUEST['x']);
			$form->addVar('y',$_REQUEST['y']);
		}

		if(isset($_REQUEST['screenitemid']) && !isset($_REQUEST['form_refresh'])){

			$irow = DBfetch($iresult);
			$resourcetype	= $irow['resourcetype'];
			$resourceid	= $irow['resourceid'];
			$width		= $irow['width'];
			$height		= $irow['height'];
			$colspan	= $irow['colspan'];
			$rowspan	= $irow['rowspan'];
			$elements	= $irow['elements'];
			$valign		= $irow['valign'];
			$halign		= $irow['halign'];
			$style		= $irow['style'];
			$url		= $irow['url'];
			$dynamic	= $irow['dynamic'];
		}
		else{
			$resourcetype	= get_request('resourcetype',	0);
			$resourceid	= get_request('resourceid',	0);
			$width		= get_request('width',		500);
			$height		= get_request('height',		100);
			$colspan	= get_request('colspan',	0);
			$rowspan	= get_request('rowspan',	0);
			$elements	= get_request('elements',	25);
			$valign		= get_request('valign',		VALIGN_DEFAULT);
			$halign		= get_request('halign',		HALIGN_DEFAULT);
			$style		= get_request('style',		0);
			$url		= get_request('url',		'');
			$dynamic	= get_request('dynamic',	SCREEN_SIMPLE_ITEM);
		}

		$form->addVar('screenid',$_REQUEST['screenid']);
// a-z order!!!
		$cmbRes = new CCombobox('resourcetype',$resourcetype,'submit()');
		$cmbRes->addItem(SCREEN_RESOURCE_CLOCK,				S_CLOCK);
		$cmbRes->addItem(SCREEN_RESOURCE_DATA_OVERVIEW,		S_DATA_OVERVIEW);
		$cmbRes->addItem(SCREEN_RESOURCE_GRAPH,				S_GRAPH);
		$cmbRes->addItem(SCREEN_RESOURCE_ACTIONS,			S_HISTORY_OF_ACTIONS);
		$cmbRes->addItem(SCREEN_RESOURCE_EVENTS,			S_HISTORY_OF_EVENTS);
		$cmbRes->addItem(SCREEN_RESOURCE_HOSTS_INFO,		S_HOSTS_INFO);
		$cmbRes->addItem(SCREEN_RESOURCE_MAP,				S_MAP);
		$cmbRes->addItem(SCREEN_RESOURCE_PLAIN_TEXT,		S_PLAIN_TEXT);
		$cmbRes->addItem(SCREEN_RESOURCE_SCREEN,			S_SCREEN);
		$cmbRes->addItem(SCREEN_RESOURCE_SERVER_INFO,		S_SERVER_INFO);
		$cmbRes->addItem(SCREEN_RESOURCE_SIMPLE_GRAPH,		S_SIMPLE_GRAPH);
		$cmbRes->addItem(SCREEN_RESOURCE_HOSTGROUP_TRIGGERS,	S_STATUS_OF_HOSTGROUP_TRIGGERS);
		$cmbRes->addItem(SCREEN_RESOURCE_HOST_TRIGGERS,			S_STATUS_OF_HOST_TRIGGERS);
		$cmbRes->addItem(SCREEN_RESOURCE_SYSTEM_STATUS,     S_SYSTEM_STATUS);
		$cmbRes->addItem(SCREEN_RESOURCE_TRIGGERS_INFO,		S_TRIGGERS_INFO);
		$cmbRes->addItem(SCREEN_RESOURCE_TRIGGERS_OVERVIEW,	S_TRIGGERS_OVERVIEW);
		$cmbRes->addItem(SCREEN_RESOURCE_URL,				S_URL);
		$form->addRow(S_RESOURCE,$cmbRes);

		if($resourcetype == SCREEN_RESOURCE_GRAPH){
// User-defined graph
			$resourceid = graph_accessible($resourceid)?$resourceid:0;

			$caption = '';
			$id=0;

			if($resourceid > 0){
				$result = DBselect('SELECT DISTINCT g.graphid,g.name,n.name as node_name, h.host'.
						' FROM graphs g '.
							' LEFT JOIN graphs_items gi ON g.graphid=gi.graphid '.
							' LEFT JOIN items i ON gi.itemid=i.itemid '.
							' LEFT JOIN hosts h ON h.hostid=i.hostid '.
							' LEFT JOIN nodes n ON n.nodeid='.DBid2nodeid('g.graphid').
						' WHERE g.graphid='.$resourceid);

				while($row=DBfetch($result)){
					$row['node_name'] = isset($row['node_name']) ? '('.$row['node_name'].') ' : '';
					$caption = $row['node_name'].$row['host'].':'.$row['name'];
					$id = $resourceid;
				}
			}

			$form->addVar('resourceid',$id);

			$textfield = new CTextbox('caption',$caption,75,'yes');
			$selectbtn = new CButton('select',S_SELECT,"javascript: return PopUp('popup.php?writeonly=1&dstfrm=".$form->getName()."&dstfld1=resourceid&dstfld2=caption&srctbl=graphs&srcfld1=graphid&srcfld2=name',800,450);");
			$selectbtn->setAttribute('onmouseover',"javascript: this.style.cursor = 'pointer';");

			$form->addRow(S_GRAPH_NAME,array($textfield,SPACE,$selectbtn));

		}
		else if($resourcetype == SCREEN_RESOURCE_SIMPLE_GRAPH){
// Simple graph
			$caption = '';
			$id=0;

			if($resourceid > 0){
				$sql = 'SELECT n.name as node_name,h.host,i.description,i.itemid,i.key_ '.
						' FROM hosts h,items i '.
							' LEFT JOIN nodes n on n.nodeid='.DBid2nodeid('i.itemid').
						' WHERE h.hostid=i.hostid '.
							' AND h.status='.HOST_STATUS_MONITORED.
							' AND i.status='.ITEM_STATUS_ACTIVE.
							' AND '.DBcondition('i.hostid',$available_hosts).
							' AND i.itemid='.$resourceid;
				$result=DBselect($sql);
				while($row=DBfetch($result)){
					$description_ = item_description($row);
					$row["node_name"] = isset($row["node_name"]) ? "(".$row["node_name"].") " : '';

					$caption = $row['node_name'].$row['host'].': '.$description_;
					$id = $resourceid;
				}
			}

			$form->addVar('resourceid',$id);

			$textfield = new Ctextbox('caption',$caption,75,'yes');
			$selectbtn = new CButton('select',S_SELECT,"javascript: return PopUp('popup.php?writeonly=1&dstfrm=".$form->getName()."&dstfld1=resourceid&dstfld2=caption&srctbl=simple_graph&srcfld1=itemid&srcfld2=description',800,450);");
			$selectbtn->setAttribute('onmouseover',"javascript: this.style.cursor = 'pointer';");

			$form->addRow(S_PARAMETER,array($textfield,SPACE,$selectbtn));
		}
		else if($resourcetype == SCREEN_RESOURCE_MAP){
// Map
			$caption = '';
			$id=0;

			if($resourceid > 0){
				$sql = 'SELECT n.name as node_name, s.sysmapid,s.name '.
							' FROM sysmaps s'.
								' LEFT JOIN nodes n ON n.nodeid='.DBid2nodeid('s.sysmapid').
							' WHERE s.sysmapid='.$resourceid;
				$result=DBselect($sql);
				while($row=DBfetch($result)){
					if(!sysmap_accessible($row['sysmapid'],PERM_READ_ONLY)) continue;

					$row['node_name'] = isset($row['node_name']) ? '('.$row['node_name'].') ' : '';
					$caption = $row['node_name'].$row['name'];
					$id = $resourceid;
				}
			}

			$form->addVar('resourceid',$id);
			$textfield = new Ctextbox('caption',$caption,60,'yes');

			$selectbtn = new Cbutton('select',S_SELECT,"javascript: return PopUp('popup.php?writeonly=1&dstfrm=".$form->getName()."&dstfld1=resourceid&dstfld2=caption&srctbl=sysmaps&srcfld1=sysmapid&srcfld2=name',400,450);");
			$selectbtn->setAttribute('onmouseover',"javascript: this.style.cursor = 'pointer';");

			$form->addRow(S_PARAMETER,array($textfield,SPACE,$selectbtn));

		}
		else if($resourcetype == SCREEN_RESOURCE_PLAIN_TEXT){
// Plain text
			$caption = '';
			$id=0;

			if($resourceid > 0){
				$sql = 'SELECT n.name as node_name,h.host,i.description,i.itemid,i.key_ '.
						' FROM hosts h,items i '.
							' LEFT JOIN nodes n on n.nodeid='.DBid2nodeid('i.itemid').
						' WHERE h.hostid=i.hostid '.
							' AND h.status='.HOST_STATUS_MONITORED.
							' AND i.status='.ITEM_STATUS_ACTIVE.
							' AND '.DBcondition('i.hostid',$available_hosts).
							' AND i.itemid='.$resourceid;
				$result=DBselect($sql);
				while($row=DBfetch($result)){
					$description_=item_description($row);
					$row["node_name"] = isset($row["node_name"]) ? '('.$row["node_name"].') ' : '';

					$caption = $row['node_name'].$row['host'].': '.$description_;
					$id = $resourceid;
				}
			}

			$form->addVar('resourceid',$id);

			$textfield = new CTextbox('caption',$caption,75,'yes');
			$selectbtn = new CButton('select',S_SELECT,"javascript: return PopUp('popup.php?writeonly=1&dstfrm=".$form->getName()."&dstfld1=resourceid&dstfld2=caption&srctbl=plain_text&srcfld1=itemid&srcfld2=description',800,450);");
			$selectbtn->setAttribute('onmouseover',"javascript: this.style.cursor = 'pointer';");

			$form->addRow(S_PARAMETER,array($textfield,SPACE,$selectbtn));
			$form->addRow(S_SHOW_LINES, new CNumericBox('elements',$elements,2));
			$form->addRow(S_SHOW_TEXT_AS_HTML, new CCheckBox('style',$style,null,1));
		}
		else if(uint_in_array($resourcetype,array(SCREEN_RESOURCE_HOSTGROUP_TRIGGERS,SCREEN_RESOURCE_HOST_TRIGGERS))){
// Status of triggers
			$caption = '';
			$id=0;

			if(SCREEN_RESOURCE_HOSTGROUP_TRIGGERS == $resourcetype){
				if($resourceid > 0){
					$options = array(
						'groupids' => $resourceid,
						'output' => API_OUTPUT_EXTEND,
						'editable' => 1
					);

					$groups = CHostgroup::get($options);
					foreach($groups as $gnum => $group){
						$caption = get_node_name_by_elid($group['groupid'], true, ':').$group['name'];
						$id = $resourceid;
					}
				}

				$form->addVar('resourceid',$id);

				$textfield = new CTextbox('caption',$caption,60,'yes');
				$selectbtn = new CButton('select',S_SELECT,"javascript: return PopUp('popup.php?writeonly=1&dstfrm=".$form->getName()."&dstfld1=resourceid&dstfld2=caption&srctbl=host_group&srcfld1=groupid&srcfld2=name',800,450);");
				$selectbtn->setAttribute('onmouseover',"javascript: this.style.cursor = 'pointer';");

				$form->addRow(S_GROUP,array($textfield,SPACE,$selectbtn));

			}
			else{
				if($resourceid > 0){
					$options = array(
						'hostids' => $resourceid,
						'output' => API_OUTPUT_EXTEND,
						'editable' => 1
					);

					$hosts = CHost::get($options);
					foreach($hosts as $hnum => $host){
						$caption = get_node_name_by_elid($host['hostid'], true, ':').$host['host'];
						$id = $resourceid;
					}
				}

				$form->addVar('resourceid',$id);

				$textfield = new CTextbox('caption',$caption,60,'yes');
				$selectbtn = new CButton('select',S_SELECT,"javascript: return PopUp('popup.php?writeonly=1&dstfrm=".$form->getName()."&dstfld1=resourceid&dstfld2=caption&srctbl=hosts&srcfld1=hostid&srcfld2=host',800,450);");
				$selectbtn->setAttribute('onmouseover',"javascript: this.style.cursor = 'pointer';");

				$form->addRow(S_HOST,array($textfield,SPACE,$selectbtn));
			}

			$form->addRow(S_SHOW_LINES, new CNumericBox('elements',$elements,2));
		}
		else if(uint_in_array($resourcetype,array(SCREEN_RESOURCE_EVENTS,SCREEN_RESOURCE_ACTIONS))){
// History of actions
// History of events
			$form->addRow(S_SHOW_LINES, new CNumericBox('elements',$elements,2));
			$form->addVar('resourceid',0);
		}
		else if(uint_in_array($resourcetype,array(SCREEN_RESOURCE_TRIGGERS_OVERVIEW,SCREEN_RESOURCE_DATA_OVERVIEW))){
// Overviews
			$caption = '';
			$id=0;

			if($resourceid > 0){
				$options = array(
					'groupids' => $resourceid,
					'output' => API_OUTPUT_EXTEND,
					'editable' => 1
				);

				$groups = CHostgroup::get($options);
				foreach($groups as $gnum => $group){
					$caption = get_node_name_by_elid($group['groupid'], true, ':').$group['name'];
					$id = $resourceid;
				}
			}

			$form->addVar('resourceid',$id);

			$textfield = new CTextbox('caption',$caption,75,'yes');
			$selectbtn = new CButton('select',S_SELECT,"javascript: return PopUp('popup.php?writeonly=1&dstfrm=".$form->getName()."&dstfld1=resourceid&dstfld2=caption&srctbl=overview&srcfld1=groupid&srcfld2=name',800,450);");
			$selectbtn->setAttribute('onmouseover',"javascript: this.style.cursor = 'pointer';");

			$form->addRow(S_GROUP,array($textfield,SPACE,$selectbtn));
		}
		else if($resourcetype == SCREEN_RESOURCE_SCREEN){
// Screens
			$caption = '';
			$id=0;

			if($resourceid > 0){
				$sql = 'SELECT DISTINCT n.name as node_name,s.screenid,s.name '.
						' FROM screens s '.
							' LEFT JOIN nodes n ON n.nodeid='.DBid2nodeid('s.screenid').
						' WHERE s.screenid='.$resourceid;
				$result=DBselect($sql);

				while($row=DBfetch($result)){
					if(!screen_accessible($row['screenid'], PERM_READ_ONLY)) continue;
					if(check_screen_recursion($_REQUEST['screenid'],$row['screenid'])) continue;

					$row['node_name'] = isset($row['node_name']) ? '('.$row['node_name'].') ' : '';
					$caption = $row['node_name'].$row['name'];
					$id = $resourceid;
				}
			}

			$form->addVar('resourceid',$id);

			$textfield = new Ctextbox('caption',$caption,60,'yes');
			$selectbtn = new Cbutton('select',S_SELECT,"javascript: return PopUp('popup.php?writeonly=1&dstfrm=".$form->getName()."&dstfld1=resourceid&dstfld2=caption&srctbl=screens2&srcfld1=screenid&srcfld2=name&screenid=".$_REQUEST['screenid']."',800,450);");
			$selectbtn->setAttribute('onmouseover',"javascript: this.style.cursor = 'pointer';");

			$form->addRow(S_PARAMETER,array($textfield,SPACE,$selectbtn));
		}
		else if(($resourcetype == SCREEN_RESOURCE_HOSTS_INFO) || ($resourcetype == SCREEN_RESOURCE_TRIGGERS_INFO)){
// HOSTS info
			$caption = '';
			$id=0;

			$available_groups = get_accessible_groups_by_user($USER_DETAILS,PERM_READ_ONLY);
			if(remove_nodes_from_id($resourceid) > 0){
				$sql = 'SELECT DISTINCT n.name as node_name,g.groupid,g.name '.
						' FROM hosts_groups hg, groups g '.
							' LEFT JOIN nodes n ON n.nodeid='.DBid2nodeid('g.groupid').
						' WHERE '.DBcondition('g.groupid',$available_groups).
							' AND g.groupid='.$resourceid;
				$result=DBselect($sql);
				while($row=DBfetch($result)){
					$row['node_name'] = isset($row['node_name']) ? '('.$row['node_name'].') ' : '';
					$caption = $row['node_name'].$row['name'];
					$id = $resourceid;
				}
			}
			else if(remove_nodes_from_id($resourceid)==0){
				$result=DBselect('SELECT DISTINCT n.name as node_name '.
						' FROM nodes n '.
						' WHERE n.nodeid='.id2nodeid($resourceid));

				while($row=DBfetch($result)){
					$row['node_name'] = isset($row['node_name']) ? '('.$row['node_name'].') ' : '';
					$caption = $row['node_name'].S_MINUS_ALL_GROUPS_MINUS;
					$id = $resourceid;
				}
			}

			$form->addVar('resourceid',$id);

			$textfield = new CTextbox('caption',$caption,60,'yes');
			$selectbtn = new CButton('select',S_SELECT,"javascript: return PopUp('popup.php?writeonly=1&dstfrm=".$form->getName()."&dstfld1=resourceid&dstfld2=caption&srctbl=host_group_scr&srcfld1=groupid&srcfld2=name',480,450);");
			$selectbtn->setAttribute('onmouseover',"javascript: this.style.cursor = 'pointer';");

			$form->addRow(S_GROUP,array($textfield,SPACE,$selectbtn));
		}
		else if($resourcetype == SCREEN_RESOURCE_CLOCK){
			$caption = get_request('caption', '');

			if(zbx_empty($caption) && (TIME_TYPE_HOST == $style) && ($resourceid > 0)){
				$options = array(
					'itemids' => $resourceid,
					'output' => API_OUTPUT_EXTEND
				);
				$items = CItem::get($options);
				$item = reset($items);

				$caption = $item['description'];
			}

			$form->addVar('resourceid',$resourceid);

			$cmbStyle = new CComboBox('style', $style, 'javascript: submit();');
			$cmbStyle->addItem(TIME_TYPE_LOCAL,	S_LOCAL_TIME);
			$cmbStyle->addItem(TIME_TYPE_SERVER,S_SERVER_TIME);
			$cmbStyle->addItem(TIME_TYPE_HOST,	S_HOST_TIME);
			
			$form->addRow(S_TIME_TYPE,	$cmbStyle);

			if(TIME_TYPE_HOST == $style){
				$textfield = new CTextbox('caption',$caption,75,'yes');
				$selectbtn = new CButton('select',S_SELECT,"javascript: return PopUp('popup.php?writeonly=1&dstfrm=".$form->getName()."&dstfld1=resourceid&dstfld2=caption&srctbl=items&srcfld1=itemid&srcfld2=description',800,450);");
				$selectbtn->setAttribute('onmouseover',"javascript: this.style.cursor = 'pointer';");
	
				$form->addRow(S_PARAMETER,array($textfield,SPACE,$selectbtn));
			}
			else{
				$form->addVar('caption',$caption);
			}
		}
		else{
			$form->addVar('resourceid',0);
		}

		if(uint_in_array($resourcetype,array(SCREEN_RESOURCE_HOSTS_INFO,SCREEN_RESOURCE_TRIGGERS_INFO))){
			$cmbStyle = new CComboBox("style", $style);
			$cmbStyle->addItem(STYLE_HORISONTAL,	S_HORIZONTAL);
			$cmbStyle->addItem(STYLE_VERTICAL,	S_VERTICAL);
			$form->addRow(S_STYLE,	$cmbStyle);
		}
		else if(uint_in_array($resourcetype,array(SCREEN_RESOURCE_TRIGGERS_OVERVIEW,SCREEN_RESOURCE_DATA_OVERVIEW))){
			$cmbStyle = new CComboBox('style', $style);
			$cmbStyle->addItem(STYLE_LEFT,	S_LEFT);
			$cmbStyle->addItem(STYLE_TOP,	S_TOP);
			$form->addRow(S_HOSTS_LOCATION,	$cmbStyle);
		}
		else{
			$form->addVar('style',	0);
		}

		if(uint_in_array($resourcetype,array(SCREEN_RESOURCE_URL))){
			$form->addRow(S_URL, new CTextBox('url',$url,60));
		}
		else{
			$form->addVar('url',	'');
		}

		if(uint_in_array($resourcetype,array(SCREEN_RESOURCE_GRAPH,SCREEN_RESOURCE_SIMPLE_GRAPH,SCREEN_RESOURCE_CLOCK,SCREEN_RESOURCE_URL))){
			$form->addRow(S_WIDTH,	new CNumericBox('width',$width,5));
			$form->addRow(S_HEIGHT,	new CNumericBox('height',$height,5));
		}
		else{
			$form->addVar('width',	500);
			$form->addVar('height',	100);
		}

		if(uint_in_array($resourcetype,array(SCREEN_RESOURCE_GRAPH,SCREEN_RESOURCE_SIMPLE_GRAPH,SCREEN_RESOURCE_MAP,
			SCREEN_RESOURCE_CLOCK,SCREEN_RESOURCE_URL))){
			$cmbHalign = new CComboBox('halign',$halign);
			$cmbHalign->addItem(HALIGN_CENTER,	S_CENTRE);
			$cmbHalign->addItem(HALIGN_LEFT,	S_LEFT);
			$cmbHalign->addItem(HALIGN_RIGHT,	S_RIGHT);
			$form->addRow(S_HORIZONTAL_ALIGN,	$cmbHalign);
		}
		else{
			$form->addVar('halign',	0);
		}

		$cmbValign = new CComboBox('valign',$valign);
		$cmbValign->addItem(VALIGN_MIDDLE,	S_MIDDLE);
		$cmbValign->addItem(VALIGN_TOP,		S_TOP);
		$cmbValign->addItem(VALIGN_BOTTOM,	S_BOTTOM);
		$form->addRow(S_VERTICAL_ALIGN,	$cmbValign);

		$form->addRow(S_COLUMN_SPAN,	new CNumericBox('colspan',$colspan,2));
		$form->addRow(S_ROW_SPAN,	new CNumericBox('rowspan',$rowspan,2));

// dynamic AddOn
		if(uint_in_array($resourcetype,array(SCREEN_RESOURCE_GRAPH,SCREEN_RESOURCE_SIMPLE_GRAPH,SCREEN_RESOURCE_PLAIN_TEXT))){
			$form->addRow(S_DYNAMIC_ITEM,	new CCheckBox('dynamic',$dynamic,null,1));
		}

		$form->addItemToBottomRow(new CButton('save',S_SAVE));
		if(isset($_REQUEST['screenitemid'])){
			$form->addItemToBottomRow(SPACE);
			$form->addItemToBottomRow(new CButtonDelete(null,
				url_param('form').url_param('screenid').url_param('screenitemid')));
		}
		$form->addItemToBottomRow(SPACE);
		$form->addItemToBottomRow(new CButtonCancel(url_param('screenid')));
		return $form;
	}

	// editmode: 0 - view with actions, 1 - edit mode, 2 - view without any actions
	function get_screen($screenid, $editmode, $effectiveperiod=NULL){
		global $USER_DETAILS;

		if($screenid == 0) return new CTableInfo(S_NO_SCREENS_DEFINED);
		if(!screen_accessible($screenid, ($editmode == 1)?PERM_READ_WRITE:PERM_READ_ONLY))
			access_deny();

		if(is_null($effectiveperiod))
			$effectiveperiod = ZBX_MIN_PERIOD;

		$result=DBselect('SELECT name,hsize,vsize FROM screens WHERE screenid='.$screenid);
		$row=DBfetch($result);
		if(!$row) return new CTableInfo(S_NO_SCREENS_DEFINED);
				
		$sql = 'SELECT * FROM screens_items WHERE screenid='.$screenid;
		$iresult = DBSelect($sql);
		
		$skip_field = array();
		$irows = array();
		while($irow = DBfetch($iresult)){
			$irows[] = $irow;
			for($i=0; $i < $irow['rowspan'] || $i==0; $i++){
				for($j=0; $j < $irow['colspan'] || $j==0; $j++){
					if($i!=0 || $j!=0){
						if(!isset($skip_field[$irow['y']+$i])) $skip_field[$irow['y']+$i] = array();
						$skip_field[$irow['y']+$i][$irow['x']+$j] = 1;
					}
				}
			}				
		}
	
		$table = new CTable(new CLink(S_NO_ROWS_IN_SCREEN.SPACE.$row['name'],'screenconf.php?config=0&form=update&screenid='.$screenid),
			($editmode == 0 || $editmode == 2) ? 'screen_view' : 'screen_edit');
		$table->setAttribute('id', 'iframe');

		if($editmode == 1){
			$new_cols = array(new Ccol(new Cimg('images/general/zero.gif','zero',1,1)));
			for($c=0;$c<$row['hsize']+1;$c++){
				$add_icon = new Cimg('images/general/closed.gif', NULL, NULL, NULL, 'pointer');
            $add_icon->addAction('onclick', "javascript: location.href = 'screenedit.php?config=1&screenid=$screenid&add_col=$c';");
				array_push($new_cols, new Ccol($add_icon));
			}
			$table->addRow($new_cols);
		}

		$empty_screen_col = array();

		for($r=0; $r < $row['vsize']; $r++){
			$new_cols = array();
			$empty_screen_row = true;

			if($editmode == 1){
				$add_icon = new Cimg('images/general/closed.gif', NULL, NULL, NULL, 'pointer');
            $add_icon->addAction('onclick', "javascript: location.href = 'screenedit.php?config=1&screenid=$screenid&add_row=$r';");
				array_push($new_cols, new Ccol($add_icon));
			}

			for($c=0; $c < $row['hsize']; $c++){
				$item = array();
				if(isset($skip_field[$r][$c])) continue;
				$item_form = false;

				$irow = false;
				foreach($irows as $tmprow){
					if(($tmprow['x'] == $c) && ($tmprow['y'] == $r)){
						$irow = $tmprow;
						break;
					}
				}
				
				if($irow){
					$screenitemid	= $irow['screenitemid'];
					$resourcetype	= $irow['resourcetype'];
					$resourceid	= $irow['resourceid'];
					$width		= $irow['width'];
					$height		= $irow['height'];
					$colspan	= $irow['colspan'];
					$rowspan	= $irow['rowspan'];
					$elements	= $irow['elements'];
					$valign		= $irow['valign'];
					$halign		= $irow['halign'];
					$style		= $irow['style'];
					$url		= $irow['url'];
					$dynamic	= $irow['dynamic'];
				}
				else{
					$screenitemid	= 0;
					$resourcetype	= 0;
					$resourceid	= 0;
					$width		= 0;
					$height		= 0;
					$colspan	= 0;
					$rowspan	= 0;
					$elements	= 0;
					$valign		= VALIGN_DEFAULT;
					$halign		= HALIGN_DEFAULT;
					$style		= 0;
					$url		= '';
					$dynamic	= 0;
				}

				if($screenitemid>0){
					$empty_screen_row = false;
					$empty_screen_col[$c] = 1;
				}

				if($editmode == 1 && $screenitemid!=0){
					$onclick_action = "ZBX_SCREENS['".$_REQUEST['screenid']."'].screen.element_onclick('screenedit.php?form=update".url_param('screenid').'&screenitemid='.$screenitemid."#form');";
					$action = 'screenedit.php?form=update'.url_param('screenid').'&screenitemid='.$screenitemid.'#form';
				}
				else if($editmode == 1 && $screenitemid==0){
					$onclick_action = "ZBX_SCREENS['".$_REQUEST['screenid']."'].screen.element_onclick('screenedit.php?form=update".url_param('screenid').'&x='.$c.'&y='.$r."#form');";
					$action = 'screenedit.php?form=update'.url_param('screenid').'&x='.$c.'&y='.$r.'#form';
				}
				else
					$action = NULL;

				if(($editmode == 1) && isset($_REQUEST['form']) && isset($_REQUEST['x']) && $_REQUEST['x']==$c &&
					isset($_REQUEST['y']) && $_REQUEST['y']==$r){ // click on empty field
					$item = get_screen_item_form();
					$item_form = true;
				}
				else if(($editmode == 1) && isset($_REQUEST['form']) &&	isset($_REQUEST['screenitemid']) &&
					(bccomp($_REQUEST['screenitemid'], $screenitemid)==0)){ // click on element
					$item = get_screen_item_form();
					$item_form = true;
				}
				else if( ($screenitemid!=0) && ($resourcetype==SCREEN_RESOURCE_GRAPH) ){
					if($editmode == 0)	$action = 'charts.php?graphid='.$resourceid.url_param('period').url_param('stime');

// GRAPH & ZOOM features
					$dom_graph_id = 'graph_'.$screenitemid.'_'.$resourceid;
					$containerid = 'graph_cont_'.$screenitemid.'_'.$resourceid;
					$graphDims = getGraphDims($resourceid);

					$graphDims['graphHeight'] = $height;
					$graphDims['width'] = $width;

					$sql = 'SELECT g.graphid, g.show_legend as legend, g.show_3d as show3d '.
							' FROM graphs g '.
							' WHERE g.graphid='.$resourceid;
					$res = DBselect($sql);
					if($graph=DBfetch($res)){
						$graphid = $graph['graphid'];
						$legend = $graph['legend'];
						$graph3d = $graph['show3d'];
					}
//-------------

// Host feature
					if(($dynamic == SCREEN_DYNAMIC_ITEM) && isset($_REQUEST['hostid']) && ($_REQUEST['hostid']>0)){
						$def_items = array();
						$di_res = get_graphitems_by_graphid($resourceid);
						while( $gitem = DBfetch($di_res)){
							$def_items[] = $gitem;
						}

						$url='';
						if($new_items = get_same_graphitems_for_host($def_items, $_REQUEST['hostid'], false)){
							$url.= make_url_from_gitems($new_items);
						}

						$url= make_url_from_graphid($resourceid,false).$url;
					}
//-------------

					if(($graphDims['graphtype'] == GRAPH_TYPE_PIE) || ($graphDims['graphtype'] == GRAPH_TYPE_EXPLODED)){
						$src = 'chart6.php?graphid='.$resourceid.url_param('stime').'&period='.$effectiveperiod;
					}
					else{
						$src = 'chart2.php?graphid='.$resourceid.url_param('stime').'&period='.$effectiveperiod;
					}


					$objData = array(
						'id' => $resourceid,
						'domid' => $dom_graph_id,
						'containerid' => $containerid,
						'objDims' => $graphDims,
						'loadSBox' => 0,
						'loadImage' => 1,
						'loadScroll' => 0,
						'dynamic' => 0
					);

					$default = false;
					if(($graphDims['graphtype'] == GRAPH_TYPE_PIE) || ($graphDims['graphtype'] == GRAPH_TYPE_EXPLODED)){
						if(($dynamic==SCREEN_SIMPLE_ITEM) || empty($url)){
							$url='chart6.php?graphid='.$resourceid;
							$default = true;
						}

						$timeline = array();
						$timeline['period'] = $effectiveperiod;
						$timeline['starttime'] = date('YmdHi', get_min_itemclock_by_graphid($resourceid));

						if(isset($_REQUEST['stime'])){
							$timeline['usertime'] = date('YmdHi', zbxDateToTime($_REQUEST['stime']) + $timeline['period']);
						}

						// $src = $url.'&width='.$width.'&height='.$height.'&legend='.$legend.'&graph3d='.$graph3d;
						$src = $url.'&width='.$width.'&height='.$height.'&legend='.$legend.'&graph3d='.$graph3d.'&period='.$effectiveperiod.url_param('stime');

						$objData['src'] = $src;

					}
					else{
						if(($dynamic==SCREEN_SIMPLE_ITEM) || empty($url)){
							$url='chart2.php?graphid='.$resourceid;
							$default = true;
						}

						$src = $url.'&width='.$width.'&height='.$height.'&period='.$effectiveperiod.url_param('stime');

						$timeline = array();
						if(isset($graphid) && !is_null($graphid) && ($editmode != 1)){
							$timeline['period'] = $effectiveperiod;
							$timeline['starttime'] = date('YmdHi', time() - ZBX_MAX_PERIOD); //get_min_itemclock_by_graphid($graphid);

							if(isset($_REQUEST['stime'])){
								$timeline['usertime'] = date('YmdHi', zbxDateToTime($_REQUEST['stime']) + $timeline['period']);
							}

							$objData['loadSBox'] = 1;
//							zbx_add_post_js('graph_zoom_init("'.$dom_graph_id.'",'.$stime.','.$effectiveperiod.','.$width.','.$height.', false);');
						}

						$objData['src'] = $src;
					}

				
					if($default && ($editmode == 1)){
						$div = new CDiv();
						$div->setAttribute('id', $containerid);
						$item[] = $div;
						$item[] = new CLink(S_CHANGE, $action);
					}
					else{
						$item = new CLink(null, $action);
						$item->setAttribute('id', $containerid);
					}

//					zbx_add_post_js('timeControl.addObject("'.$dom_graph_id.'",'.zbx_jsvalue($timeline).','.zbx_jsvalue($objData).');');
					insert_js('timeControl.addObject("'.$dom_graph_id.'",'.zbx_jsvalue($timeline).','.zbx_jsvalue($objData).');');

				}
				else if( ($screenitemid!=0) && ($resourcetype==SCREEN_RESOURCE_SIMPLE_GRAPH) ){
					$dom_graph_id = 'graph_'.$screenitemid.'_'.$resourceid;
					$containerid = 'graph_cont_'.$screenitemid.'_'.$resourceid;

					$graphDims = getGraphDims();
					$graphDims['graphHeight'] = $height;
					$graphDims['width'] = $width;

					$objData = array(
						'id' => $resourceid,
						'domid' => $dom_graph_id,
						'containerid' => $containerid,
						'objDims' => $graphDims,
						'loadSBox' => 0,
						'loadImage' => 1,
						'loadScroll' => 0,
						'dynamic' => 0
					);

// Host feature
					if(($dynamic == SCREEN_DYNAMIC_ITEM) && isset($_REQUEST['hostid']) && ($_REQUEST['hostid']>0)){
						if($newitemid = get_same_item_for_host($resourceid,$_REQUEST['hostid'])){
							$resourceid = $newitemid;
						}
						else{
							$resourceid='';
						}
					}
//-------------

					if(($editmode == 0) && !empty($resourceid)) $action = 'history.php?action=showgraph&itemid='.$resourceid.url_param('period').url_param('stime');

					$timeline = array();
					$timeline['starttime'] = date('YmdHi', time() - ZBX_MAX_PERIOD);

					if(!zbx_empty($resourceid) && ($editmode != 1)){
						$timeline['period'] = $effectiveperiod;

						if(isset($_REQUEST['stime'])){
							$timeline['usertime'] = date('YmdHi', zbxDateToTime($_REQUEST['stime']) + $timeline['period']);
						}

						$objData['loadSBox'] = 1;
					}

					$src = (zbx_empty($resourceid))?'chart3.php?':'chart.php?itemid='.$resourceid.'&';
					$src.= $url.'width='.$width.'&height='.$height;

					$objData['src'] = $src;

					
					if($editmode == 1){
						$div = new CDiv();
						$div->setAttribute('id', $containerid);
						$item[] = $div;
						$item[] = new CLink(S_CHANGE, $action);
					}
					else{
						$item = new CLink(null, $action);
						$item->setAttribute('id', $containerid);
					}
					
//					zbx_add_post_js('timeControl.addObject("'.$dom_graph_id.'",'.zbx_jsvalue($timeline).','.zbx_jsvalue($objData).');');
					insert_js('timeControl.addObject("'.$dom_graph_id.'",'.zbx_jsvalue($timeline).','.zbx_jsvalue($objData).');');
				}
				else if( ($screenitemid!=0) && ($resourcetype==SCREEN_RESOURCE_MAP) ){

					$image_map = new CImg("map.php?noedit=1&sysmapid=$resourceid"."&width=$width&height=$height");

					if($editmode == 0){
						$action_map = get_action_map_by_sysmapid($resourceid);
						$image_map->setMap($action_map->getName());
						$item = array($action_map,$image_map);
					}
					else {
						$item = $image_map;
//						$item = new CLink($image_map, $action);
					}
				}
				else if( ($screenitemid!=0) && ($resourcetype==SCREEN_RESOURCE_PLAIN_TEXT) ){
// Host feature
					if(($dynamic == SCREEN_DYNAMIC_ITEM) && isset($_REQUEST['hostid']) && ($_REQUEST['hostid']>0)){
						if($newitemid = get_same_item_for_host($resourceid,$_REQUEST['hostid'])){
							$resourceid = $newitemid;
						}
						else{
							$resourceid=0;
						}
					}
//-------------
					$item = array(get_screen_plaintext($resourceid,$elements,$style));
					if($editmode == 1)	array_push($item,new CLink(S_CHANGE,$action));
				}
				else if(($screenitemid!=0) && ($resourcetype==SCREEN_RESOURCE_HOSTGROUP_TRIGGERS)){
					$params = array(
						'groupids' => null,
						'hostids' => null,
						'maintenance' => null,
						'severity' => null,
						'limit' => $elements
					);

					$tr_form = S_ALL_S;
					if($resourceid > 0){
						$options = array(
							'groupids' => $resourceid,
							'output' => API_OUTPUT_EXTEND
						);
						$hostgroups = CHostgroup::get($options);
						$hostgroup = reset($hostgroups);

						$tr_form = new CSpan(S_GROUP.': '.$hostgroup['name'], 'white');
						$params['groupid'] = $hostgroup['groupid'];
					}
///-----------------------
					else{
						$params['groupid'] = get_request('tr_groupid', CProfile::get('web.screens.tr_groupid',0));
						$params['hostid'] = get_request('tr_hostid', CProfile::get('web.screens.tr_hostid',0));

						CProfile::update('web.screens.tr_groupid',$params['groupid'], PROFILE_TYPE_ID);
						CProfile::update('web.screens.tr_hostid',$params['hostid'], PROFILE_TYPE_ID);

						$tr_form = new CForm();

						$cmbGroup = new CComboBox('tr_groupid',$params['groupid'],'submit()');
						$cmbHosts = new CComboBox('tr_hostid',$params['hostid'],'submit()');

						$cmbGroup->addItem(0,S_ALL_SMALL);
						$cmbHosts->addItem(0,S_ALL_SMALL);

						$options = array(
							'monitored_hosts' => 1,
							'output' => API_OUTPUT_EXTEND
						);
						$groups = CHostGroup::get($options);
						foreach($groups as $gnum => $group){
							$cmbGroup->addItem(
								$group['groupid'],
								get_node_name_by_elid($group['groupid'], null, ': ').$group['name']
							);
						}
						$tr_form->addItem(array(S_GROUP.SPACE,$cmbGroup));

						$options = array(
							'monitored_hosts' => 1,
							'output' => API_OUTPUT_EXTEND
						);
						if($params['groupid'] > 0)
							$options['groupids'] = $params['groupid'];

						$hosts = CHost::get($options);
						foreach($hosts as $hnum => $host){
							$cmbHosts->addItem(
								$host['hostid'],
								get_node_name_by_elid($host['hostid'], null, ': ').$host['host']
							);
						}

						$tr_form->addItem(array(SPACE.S_HOST.SPACE,$cmbHosts));

						$item[] = make_latest_issues($params);

						if($editmode == 1)	array_push($item,new CLink(S_CHANGE,$action));
					}
///-----------------------

					$item = array(get_table_header(array(S_STATUS_OF_TRIGGERS_BIG,SPACE,zbx_date2str(S_SCREENS_TRIGGER_FORM_DATE_FORMAT)), $tr_form));
					$item[] = make_latest_issues($params);

					if($editmode == 1)	array_push($item,new CLink(S_CHANGE,$action));
				}
				else if(($screenitemid!=0) && ($resourcetype==SCREEN_RESOURCE_HOST_TRIGGERS)){
					$params = array(
						'groupids' => null,
						'hostids' => null,
						'maintenance' => null,
						'severity' => null,
						'limit' => $elements
					);
					$tr_form = S_ALL_S;

					if($resourceid > 0){
						$options = array(
							'hostids' => $resourceid,
							'output' => API_OUTPUT_EXTEND
						);
						$hosts = CHost::get($options);
						$host = reset($hosts);

						$tr_form = new CSpan(S_HOST.': '.$host['host'], 'white');
						$params['hostid'] = $host['hostid'];
					}
///-----------------------
					else{
						$params['groupid'] = get_request('tr_groupid',CProfile::get('web.screens.tr_groupid',0));
						$params['hostid'] = get_request('tr_hostid',CProfile::get('web.screens.tr_hostid',0));

						CProfile::update('web.screens.tr_groupid',$params['groupid'], PROFILE_TYPE_ID);
						CProfile::update('web.screens.tr_hostid',$params['hostid'], PROFILE_TYPE_ID);

						$tr_form = new CForm();

						$cmbGroup = new CComboBox('tr_groupid',$params['groupid'],'submit()');
						$cmbHosts = new CComboBox('tr_hostid',$params['hostid'],'submit()');

						$cmbGroup->addItem(0,S_ALL_SMALL);
						$cmbHosts->addItem(0,S_ALL_SMALL);

						$options = array(
							'monitored_hosts' => 1,
							'output' => API_OUTPUT_EXTEND
						);
						$groups = CHostGroup::get($options);
						foreach($groups as $gnum => $group){
							$cmbGroup->addItem(
								$group['groupid'],
								get_node_name_by_elid($group['groupid'], null, ': ').$group['name']
							);
						}
						$tr_form->addItem(array(S_GROUP.SPACE,$cmbGroup));

						$options = array(
							'monitored_hosts' => 1,
							'output' => API_OUTPUT_EXTEND
						);
						if($params['groupid'] > 0)
							$options['groupids'] = $params['groupid'];

						$hosts = CHost::get($options);
						foreach($hosts as $hnum => $host){
							$cmbHosts->addItem(
								$host['hostid'],
								get_node_name_by_elid($host['hostid'], null, ': ').$host['host']
							);
						}

						$tr_form->addItem(array(SPACE.S_HOST.SPACE,$cmbHosts));

						$item[] = make_latest_issues($params);

						if($editmode == 1)	array_push($item,new CLink(S_CHANGE,$action));
					}
///-----------------------

					$item = array(get_table_header(array(S_STATUS_OF_TRIGGERS_BIG,SPACE,zbx_date2str(S_SCREENS_TRIGGER_FORM_DATE_FORMAT)), $tr_form));
					$item[] = make_latest_issues($params);

					if($editmode == 1)	array_push($item,new CLink(S_CHANGE,$action));
				}
				else if(($screenitemid!=0) && ($resourcetype==SCREEN_RESOURCE_SYSTEM_STATUS)){
					$params = array(
						'groupids' => null,
						'hostids' => null,
						'maintenance' => null,
						'severity' => null,
						'limit' => null
					);

					$item = array(get_table_header(array(S_SYSTEM_STATUS,SPACE,zbx_date2str(S_SCREENS_TRIGGER_FORM_DATE_FORMAT))));
					$item[] = make_system_summary($params);

					if($editmode == 1)	array_push($item,new CLink(S_CHANGE,$action));
				}
				else if( ($screenitemid!=0) && ($resourcetype==SCREEN_RESOURCE_HOSTS_INFO) ){
					$item = array(new CHostsInfo($resourceid, $style));
					if($editmode == 1)	array_push($item,new CLink(S_CHANGE,$action));
				}
				else if( ($screenitemid!=0) && ($resourcetype==SCREEN_RESOURCE_TRIGGERS_INFO) ){
					$item = new CTriggersInfo($resourceid, $style);
					$item = array($item);
					if($editmode == 1)	array_push($item,new CLink(S_CHANGE,$action));
				}
				else if( ($screenitemid!=0) && ($resourcetype==SCREEN_RESOURCE_SERVER_INFO) ){
//					$item = array(get_table_header(S_STATUS_OF_ZABBIX_BIG),make_status_of_zbx());
					$item = array(new CServerInfo());
					if($editmode == 1)	array_push($item,new CLink(S_CHANGE,$action));
				}
				else if( ($screenitemid!=0) && ($resourcetype==SCREEN_RESOURCE_CLOCK) ){
					$error = null;
					$timeOffset = null;
					$timeZone = null;
					
					switch($style){
					 case TIME_TYPE_HOST:
						$options = array(
							'itemids' => $resourceid,
							'select_hosts' => API_OUTPUT_EXTEND,
							'output' => API_OUTPUT_EXTEND
						);

						$items = CItem::get($options);
						$item = reset($items);
						$host = reset($item['hosts']);

//2010-12-01,12:44:13.324,+4:00
//						$item['lastvalue'] = '2010-1-15,10:23:13.324,-4:0';
//						$item['lastclock'] = time();

						$timeType = $host['host'];

						preg_match('/([+-]{1})([\d]{1,2}):([\d]{1,2})/', $item['lastvalue'], $arr);
						if(!empty($arr)){
							$timeZone = $arr[2]*3600 + $arr[3]*60;
							if($arr[1] == '-') $timeZone = 0 - $timeZone;
						}

						if($lastvalue = strtotime($item['lastvalue'])){
							$diff = (time() - $item['lastclock']);
							$timeOffset = $lastvalue + $diff;
						}
						else{
							$error = S_NO_DATA_BIG;
						}

						break;
					case TIME_TYPE_SERVER:
						$error = null;
						$timeType = S_SERVER_BIG;
						$timeOffset = time();
						$timeZone = date('Z');
						break;
					default:
						$error = null;
						$timeType = S_LOCAL_BIG;
						$timeOffset = null;
						$timeZone = null;
					}

					$item = new CFlashClock($width, $height, $action);
					$item->setTimeError($error);
					$item->setTimeType($timeType);
					$item->setTimeZone($timeZone);
					$item->setTimeOffset($timeOffset);
					
				}
				else if( ($screenitemid!=0) && ($resourcetype==SCREEN_RESOURCE_SCREEN) ){
					$item = array(get_screen($resourceid, 2, $effectiveperiod));
					if($editmode == 1)	array_push($item,new CLink(S_CHANGE,$action));
				}
				else if( ($screenitemid!=0) && ($resourcetype==SCREEN_RESOURCE_TRIGGERS_OVERVIEW) ){
					$hostids = array();
					$res = DBselect('SELECT DISTINCT hg.hostid FROM hosts_groups hg WHERE hg.groupid='.$resourceid);
					while($tmp_host = DBfetch($res)) $hostids[$tmp_host['hostid']] = $tmp_host['hostid'];

					$item = array(get_triggers_overview($hostids,$style));
					if($editmode == 1)	array_push($item,new CLink(S_CHANGE,$action));
				}
				else if(($screenitemid!=0) && ($resourcetype==SCREEN_RESOURCE_DATA_OVERVIEW)){
					$hostids = array();
					$res = DBselect('SELECT DISTINCT hg.hostid FROM hosts_groups hg WHERE hg.groupid='.$resourceid);
					while($tmp_host = DBfetch($res)) $hostids[$tmp_host['hostid']] = $tmp_host['hostid'];

					$item = array(get_items_data_overview($hostids,$style));
					if($editmode == 1)	array_push($item,new CLink(S_CHANGE,$action));
				}
				else if(($screenitemid!=0) && ($resourcetype==SCREEN_RESOURCE_URL)){
					$item = array(new CIFrame($url,$width,$height,"auto"));
					if($editmode == 1)	array_push($item,BR(),new CLink(S_CHANGE,$action));
				}
				else if(($screenitemid!=0) && ($resourcetype==SCREEN_RESOURCE_ACTIONS)){
					$item = array(get_history_of_actions($elements));
					if($editmode == 1)	array_push($item,new CLink(S_CHANGE,$action));
				}
				else if(($screenitemid!=0) && ($resourcetype==SCREEN_RESOURCE_EVENTS)){
					$item = array(get_history_of_triggers_events(0, $elements));
					if($editmode == 1)	array_push($item,new CLink(S_CHANGE,$action));
				}
				else{
					$item = array(SPACE);
					if($editmode == 1)	array_push($item,BR(),new CLink(S_CHANGE,$action));
				}

				$str_halign = 'def';
				if($halign == HALIGN_CENTER)	$str_halign = 'cntr';
				if($halign == HALIGN_LEFT)	$str_halign = 'left';
				if($halign == HALIGN_RIGHT)	$str_halign = 'right';

				$str_valign = 'def';
				if($valign == VALIGN_MIDDLE)	$str_valign = 'mdl';
				if($valign == VALIGN_TOP)	$str_valign = 'top';
				if($valign == VALIGN_BOTTOM)	$str_valign = 'bttm';

				if(($editmode == 1) && !$item_form){
					$item = new CDiv($item,'draggable');
					$item->setAttribute('id','position_'.$r.'_'.$c);
					if($editmode == 1)	$item->setAttribute('onclick','javascript: '.$onclick_action);
				}

				$new_col = new CCol($item,$str_halign.'_'.$str_valign);

				if($colspan) $new_col->SetColSpan($colspan);
				if($rowspan) $new_col->SetRowSpan($rowspan);

				array_push($new_cols, $new_col);
			}

			if($editmode == 1){
				$rmv_icon = new Cimg('images/general/opened.gif', NULL, NULL, NULL, 'pointer');
				if($empty_screen_row){
					$rmv_row_link = "javascript: location.href = 'screenedit.php?config=1&screenid=$screenid&rmv_row=$r';";
				}
				else{
					$rmv_row_link = "javascript: if(Confirm('".S_THIS_SCREEN_ROW_NOT_EMPTY.'. '.S_DELETE_IT_Q."')){".
									" location.href = 'screenedit.php?config=1&screenid=$screenid&rmv_row=$r';}";
				}
				$rmv_icon->addAction('onclick',$rmv_row_link);

				array_push($new_cols, new Ccol($rmv_icon));
			}
			$table->addRow(new CRow($new_cols));
		}

		if($editmode == 1){
         $add_icon = new Cimg('images/general/closed.gif', NULL, NULL, NULL, 'pointer');
         $add_icon->addAction('onclick', "javascript: location.href = 'screenedit.php?config=1&screenid=$screenid&add_row={$row['vsize']}';");
			$new_cols = array(new Ccol($add_icon));
			for($c=0;$c<$row['hsize'];$c++){
				$rmv_icon = new Cimg('images/general/opened.gif', NULL, NULL, NULL, 'pointer');
				if(isset($empty_screen_col[$c])){
					$rmv_col_link = "javascript: if(Confirm('".S_THIS_SCREEN_COLUMN_NOT_EMPTY.'. '.S_DELETE_IT_Q."')){".
										" location.href = 'screenedit.php?config=1&screenid=$screenid&rmv_col=$c';}";
				}
				else{
					$rmv_col_link = "javascript: location.href = 'screenedit.php?config=1&screenid=$screenid&rmv_col=$c';";
				}
				$rmv_icon->addAction('onclick',$rmv_col_link);
				array_push($new_cols, new Ccol($rmv_icon));
			}

			array_push($new_cols, new Ccol(new Cimg('images/general/zero.gif','zero',1,1)));
			$table->addRow($new_cols);
		}


	return $table;
	}

	function separateScreenElements($screen){
		$elements = array(
			'sysmaps' => array(),
			'screens' => array(),
			'hostgroups' => array(),
			'hosts' => array(),
			'graphs' => array(),
			'items' => array()
		);


		foreach($screen['screenitems'] as $snum => $screenItem){
			if($screenItem['resourceid'] == 0) continue;

			switch($screenItem['resourcetype']){
				case SCREEN_RESOURCE_HOSTS_INFO:
				case SCREEN_RESOURCE_TRIGGERS_INFO:
				case SCREEN_RESOURCE_TRIGGERS_OVERVIEW:
				case SCREEN_RESOURCE_DATA_OVERVIEW:
				case SCREEN_RESOURCE_HOSTGROUP_TRIGGERS:
					$elements['hostgroups'][] = $screenItem['resourceid'];
				break;
				case SCREEN_RESOURCE_HOST_TRIGGERS:
					$elements['hosts'][] = $screenItem['resourceid'];
				break;
				case SCREEN_RESOURCE_GRAPH:
					$elements['graphs'][] = $screenItem['resourceid'];
				break;
				case SCREEN_RESOURCE_SIMPLE_GRAPH:
				case SCREEN_RESOURCE_PLAIN_TEXT:
					$elements['items'][] = $screenItem['resourceid'];
				break;
				case SCREEN_RESOURCE_MAP:
					$elements['sysmaps'][] = $screenItem['resourceid'];
				break;
				case SCREEN_RESOURCE_SCREEN:
					$elements['screens'][] = $screenItem['resourceid'];
				break;
			}
		}

	return $elements;
	}

	function prepareScreenExport(&$exportScreens){
		$screens = array();
		$sysmaps = array();
		$hostgroups = array();
		$hosts = array();
		$graphs = array();
		$items = array();

		foreach($exportScreens as $snum => $screen){
			$screenItems = separateScreenElements($screen);

			$screens += zbx_objectValues($screenItems['screens'], 'resourceid');
			$sysmaps += zbx_objectValues($screenItems['sysmaps'], 'resourceid');
			$hostgroups += zbx_objectValues($screenItems['hostgroups'], 'resourceid');
			$hosts += zbx_objectValues($screenItems['hosts'], 'resourceid');
			$graphs += zbx_objectValues($screenItems['graphs'], 'resourceid');
			$items += zbx_objectValues($screenItems['items'], 'resourceid');
		}

		$screens = screenIdents($screens);
		$sysmaps = sysmapIdents($sysmaps);
		$hostgroups = hostgroupIdents($hostgroups);
		$hosts = hostIdents($hosts);
		$graphs = graphIdents($graphs);
		$items = itemIdents($items);

		try{
			foreach($exportScreens as $snum => &$screen){
				unset($screen['screenid']);
				$screen['backgroundid'] = ($screen['backgroundid'] > 0)?$images[$screen['backgroundid']]:'';

				foreach($screen['screenitems'] as $snum => &$screenItem){
					unset($screenItem['screenid']);
					unset($screenItem['screenitemid']);
					if($screenItem['resourceid'] == 0) continue;

					switch($screenItem['resourcetype']){
						case SCREEN_RESOURCE_HOSTS_INFO:
						case SCREEN_RESOURCE_TRIGGERS_INFO:
						case SCREEN_RESOURCE_TRIGGERS_OVERVIEW:
						case SCREEN_RESOURCE_DATA_OVERVIEW:
						case SCREEN_RESOURCE_HOSTGROUP_TRIGGERS:
							$screenItem['resourceid'] = $hostgroups[$screenItem['resourceid']];
						break;
						case SCREEN_RESOURCE_HOST_TRIGGERS:
							$screenItem['resourceid'] = $hosts[$screenItem['resourceid']];
						break;
						case SCREEN_RESOURCE_GRAPH:
							$screenItem['resourceid'] = $graphs[$screenItem['resourceid']];
						break;
						case SCREEN_RESOURCE_SIMPLE_GRAPH:
						case SCREEN_RESOURCE_PLAIN_TEXT:
							$screenItem['resourceid'] = $items[$screenItem['resourceid']];
						break;
						case SCREEN_RESOURCE_MAP:
							$screenItem['resourceid'] = $sysmaps[$screenItem['resourceid']];
						break;
						case SCREEN_RESOURCE_SCREEN:
							$screenItem['resourceid'] = $screens[$screenItem['resourceid']];
						break;
					}
				}
				unset($screenItem);
			}
			unset($screen);
		}
		catch(Exception $e){
			throw new exception($e->show_message());
		}
	}
?>
