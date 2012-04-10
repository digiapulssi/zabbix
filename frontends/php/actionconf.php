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
require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/include/forms.inc.php';

$page['title'] = _('Configuration of actions');
$page['file'] = 'actionconf.php';
$page['hist_arg'] = array();

require_once dirname(__FILE__).'/include/page_header.php';

$_REQUEST['eventsource'] = get_request('eventsource', CProfile::get('web.actionconf.eventsource', EVENT_SOURCE_TRIGGERS));
?>
<?php
//		VAR			TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION
	$fields=array(
		'actionid'=>		array(T_ZBX_INT, O_OPT, P_SYS, DB_ID, null),
		'name'=>			array(T_ZBX_STR, O_OPT,	 null, NOT_EMPTY, 'isset({save})', _('Name')),
		'eventsource'=>		array(T_ZBX_INT, O_MAND, null, IN(array(EVENT_SOURCE_TRIGGERS,EVENT_SOURCE_DISCOVERY,EVENT_SOURCE_AUTO_REGISTRATION)),	null),
		'evaltype'=>		array(T_ZBX_INT, O_OPT, null, IN(array(ACTION_EVAL_TYPE_AND_OR,ACTION_EVAL_TYPE_AND,ACTION_EVAL_TYPE_OR)),	'isset({save})'),
		'esc_period'=>		array(T_ZBX_INT, O_OPT, null, BETWEEN(60, 999999),
			'isset({save})&&isset({escalation})', _('Default escalation period')),
		'escalation'=>		array(T_ZBX_INT, O_OPT, null, IN("0,1"), null),
		'status'=>			array(T_ZBX_INT, O_OPT, null, IN(array(ACTION_STATUS_ENABLED,ACTION_STATUS_DISABLED)), null),
		'def_shortdata'=>	array(T_ZBX_STR, O_OPT,	null, null, 'isset({save})'),
		'def_longdata'=>	array(T_ZBX_STR, O_OPT,	null, null, 'isset({save})'),
		'recovery_msg'=>	array(T_ZBX_INT, O_OPT,	null, null, null),
		'r_shortdata'=>		array(T_ZBX_STR, O_OPT,	null, NOT_EMPTY, 'isset({recovery_msg})&&isset({save})',
			_('Recovery subject')),
		'r_longdata'=>		array(T_ZBX_STR, O_OPT,	null, NOT_EMPTY, 'isset({recovery_msg})&&isset({save})',
			_('Recovery message')),
		'g_actionid'=>		array(T_ZBX_INT, O_OPT,	null, DB_ID, null),
		'conditions'=>		array(null, O_OPT, null, null, null),
		'g_conditionid'=>	array(null, O_OPT, null, null, null),
		'new_condition'=>	array(null, O_OPT, null, null, 'isset({add_condition})'),
		'operations'=>		array(null, O_OPT, null, null, 'isset({save})'),
		'g_operationid'=>	array(null, O_OPT, null, null, null),
		'edit_operationid'=>	array(null, O_OPT, P_ACT, DB_ID, null),
		'new_operation'=>		array(null, O_OPT, null, null, 'isset({add_operation})'),
		'opconditions'=>		array(null, O_OPT, null, null, null),
		'g_opconditionid'=>		array(null, O_OPT, null, null, null),
		'new_opcondition'=>		array(null,	O_OPT,  null,	null,	'isset({add_opcondition})'),
// Actions
		'go'=>					array(T_ZBX_STR, O_OPT, P_SYS|P_ACT, NULL, NULL),
// form
		'add_condition'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'del_condition'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'cancel_new_condition'=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'add_operation'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'del_operation'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'cancel_new_operation'=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'add_opcondition'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'del_opcondition'=>		array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'cancel_new_opcondition'=>	array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),

		'save'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'clone'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'delete'=>			array(T_ZBX_STR, O_OPT, P_SYS|P_ACT,	null,	null),
		'cancel'=>			array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
