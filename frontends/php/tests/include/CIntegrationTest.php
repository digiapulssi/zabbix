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

require_once 'vendor/autoload.php';

require_once dirname(__FILE__).'/CAPITest.php';
require_once dirname(__FILE__).'/CZabbixClient.php';
require_once dirname(__FILE__).'/helpers/CLogHelper.php';

/**
 * Base class for integration tests.
 */
class CIntegrationTest extends CAPITest {

	// Default iteration count for wait operations.
	const WAIT_ITERATIONS			= 20;

	// Default delays (in seconds):
	const WAIT_ITERATION_DELAY		= 1; // Wait iteration delay.
	const COMPONENT_STARTUP_DELAY	= 5; // Component start delay.
	const CACHE_RELOAD_DELAY		= 2; // Configuration cache reload delay.

	// Zabbix component constants.
	const COMPONENT_SERVER	= 'server';
	const COMPONENT_PROXY	= 'proxy';
	const COMPONENT_AGENT	= 'agentd';

	/**
	 * Components required by test suite.
	 *
	 * @var array
	 */
	private static $suite_components = [];

	/**
	 * Hosts to be enabled for test suite.
	 *
	 * @var array
	 */
	private static $suite_hosts = [];

	/**
	 * Configuration provider for test suite.
	 *
	 * @var array
	 */
	private static $suite_configuration = [];

	/**
	 * Components required by test case.
	 *
	 * @var array
	 */
	private $case_components = [];

	/**
	 * Hosts to be enabled for test case.
	 *
	 * @var array
	 */
	private $case_hosts = [];

	/**
	 * Configuration provider for test case.
	 *
	 * @var array
	 */
	private $case_configuration = [];

	/**
	 * Process annotations defined on suite / case level.
	 *
	 * @param string $type    annotation type ('class' or 'method')
	 *
	 * @throws Exception    on invalid configuration provider
	 */
	protected function processAnnotations($type) {
		$annotations = $this->getAnnotationsByType($this->annotations, $type);
		$result = [
			'components'	=> [],
			'hosts'			=> [],
			'configuration'	=> []
		];

		// Get required components.
		foreach ($this->getAnnotationTokensByName($annotations, 'required-components') as $component) {
			if ($component === 'agent') {
				$component = self::COMPONENT_AGENT;
			}

			self::validateComponent($component);
			$result['components'][$component] = true;
		}

		$result['components'] = array_keys($result['components']);

		// Get hosts to enable.
		foreach ($this->getAnnotationTokensByName($annotations, 'hosts') as $host) {
			$result['hosts'][$host] = true;
		}

		$result['hosts'] = array_keys($result['hosts']);

		// Get configuration from configuration data provider.
		foreach ($this->getAnnotationTokensByName($annotations, 'configurationDataProvider') as $provider) {
			if (!method_exists($this, $provider) || !is_array($config = call_user_func([$this, $provider]))) {
				throw new Exception('Configuration data provider "'.$provider.'" is not valid.');
			}

			$result['configuration'] = array_merge($result['configuration'], $config);
		}

		return $result;
	}

	/**
	 * Set status for hosts.
	 *
	 * @param array   $hosts     array of hostids or host names
	 * @param integer $status    status to be set
	 */
	protected static function setHostStatus($hosts, $status) {
		if (is_scalar($hosts)) {
			$hosts = [$hosts];
		}

		if ($hosts && is_array($hosts)) {
			$filters = [];
			$criteria = [];

			foreach ($hosts as $host) {
				$filters[(is_numeric($host) ? 'hostid' : 'host')][] = zbx_dbstr($host);
			}

			foreach ($filters as $key => $values) {
				$criteria[] = $key.' in ('.implode(',', $values).')';
			}

			DBexecute('UPDATE hosts SET status='.zbx_dbstr($status).' WHERE '.implode(' OR ', $criteria));
		}
	}

