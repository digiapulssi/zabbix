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


class CControllerPopupHttpStep extends CController {

	protected function init() {
		$this->disableSIDvalidation();
	}

	protected function checkInput() {
		$fields = [
			'dstfrm' =>				'string|fatal',
			'stepid' =>				'int32',
			'list_name' =>			'string',
			'name' =>				'string|not_empty',
			'url' =>				'string|not_empty',
			'post_type' =>			'in '.implode(',', [ZBX_POSTTYPE_RAW, ZBX_POSTTYPE_FORM]),
			'posts' =>				'string',
			'pairs' =>				'array',
			'retrieve_mode' =>		'in '.implode(',', [HTTPTEST_STEP_RETRIEVE_MODE_CONTENT, HTTPTEST_STEP_RETRIEVE_MODE_HEADERS]),
			'follow_redirects' =>	'in '.implode(',', [HTTPTEST_STEP_FOLLOW_REDIRECTS_ON, HTTPTEST_STEP_FOLLOW_REDIRECTS_OFF]),
			'timeout' =>			'string',
			'required' =>			'string',
			'status_codes' =>		'string',
			'templated' =>			'in 0,1',
			'old_name' =>			'string',
			'steps_names' =>		'array',
			'validate' =>			'in 1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$output = [];
			if (($messages = getMessages()) !== null) {
				$output['errors'] = $messages->toString();
			}

			$this->setResponse(
				(new CControllerResponseData(['main_block' => CJs::encodeJson($output)]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions() {
		return true;
	}

	protected function doAction() {
		$page_options = [
			'dstfrm' => $this->getInput('dstfrm'),
			'list_name' => $this->getInput('list_name', ''),
			'name' => $this->getInput('name'),
			'templated' => $this->getInput('templated', 0),
			'post_type' => $this->getInput('post_type', ZBX_POSTTYPE_FORM),
			'posts' => $this->getInput('posts', ''),
			'url' => $this->getInput('url', ''),
			'timeout' => $this->getInput('timeout', DB::getDefault('httpstep', 'timeout')),
			'required' => $this->getInput('required', ''),
			'status_codes' => $this->getInput('status_codes', ''),
			'old_name' => $this->getInput('old_name', ''),
			'pairs' => $this->getInput('pairs', []),
			'stepid' => $this->getInput('stepid', -1),
			'steps_names' => $this->getInput('steps_names', [])
		];

		if ($page_options['stepid'] >= 0) {
			$page_options['follow_redirects'] = $this->getInput('follow_redirects', HTTPTEST_STEP_FOLLOW_REDIRECTS_ON);
			$page_options['retrieve_mode'] = $this->getInput('retrieve_mode', HTTPTEST_STEP_RETRIEVE_MODE_CONTENT);
		}
		else {
			$page_options['follow_redirects'] = HTTPTEST_STEP_FOLLOW_REDIRECTS_ON;
			$page_options['retrieve_mode'] = HTTPTEST_STEP_RETRIEVE_MODE_CONTENT;
		}

		$page_options['step_pair_key_map'] = [
			'variables' => 'step_variables',
			'headers' => 'step_headers'
		];

		if ($this->hasInput('validate')) {
			$output = [];

			// Validate if step names are unique.
			if (($page_options['stepid'] >= 0 && $page_options['name'] !== $page_options['old_name'])
					|| $page_options['stepid'] < 0) {
				foreach ($page_options['steps_names'] as $name) {
					if ($name === $page_options['name']) {
						error(_s('Step with name "%1$s" already exists.', $name));
					}
				}
			}

			// Return collected error messages.
			if (($messages = getMessages()) !== null) {
				$output['errors'] = $messages->toString();
			}
			else {
				// Return valid response.
				$params = [
					'name' => $page_options['name'],
					'timeout' => $page_options['timeout'],
					'url' => $page_options['url'],
					'post_type' => $page_options['post_type'],
					'posts' => $page_options['posts'],
					'pairs' => $page_options['pairs'],
					'required' => $page_options['required'],
					'status_codes' => $page_options['status_codes'],
					'follow_redirects' => $page_options['follow_redirects'],
					'retrieve_mode' => $page_options['retrieve_mode']
				];

				if ($page_options['stepid'] >= 0) {
					$params['stepid'] = $page_options['stepid'];
				}

				foreach ($params['pairs'] as &$pair) {
					foreach ($page_options['step_pair_key_map'] as $server_step_pair_name => $frontend_step_pair_name) {
						if (array_key_exists('type', $pair) && $pair['type'] === $frontend_step_pair_name) {
							$pair['type'] = $server_step_pair_name;
						}
					}
				}
				unset($pair);

				$output = [
					'dstfrm' => $page_options['dstfrm'],
					'list_name' => $page_options['list_name'],
					'params' => $params
				];
			}

			$this->setResponse(
				(new CControllerResponseData(['main_block' => CJs::encodeJson($output)]))->disableView()
			);
		}
		else {
			$data = [
				'title' => _('Step of web scenario'),
				'options' => $page_options
			];

			$this->setResponse(new CControllerResponseData($data));
		}
	}
}
