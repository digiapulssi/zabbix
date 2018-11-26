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

require_once dirname(__FILE__).'/../include/CLegacyWebTest.php';

class testFormAdministrationAuthentication extends CLegacyWebTest {

	public function getHttpAuthenticationData() {
		return [
			// HTTP authentication disabled, default zabbix login form.
			[
				[
					'user' => 'Admin',
					'password' => 'zabbix',
					'http_authentication' => [
						'http_enabled' => false
					],
					'pages' => [
						[
							'page' => 'zabbix.php?action=dashboard.view',
							'guest' => true,
							'target' => 'Global view'
						],
						[
							'page' => 'index.php?form=default',
							'action' => 'login',
							'target' => 'Global view'
						],
						[
							'page' => 'index.php',
							'action' => 'login',
							'target' => 'Global view'
						],
						// Redirect to default zabbix login form, if open HTTP login form.
						[
							'page' => 'index_http.php',
							'action' => 'login',
							'target' => 'Global view'
						],
						// Couldn't open GUI page due access.
						[
							'page' => 'adm.gui.php',
							'error' => 'Access denied'
						],
						// Login after logout.
						[
							'page' => 'index.php?reconnect=1&form=default',
							'action' => 'login',
							'target' => 'Global view'
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
						'http_case_sensitive'	=> '1',
						'ldap_configured'		=> '0',
						'ldap_case_sensitive'	=> '1'
					]
				]
			],
			// HTTP authentication enabled, but file isn't created.
			[
				[
					'user' => 'Admin',
					'password' => 'zabbix',
					'http_authentication' => [
						'http_enabled' => true
					],
					'pages' => [
						[
							'page' => 'zabbix.php?action=dashboard.view',
							'guest' => true,
							'target' => 'Global view'
						],
						[
							'page' => 'index.php?form=default',
							'action' => 'login',
							'target' => 'Global view'
						],
						[
							'page' => 'index.php',
							'action' => 'login',
							'target' => 'Global view'
						],
						// Redirect to default zabbix login form, if open HTTP login form.
						[
							'page' => 'index_http.php',
							'error' => 'You are not logged in'
						],
						// Couldn't open GUI page due access.
						[
							'page' => 'adm.gui.php',
							'error' => 'Access denied'
						],
						// Login after logout.
						[
							'page' => 'index.php?reconnect=1&form=default',
							'action' => 'login',
							'target' => 'Global view'
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
						'ldap_case_sensitive'	=> '1'
					]
				]
			],
			// HTTP authentication enabled (default login form is set to 'Zabbix login form').
			[
				[
					'user' => 'Admin',
					'password' => '123456',
					'db_password' => 'zabbix',
					'file' => 'pwfile',
					'http_authentication' => [
						'http_enabled' => true,
						'http_login_form' => 'Zabbix login form'
					],
					'pages' => [
						[
							'page' => 'zabbix.php?action=dashboard.view',
							'guest' => true,
							'target' => 'Global view'
						],
						// No redirect - sign in through default zabbix login form.
						[
							'page' => 'index.php?form=default',
							'action' => 'login',
							'target' => 'Global view'
						],
						// No redirect - sign in through default zabbix login form.
						[
							'page' => 'index.php',
							'action' => 'login',
							'target' => 'Global view'
						],
						// Redirect to HTTP login form and user is signed on Dashboard page.
						[
							'page' => 'index_http.php',
							'action' => 'login_http',
							'target' => 'Global view'
						],
						// Sign in through zabbix login form after logout.
						[
							'page' => 'index.php?reconnect=1&form=default',
							'action' => 'login',
							'target' => 'Global view'
						],
						// Couldn't open Hosts page due access.
						[
							'page' => 'hosts.php?ddreset=1',
							'error' => 'Access denied'
						],
						// Couldn't open GUI page due access.
						[
							'page' => 'adm.gui.php',
							'error' => 'Access denied'
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
						'ldap_case_sensitive'	=> '1'
					]
				]
			],
			// HTTP authentication enabled (default login form is set to 'HTTP login form').
			[
				[
					'user' => 'Admin',
					'password' => '123456',
					'db_password' => 'zabbix',
					'file' => 'pwfile',
					'http_authentication' => [
						'http_enabled' => true,
						'http_login_form' => 'HTTP login form'
					],
					'pages' => [
						// No redirect - default zabbix login form.
						[
							'page' => 'index.php?form=default',
							'action' => 'login',
							'target' => 'Global view'
						],
//						// wait for ZBX-14774.
//						// Redirect to HTTP login form and user is signed on hosts page.
//						[
//							'page' => 'hosts.php?ddreset=1',
//							'action' => 'login_http',
//							'target' => 'Hosts'
//						],
						// Redirect to HTTP login form and user is signed on dashboard page.
						[
							'page' => 'index.php',
							'action' => 'login_http',
							'target' => 'Global view'
						],
						// Redirect to dashboard page and user is signed.
						[
							'page' => 'index_http.php',
							'action' => 'login_http',
							'target' => 'Global view'
						],
						// Sign in through zabbix login form after logout.
						[
							'page' => 'index.php?reconnect=1&form=default',
							'action' => 'login',
							'target' => 'Global view'
						],
						// Redirect to HTTP login form and user is signed on GUI page.
						[
							'page' => 'adm.gui.php',
							'action' => 'login_http',
							'target' => 'GUI'
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
						'http_strip_domains'	=> '',
						'http_case_sensitive'	=> '1',
						'ldap_configured'		=> '0',
						'ldap_case_sensitive'	=> '1'
					]
				]
			],
			// HTTP authentication - Check domain (@local.com).
			[
				[
					'user' => 'Admin@local.com',
					'password' => '123456',
					'file' => 'htaccess',
					'db_password' => 'zabbix',
					'http_authentication' => [
						'http_enabled' => true,
						'http_login_form' => 'HTTP login form',
						'http_domain' => 'local.com',
					],
					'pages' => [
						[
							'page' => 'zabbix.php?action=dashboard.view',
							'guest' => true,
							'target' => 'Global view'
						],
						[
							'page' => 'index.php',
							'action' => 'login_http',
							'target' => 'Global view'
						],
						[
							'page' => 'index_http.php',
							'action' => 'login_http',
							'target' => 'Global view'
						],
						[
							'page' => 'index.php?form=default',
							'action' => 'login',
							'target' => 'Global view'
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
						'ldap_case_sensitive'	=> '1'
					]
				]
			],
			// HTTP authentication - Login with user admin-zabbix (Zabbix Admin).
			[
				[
					'user' => 'local.com\\admin-zabbix',
					'password' => 'zabbix',
					'file' => 'htaccess',
					'http_authentication' => [
						'http_enabled' => true,
						'http_login_form' => 'HTTP login form',
						'http_domain' => 'local.com'
					],
					'pages' => [
						[
							'page' => 'index.php?form=default',
							'action' => 'login',
							'target' => 'Global view'
						],
						[
							'page' => 'zabbix.php?action=dashboard.view',
							'guest' => true,
							'target' => 'Global view'
						],
						[
							'page' => 'users.php',
							'error' => 'Access denied'
						],
//						// Redirect to HTTP login form and user is signed on hosts page.
//						// wait for ZBX-14774.
//						[
//							'page' => 'hosts.php?ddreset=1',
//							'action' => 'login_http',
//							'target' => 'Hosts'
//						],
						// Redirect to HTTP login form and user is signed on dashboard page.
						[
							'page' => 'index.php',
							'action' => 'login_http',
							'target' => 'Global view'
						],
						// Redirect to dashboard page and user is signed.
						[
							'page' => 'index_http.php',
							'action' => 'login_http',
							'target' => 'Global view'
						],
						// Login after logout.
						[
							'page' => 'index.php?reconnect=1&form=default',
							'action' => 'login',
							'target' => 'Global view'
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
						'ldap_case_sensitive'	=> '1'
					]
				]
			],
			// HTTP authentication - Login with user test-user (Zabbix User),
			[
				[
					'user' => 'local.com\\test-user',
					'password' => 'zabbix',
					'file' => 'htaccess',
					'http_authentication' => [
						'http_enabled' => true,
						'http_login_form' => 'HTTP login form',
						'http_domain' => 'local.com'
					],
					'pages' => [
						[
							'page' => 'index.php?form=default',
							'action' => 'login',
							'target' => 'Global view'
						],
						[
							'page' => 'users.php',
							'error' => 'Access denied'
						],
//						// wait for ZBX-14774.
//						[
//							'page' => 'hosts.php?ddreset=1',
//							'action' => 'login_http',
//							'target' => 'hosts'
//						],
						[
							'page' => 'zabbix.php?action=dashboard.view',
							'action' => 'login_http',
							'target' => 'Global view'
						],
						[
							'page' => 'index.php',
							'action' => 'login_http',
							'target' => 'Global view'
						],
						[
							'page' => 'index_http.php',
							'action' => 'login_http',
							'target' => 'Global view'
						],
						[
							'page' => 'index.php?reconnect=1&form=default',
							'action' => 'login',
							'target' => 'Global view'
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
						'ldap_case_sensitive'	=> '1'
					]
				]
			],
			// HTTP authentication - Case sensitive login,
			[
				[
					'user' => 'admin',
					'password' => 'zabbix',
					'file' => 'htaccess',
					'http_authentication' => [
						'http_enabled' => true,
						'http_login_form' => 'Zabbix login form',
						'http_case_sensitive' => true
					],
					'pages' => [
						[
							'page' => 'index_http.php',
							'error' => 'You are not logged in',
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
						'http_login_form'		=> '0',
						'http_strip_domains'	=> '',
						'http_case_sensitive'	=> '1',
						'ldap_configured'		=> '0',
						'ldap_case_sensitive'	=> '1'
					]
				]
			],
			[
				[
					'user' => 'admin',
					'password' => 'zabbix',
					'file' => 'htaccess',
					'user_case_sensitive' => 'Admin',
					'http_authentication' => [
						'http_enabled' => true,
						'http_login_form' => 'Zabbix login form',
						'http_case_sensitive' => false
					],
					'ldap_authentication' => [
						'ldap_case_sensitive' => false
					],
					'pages' => [
						[
							'page' => 'index_http.php',
							'action' => 'login_http',
							'target' => 'Global view'
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
						'http_login_form'		=> '0',
						'http_strip_domains'	=> '',
						'http_case_sensitive'	=> '0',
						'ldap_configured'		=> '0',
						'ldap_case_sensitive'	=> '1'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getHttpAuthenticationData
	 * @backup config
	 *
	 * Internal authentication with HTTP settings.
	 */
	public function testFormAdministrationAuthentication_HttpAuthentication($data) {
		$this->httpConfiguration($data);

		// Check authentication on pages.
		if (array_key_exists('pages', $data)) {
			foreach ($data['pages'] as $check) {

				if (array_key_exists('guest', $check) && $check['guest'] === true) {
					$alias = 'guest';
					$this->zbxTestOpen($check['page']);
				}
				else {
					$alias = $this->getUserName($data['user']);
				}

				// Login for non guest user.
				if (array_key_exists('action', $check)) {
					$action = $check['action'];
					// HTTP login - username and password is sending in url.
					if ($action === 'login_http') {
						$this->openAsHttpUser($data['user'], $data['password'], $check['page']);
					}
					// Sign in using the default zabbix login form.
					elseif ($action === 'login') {
						$this->zbxTestOpen($check['page']);
						$this->zbxTestWaitForPageToLoad();

						// Check button 'Sign in with HTTP'.
						if (isset($data['http_authentication']['http_enabled']) && $data['http_authentication']['http_enabled'] === true) {
							$this->zbxTestAssertElementPresentXpath('//a[@href="index_http.php"][text()="Sign in with HTTP"]');
						}
						else {
							$this->zbxTestAssertElementNotPresentXpath('//a[@href="index_http.php"][text()="Sign in with HTTP"]');
						}

						$this->zbxTestInputTypeWait('name', $alias);
						if (!array_key_exists('db_password', $data)) {
							$data['db_password'] = $data['password'];
						}
						$this->zbxTestInputTypeWait('password', $data['db_password']);
						$this->zbxTestClick('enter');
					}
				}

				if (array_key_exists('error', $check)) {
					$this->zbxTestOpen($check['page']);
					$this->zbxTestAssertElementPresentXpath('//output[@class="msg-bad msg-global"][text()="'.$check['error'].'"]');
					$this->openAsHttpUser($data['user'], $data['password'], $check['page']);
					$this->zbxTestAssertElementPresentXpath('//output[@class="msg-bad msg-global"][text()="'.$check['error'].'"]');
					continue;
				}

				// Check page header after successful login.
				if (array_key_exists('target', $check)) {
					$this->zbxTestCheckHeader($check['target']);
				}

				// Check user data in DB after login.
				$session = $this->webDriver->manage()->getCookieNamed(ZBX_SESSION_NAME);
				$user_data = DBfetch(DBselect('SELECT alias FROM users WHERE userid = ('.
						'SELECT DISTINCT userid FROM sessions WHERE sessionid='.zbx_dbstr($session['value']).')'));
				if (array_key_exists('user_case_sensitive', $data)) {
					$this->assertEquals($user_data['alias'], $data['user_case_sensitive']);
				}
				else {
					$this->assertEquals($user_data['alias'], $alias);
				}

				$this->zbxTestLogout();
				$this->zbxTestWaitForPageToLoad();
				$this->webDriver->manage()->deleteAllCookies();
			}
		}
	}

	private function httpConfiguration($data) {
		$this->zbxTestLogin('zabbix.php?action=authentication.edit&ddreset=1');
		$this->zbxTestCheckHeader('Authentication');
		$this->zbxTestCheckTitle('Configuration of authentication');

		// Configuration at 'HTTP settings' tab.
		if (array_key_exists('http_authentication', $data)) {
			$http_auth = $data['http_authentication'];

			$this->zbxTestTabSwitch('HTTP settings');

			// Check disabled or enabled fields in form for HTTP auth.
			$fields_xpath = ['//select[@id="http_login_form"]', '//input[@id="http_strip_domains"]', '//input[@id="http_case_sensitive"]'];
			if (array_key_exists('http_enabled', $http_auth) && $http_auth['http_enabled'] === true) {
				$this->zbxTestCheckboxSelect('http_auth_enabled');
				foreach ($fields_xpath as $xpath) {
					$this->zbxTestIsEnabled($xpath);
				}
			}
			else {
				foreach ($fields_xpath as $xpath) {
					$this->assertFalse($this->query('xpath', $xpath)->one()->isEnabled());
				}
			}

			if (array_key_exists('http_login_form', $http_auth)) {
				$this->zbxTestDropdownSelect('http_login_form', $http_auth['http_login_form']);
			}

			if (array_key_exists('http_domain', $http_auth)) {
				$this->zbxTestInputType('http_strip_domains', $http_auth['http_domain']);
			}

			if (array_key_exists('http_case_sensitive', $http_auth)) {
				$this->zbxTestCheckboxSelect('http_case_sensitive', $http_auth['http_case_sensitive']);
			}
		}

		// File .htaccess creation.
		if (array_key_exists('file', $data)) {
			if ($data['file'] === 'htaccess') {
				$this->assertTrue(file_put_contents(PHPUNIT_BASEDIR.'/.htaccess', 'SetEnv REMOTE_USER "'.
						$data['user'].'"') !== false);
			}
			elseif ($data['file'] === 'pwfile') {
				$this->assertTrue(exec('htpasswd -c -b "'.PHPUNIT_BASEDIR.'/.pwd" "'.$data['user'].'" "'.
						$data['password'].'" > /dev/null 2>&1') !== false);
				$content = '<Files index_http.php>'."\n".
						'	AuthType Basic'."\n".
						'	AuthName "Password Required"'."\n".
						'	AuthUserFile "'.PHPUNIT_BASEDIR.'/.pwd"'."\n".
						'	Require valid-user'."\n".
						'</Files>';
				$this->assertTrue(file_put_contents(PHPUNIT_BASEDIR.'/.htaccess', $content) !== false);
			}
		}

		$this->zbxTestClick('update');
		$this->zbxTestCheckFatalErrors();
		// Check DB configuration.
		$sql = 'SELECT authentication_type, ldap_host, ldap_port, ldap_base_dn, ldap_bind_dn, ldap_bind_password, '.
				'ldap_search_attribute, http_auth_enabled, http_login_form, http_strip_domains, '.
				'http_case_sensitive, ldap_configured, ldap_case_sensitive'.
				' FROM config';
		$result = CDBHelper::getRow($sql);
		$this->assertEquals($data['db_check'], $result);

		$this->zbxTestLogout();
		$this->zbxTestWaitForPageToLoad();
		$this->webDriver->manage()->deleteAllCookies();
	}

	public function getLdapAuthenticationData() {
		return [
			[
				[
					'error' => 'Incorrect value for field "authentication_type": LDAP is not configured.'
				]
			],
			[
				[
					'user' => 'Admin',
					'password' => 'zabbix',
					'ldap_authentication' => [
						'ldap_enabled' => true,
						'ldap_host' => 'ldap.forumsys.com',
						'ldap_port' => '389',
						'ldap_base_dn' => 'dc=example,dc=com',
						'ldap_search_attribute' => 'uid',
						'ldap_bind_dn' => 'cn=read-only-admin,dc=example,dc=com',
						'ldap_case_sensitive' => true,
						'ldap_bind_password' => 'password',
						'ldap_test_user' => 'galieleo',
						'ldap_test_password' => 'password'
					],
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
						'http_case_sensitive'	=> '1',
						'ldap_configured'		=> '1',
						'ldap_case_sensitive'	=> '1'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getLdapAuthenticationData
	 * @backup config
	 *
	 * LDAP authentication with LDAP settings.
	 */
	public function testFormAdministrationAuthentication_LdapAuthentication($data) {
		$this->zbxTestLogin('zabbix.php?action=authentication.edit&ddreset=1');
		$this->zbxTestCheckHeader('Authentication');
		$this->zbxTestCheckTitle('Configuration of authentication');

		$this->zbxTestClickXpathWait('//label[@for="authentication_type_1"]');

		// Configuration at 'LDAP settings' tab.
		if (array_key_exists('ldap_authentication', $data)) {
			$ldap_auth = $data['ldap_authentication'];

			$this->zbxTestTabSwitch('LDAP settings');
			if (array_key_exists('ldap_enabled', $ldap_auth)) {
				$this->zbxTestCheckboxSelect('ldap_configured', $ldap_auth['ldap_enabled']);
			}
			if (array_key_exists('ldap_case_sensitive', $ldap_auth)) {
				$this->zbxTestCheckboxSelect('ldap_case_sensitive', $ldap_auth['ldap_case_sensitive']);
			}

			$fields = ['ldap_host', 'ldap_port', 'ldap_base_dn', 'ldap_search_attribute', 'ldap_bind_dn',
					'ldap_bind_password', 'ldap_test_user', 'ldap_test_password'
			];

			foreach ($fields as $field) {
				if (array_key_exists($field, $ldap_auth)) {
					$this->zbxTestInputType($field, $ldap_auth[$field]);
				}
			}
		}

		$this->zbxTestClick('update');

		// Accept alert message, if it exist.
		if ($this->zbxTestIsAlertPresent()) {
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
			$result = CDBHelper::getRow($sql);
			$this->assertEquals($data['db_check'], $result);
		}
	}

	private function openAsHttpUser($user, $password, $url) {
		$parts = explode('//', PHPUNIT_URL.$url, 2);
		$full_url = $parts[0].'//'.$user.':'.$password.'@'.$parts[1];
		$this->webDriver->get($full_url);
	}

	private function getUserName($alias) {
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

		if (file_exists(PHPUNIT_BASEDIR.'/.htaccess')) {
			unlink(PHPUNIT_BASEDIR.'/.htaccess');
		}

		if (file_exists(PHPUNIT_BASEDIR.'/.pwd')) {
			unlink(PHPUNIT_BASEDIR.'/.pwd');
		}

		// Cleanup is required to avoid browser sending Basic auth header.
		self::closePage();
	}
}