/* other */
		'form'=>			array(T_ZBX_STR, O_OPT, P_SYS,	null,	null),
		'form_refresh'=>	array(T_ZBX_INT, O_OPT,	null,	null,	null),
//ajax
		'favobj'=>		array(T_ZBX_STR, O_OPT, P_ACT,	NULL, NULL),
		'favref'=>		array(T_ZBX_STR, O_OPT, P_ACT,	NOT_EMPTY, 'isset({favobj})'),
		'favstate'=>	array(T_ZBX_INT, O_OPT, P_ACT,	NOT_EMPTY, 'isset({favobj}) && ("filter"=={favobj})'),
	);

	check_fields($fields);
	validate_sort_and_sortorder('name', ZBX_SORT_UP);

	$_REQUEST['go'] = get_request('go', 'none');
?>
<?php
/* AJAX */
// for future use
	if(isset($_REQUEST['favobj'])){
		if('filter' == $_REQUEST['favobj']){
			CProfile::update('web.audit.filter.state', $_REQUEST['favstate'], PROFILE_TYPE_INT);
		}
	}

	if((PAGE_TYPE_JS == $page['type']) || (PAGE_TYPE_HTML_BLOCK == $page['type'])){
		require_once dirname(__FILE__).'/include/page_footer.php';
		exit();
	}
//--------

	if(isset($_REQUEST['actionid'])){
		$aa = API::Action()->get(array('actionids' => $_REQUEST['actionid'], 'editable' => 1));
		if(empty($aa)){
			access_deny();
		}
	}

	CProfile::update('web.actionconf.eventsource', $_REQUEST['eventsource'], PROFILE_TYPE_INT);
