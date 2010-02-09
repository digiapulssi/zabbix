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
require_once('include/config.inc.php');
require_once('include/audit.inc.php');
require_once('include/actions.inc.php');
require_once('include/users.inc.php');

	$page['title'] = 'S_AUDIT';
	$page['file'] = 'auditlogs.php';
	$page['hist_arg'] = array();
	$page['scripts'] = array('class.calendar.js','scriptaculous.js?load=effects');

	$page['type'] = detect_page_type(PAGE_TYPE_HTML);

include_once('include/page_header.php');
?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
// actions
		'groupid'=>			array(T_ZBX_INT, O_OPT,	P_SYS|P_NZERO,	DB_ID,	NULL),
		'hostid'=>			array(T_ZBX_INT, O_OPT,	P_SYS|P_NZERO,	DB_ID,	NULL),
// filter
		'action'=>			array(T_ZBX_INT, O_OPT,	P_SYS,	BETWEEN(-1,6),	NULL),
		'resourcetype'=>	array(T_ZBX_INT, O_OPT,	P_SYS,	BETWEEN(-1,28),	NULL),
		'filter_rst'=>		array(T_ZBX_INT, O_OPT,	P_SYS,	IN(array(0,1)),	NULL),
		'filter_set'=>		array(T_ZBX_STR, O_OPT,	P_SYS,	null,	NULL),
		'alias' =>			array(T_ZBX_STR, O_OPT,	P_SYS,	null,	NULL),
		'nav_time'=>	array(T_ZBX_INT, O_OPT,	P_UNSET_EMPTY,	null,	NULL),
//ajax
		'favobj'=>		array(T_ZBX_STR, O_OPT, P_ACT,	NULL,			NULL),
		'favid'=>		array(T_ZBX_STR, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj})'),
		'state'=>		array(T_ZBX_INT, O_OPT, P_ACT,  NOT_EMPTY,		'isset({favobj}) && ("filter"=={favobj})'),
	);

	check_fields($fields);
	validate_sort_and_sortorder('clock',ZBX_SORT_DOWN);
?>
<?php
/* AJAX */
	if(isset($_REQUEST['favobj'])){
		if('filter' == $_REQUEST['favobj']){
			CProfile::update('web.auditlogs.filter.state',$_REQUEST['state'], PROFILE_TYPE_INT);
		}
	}

	if((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])){
		exit();
	}
//--------

/* FILTER */
	if(isset($_REQUEST['filter_rst'])){
		$_REQUEST['alias'] = '';
		$_REQUEST['action'] = -1;
		$_REQUEST['resourcetype'] = -1;
		$_REQUEST['nav_time'] = time();
	}

	$_REQUEST['alias'] = get_request('alias', CProfile::get('web.auditlogs.filter.alias', ''));
	$_REQUEST['action'] = get_request('action', CProfile::get('web.auditlogs.filter.action',-1));
	$_REQUEST['resourcetype'] = get_request('resourcetype', CProfile::get('web.auditlogs.filter.resourcetype',-1));
	$_REQUEST['nav_time'] = get_request('nav_time', CProfile::get('web.auditlogs.filter.nav_time',time()));

	if(isset($_REQUEST['filter_set']) || isset($_REQUEST['filter_rst'])){
		CProfile::update('web.auditlogs.filter.alias', $_REQUEST['alias'], PROFILE_TYPE_STR);
		CProfile::update('web.auditlogs.filter.action', $_REQUEST['action'], PROFILE_TYPE_INT);
		CProfile::update('web.auditlogs.filter.resourcetype', $_REQUEST['resourcetype'], PROFILE_TYPE_INT);

		CProfile::update('web.auditlogs.filter.nav_time', $_REQUEST['nav_time'], PROFILE_TYPE_INT);
	}
// --------------

// Navigation initialization
	$nav_time = $_REQUEST['nav_time'];
// -------------
?>
<?php

	$audit_wdgt = new CWidget();

