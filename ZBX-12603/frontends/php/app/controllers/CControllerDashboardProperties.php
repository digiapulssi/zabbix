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

class CControllerDashboardProperties extends CControllerDashboardAbstract {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'dashboardid' =>		'db dashboard.dashboardid',
			'editable'	  =>		'in 0,1',
			'name'		  =>		'string',
			'userid'	  =>		'db users.userid',
			'new'		  =>		'in 1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		return !$this->getInput('dashboardid') || (bool) API::Dashboard()->get([
			'output' => [],
			'dashboardids' => $this->getInput('dashboardid'),
			'editable' => true
		]);
	}

	protected function doAction() {
		if ($this->getInput('new', false)) {
			$dashboard = (new CControllerDashboardView())->getNewDashboard();
		}
		else {
			$dashboards = API::Dashboard()->get([
				'output' => ['name', 'dashboardid', 'userid'],
				'dashboardids' => $this->getInput('dashboardid'),
				'editable' => (bool) $this->getInput('editable', false),
				'preservekeys' => true
			]);

			$dashboard = reset($dashboards);
		}

		if ($dashboard !== false) {
			/*
			 * TODO miks: improve consistancy.
			 *
			 * CControllerDashboardView::getNewDashboard returns owner as array, but API returns only integer userid.
			 */
			if ($dashboard['userid']) {
				$userid = $this->getInput('userid', $dashboard['userid']);

				// Get user data.
				$user = API::User()->get([
					'output' => ['alias', 'name', 'surname', 'userid'],
					'userids' => $userid
				]);

				if (($user = reset($user)) !== false) {
					$user['name'] = getUserFullname($user);
					unset($user['alias'], $user['surname']);
				}

				$user['id'] = $user['userid'];
				unset($user['userid']);
			}
			elseif (array_key_exists('owner', $dashboard)) {
				$user = $dashboard['owner'];
			}

			// Prepare data for view.
			$data = [
				'name' => $this->getInput('name', $dashboard['name']),
				'dashboardid' => $dashboard['dashboardid'],
				'owner' => $user
			];
		}
		else {
			$data['error'] = _('No permissions to referred object or it does not exist!');
		}

		$this->setResponse(new CControllerResponseData($data));
	}
}