?>
<?php
	if (isset($_REQUEST['clone']) && isset($_REQUEST['actionid'])) {
		unset($_REQUEST['actionid']);
		$_REQUEST['form'] = 'clone';
	}
	elseif (isset($_REQUEST['cancel_new_operation'])) {
		unset($_REQUEST['new_operation']);
	}
	elseif (isset($_REQUEST['cancel_new_opcondition'])) {
		unset($_REQUEST['new_opcondition']);
	}
	elseif (isset($_REQUEST['save'])) {
		if (!count(get_accessible_nodes_by_user($USER_DETAILS, PERM_READ_WRITE, PERM_RES_IDS_ARRAY))) {
			access_deny();
		}

		$action = array(
			'name'				=> get_request('name'),
			'eventsource'		=> get_request('eventsource', 0),
			'evaltype'			=> get_request('evaltype', 0),
			'status'			=> get_request('status', ACTION_STATUS_DISABLED),
			'esc_period'		=> get_request('esc_period', 0),
			'def_shortdata'		=> get_request('def_shortdata', ''),
			'def_longdata'		=> get_request('def_longdata', ''),
			'recovery_msg'		=> get_request('recovery_msg', 0),
			'r_shortdata'		=> get_request('r_shortdata', ''),
			'r_longdata'		=> get_request('r_longdata', ''),
			'conditions'		=> get_request('conditions', array()),
			'operations'		=> get_request('operations', array()),
		);

		foreach ($action['operations'] as $anum => $op) {
			if (isset($op['opmessage']) && !isset($op['opmessage']['default_msg'])) {
				$action['operations'][$anum]['opmessage']['default_msg'] = 0;
			}
		}
		DBstart();
		if (isset($_REQUEST['actionid'])) {
			$action['actionid']= $_REQUEST['actionid'];

			$result = API::Action()->update($action);
			show_messages($result, _('Action updated'), _('Cannot update action'));
		}
		else {
			$result = API::Action()->create($action);
			show_messages($result, _('Action added'), _('Cannot add action'));
		}

		$result = DBend($result);
		if($result) {
			add_audit(!isset($_REQUEST['actionid'])?AUDIT_ACTION_ADD:AUDIT_ACTION_UPDATE,
				AUDIT_RESOURCE_ACTION,
				_('Name').': '.$_REQUEST['name']);

			unset($_REQUEST['form']);
		}
	}
	elseif (isset($_REQUEST['delete']) && isset($_REQUEST['actionid'])) {
		if (!count(get_accessible_nodes_by_user($USER_DETAILS, PERM_READ_WRITE, PERM_RES_IDS_ARRAY))) {
			access_deny();
		}

		$result = API::Action()->delete($_REQUEST['actionid']);

		show_messages($result, _('Action deleted'), _('Cannot delete action'));
		if ($result) {
			unset($_REQUEST['form']);
			unset($_REQUEST['actionid']);
		}
	}
	elseif (isset($_REQUEST['add_condition']) && isset($_REQUEST['new_condition'])) {
		$new_condition = $_REQUEST['new_condition'];

		try {
			CAction::validateConditions($new_condition);
			$_REQUEST['conditions'] = get_request('conditions', array());

			$exists = false;
			foreach ($_REQUEST['conditions'] as $condition) {
				if (($new_condition['conditiontype'] === $condition['conditiontype'])
					&& ($new_condition['operator'] === $condition['operator'])
					&& (!isset($new_condition['value']) || $new_condition['value'] === $condition['value'])
				) {
					$exists = true;
					break;
				}
			}

			if (!$exists) {
				array_push($_REQUEST['conditions'], $new_condition);
			}
		}
		catch (APIException $e) {
			error($e->getMessage());
		}
	}
	elseif (isset($_REQUEST['del_condition']) && isset($_REQUEST['g_conditionid'])) {
		$_REQUEST['conditions'] = get_request('conditions', array());
		foreach ($_REQUEST['g_conditionid'] as $condition) {
			unset($_REQUEST['conditions'][$condition]);
		}
	}
	elseif (isset($_REQUEST['add_opcondition']) && isset($_REQUEST['new_opcondition'])) {
		$new_opcondition = $_REQUEST['new_opcondition'];

		try {
			CAction::validateOperationConditions($new_opcondition);
			$new_operation = get_request('new_operation', array());

			if (!isset($new_operation['opconditions'])) {
				$new_operation['opconditions'] = array();
			}
			if (!str_in_array($new_opcondition, $new_operation['opconditions'])) {
				array_push($new_operation['opconditions'], $new_opcondition);
			}

			$_REQUEST['new_operation'] = $new_operation;

			unset($_REQUEST['new_opcondition']);
		}
		catch (APIException $e) {
			error($e->getMessage());
		}
	}
	elseif (isset($_REQUEST['del_opcondition']) && isset($_REQUEST['g_opconditionid'])) {
		$new_operation = get_request('new_operation', array());

		foreach ($_REQUEST['g_opconditionid'] as $condition) {
			unset($new_operation['opconditions'][$condition]);
		}

		$_REQUEST['new_operation'] = $new_operation;
	}
	elseif (isset($_REQUEST['add_operation']) && isset($_REQUEST['new_operation'])) {
		$new_operation = $_REQUEST['new_operation'];
		$result = true;

		if(API::Action()->validateOperations($new_operation)){
			$_REQUEST['operations'] = get_request('operations', array());

			$uniqOperations = array(
				OPERATION_TYPE_HOST_ADD => 0,
				OPERATION_TYPE_HOST_REMOVE => 0,
				OPERATION_TYPE_HOST_ENABLE => 0,
				OPERATION_TYPE_HOST_DISABLE => 0,
			);
			if(isset($uniqOperations[$new_operation['operationtype']])){
				foreach($_REQUEST['operations'] as $operation){
					if(isset($uniqOperations[$operation['operationtype']]))
						$uniqOperations[$operation['operationtype']]++;
				}
				if($uniqOperations[$new_operation['operationtype']]){
					$result = false;
					info(_s('Operation "%s" already exists.', operation_type2str($new_operation['operationtype'])));
					show_messages();
				}
			}

			if($result){
				if(isset($new_operation['id'])){
					$_REQUEST['operations'][$new_operation['id']] = $new_operation;
				}
				else{
					$_REQUEST['operations'][] = $new_operation;
					sortOperations($_REQUEST['operations']);
				}
			}

			unset($_REQUEST['new_operation']);
		}
	}
	elseif (isset($_REQUEST['del_operation']) && isset($_REQUEST['g_operationid'])) {
		$_REQUEST['operations'] = get_request('operations', array());
		foreach ($_REQUEST['g_operationid'] as $condition) {
			unset($_REQUEST['operations'][$condition]);
		}
		sortOperations($_REQUEST['operations']);
	}
	elseif (isset($_REQUEST['edit_operationid'])) {
		$_REQUEST['edit_operationid'] = array_keys($_REQUEST['edit_operationid']);
		$edit_operationid = $_REQUEST['edit_operationid'] = array_pop($_REQUEST['edit_operationid']);
		$_REQUEST['operations'] = get_request('operations', array());

		if(isset($_REQUEST['operations'][$edit_operationid])){
			$_REQUEST['new_operation'] = $_REQUEST['operations'][$edit_operationid];
			$_REQUEST['new_operation']['id'] = $edit_operationid;
			$_REQUEST['new_operation']['action'] = 'update';
		}
	}