	/**
	 * @inheritdoc
	 */
	protected function onBeforeTestSuite() {
		parent::onBeforeTestSuite();

		$result = $this->processAnnotations('class');
		self::$suite_components = $result['components'];
		self::$suite_hosts = $result['hosts'];
		self::$suite_configuration = self::getDefaultComponentConfiguration();

		foreach ([self::COMPONENT_SERVER, self::COMPONENT_PROXY, self::COMPONENT_AGENT] as $component) {
			if (!array_key_exists($component, $result['configuration'])) {
				continue;
			}

			self::$suite_configuration[$component] = array_merge(self::$suite_configuration[$component],
					$result['configuration'][$component]
			);
		}

		self::setHostStatus(self::$suite_hosts, HOST_STATUS_MONITORED);

		foreach (self::$suite_components as $component) {
			self::prepareComponentConfiguration($component, self::$suite_configuration);
			$this->startComponent($component);
		}
	}

	/**
	 * Callback executed before every test case.
	 *
	 * @before
	 */
	public function onBeforeTestCase() {
		parent::onBeforeTestCase();

		$result = $this->processAnnotations('method');
		$this->case_components = array_diff($result['components'], self::$suite_components);
		$this->case_hosts = array_diff($result['hosts'], self::$suite_hosts);
		$this->case_configuration = self::$suite_configuration;

		foreach ([self::COMPONENT_SERVER, self::COMPONENT_PROXY, self::COMPONENT_AGENT] as $component) {
			if (!array_key_exists($component, $result['configuration'])) {
				continue;
			}

			$this->case_configuration[$component] = array_merge($this->case_configuration[$component],
					$result['configuration'][$component]
			);
		}

		self::setHostStatus($this->case_hosts, HOST_STATUS_MONITORED);

		foreach (self::$suite_components as $component) {
			if ($this->case_configuration[$component] === self::$suite_configuration[$component]) {
				continue;
			}

			self::prepareComponentConfiguration($component, $this->case_configuration);
			$this->restartComponent($component);
		}

		foreach ($this->case_components as $component) {
			self::prepareComponentConfiguration($component, $this->case_configuration);
			$this->startComponent($component);
		}
	}

	/**
	 * Callback executed after every test case.
	 *
	 * @after
	 */
	public function onAfterTestCase() {
		foreach ($this->case_components as $component) {
			try {
				self::stopComponent($component);
			}
			catch (Exception $e) {
				self::addWarning($e->getMessage());
			}
		}

		foreach (self::$suite_components as $component) {
			if ($this->case_configuration[$component] === self::$suite_configuration[$component]) {
				continue;
			}

			self::prepareComponentConfiguration($component, self::$suite_configuration);
			$this->restartComponent($component);
		}

		self::setHostStatus($this->case_hosts, HOST_STATUS_NOT_MONITORED);

		parent::onAfterTestCase();
	}

	/**
	 * Callback executed after every test suite.
	 *
	 * @afterClass
	 */
	public static function onAfterTestSuite() {
		foreach (self::$suite_components as $component) {
			try {
				self::stopComponent($component);
			}
			catch (Exception $e) {
				self::addWarning($e->getMessage());
			}
		}

		self::setHostStatus(self::$suite_hosts, HOST_STATUS_NOT_MONITORED);

		parent::onAfterTestSuite();
	}

	/**
	 * Validate component name.
	 *
	 * @param string $component    component name to be validated.
	 *
	 * @throws Exception    on invalid component name
	 */
	private static function validateComponent($component) {
		if (!in_array($component, [self::COMPONENT_SERVER, self::COMPONENT_PROXY, self::COMPONENT_AGENT])) {
			throw new Exception('Unknown component name "'.$component.'".');
		}
	}

	/**
	 * Wait for component to start.
	 *
	 * @param string $component    component name
	 *
	 * @throws Exception    on failed wait operation
	 */
	protected static function waitForStartup($component) {
		self::validateComponent($component);

		for ($r = 0; $r < self::WAIT_ITERATIONS; $r++) {
			$pid = @file_get_contents('/tmp/zabbix_'.$component.'.pid');
			if ($pid && is_numeric($pid) && posix_kill($pid, 0)) {
				sleep(self::COMPONENT_STARTUP_DELAY);

				return;
			}

			sleep(self::WAIT_ITERATION_DELAY);
		}

		throw new Exception('Failed to wait for component "'.$component.'" to start.');
	}

