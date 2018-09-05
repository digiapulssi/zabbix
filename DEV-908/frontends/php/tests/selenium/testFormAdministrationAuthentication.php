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

require_once dirname(__FILE__).'/../include/class.cwebtest.php';

class testFormAdministrationAuthentication extends CWebTest {
	public function getAuthenticationData() {
		return [
			// HTTP authentication (default login form - 'Zabbix login form')
			[
				[
					'default_auth' => 'Internal',
					'http_auth_enabled' => true,
					'http_login_form' => 'Zabbix login form',
					'user' => 'Admin',
					'password' => 'zabbix',
					'http_case_sensitive' => true,
					'file' => 'pwfile',
					'pages' => [
						[
							'page' => 'index.php?form=default',
							'action' => 'login',
							'target' => 'dashboard'
						]
					],
					'db_check' => [
						'authentication_type'	=> '0',
						'ldap_host'				=> '',
						'ldap_port'				=> '389',
						'ldap_base_dn'			=> '',
						'ldap_bind_dn'			=> '',
						'ldap_bind_password'	=> '',
						'ldap_search_attribute'	=> '',
						'http_auth_enabled'		=> '1',
						'http_login_form'		=> '0',
						'http_strip_domains'	=> '',
						'http_case_sensitive'	=> '1',
						'ldap_configured'		=> '0',
						'ldap_case_sensitive'	=> '0'
					]
				]
			],
			// Zabbix DB authentication
			[
				[
					'default_auth' => 'Internal',
					'user' => 'Admin',
					'password' => 'zabbix',
					'pages' => [
						[
							'page' => 'index.php?form=default',
							'action' => 'login',
							'target' => 'dashboard'
						]
					],
					'db_check' => [
						'authentication_type'	=> '0',
						'ldap_host'				=> '',
						'ldap_port'				=> '389',
						'ldap_base_dn'			=> '',
						'ldap_bind_dn'			=> '',
						'ldap_bind_password'	=> '',
						'ldap_search_attribute'	=> '',
						'http_auth_enabled'		=> '0',
						'http_login_form'		=> '0',
						'http_strip_domains'	=> '',
						'http_case_sensitive'	=> '0',
						'ldap_configured'		=> '0',
						'ldap_case_sensitive'	=> '0'
					]
				]
			],
			// HTTP authentication (default login form - 'HTTP login form')
			[
				[
					'default_auth' => 'Internal',
					'http_auth_enabled' => true,
					'http_login_form' => 'HTTP login form',
					'http_domain' => 'local.com',
					'user' => 'Admin',
					'password' => '123456',
					'db_password' => 'zabbix',
					'file' => 'pwfile',
					'pages' => [
						// No redirect - default zabbix login page.
						[
							'page' => 'index.php?form=default',
							'action' => 'login',
							'target' => 'dashboard'
						],
						// Redirect HTTP login page, open Host page.
						// uncomment after ZBX-14774 will be resolved.
//						[
//							'page' => 'hosts.php?ddreset=1',
//							'action' => 'login_http',
//							'target' => 'hosts'
//						],
						// Redirect to HTTP login page.
						[
							'page' => 'index.php',
							'action' => 'login_http',
							'target' => 'dashboard'
						],
						// HTTP login page.
						[
							'page' => 'index_http.php',
							'action' => 'login_http',
							'target' => 'dashboard'
						],
						// Login after logout.
						[
							'page' => 'index.php?reconnect=1&form=default',
							'action' => 'login',
							'target' => 'dashboard'
						]
					],
					'db_check' => [
						'authentication_type'	=> '0',
						'ldap_host'				=> '',
						'ldap_port'				=> '389',
						'ldap_base_dn'			=> '',
						'ldap_bind_dn'			=> '',
						'ldap_bind_password'	=> '',
						'ldap_search_attribute'	=> '',
						'http_auth_enabled'		=> '1',
						'http_login_form'		=> '1',
						'http_strip_domains'	=> 'local.com',
						'http_case_sensitive'	=> '1',
						'ldap_configured'		=> '0',
						'ldap_case_sensitive'	=> '0'
					]
				]
			],
			// HTTP authentication - Check domain (@local.com).
			[
				[
					'default_auth' => 'Internal',
					'http_auth_enabled' => true,
					'http_login_form' => 'HTTP login form',
					'http_domain' => 'local.com',
					'user' => 'Admin@local.com',
					'file' => 'htaccess',
					'pages' => [
						[
							'page' => 'index.php?form=default',
							'action' => 'login_http_domain',
							'target' => 'dashboard'
						],
					],
					'db_check' => [
						'authentication_type'	=> '0',
						'ldap_host'				=> '',
						'ldap_port'				=> '389',
						'ldap_base_dn'			=> '',
						'ldap_bind_dn'			=> '',
						'ldap_bind_password'	=> '',
						'ldap_search_attribute'	=> '',
						'http_auth_enabled'		=> '1',
						'http_login_form'		=> '1',
						'http_strip_domains'	=> 'local.com',
						'http_case_sensitive'	=> '1',
						'ldap_configured'		=> '0',
						'ldap_case_sensitive'	=> '0'
					]
				]
			],
			// HTTP authentication - Check domain (@local.com).
			[
				[
					'default_auth' => 'Internal',
					'http_auth_enabled' => true,
					'http_login_form' => 'HTTP login form',
					'http_domain' => 'local.com',
					'user' => 'Admin@local.com',
					'file' => 'htaccess',
					'pages' => [
						[
							'page' => 'index.php?form=default',
							'action' => 'login_http_domain',
							'target' => 'dashboard'
						],
					],
					'db_check' => [
						'authentication_type'	=> '0',
						'ldap_host'				=> '',
						'ldap_port'				=> '389',
						'ldap_base_dn'			=> '',
						'ldap_bind_dn'			=> '',
						'ldap_bind_password'	=> '',
						'ldap_search_attribute'	=> '',
						'http_auth_enabled'		=> '1',
						'http_login_form'		=> '1',
						'http_strip_domains'	=> 'local.com',
						'http_case_sensitive'	=> '1',
						'ldap_configured'		=> '0',
						'ldap_case_sensitive'	=> '0'
					]
				]
			],
			// HTTP authentication - Login with user admin-zabbix (Zabbix Admin).
			[
				[
					'default_auth' => 'Internal',
					'http_auth_enabled' => true,
					'http_login_form' => 'HTTP login form',
					'http_domain' => 'local.com',
					'user' => 'local.com\\admin-zabbix',
					'password' => 'zabbix',
					'file' => 'htaccess',
					'pages' => [
						[
							'page' => 'index.php?form=default',
							'action' => 'login_http_domain',
							'target' => 'dashboard'
						],
						[
							'page' => 'users.php',
							'action' => 'login_http_domain',
							'target' => 'error',
							'error' => 'Access denied'
						],
						// Redirect HTTP login page, open Host page.
						// uncomment after ZBX-14774 will be resolved.
//						[
//							'page' => 'hosts.php?ddreset=1',
//							'action' => 'login_http',
//							'target' => 'hosts'
//						],
						// Redirect to HTTP login page.
						[
							'page' => 'index.php',
							'action' => 'login_http',
							'target' => 'dashboard'
						],
						// HTTP login page.
						[
							'page' => 'index_http.php',
							'action' => 'login_http',
							'target' => 'dashboard'
						],
						// Login after logout.
						[
							'page' => 'index.php?reconnect=1&form=default',
							'action' => 'login',
							'target' => 'dashboard'
						]
					],
					'db_check' => [
						'authentication_type'	=> '0',
						'ldap_host'				=> '',
						'ldap_port'				=> '389',
						'ldap_base_dn'			=> '',
						'ldap_bind_dn'			=> '',
						'ldap_bind_password'	=> '',
						'ldap_search_attribute'	=> '',
						'http_auth_enabled'		=> '1',
						'http_login_form'		=> '1',
						'http_strip_domains'	=> 'local.com',
						'http_case_sensitive'	=> '1',
						'ldap_configured'		=> '0',
						'ldap_case_sensitive'	=> '0'
					]
				]
			],
			// HTTP authentication - Login with user test-user (Zabbix User),
			[
				[
					'default_auth' => 'Internal',
					'http_auth_enabled' => true,
					'http_login_form' => 'HTTP login form',
					'http_domain' => 'local.com',
					'user' => 'local.com\\test-user',
					'password' => 'zabbix',
					'file' => 'htaccess',
					'pages' => [
						[
							'page' => 'index.php?form=default',
							'action' => 'login_http_domain',
							'target' => 'dashboard'
						],
						[
							'page' => 'users.php',
							'action' => 'login_http_domain',
							'target' => 'error',
							'error' => 'Access denied'
						],
						// Redirect HTTP login page, open Host page.
						// uncomment after ZBX-14774 will be resolved.
//						[
//							'page' => 'hosts.php?ddreset=1',
//							'action' => 'login_http',
//							'target' => 'hosts'
//						],
						// Redirect to HTTP login page.
						[
							'page' => 'index.php',
							'action' => 'login_http',
							'target' => 'dashboard'
						],
						// HTTP login page.
						[
							'page' => 'index_http.php',
							'action' => 'login_http',
							'target' => 'dashboard'
						],
						// Login after logout.
						[
							'page' => 'index.php?reconnect=1&form=default',
							'action' => 'login',
							'target' => 'dashboard'
						]
					],
					'db_check' => [
						'authentication_type'	=> '0',
						'ldap_host'				=> '',
						'ldap_port'				=> '389',
						'ldap_base_dn'			=> '',
						'ldap_bind_dn'			=> '',
						'ldap_bind_password'	=> '',
						'ldap_search_attribute'	=> '',
						'http_auth_enabled'		=> '1',
						'http_login_form'		=> '1',
						'http_strip_domains'	=> 'local.com',
						'http_case_sensitive'	=> '1',
						'ldap_configured'		=> '0',
						'ldap_case_sensitive'	=> '0'
					]
				]
			],
			// LDAP authentication
			[
				[
					'default_auth' => 'LDAP',
					'user' => 'Admin',
					'password' => 'zabbix',
					'ldap_enabled' => true,
					'ldap_host' => 'ldap.forumsys.com',
					'ldap_port' => '389',
					'ldap_base_dn' => 'dc=example,dc=com',
					'ldap_search_attribute' => 'uid',
					'ldap_bind_dn' => 'cn=read-only-admin,dc=example,dc=com',
					'ldap_case_sensitive' => true,
					'ldap_bind_password' => 'password',
					'ldap_test_user' => 'galieleo',
					'ldap_test_password' => 'password',
					'db_check' => [
						'authentication_type'	=> '1',
						'ldap_host'				=> 'ldap.forumsys.com',
						'ldap_port'				=> '389',
						'ldap_base_dn'			=> 'dc=example,dc=com',
						'ldap_bind_dn'			=> 'cn=read-only-admin,dc=example,dc=com',
						'ldap_bind_password'	=> 'password',
						'ldap_search_attribute'	=> 'uid',
						'http_auth_enabled'		=> '0',
						'http_login_form'		=> '0',
						'http_strip_domains'	=> '',
						'http_case_sensitive'	=> '0',
						'ldap_configured'		=> '1',
						'ldap_case_sensitive'	=> '1'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getAuthenticationData
	 * @backup config
	 */
	public function testFormAdministrationAuthentication_Authentication($data) {
		$this->zbxTestLogin('zabbix.php?action=authentication.edit&ddreset=1');
		$this->zbxTestCheckHeader('Authentication');
		$this->zbxTestCheckTitle('Configuration of authentication');

		// Configuration at 'Authentication' tab.
		if (array_key_exists('default_auth', $data)) {
			$this->zbxTestClick('tab_auth');
			// Select default authentication Internal / LDAP.
			if ($data['default_auth'] === 'Internal') {
				$this->zbxTestClickXpathWait('//label[@for="authentication_type_0"]');
			}
			else {
				$this->zbxTestClickXpathWait('//label[@for="authentication_type_1"]');
			}
		}

		// Configuration at 'HTTP settings' tab.
		$keys = [];
		foreach (array_keys($data) as $key) {
			if (strpos($key, 'http_') === 0) {
				$this->zbxTestClick('tab_http');
				break;
			}
		}

		if (array_key_exists('http_auth_enabled', $data) && $data['http_auth_enabled'] === true) {
			$this->zbxTestCheckboxSelect('http_auth_enabled');
		}

		if (array_key_exists('http_login_form', $data)) {
			$this->zbxTestWaitForPageToLoad();
			if ($this->zbxTestIsEnabled('//select[@id="http_login_form"]')) {
				$this->zbxTestDropdownSelect('http_login_form', $data['http_login_form']);
			}
		}

		if (array_key_exists('http_domain', $data)) {
			if ($this->zbxTestIsEnabled('//input[@id="http_strip_domains"]')) {
				$this->zbxTestInputType('http_strip_domains', $data['http_domain']);
			}
		}

		if (array_key_exists('http_case_sensitive', $data) && $data['http_case_sensitive'] === true) {
			if ($this->zbxTestIsEnabled('//input[@id="http_case_sensitive"]')) {
				$this->zbxTestCheckboxSelect('http_case_sensitive');
			}
		}

		// Configuration at 'LDAP settings' tab.
		$keys = [];
		foreach (array_keys($data) as $key) {
			if (strpos($key, 'ldap_') === 0) {
				$this->zbxTestClick('tab_ldap');
				break;
			}
		}

		if (array_key_exists('ldap_enabled', $data) && $data['ldap_enabled'] === true) {
			$this->zbxTestCheckboxSelect('ldap_configured');
		}

		if (array_key_exists('ldap_host', $data)) {
			$this->zbxTestInputType('ldap_host', $data['ldap_host']);
		}

		if (array_key_exists('ldap_port', $data)) {
			$this->zbxTestInputType('ldap_port', $data['ldap_port']);
		}

		if (array_key_exists('ldap_base_dn', $data)) {
			$this->zbxTestInputType('ldap_base_dn', $data['ldap_base_dn']);
		}

		if (array_key_exists('ldap_search_attribute', $data)) {
			$this->zbxTestInputType('ldap_search_attribute', $data['ldap_search_attribute']);
		}

		if (array_key_exists('ldap_bind_dn', $data)) {
			$this->zbxTestInputType('ldap_bind_dn', $data['ldap_bind_dn']);
		}

		if (array_key_exists('ldap_case_sensitive', $data) && $data['ldap_case_sensitive'] === true) {
			$this->zbxTestCheckboxSelect('ldap_case_sensitive');
		}

		if (array_key_exists('ldap_bind_password', $data)) {
			$this->zbxTestInputType('ldap_bind_password', $data['ldap_bind_password']);
		}

		if (array_key_exists('ldap_test_user', $data)) {
			$this->zbxTestInputType('ldap_test_user', $data['ldap_test_user']);
		}

		if (array_key_exists('ldap_test_password', $data)) {
			$this->zbxTestInputType('ldap_test_password', $data['ldap_test_password']);
		}

		// File .htaccess creation.
		if (array_key_exists('file', $data)) {
			if ($data['file'] === 'htaccess') {
				$this->assertTrue(file_put_contents(PHPUNIT_BASEDIR.'.htaccess', 'SetEnv REMOTE_USER "'.
						$data['user'].'"') !== false);
			}
			elseif ($data['file'] === 'pwfile') {
				$this->assertTrue(exec('htpasswd -c -b "'.PHPUNIT_BASEDIR.'../.pwd" "'.$data['user'].'" "'.
						$data['password'].'" > /dev/null 2>&1') !== false);
				$content = '<Files index_http.php>'."\n".
						'	AuthType Basic'."\n".
						'	AuthName "Password Required"'."\n".
						'	AuthUserFile "'.PHPUNIT_BASEDIR.'../.pwd"'."\n".
						'	Require valid-user'."\n".
						'</Files>';
				$this->assertTrue(file_put_contents(PHPUNIT_BASEDIR.'.htaccess', $content) !== false);
			}
		}

		$this->zbxTestClick('update');

		// Accept alert message, if it exist.
		if ($this->isAlertPresent()) {
			$this->zbxTestAcceptAlert();
		}

		if (array_key_exists('error', $data)) {
			$this->zbxTestWaitUntilMessageTextPresent('msg-bad', $data['error']);
		}
		else {
			$this->zbxTestCheckFatalErrors();

			// Check DB configuration.
			$sql = 'SELECT authentication_type, ldap_host, ldap_port, ldap_base_dn, ldap_bind_dn, ldap_bind_password, '.
					'ldap_search_attribute, http_auth_enabled, http_login_form, http_strip_domains, '.
					'http_case_sensitive, ldap_configured, ldap_case_sensitive'.
					' FROM config';
			$result = DBdata($sql, false);
			$this->assertEquals($data['db_check'], $result[0][0]);
		}

		$this->zbxTestLogout();
		$this->zbxTestWaitForPageToLoad();
		$this->webDriver->manage()->deleteAllCookies();

		$alias = $this->UserName($data['user']);

		if (array_key_exists('pages', $data)) {
			foreach ($data['pages'] as $check) {
				if (!array_key_exists('page', $check)) {
					continue;
				}

				if (array_key_exists('action', $check)) {
					$action = $check['action'];
					// Login with HTTP - user/password is sending in url
					if ($action === 'login_http') {
						$parts = explode('//', PHPUNIT_URL.$check['page'], 2);
						$url = $parts[0].'//'.$data['user'].':'.$data['password'].'@'.$parts[1];
						$this->webDriver->get($url);
					}
					elseif ($action === 'login_http_domain') {
						$this->zbxTestOpen($check['page']);
					}
					elseif ($action === 'login') {
						$this->zbxTestOpen($check['page']);
						$this->zbxTestWaitForPageToLoad();
						// Check button 'Sign in with HTTP'.
						if (!array_key_exists('http_auth_enabled', $data)) {
							$this->zbxTestAssertElementNotPresentXpath('//a[@href="index_http.php"]'.
									'[text()="Sign in with HTTP"]');
						}
						elseif (array_key_exists('http_auth_enabled', $data) || $data['http_auth_enabled'] === true) {
							$this->zbxTestAssertElementPresentXpath('//a[@href="index_http.php"]'.
									'[text()="Sign in with HTTP"]');
						}

						$this->zbxTestInputTypeWait('name', $alias);
						if (!array_key_exists('db_password', $data)) {
							$data['db_password'] = $data['password'];
						}
						$this->zbxTestInputTypeWait('password', $data['db_password']);
						$this->zbxTestClick('enter');
					}
				}

				// Check page after login.
				if (array_key_exists('target', $check)) {
					$target = $check['target'];
					if ($target === 'dashboard') {
						$this->zbxTestCheckHeader('Dashboard');
					}
					elseif ($target === 'hosts') {
						$this->zbxTestCheckHeader('Hosts');
					}
					elseif ($target === 'error') {
						$this->zbxTestAssertElementPresentXpath('//output[@class="msg-bad msg-global"][text()="'.
								$check['error'].'"]');
						continue;
					}
				}

				// Check user after login.
				if ($data['default_auth'] !== 'LDAP') {
					$session = $this->webDriver->manage()->getCookieNamed(ZBX_SESSION_NAME);
					$user_data = DBfetch(DBselect('SELECT alias FROM users WHERE userid = ('.
							'SELECT DISTINCT userid FROM sessions WHERE sessionid='.zbx_dbstr($session['value']).')'));

					$this->assertEquals($user_data['alias'], $alias);
				}

				$this->zbxTestLogout();
				$this->zbxTestWaitForPageToLoad();
				$this->webDriver->manage()->deleteAllCookies();
			}
		}
	}

	private function isAlertPresent() {
		try {
			$alert = $this->webDriver->switchTo()->alert();
			$alert->getText();
			return true;
		}
		catch (NoAlertOpenException $e) {
			return false;
		}
	}

	private function UserName($alias) {
		$separator = strpos($alias, '@');
		if ($separator !== false) {
			$alias = substr($alias, 0, $separator);
		}
		else {
			$separator = strpos($alias, '\\');
			if ($separator !== false) {
				$alias = substr($alias, $separator + 1);
			}
		}
		return $alias;
	}

	/**
	 * Callback executed after every test case.
	 *
	 * @after
	 */
	public function onAfterTestCase() {
		parent::onAfterTestCase();

		if (file_exists(PHPUNIT_BASEDIR.'.htaccess')) {
			unlink(PHPUNIT_BASEDIR.'.htaccess');
		}

		// Cleanup is required to avoid browser sending Basic auth header.
		self::closeBrowser();
	}
}