// ------ GO ------
	elseif (str_in_array($_REQUEST['go'], array('activate','disable')) && isset($_REQUEST['g_actionid'])) {
		if (!count($nodes = get_accessible_nodes_by_user($USER_DETAILS, PERM_READ_WRITE, PERM_RES_IDS_ARRAY)))
			access_deny();

		$status = ($_REQUEST['go'] == 'activate')?0:1;
		$status_name = $status?'disabled':'enabled';

		DBstart();
		$actionids = array();
		$sql = 'SELECT DISTINCT a.actionid '.
					' FROM actions a '.
					' WHERE '.DBin_node('a.actionid', $nodes).
						' AND '.DBcondition('a.actionid', $_REQUEST['g_actionid']);

		$go_result=DBselect($sql);
		while ($row=DBfetch($go_result)) {
			$res = DBexecute("update actions set status=$status where actionid={$row['actionid']}");
			if($res)
				$actionids[] = $row['actionid'];
		}
		$go_result = DBend($res);

		if ($go_result && isset($res)) {
			show_messages($go_result, _('Status updated'), _('Cannot update status'));
			add_audit(AUDIT_ACTION_UPDATE, AUDIT_RESOURCE_ACTION, ' Actions ['.implode(',', $actionids).'] '.$status_name);
		}
	}
	elseif (($_REQUEST['go'] == 'delete') && isset($_REQUEST['g_actionid'])) {
		if (!count($nodes = get_accessible_nodes_by_user($USER_DETAILS, PERM_READ_WRITE, PERM_RES_IDS_ARRAY)))
			access_deny();

		$go_result = API::Action()->delete($_REQUEST['g_actionid']);
		show_messages($go_result, _('Selected actions deleted'), _('Cannot delete selected actions'));
	}

	if ($_REQUEST['go'] != 'none' && isset($go_result) && $go_result) {
		$url = new CUrl();
		$path = $url->getPath();
		insert_js('cookie.eraseArray("'.$path.'")');
	}