	/**
	 * Wait for component to stop.
	 *
	 * @param string $component    component name
	 *
	 * @throws Exception    on failed wait operation
	 */
	protected static function waitForShutdown($component) {
		self::validateComponent($component);

		for ($r = 0; $r < self::WAIT_ITERATIONS; $r++) {
			if (!file_exists('/tmp/zabbix_'.$component.'.pid')) {
				return;
			}

			sleep(self::WAIT_ITERATION_DELAY);
		}

		throw new Exception('Failed to wait for component "'.$component.'" to start.');
	}

	/**
	 * Execute command and the execution result.
	 *
	 * @param string $command   command to be executed
	 * @param array  $params    parameters to be passed
	 * @param string $suffix    command suffix
	 *
	 * @return string
	 *
	 * @throws Exception    on execution error
	 */
	private static function executeCommand($command, $params = [], $suffix = '') {
		$return = null;
		$output = null;

		if ($params) {
			foreach ($params as &$param) {
				$param = escapeshellarg($param);
			}
			unset($param);

			$params = ' '.implode(' ', $params);
		}
		else {
			$params = null;
		}

		exec($command.$params.$suffix, $output, $return);

		if ($return !== 0) {
			throw new Exception('Failed to execute command "'.$command.$params.$suffix.'".');
		}

		return $output;
	}

	/**
	 * Get default configuration of components.
	 *
	 * @return array
	 */
	protected static function getDefaultComponentConfiguration() {
		global $DB;

		$db = [
			'DBName' => $DB['DATABASE'],
			'DBUser' => $DB['USER'],
			'DBPassword' => $DB['PASSWORD']
		];

		if ($DB['SERVER'] !== 'localhost' && $DB['SERVER'] !== '127.0.0.1') {
			$db['DBHost'] = $DB['SERVER'];
		}

		if ($DB['PORT'] != 0) {
			$db['DBPort'] = $DB['PORT'];
		}

		if ($DB['SCHEMA']) {
			$db['DBSchema'] = $DB['SCHEMA'];
		}

		$configuration = [
			self::COMPONENT_SERVER => $db,
			self::COMPONENT_PROXY => $db,
			self::COMPONENT_AGENT => []
		];

		$configuration[self::COMPONENT_PROXY]['DBName'] .= '-proxy';

		return $configuration;
	}

	/**
	 * Create configuration file for component.
	 *
	 * @param string $component    component name
	 * @param array  $values       configuration array
	 *
	 * @throws Exception    on failed configuration file write
	 */
	protected static function prepareComponentConfiguration($component, $values) {
		self::validateComponent($component);

		$path = PHPUNIT_CONFIG_SOURCE_DIR.'zabbix_'.$component.'.conf';
		if (!file_exists($path) || ($config = @file_get_contents($path)) === false) {
			throw new Exception('There is no configuration file for component "'.$component.'".');
		}

		if (array_key_exists($component, $values) && $values[$component] && is_array($values[$component])) {
			foreach ($values[$component] as $key => $value) {
				$result = preg_replace('/^'.$key.'\s*=.*$/m', $key.'='.$value, $config);
				if ($result !== $config) {
					$config = $result;
					continue;
				}
				else {
					$config .= "\n".$key.'='.$value;
				}
			}
		}

		if (file_put_contents(PHPUNIT_CONFIG_DIR.'zabbix_'.$component.'.conf', $config) === false) {
			throw new Exception('Failed to create configuration file for component "'.$component.'".');
		}
	}

	/**
	 * Start component.
	 *
	 * @param string $component    component name
	 *
	 * @throws Exception    on missing configuration or failed start
	 */
	protected function startComponent($component) {
		self::validateComponent($component);

		$config = PHPUNIT_CONFIG_DIR.'zabbix_'.$component.'.conf';
		if (!file_exists($config)) {
			throw new Exception('There is no configuration file for component "'.$component.'".');
		}

		$this->clearLog($component);
		self::executeCommand(PHPUNIT_BINARY_DIR.'zabbix_'.$component, ['-c', $config]);
		self::waitForStartup($component);
	}

	/**
	 * Stop component.
	 *
	 * @param string $component    component name
	 *
	 * @throws Exception    on missing configuration or failed stop
	 */
	protected static function stopComponent($component) {
		self::validateComponent($component);
		self::executeCommand('pkill zabbix_'.$component);
		self::waitForShutdown($component);
	}