// HEADER
	$frmForm = new CForm();
	$frmForm->setMethod('get');

	$cmbConf = new CComboBox('config', 'auditlogs.php');
	$cmbConf->setAttribute('onchange', 'javascript: redirect(this.options[this.selectedIndex].value);');
		$cmbConf->addItem('auditlogs.php', S_LOGS);
		$cmbConf->addItem('auditacts.php', S_ACTIONS);
	$frmForm->addItem($cmbConf);

	$audit_wdgt->addPageHeader(S_AUDIT_LOGS_BIG,$frmForm);

	$numrows = new CDiv();
	$numrows->setAttribute('name', 'numrows');

	$audit_wdgt->addHeader(S_ACTIONS_BIG);
	$audit_wdgt->addHeader($numrows);
//--------

/************************* FILTER **************************/
/***********************************************************/

	$filterForm = new CFormTable();
	$filterForm->setAttribute('name', 'zbx_filter');
	$filterForm->setAttribute('id', 'zbx_filter');

	$script = new CJSscript("javascript: if(CLNDR['audit_since'].clndr.setSDateFromOuterObj()){".
							"$('nav_time').value = parseInt(CLNDR['audit_since'].clndr.sdt.getTime()/1000);}");
	$filterForm->addAction('onsubmit', $script);
	$filterForm->addVar('nav_time', ($_REQUEST['nav_time']>0)?$_REQUEST['nav_time']:'');

	$row = new CRow(array(
		new CCol(S_USER,'form_row_l'),
		new CCol(array(
			new CTextBox('alias', $_REQUEST['alias'], 32),
			new CButton('btn1', S_SELECT,"return PopUp('popup.php?"."dstfrm=".$filterForm->GetName()."&dstfld1=alias&srctbl=users&srcfld1=alias&real_hosts=1');",'T')
		),'form_row_r')
	));

	$filterForm->addRow($row);

	$cmbAction = new CComboBox('action',$_REQUEST['action']);
		$cmbAction->addItem(-1,S_ALL_S);
		$cmbAction->addItem(AUDIT_ACTION_LOGIN,		S_LOGIN);
		$cmbAction->addItem(AUDIT_ACTION_LOGOUT,	S_LOGOUT);
		$cmbAction->addItem(AUDIT_ACTION_ADD,		S_ADD);
		$cmbAction->addItem(AUDIT_ACTION_UPDATE,	S_UPDATE);
		$cmbAction->addItem(AUDIT_ACTION_DELETE,	S_DELETE);
		$cmbAction->addItem(AUDIT_ACTION_ENABLE,	S_ENABLE);
		$cmbAction->addItem(AUDIT_ACTION_DISABLE,	S_DISABLE);

	$filterForm->addRow(S_ACTION, $cmbAction);

	$cmbResource = new CComboBox('resourcetype',$_REQUEST['resourcetype']);
		$cmbResource->addItem(-1,S_ALL_S);
		$cmbResource->addItem(AUDIT_RESOURCE_USER,			S_USER);
//		$cmbResource->addItem(AUDIT_RESOURCE_ZABBIX,		S_ZABBIX);
		$cmbResource->addItem(AUDIT_RESOURCE_ZABBIX_CONFIG,	S_ZABBIX_CONFIG);
		$cmbResource->addItem(AUDIT_RESOURCE_MEDIA_TYPE,	S_MEDIA_TYPE);
		$cmbResource->addItem(AUDIT_RESOURCE_HOST,			S_HOST);
		$cmbResource->addItem(AUDIT_RESOURCE_ACTION,		S_ACTION);
		$cmbResource->addItem(AUDIT_RESOURCE_GRAPH,			S_GRAPH);
		$cmbResource->addItem(AUDIT_RESOURCE_GRAPH_ELEMENT,		S_GRAPH_ELEMENT);
