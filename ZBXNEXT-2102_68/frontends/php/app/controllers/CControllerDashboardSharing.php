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

class CControllerDashboardSharing extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'dashboardid' =>		'db dashboard.dashboardid',
			'editable'	  =>		'in 0,1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		return (bool) API::Dashboard()->get([
			'output' => [],
			'dashboardids' => $this->getInput('dashboardid'),
			'editable' => true
		]);
	}

	protected function doAction() {
		$dashboards = API::Dashboard()->get([
			'output' => ['dashboardid', 'private'],
			'selectUsers' => 'extend',
			'selectUserGroups' => 'extend',
			'dashboardids' => $this->getInput('dashboardid'),
			'editable' => (bool) $this->getInput('editable', false)
		]);

		if (($dashboard = reset($dashboards)) !== false) {
			// Get selected user groups.
			$user_groups = [];
			if ($dashboard['userGroups']) {
				$user_groups = API::UserGroup()->get([
					'output' => ['name'],
					'usrgrpids' => zbx_objectValues($dashboard['userGroups'], 'usrgrpid'),
					'preservekeys' => true
				]);

				foreach ($dashboard['userGroups'] as &$usrgrp) {
					$usrgrp['name'] = $user_groups[$usrgrp['usrgrpid']]['name'];
				}
				unset($usrgrp, $user_groups);

			}

			// Get selected users.
			if ($dashboard['users']) {
				$users = API::User()->get([
					'output' => ['alias', 'name', 'surname'],
					'userids' => zbx_objectValues($dashboard['users'], 'userid'),
					'preservekeys' => true
				]);

				foreach ($dashboard['users'] as &$user) {
					$user['name'] = getUserFullname($users[$user['userid']]);
				}
				unset($user, $users);
			}

			$data = [
				'private' => $dashboard['private'],
				'users' => $dashboard['users'],
				'userGroups' => $dashboard['userGroups'],
				'dashboardid' => $dashboard['dashboardid']
			];
		}
		else {
			$data['error'] = _('No permissions to referred object or it does not exist!');
		}

		$this->setResponse(new CControllerResponseData($data));
	}
}