	/**
	 * Restart component.
	 *
	 * @param string $component    component name
	 *
	 * @throws Exception    on missing configuration or failed restart
	 */
	protected static function restartComponent($component) {
		self::stopComponent($component);
		self::startComponent($component);
	}

	/**
	 * Get client for component.
	 *
	 * @param string $component    component name
	 *
	 * @throws Exception    on invalid component type
	 */
	protected function getClient($component) {
		self::validateComponent($component);

		if ($component === self::COMPONENT_AGENT) {
			throw new Exception('There is no client available for Zabbix Agent.');
		}

		return new CZabbixClient('localhost', $this->getConfigurationValue($component, 'ListenPort', 10051),
				ZBX_SOCKET_TIMEOUT, ZBX_SOCKET_BYTES_LIMIT
		);
	}

	/**
	 * Get name of active component used in test.
	 *
	 * @return string
	 */
	protected function getActiveComponent() {
		$components = [];
		foreach (array_merge(self::$suite_components, $this->case_components) as $component) {
			if ($component !== self::COMPONENT_AGENT) {
				$components[] = $component;
			}
		}

		if (count($components) === 1) {
			return $components[0];
		}
		else {
			return self::COMPONENT_SERVER;
		}
	}

	/**
	 * Send value for items to server.
	 *
	 * @param string $type         data type
	 * @param array  $values       item values
	 * @param string $component    component name or null for active component
	 *
	 * @return array    processing result
	 */
	protected function sendDataValues($type, $values, $component = null) {
		if ($component === null) {
			$component = $this->getActiveComponent();
		}

		$result = $this->getClient($component)->sendDataValues($type, $values);

		// Check that discovery data was sent.
		$this->assertTrue(array_key_exists('processed', $result), 'Result doesn\'t contain "processed" count.');
		$this->assertEquals(count($values), $result['processed'],
				'Processed value count doesn\'t match sent value count.'
		);

		return $result;
	}

	/**
	 * Send single item value.
	 *
	 * @param string $type         data type
	 * @param string $host         host name
	 * @param string $key          item key
	 * @param mixed  $value        item value
	 * @param string $component    component name or null for active component
	 *
	 * @return array    processing result
	 */
	protected function sendDataValue($type, $host, $key, $value, $component = null) {
		if (!is_scalar($value)) {
			$value = json_encode($value);
		}

		$data = [
			'host' => $host,
			'key' => $key,
			'value' => $value
		];

		return $this->sendDataValues($type, [$data], $component);
	}

	/**
	 * Send values to trapper items.
	 *
	 * @param array  $values       item values
	 * @param string $component    component name or null for active component
	 *
	 * @return array    processing result
	 */
	protected function sendSenderValues($values, $component = null) {
		return $this->sendDataValues('sender', $values, $component);
	}

	/**
	 * Send single value for trapper item.
	 *
	 * @param string $host         host name
	 * @param string $key          item key
	 * @param mixed  $value        item value
	 * @param string $component    component name or null for active component
	 *
	 * @return array    processing result
	 */
	protected function sendSenderValue($host, $key, $value, $component = null) {
		return $this->sendDataValue('sender', $host, $key, $value, $component);
	}

	/**
	 * Send values to active agent items.
	 *
	 * @param array  $values       item values
	 * @param string $component    component name or null for active component
	 *
	 * @return array    processing result
	 */
	protected function sendAgentValues($values, $component = null) {
		return $this->sendDataValues('agent', $values, $component);
	}

	/**
	 * Send single value for active agent item.
	 *
	 * @param string $host         host name
	 * @param string $key          item key
	 * @param mixed  $value        item value
	 * @param string $component    component name or null for active component
	 *
	 * @return array    processing result
	 */
	protected function sendAgentValue($host, $key, $value, $component = null) {
		return $this->sendDataValue('agent', $host, $key, $value, $component);
	}

	/**
	 * Get list of active checks for host.
	 *
	 * @param string $host         host name
	 * @param string $component    component name or null for active component
	 *
	 * @return array
	 */
	protected function getActiveAgentChecks($host, $component = null) {
		if ($component === null) {
			$component = $this->getActiveComponent();
		}

		$client = $this->getClient($component);
		$checks = $client->getActiveChecks($host);

		if (!is_array($checks)) {
			$this->fail('Cannot retrieve active checks for host "'.$host.'": '.$client->getError().'.');
		}

		return $checks;
	}

