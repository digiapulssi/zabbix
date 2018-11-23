<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


class CExternalService {

	/**
	 * At least one external service media is active for current user.
	 *
	 * @var string
	 */
	public static $media_active = false;

	/**
	 * External service status.
	 *
	 * @var bool
	 */
	public static $enabled = false;

	/**
	 * Media severity.
	 *
	 * @var string
	 */
	public static $severity;

	/**
	 * Initialize the external service. First check if event trigger severity corresponds to minimum required severity
	 * to create, update or request a ticket. Then check if current user has the external service set in his media.
	 * If everything so far is ok, in next step check if Zabbix server is online. In case it's not possible to connect
	 * to Zabbix server, it's not possibe to start the external Service and return false with error message from
	 * Zabbix server.
	 *
	 * @return bool
	 */
	public static function init() {
		global $ZBX_SERVER, $ZBX_SERVER_PORT;

		$mediatypes = API::MediaType()->get([
			'output' => ['mediatypeid'],
			'selectMedias' => ['mediaid', 'userid', 'active', 'severity'],
			'userids' => [CWebUser::$data['userid']],
			'filter' => [
				'type' => [MEDIA_TYPE_SERVICENOW, MEDIA_TYPE_REMEDY],
				'status' => MEDIA_TYPE_STATUS_ACTIVE
			]
		]);

		if (!$mediatypes) {
			return false;
		}

		foreach ($mediatypes as $mediatype) {
			foreach ($mediatype['medias'] as $media) {
				if ($media['userid'] == CWebUser::$data['userid'] && $media['active'] == MEDIA_TYPE_STATUS_ACTIVE) {
					self::$media_active = true;
					self::$severity = $media['severity'];
					break 2;
				}
			}
		}

		// At least one media should be active.
		if (!self::$media_active) {
			return false;
		}

		// Check if server is online to do further requests to it.
		$zabbixServer = new CZabbixServer(
			$ZBX_SERVER,
			$ZBX_SERVER_PORT,
			ZBX_SOCKET_EXTERNAL_TIMEOUT,
			ZBX_SOCKET_BYTES_LIMIT
		);

		self::$enabled = $zabbixServer->isRunning(CWebUser::getSessionCookie());

		if (!self::$enabled) {
			error($zabbixServer->getError());
		}

		return self::$enabled;
	}

	/**
	 * Query Zabbix server about an existing event. Returns false if external service is not enabled, no event data was
	 * passed, error connecting to Zabbix server or something went wrong with actual ticket. If query was success,
	 * receive array of raw ticket data from Zabbix server and then process each field. Returns array of processed
	 * ticket data (link to ticket, correct time format etc).
	 *
	 * @global string $ZBX_SERVER
	 * @global string $ZBX_SERVER_PORT
	 *
	 * @param int     $eventid
	 *
	 * @return array
	 */
	public static function mediaQuery($eventid = null) {
		global $ZBX_SERVER, $ZBX_SERVER_PORT;

		if (!self::$enabled || $eventid === null) {
			return [];
		}

		$zabbixServer = new CZabbixServer(
			$ZBX_SERVER,
			$ZBX_SERVER_PORT,
			ZBX_SOCKET_EXTERNAL_TIMEOUT,
			ZBX_SOCKET_BYTES_LIMIT
		);

		$ticket = $zabbixServer->mediaQuery([$eventid], get_cookie('zbx_sessionid'));
		$zabbixServerError = $zabbixServer->getError();

		if ($zabbixServerError) {
			error($zabbixServerError);

			self::$enabled = false;

			return [];
		}
		else {
			$ticket = zbx_toHash($ticket, 'eventid');

			if (array_key_exists('error', $ticket[$eventid]) && $ticket[$eventid]['error'] !== '') {
				error($ticket[$eventid]['error']);

				self::$enabled = false;

				return [];
			}
			elseif ($ticket[$eventid]['externalid'] !== '') {
				return self::getDetails($ticket[$eventid]);
			}
			else {
				return [];
			}
		}
	}

	/**
	 * Send event data to external service to create, update or reopen a ticket. Returns false if external service is
	 * not enabled, no event data was passed, error connecting to Zabbix server or something went wrong with actual
	 * ticket. If operation was success, receive array of raw ticket data from Zabbix server and then process each
	 * field. Returns array of processed ticket data (link to ticket, correct time format etc).
	 *
	 * @global string $ZBX_SERVER
	 * @global string $ZBX_SERVER_PORT
	 *
	 * @param string $event['eventid']  An existing event ID.
	 * @param string $event['message']  User message when acknowledging event.
	 * @param string $event['subject']  Trigger status 'OK' or 'PROBLEM'
	 *
	 * @return array
	 */
	public static function mediaAcknowledge(array $event = []) {
		global $ZBX_SERVER, $ZBX_SERVER_PORT;

		if (!self::$enabled || !$event) {
			return [];
		}

		$zabbixServer = new CZabbixServer(
			$ZBX_SERVER,
			$ZBX_SERVER_PORT,
			ZBX_SOCKET_EXTERNAL_TIMEOUT,
			ZBX_SOCKET_BYTES_LIMIT
		);

		$tickets = $zabbixServer->mediaAcknowledge([$event], get_cookie('zbx_sessionid'));
		$zabbixServerError = $zabbixServer->getError();

		if ($zabbixServerError) {
			error($zabbixServerError);

			self::$enabled = false;

			return [];
		}
		else {
			$tickets = zbx_toHash($tickets, 'eventid');
			$eventid = $event['eventid'];

			if (array_key_exists('error', $tickets[$eventid]) && $tickets[$eventid]['error'] !== '') {
				error($tickets[$eventid]['error']);

				self::$enabled = false;

				return [];
			}
			elseif ($tickets[$eventid]['externalid'] !== '') {
				switch ($tickets[$eventid]['action']) {
					case ZBX_TICKET_ACTION_CREATE:
						$messageSuccess = _s('Ticket "%1$s" has been created.', $tickets[$eventid]['externalid']);
						break;

					case ZBX_TICKET_ACTION_UPDATE:
						$messageSuccess = _s('Ticket "%1$s" has been updated.', $tickets[$eventid]['externalid']);
						break;

					case ZBX_TICKET_ACTION_REOPEN:
						$messageSuccess = _s('Ticket "%1$s" has been reopened.', $tickets[$eventid]['externalid']);
				}

				info($messageSuccess);

				return self::getDetails($tickets[$eventid]);
			}
		}
	}

	/**
	 * Creates external service ticket link and converts clock to readable time format and returns array of ticket data.
	 *
	 * @param array $data  External service ticket data.
	 *
	 * @return array
	 */
	protected static function getDetails(array $data) {
		$link = (array_key_exists('url', $data) && $data['url'] !== '')
			? (new CLink($data['externalid'], $data['url'], null, null, true))->setTarget('_blank')
			: $data['externalid'];

		$return = [
			'ticketId' => $data['externalid'],
			'link' => $link,
			'created' => zbx_date2str(DATE_TIME_FORMAT_SECONDS, $data['clock']),
			'action' => $data['action']
		];

		// media.acknowledge might not return status for Remedy service.
		if (array_key_exists('status', $data)) {
			$return['status'] = $data['status'];
		}

		// media.acknowledge and media.query might not return assignee for Remedy service for new tickets.
		if (array_key_exists('assignee', $data)) {
			$return['assignee'] = $data['assignee'];
		}

		return $return;
	}
}