//		$cmbResource->addItem(AUDIT_RESOURCE_ESCALATION,		S_ESCALATION);
//		$cmbResource->addItem(AUDIT_RESOURCE_ESCALATION_RULE,	S_ESCALATION_RULE);
//		$cmbResource->addItem(AUDIT_RESOURCE_AUTOREGISTRATION,	S_AUTOREGISTRATION);
		$cmbResource->addItem(AUDIT_RESOURCE_USER_GROUP,	S_USER_GROUP);
		$cmbResource->addItem(AUDIT_RESOURCE_APPLICATION,	S_APPLICATION);
		$cmbResource->addItem(AUDIT_RESOURCE_TRIGGER,		S_TRIGGER);
		$cmbResource->addItem(AUDIT_RESOURCE_HOST_GROUP,	S_HOST_GROUP);
		$cmbResource->addItem(AUDIT_RESOURCE_ITEM,			S_ITEM);
		$cmbResource->addItem(AUDIT_RESOURCE_IMAGE,			S_IMAGE);
		$cmbResource->addItem(AUDIT_RESOURCE_VALUE_MAP,		S_VALUE_MAP);
		$cmbResource->addItem(AUDIT_RESOURCE_IT_SERVICE,	S_IT_SERVICE);
		$cmbResource->addItem(AUDIT_RESOURCE_MAP,			S_MAP);
		$cmbResource->addItem(AUDIT_RESOURCE_SCREEN,		S_SCREEN);
		$cmbResource->addItem(AUDIT_RESOURCE_NODE,			S_NODE);
		$cmbResource->addItem(AUDIT_RESOURCE_SCENARIO,		S_SCENARIO);
		$cmbResource->addItem(AUDIT_RESOURCE_DISCOVERY_RULE,S_DISCOVERY_RULE);
		$cmbResource->addItem(AUDIT_RESOURCE_SLIDESHOW,		S_SLIDESHOW);
		$cmbResource->addItem(AUDIT_RESOURCE_SCRIPT,		S_SCRIPT);
		$cmbResource->addItem(AUDIT_RESOURCE_PROXY,			S_PROXY);
		$cmbResource->addItem(AUDIT_RESOURCE_MAINTENANCE,	S_MAINTENANCE);
		$cmbResource->addItem(AUDIT_RESOURCE_REGEXP,		S_REGULAR_EXPRESSION);

	$filterForm->addRow(S_RESOURCE, $cmbResource);

	$clndr_icon = new CImg('images/general/bar/cal.gif','calendar', 16, 12, 'pointer');
	$clndr_icon->addAction('onclick',"javascript: var pos = getPosition(this); pos.top+=10; pos.left+=16; CLNDR['audit_since'].clndr.clndrshow(pos.top,pos.left);");
	$clndr_icon->setAttribute('style','vertical-align: middle;');

	$nav_clndr =  array(
		new CNumericBox('nav_day',(($_REQUEST['nav_time']>0)?date('d',$_REQUEST['nav_time']):''),2),
		new CNumericBox('nav_month',(($_REQUEST['nav_time']>0)?date('m',$_REQUEST['nav_time']):''),2),
		new CNumericBox('nav_year',(($_REQUEST['nav_time']>0)?date('Y',$_REQUEST['nav_time']):''),4),
		new CNumericBox('nav_hour',(($_REQUEST['nav_time']>0)?date('H',$_REQUEST['nav_time']):''),2),
		':',
		new CNumericBox('nav_minute',(($_REQUEST['nav_time']>0)?date('i',$_REQUEST['nav_time']):''),2),
		$clndr_icon
	);

	$filterForm->addRow(S_ACTIONS_SINCE,$nav_clndr);

	zbx_add_post_js('create_calendar(null,'.
				'["nav_day","nav_month","nav_year","nav_hour","nav_minute"],'.
				'"audit_since");');

	zbx_add_post_js('addListener($("filter_icon"),'.
				'"click",CLNDR[\'audit_since\'].clndr.clndrhide.bindAsEventListener(CLNDR[\'audit_since\'].clndr));');

