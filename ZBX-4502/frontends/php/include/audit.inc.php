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
function audit_resource2str($resource_type = null) {
	$resources = array(
		AUDIT_RESOURCE_USER => _('User'),
		AUDIT_RESOURCE_ZABBIX_CONFIG => _('Configuration of Zabbix'),
		AUDIT_RESOURCE_MEDIA_TYPE => _('Media type'),
		AUDIT_RESOURCE_HOST => _('Host'),
		AUDIT_RESOURCE_ACTION => _('Action'),
		AUDIT_RESOURCE_GRAPH => _('Graph'),
		AUDIT_RESOURCE_GRAPH_ELEMENT => _('Graph element'),
		AUDIT_RESOURCE_USER_GROUP => _('User group'),
		AUDIT_RESOURCE_APPLICATION => _('Application'),
		AUDIT_RESOURCE_TRIGGER => _('Trigger'),
		AUDIT_RESOURCE_TRIGGER_PROTOTYPE => _('Trigger prototype'),
		AUDIT_RESOURCE_HOST_GROUP => _('Host group'),
		AUDIT_RESOURCE_ITEM => _('Item'),
		AUDIT_RESOURCE_IMAGE => _('Image'),
		AUDIT_RESOURCE_VALUE_MAP => _('Value map'),
		AUDIT_RESOURCE_IT_SERVICE => _('IT service'),
		AUDIT_RESOURCE_MAP => _('Map'),
		AUDIT_RESOURCE_SCREEN => _('Screen'),
		AUDIT_RESOURCE_NODE => _('Node'),
		AUDIT_RESOURCE_SCENARIO => _('Scenario'),
		AUDIT_RESOURCE_DISCOVERY_RULE => _('Discovery rule'),
		AUDIT_RESOURCE_SLIDESHOW => _('Slide show'),
		AUDIT_RESOURCE_PROXY => _('Proxy'),
		AUDIT_RESOURCE_REGEXP => _('Regular expression'),
		AUDIT_RESOURCE_MAINTENANCE => _('Maintenance'),
		AUDIT_RESOURCE_SCRIPT => _('Script'),
		AUDIT_RESOURCE_MACRO => _('Macro'),
		AUDIT_RESOURCE_TEMPLATE => _('Template')
	);

	if (is_null($resource_type)) {
		natsort($resources);
		return $resources;
	}
	elseif (isset($resources[$resource_type])) {
		return $resources[$resource_type];
	}
	else {
		return _('Unknown resource');
	}
}

function add_audit_if($condition, $action, $resourcetype, $details) {
	if ($condition) {
		return add_audit($action,$resourcetype,$details);
	}
	return false;
}

function add_audit($action, $resourcetype, $details) {
	if (CWebUser::$data['userid'] == 0) {
		return true;
	}

	$auditid = get_dbid('auditlog', 'auditid');

	if (zbx_strlen($details) > 128) {
		$details = zbx_substr($details, 0, 125).'...';
	}

	$ip = isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR'];

	$result = DBexecute('INSERT INTO auditlog (auditid,userid,clock,action,resourcetype,details,ip)'.
			' VALUES ('.$auditid.','.CWebUser::$data['userid'].','.time().','.$action.','.$resourcetype.
			','.zbx_dbstr($details).','.zbx_dbstr($ip).')');
	if ($result) {
		$result = $auditid;
	}
	return $result;
}

function add_audit_ext($action, $resourcetype, $resourceid, $resourcename, $table_name, $values_old, $values_new) {
	$values_diff = array();
	if ($action == AUDIT_ACTION_UPDATE && !empty($values_new)) {
		foreach ($values_new as $id => $value) {
			if ($values_old[$id] !== $value) {
				array_push($values_diff, $id);
			}
		}
		if (count($values_diff) == 0) {
			return true;
		}
	}

	$auditid = get_dbid('auditlog', 'auditid');

	if (zbx_strlen($resourcename) > 255) {
		$resourcename = zbx_substr($resourcename, 0, 252).'...';
	}

	$ip = isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR'];
	$result = DBexecute('INSERT INTO auditlog (auditid,userid,clock,ip,action,resourcetype,resourceid,resourcename)'.
			' VALUES ('.$auditid.','.CWebUser::$data['userid'].','.time().','.zbx_dbstr($ip).
			','.$action.','.$resourcetype.','.$resourceid.','.zbx_dbstr($resourcename).')');

	if ($result && $action == AUDIT_ACTION_UPDATE) {
		foreach ($values_diff as $id) {
			$auditdetailid = get_dbid('auditlog_details', 'auditdetailid');
			$result &= DBexecute('INSERT INTO auditlog_details (auditdetailid,auditid,table_name,field_name,oldvalue,newvalue)'.
					' VALUES ('.$auditdetailid.','.$auditid.','.zbx_dbstr($table_name).','.
					zbx_dbstr($id).','.zbx_dbstr($values_old[$id]).','.zbx_dbstr($values_new[$id]).')');
		}
	}

	if ($result) {
		$result = $auditid;
	}

	return $result;
}
?>
