<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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


require_once dirname(__FILE__).'/include/config.inc.php';

$requestType = getRequest('type', PAGE_TYPE_JSON);
if ($requestType == PAGE_TYPE_JSON) {
	$http_request = new CHttpRequest();
	$json = new CJson();
	$data = $json->decode($http_request->body(), true);
}
else {
	$data = $_REQUEST;
}

$page['title'] = 'RPC';
$page['file'] = 'jsrpc.php';
$page['type'] = detect_page_type($requestType);

require_once dirname(__FILE__).'/include/page_header.php';

if (!is_array($data) || !isset($data['method'])
		|| ($requestType == PAGE_TYPE_JSON && (!isset($data['params']) || !is_array($data['params'])))) {
		// TODO this fatal_error method could also set response status code ... I did run into issue when jsRPC acted as successful in case of invalid request.
	fatal_error('Wrong RPC call to JS RPC!');
}

$result = [];
switch ($data['method']) {
	case 'host.get':
		$result = API::Host()->get([
			'startSearch' => true,
			'search' => $data['params']['search'],
			'output' => ['hostid', 'host', 'name'],
			'sortfield' => 'name',
			'limit' => 15
		]);
		break;

	case 'zabbix.status':
		CSession::start();
		if (!CSession::keyExists('serverCheckResult')
				|| (CSession::getValue('serverCheckTime') + SERVER_CHECK_INTERVAL) <= time()) {
			$zabbixServer = new CZabbixServer($ZBX_SERVER, $ZBX_SERVER_PORT, ZBX_SOCKET_TIMEOUT, 0);
			CSession::setValue('serverCheckResult', $zabbixServer->isRunning());
			CSession::setValue('serverCheckTime', time());
		}

		$result = [
			'result' => (bool) CSession::getValue('serverCheckResult'),
			'message' => CSession::getValue('serverCheckResult')
				? ''
				: _('Zabbix server is not running: the information displayed may not be current.')
		];
		break;

	case 'screen.get':
		$result = '';
		$screenBase = CScreenBuilder::getScreen($data);
		if ($screenBase !== null) {
			$screen = $screenBase->get();

			if ($data['mode'] == SCREEN_MODE_JS) {
				$result = $screen;
			}
			else {
				if (is_object($screen)) {
					$result = $screen->toString();
				}
			}
		}
		break;

	/**
	 * Create multi select data.
	 * Supported objects: "applications", "hosts", "hostGroup", "templates", "triggers"
	 *
	 * @param string $data['objectName']
	 * @param string $data['search']
	 * @param int    $data['limit']
	 *
	 * @return array(int => array('value' => int, 'text' => string))
	 */
	case 'multiselect.get':
		$config = select_config();

		switch ($data['objectName']) {
			case 'hostGroup':
				$hostGroups = API::HostGroup()->get([
					'editable' => isset($data['editable']) ? $data['editable'] : false,
					'output' => ['groupid', 'name'],
					'search' => isset($data['search']) ? ['name' => $data['search']] : null,
					'filter' => isset($data['filter']) ? $data['filter'] : null,
					'limit' => isset($data['limit']) ? $data['limit'] : null
				]);

				if ($hostGroups) {
					CArrayHelper::sort($hostGroups, [
						['field' => 'name', 'order' => ZBX_SORT_UP]
					]);

					if (isset($data['limit'])) {
						$hostGroups = array_slice($hostGroups, 0, $data['limit']);
					}

					foreach ($hostGroups as $hostGroup) {
						$result[] = [
							'id' => $hostGroup['groupid'],
							'name' => $hostGroup['name']
						];
					}
				}
				break;

			case 'hosts':
				$hosts = API::Host()->get([
					'editable' => isset($data['editable']) ? $data['editable'] : false,
					'output' => ['hostid', 'name'],
					'templated_hosts' => isset($data['templated_hosts']) ? $data['templated_hosts'] : null,
					'search' => isset($data['search']) ? ['name' => $data['search']] : null,
					'limit' => $config['search_limit']
				]);

				if ($hosts) {
					CArrayHelper::sort($hosts, [
						['field' => 'name', 'order' => ZBX_SORT_UP]
					]);

					if (isset($data['limit'])) {
						$hosts = array_slice($hosts, 0, $data['limit']);
					}

					foreach ($hosts as $host) {
						$result[] = [
							'id' => $host['hostid'],
							'name' => $host['name']
						];
					}
				}
				break;

			case 'templates':
				$templates = API::Template()->get([
					'editable' => isset($data['editable']) ? $data['editable'] : false,
					'output' => ['templateid', 'name'],
					'search' => isset($data['search']) ? ['name' => $data['search']] : null,
					'limit' => $config['search_limit']
				]);

				if ($templates) {
					CArrayHelper::sort($templates, [
						['field' => 'name', 'order' => ZBX_SORT_UP]
					]);

					if (isset($data['limit'])) {
						$templates = array_slice($templates, 0, $data['limit']);
					}

					foreach ($templates as $template) {
						$result[] = [
							'id' => $template['templateid'],
							'name' => $template['name']
						];
					}
				}
				break;

			case 'applications':
				$applications = API::Application()->get([
					'hostids' => zbx_toArray($data['hostid']),
					'output' => ['applicationid', 'name'],
					'search' => isset($data['search']) ? ['name' => $data['search']] : null,
					'limit' => $config['search_limit']
				]);

				if ($applications) {
					CArrayHelper::sort($applications, [
						['field' => 'name', 'order' => ZBX_SORT_UP]
					]);

					if (isset($data['limit'])) {
						$applications = array_slice($applications, 0, $data['limit']);
					}

					foreach ($applications as $application) {
						$result[] = [
							'id' => $application['applicationid'],
							'name' => $application['name']
						];
					}
				}
				break;

			case 'triggers':
				$triggers = API::Trigger()->get([
					'editable' => isset($data['editable']) ? $data['editable'] : false,
					'output' => ['triggerid', 'description'],
					'selectHosts' => ['name'],
					'search' => isset($data['search']) ? ['description' => $data['search']] : null,
					'limit' => $config['search_limit']
				]);

				if ($triggers) {
					CArrayHelper::sort($triggers, [
						['field' => 'description', 'order' => ZBX_SORT_UP]
					]);

					if (isset($data['limit'])) {
						$triggers = array_slice($triggers, 0, $data['limit']);
					}

					foreach ($triggers as $trigger) {
						$hostName = '';

						if ($trigger['hosts']) {
							$trigger['hosts'] = reset($trigger['hosts']);

							$hostName = $trigger['hosts']['name'].NAME_DELIMITER;
						}

						$result[] = [
							'id' => $trigger['triggerid'],
							'name' => $trigger['description'],
							'prefix' => $hostName
						];
					}
				}
				break;

			case 'users':
				$users = API::User()->get([
					'editable' => array_key_exists('editable', $data) ? $data['editable'] : false,
					'output' => ['userid', 'alias', 'name', 'surname'],
					'search' => array_key_exists('search', $data)
						? [
							'alias' => $data['search'],
							'name' => $data['search'],
							'surname' => $data['search']
						]
						: null,
					'searchByAny' => true,
					'limit' => $config['search_limit']
				]);

				if ($users) {
					CArrayHelper::sort($users, [
						['field' => 'alias', 'order' => ZBX_SORT_UP]
					]);

					if (array_key_exists('limit', $data)) {
						$users = array_slice($users, 0, $data['limit']);
					}

					foreach ($users as $user) {
						$result[] = [
							'id' => $user['userid'],
							'name' => getUserFullname($user)
						];
					}
				}
				break;

			case 'usersGroups':
				$groups = API::UserGroup()->get([
					'output' => ['usrgrpid', 'name'],
					'search' => array_key_exists('search', $data) ? ['name' => $data['search']] : null,
					'limit' => $config['search_limit']
				]);

				if ($groups) {
					CArrayHelper::sort($groups, [
						['field' => 'name', 'order' => ZBX_SORT_UP]
					]);

					if (array_key_exists('limit', $data)) {
						$groups = array_slice($groups, 0, $data['limit']);
					}

					foreach ($groups as $group) {
						$result[] = CArrayHelper::renameKeys($group, ['usrgrpid' => 'id']);
					}
				}
				break;

		}
		break;

	default:
		fatal_error('Wrong RPC call to JS RPC!');
}

if ($requestType == PAGE_TYPE_JSON) {
	if (isset($data['id'])) {
		echo $json->encode([
			'jsonrpc' => '2.0',
			'result' => $result,
			'id' => $data['id']
		]);
	}
}
elseif ($requestType == PAGE_TYPE_TEXT_RETURN_JSON) {
	$json = new CJson();

	echo $json->encode([
		'jsonrpc' => '2.0',
		'result' => $result
	]);
}
elseif ($requestType == PAGE_TYPE_TEXT || $requestType == PAGE_TYPE_JS) {
	echo $result;
}

require_once dirname(__FILE__).'/include/page_footer.php';