	/**
	 * Reload configuration cache.
	 *
	 * @param string $component    component name or null for active component
	 */
	protected function reloadConfigurationCache($component = null) {
		if ($component === null) {
			$component = $this->getActiveComponent();
		}

		$params = ['--runtime-control', 'config_cache_reload'];
		self::executeCommand(PHPUNIT_BINARY_DIR.'zabbix_'.$component, $params, '> /dev/null 2>&1');
		sleep(self::CACHE_RELOAD_DELAY);
	}

	/**
	 * Request data from API until data is present (@see call).
	 *
	 * @param string  $method        API method to be called
	 * @param mixed   $params        API call params
	 * @param integer $iterations    iteration count
	 * @param integer $delay         iteration delay
	 *
	 * @return array
	 */
	public function callUntilDataIsPresent($method, $params, $iterations = null, $delay = null) {
		if ($iterations === null) {
			$iterations = self::WAIT_ITERATIONS;
		}

		if ($delay === null) {
			$delay = self::WAIT_ITERATION_DELAY;
		}

		$exception = null;
		for ($i = 0; $i < $iterations; $i++) {
			try {
				$response = $this->call($method, $params);

				if (is_array($response['result']) && count($response['result']) > 0) {
					return $response;
				}
			} catch (Exception $e) {
				$exception = $e;
			}

			sleep($delay);
		}

		if ($exception !== null) {
			throw $exception;
		}

		$this->fail('Data requested from '.$method.' API is not present withing specified interval. Params used:'.
				"\n".json_encode($params)
		);
	}

	/**
	 * Get path of the log file for component.
	 *
	 * @param string $component    name of the component
	 *
	 * @return string
	 */
	protected function getLogPath($component) {
		self::validateComponent($component);

		return $this->getConfigurationValue($component, 'LogFile', '/tmp/zabbix_'.$component.'.log');
	}

	/**
	 * Get current configuration value.
	 *
	 * @param string $component    name of the component
	 * @param string $key          name of the configuration parameter
	 * @param mixed  $default      default value
	 *
	 * @return mixed
	 */
	protected function getConfigurationValue($component, $key, $default = null) {
		if (array_key_exists($component, $this->case_configuration)
				&& array_key_exists($key, $this->case_configuration[$component])) {
			return $this->case_configuration[$component][$key];
		}

		return $default;
	}

	/**
	 * Clear contents of log.
	 *
	 * @param string $component    name of the component
	 */
	protected function clearLog($component) {
		CLogHelper::clearLog($this->getLogPath($component));
	}

	/**
	 * Check if line is present.
	 *
	 * @param string       $component     name of the component
	 * @param string|array $lines         line(s) to look for
	 * @param boolean      $incremental   flag to be used to enable incremental read
	 *
	 * @return boolean
	 */
	protected function isLogLinePresent($component, $lines, $incremental = true) {
		return CLogHelper::isLogLinePresent($this->getLogPath($component), $lines, $incremental);
	}

	/**
	 * Wait until line is present in log.
	 *
	 * @param string       $component     name of the component
	 * @param string|array $lines         line(s) to look for
	 * @param boolean      $incremental   flag to be used to enable incremental read
	 * @param integer      $iterations    iteration count
	 * @param integer      $delay         iteration delay
	 *
	 * @throws Exception    on failed wait operation
	 */
	protected function waitForLogLineToBePresent($component, $lines, $incremental = true, $iterations = null, $delay = null) {
		if ($iterations === null) {
			$iterations = self::WAIT_ITERATIONS;
		}

		if ($delay === null) {
			$delay = self::WAIT_ITERATION_DELAY;
		}

		for ($r = 0; $r < $iterations; $r++) {
			if ($this->isLogLinePresent($component, $lines, $incremental)) {
				return;
			}

			sleep($delay);
		}

		if (count($lines) > 1) {
			$quoted = [];
			foreach ($lines as $line) {
				$quoted[] = '"'.$line.'"';
			}

			$description = 'any of the lines ['.implode(', ', $quoted).']';
		}
		else {
			$description = 'line '.$quoted[0];
		}

		throw new Exception('Failed to wait for '.$description.' to be present in '.$component.' log file.');
	}
}
