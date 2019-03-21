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


class CControllerNotificationsGet extends CController {

	protected function checkInput() {
		return true;
	}

	protected function checkPermissions() {
		return (!CWebUser::isGuest() && $this->getUserType() >= USER_TYPE_ZABBIX_USER);
	}

	protected function doAction() {
		$msgsettings = getMessageSettings();
		$triggerLimit = 15;

		$result = [
			'notifications' => [],
			'listid' => '',
			'settings' => [
				'timeout' => intval($msgsettings['sounds.repeat']),
				'muted' => boolval($msgsettings['sounds.mute']),
				'files' => [
					'-1' => $msgsettings['sounds.recovery'],
					'0' => $msgsettings['sounds.0'],
					'1' => $msgsettings['sounds.1'],
					'2' => $msgsettings['sounds.2'],
					'3' => $msgsettings['sounds.3'],
					'4' => $msgsettings['sounds.4'],
					'5' => $msgsettings['sounds.5']
				]
			]
		];

		if (!$msgsettings['triggers.severities']) {
			return $this->setResponse(new CControllerResponseData(['main_block' => json_encode($result)]));
		}

		$options = [
			'monitored' => true,
			'lastChangeSince' => max([$msgsettings['last.clock'], time() - $msgsettings['timeout']]),
			'value' => [TRIGGER_VALUE_TRUE, TRIGGER_VALUE_FALSE],
			'priority' => array_keys($msgsettings['triggers.severities']),
			'triggerLimit' => $triggerLimit
		];
		if (!$msgsettings['triggers.recovery']) {
			$options['value'] = [TRIGGER_VALUE_TRUE];
		}

		$events = getLastEvents($options);

		$sort_clock = [];
		$sort_event = [];
		$listid = '';

		$used_triggers = [];
		foreach ($events as $event) {
			if (count($used_triggers) == $triggerLimit) {
				break;
			}

			if (isset($used_triggers[$event['objectid']])) {
				continue;
			}

			$uid = $event['eventid'].'_'.$event['value'];
			$result['listid'] .= $uid;

			$trigger = $event['trigger'];
			$host = $event['host'];

			if ($event['value'] == TRIGGER_VALUE_FALSE) {
				$priority = 0;
				$title = _('Resolved');
				$fileid = '-1';
			}
			else {
				$priority = $trigger['priority'];
				$title = _('Problem on');
				$fileid = $trigger['priority'];
			}

			$url_tr_status = 'tr_status.php?hostid='.$host['hostid'];
			$url_events = 'events.php?filter_set=1&triggerid='.$event['objectid'].'&source='.EVENT_SOURCE_TRIGGERS;
			$url_tr_events = 'tr_events.php?eventid='.$event['eventid'].'&triggerid='.$event['objectid'];

			$result['notifications'][] = [
				'uid' => $uid,
				'id' => $event['eventid'],
				'ttl' => $event['clock'] + $msgsettings['timeout'] - time(),
				'priority' => $priority,
				'file' => $fileid,
				'severity_style' => getSeverityStyle($trigger['priority'], $event['value'] == TRIGGER_VALUE_TRUE),
				'title' => $title.' [url='.$url_tr_status.']'.CHtml::encode($host['name']).'[/url]',
				'body' => [
					'[url='.$url_events.']'.CHtml::encode($trigger['description']).'[/url]',
					'[url='.$url_tr_events.']'.
						zbx_date2str(DATE_TIME_FORMAT_SECONDS, $event['clock']).'[/url]',
				]
			];

			$sort_clock[$uid] = $event['clock'];
			$sort_event[$uid] = $event['eventid'];
			$used_triggers[$event['objectid']] = true;
		}

		array_multisort($sort_clock, SORT_ASC, $sort_event, SORT_ASC, $result['notifications']);
		$result['listid'] = sprintf('%u', crc32($result['listid']));

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($result)]));
	}
}