//*/
	$reset = new CButton('filter_rst',S_RESET);
	$reset->setType('button');
	$reset->setAction('javascript: var uri = new Curl(location.href); uri.setArgument("filter_rst",1); location.href = uri.getUrl();');

	$filterForm->addItemToBottomRow(new CButton("filter_set",S_FILTER));
	$filterForm->addItemToBottomRow($reset);

	$audit_wdgt->addFlicker($filterForm, CProfile::get('web.auditlogs.filter.state',1));
//-------

	$sql_cond = '';
	if($_REQUEST['alias'])
		$sql_cond.=' AND u.alias='.zbx_dbstr($_REQUEST['alias']);

	if(($_REQUEST['action']>-1))
		$sql_cond.=' AND a.action='.$_REQUEST['action'].' ';

	if(($_REQUEST['resourcetype']>-1))
		$sql_cond.=' AND a.resourcetype='.$_REQUEST['resourcetype'].' ';

	$sql_cond.=' AND a.clock>'.$nav_time;

	$actions = array();

	$table = new CTableInfo();
	$table->setHeader(array(
			make_sorting_header(S_TIME,'clock'),
			make_sorting_header(S_USER,'alias'),
			make_sorting_header(S_IP,'ip'),
			make_sorting_header(S_RESOURCE,'resourcetype'),
			make_sorting_header(S_ACTION,'action'),
			S_ID,
			S_DESCRIPTION,
			S_DETAILS));

	$sql = 'SELECT a.auditid, a.clock, u.alias, a.ip, a.resourcetype, a.action, a.resourceid, a.resourcename, a.details '.
					' FROM auditlog a, users u '.
					' WHERE u.userid=a.userid '.
						$sql_cond.
						' AND '.DBin_node('u.userid', get_current_nodeid(null, PERM_READ_ONLY)).
					' ORDER BY a.clock DESC';
	$result = DBselect($sql, ($config['search_limit']+1));
	while($row=DBfetch($result)){
		switch($row['action']){
			case AUDIT_ACTION_ADD:		$action = S_ADDED; break;
			case AUDIT_ACTION_UPDATE:	$action = S_UPDATED; break;
			case AUDIT_ACTION_DELETE:	$action = S_DELETED; break;
			case AUDIT_ACTION_LOGIN:	$action = S_LOGIN;	break;
			case AUDIT_ACTION_LOGOUT:	$action = S_LOGOUT; break;
			case AUDIT_ACTION_ENABLE:	$action = S_ENABLED; break;
			case AUDIT_ACTION_DISABLE:	$action = S_DISABLED; break;
			default: $action = S_UNKNOWN_ACTION;
		}

		$row['action'] = $action;
		$row['resourcetype'] = audit_resource2str($row['resourcetype']);

		$actions[$row['auditid']] = $row;
	}

// sorting && paging
	order_page_result($actions, 'clock');
	$paging = getPagingLine($actions);
//---------

	foreach($actions as $num => $row){
		if(empty($row['details'])){
			$details = array();
			$sql = 'SELECT table_name,field_name,oldvalue,newvalue '.
					' FROM auditlog_details '.
					' WHERE auditid='.$row['auditid'];
			$db_details = DBselect($sql);
			while($db_detail = DBfetch($db_details)){
				$details[] = array($db_detail['table_name'].'.'.
									$db_detail['field_name'].': '.
									$db_detail['oldvalue'].' => '.
									$db_detail['newvalue'],BR());
			}
		}
		else{
			$details = $row['details'];
		}

		$table->addRow(array(
			date(S_DATE_FORMAT_YMDHMS,$row['clock']),
			$row['alias'],
			$row['ip'],
			$row['resourcetype'],
			$row['action'],
			$row['resourceid'],
			$row['resourcename'],
			new CCol($details, 'wraptext')
		));
	}

// PAGING FOOTER
	$table = array($paging, $table, $paging);
//---------

	$audit_wdgt->addItem($table);
	$audit_wdgt->show();
?>
<?php

include_once('include/page_footer.php');
?>