?>
<?php
	$action_wdgt = new CWidget();

	if (!isset($_REQUEST['form'])) {
		$formActions = new CForm('get');
		$formActions->cleanItems();
		$formActions->addVar('eventsource', $_REQUEST['eventsource']);
		$formActions->addItem(new CSubmit('form', _('Create action')));
	}


	if(isset($_REQUEST['form'])){
		$action = null;
		if(isset($_REQUEST['actionid'])){
			$options = array(
				'actionids' => $_REQUEST['actionid'],
				'selectOperations' => API_OUTPUT_EXTEND,
				'selectConditions' => API_OUTPUT_EXTEND,
				'output' => API_OUTPUT_EXTEND,
				'editable' => true
			);
			$actions = API::Action()->get($options);
			$action = reset($actions);
		}
		else {
			$eventsource = get_request('eventsource');
			$evaltype = get_request('evaltype');
			$esc_period	= get_request('esc_period');
		}

		if (isset($action['actionid']) && !isset($_REQUEST['form_refresh'])) {
			sortOperations($action['operations']);
		}
		else {
			if (isset($_REQUEST['escalation']) && 0 == $_REQUEST['esc_period']) {
				$_REQUEST['esc_period'] = SEC_PER_HOUR;
			}

			$action['name'] = get_request('name');
			$action['eventsource'] = get_request('eventsource');
			$action['evaltype'] = get_request('evaltype', 0);
			$action['esc_period'] = get_request('esc_period', SEC_PER_HOUR);
			$action['status'] = get_request('status', isset($_REQUEST['form_refresh']) ? 1 : 0);
			$action['recovery_msg'] = get_request('recovery_msg', 0);
			$action['r_shortdata'] = get_request('r_shortdata', ACTION_DEFAULT_SUBJ_TRIGGER);
			$action['r_longdata'] = get_request('r_longdata', ACTION_DEFAULT_MSG_TRIGGER);
			$action['conditions'] = get_request('conditions', array());
			$action['operations'] = get_request('operations', array());

			if (isset($_REQUEST['actionid']) && isset($_REQUEST['form_refresh'])) {
				$action['def_shortdata'] = get_request('def_shortdata');
				$action['def_longdata'] = get_request('def_longdata');
			}
			else {
				if ($eventsource == EVENT_SOURCE_TRIGGERS) {
					$action['def_shortdata'] = get_request('def_shortdata', ACTION_DEFAULT_SUBJ_TRIGGER);
					$action['def_longdata'] = get_request('def_longdata', ACTION_DEFAULT_MSG_TRIGGER);
				}
				elseif ($eventsource == EVENT_SOURCE_DISCOVERY) {
					$action['def_shortdata'] = get_request('def_shortdata', ACTION_DEFAULT_SUBJ_DISCOVERY);
					$action['def_longdata'] = get_request('def_longdata', ACTION_DEFAULT_MSG_DISCOVERY);
				}
				elseif ($eventsource == EVENT_SOURCE_AUTO_REGISTRATION) {
					$action['def_shortdata'] = get_request('def_shortdata', ACTION_DEFAULT_SUBJ_AUTOREG);
					$action['def_longdata'] = get_request('def_longdata', ACTION_DEFAULT_MSG_AUTOREG);
				}
			}
		}

		if (!isset($action['actionid']) && !isset($_REQUEST['form_refresh'])) {
			$action['conditions'] = array(
				array(
					'conditiontype' => CONDITION_TYPE_TRIGGER_VALUE,
					'operator' => CONDITION_OPERATOR_EQUAL,
					'value' => TRIGGER_VALUE_TRUE,
				),
				array(
					'conditiontype' => CONDITION_TYPE_MAINTENANCE,
					'operator' => CONDITION_OPERATOR_NOT_IN,
					'value' => '',
				),
			);
		}

		$actionForm = new CView('configuration.action.edit', $action);

		$action_wdgt->addPageHeader(_('CONFIGURATION OF ACTIONS'));
		$action_wdgt->addItem($actionForm->render());

		show_messages();
	}
	else {
		$form = new CForm('get');

		$cmbSource = new CComboBox('eventsource', $_REQUEST['eventsource'], 'submit()');
		$cmbSource->addItem(EVENT_SOURCE_TRIGGERS, _('Triggers'));
		$cmbSource->addItem(EVENT_SOURCE_DISCOVERY, _('Discovery'));
		$cmbSource->addItem(EVENT_SOURCE_AUTO_REGISTRATION, _('Auto registration'));
		$form->addItem(array(_('Event source'), SPACE, $cmbSource));

		$action_wdgt->addPageHeader(_('CONFIGURATION OF ACTIONS'), $formActions);
		$action_wdgt->addHeader(_('Actions'), $form);
		$action_wdgt->addHeaderRowNumber();

// table
		$form = new CForm();
		$form->setName('actions');

		$tblActions = new CTableInfo(_('No actions defined.'));
		$tblActions->setHeader(array(
			new CCheckBox('all_items', null, "checkAll('".$form->getName()."','all_items','g_actionid');"),
			make_sorting_header(_('Name'), 'name'),
			_('Conditions'),
			_('Operations'),
			make_sorting_header(_('Status'), 'status')
		));


		$sortfield = getPageSortField('name');
		$sortorder = getPageSortOrder();
		$options = array(
			'output' => API_OUTPUT_EXTEND,
			'filter' => array(
				'eventsource' => array($_REQUEST['eventsource'])
			),
			'selectConditions' => API_OUTPUT_EXTEND,
			'selectOperations' => API_OUTPUT_EXTEND,
			'editable' => 1,
			'sortfield' => $sortfield,
			'sortorder' => $sortorder,
			'limit' => ($config['search_limit']+1)
		);
		$actions = API::Action()->get($options);
//SDII($actions);
// sorting && paging
		order_result($actions, $sortfield, $sortorder);
		$paging = getPagingLine($actions);
//-------

		foreach($actions as $anum => $action){

			$conditions = array();
			order_result($action['conditions'], 'conditiontype', ZBX_SORT_DOWN);
			foreach($action['conditions'] as $cnum => $condition){
				$conditions[] = array(
					get_condition_desc($condition['conditiontype'], $condition['operator'], $condition['value']),
					BR()
				);
			}

			sortOperations($action['operations']);
			$operations = array();
			foreach($action['operations'] as $onum => $operation){
				$operations[] = get_operation_desc(SHORT_DESCRIPTION, $operation);
			}

			if($action['status'] == ACTION_STATUS_DISABLED){
				$status= new CLink(_('Disabled'),
					'actionconf.php?go=activate&g_actionid%5B%5D='.$action['actionid'].url_param('eventsource'),
					'disabled');
			}
			else{
				$status= new CLink(_('Enabled'),
					'actionconf.php?go=disable&g_actionid%5B%5D='.$action['actionid'].url_param('eventsource'),
					'enabled');
			}

			$tblActions->addRow(array(
				new CCheckBox('g_actionid['.$action['actionid'].']', null, null, $action['actionid']),
				new CLink($action['name'], 'actionconf.php?form=update&actionid='.$action['actionid']),
				$conditions,
				new CCol($operations, 'wraptext'),
				$status
			));
		}

//----- GO ------
		$goBox = new CComboBox('go');
		$goOption = new CComboItem('activate', _('Enable selected'));
		$goOption->setAttribute('confirm', _('Enable selected actions?'));
		$goBox->addItem($goOption);

		$goOption = new CComboItem('disable', _('Disable selected'));
		$goOption->setAttribute('confirm', _('Disable selected actions?'));
		$goBox->addItem($goOption);

		$goOption = new CComboItem('delete', _('Delete selected'));
		$goOption->setAttribute('confirm', _('Delete selected actions?'));
		$goBox->addItem($goOption);

		$goButton = new CSubmit('goButton', _('Go'));
		$goButton->setAttribute('id', 'goButton');
		zbx_add_post_js('chkbxRange.pageGoName = "g_actionid";');

		$footer = get_table_header(array($goBox, $goButton));


		$form->addItem(array($paging, $tblActions, $paging, $footer));
		$action_wdgt->addItem($form);
	}

	$action_wdgt->show();

?>
<?php

require_once dirname(__FILE__).'/include/page_footer.php';

?>
