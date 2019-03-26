<?php

class CSlaReport
{
	public static $error;

	private static $sql_count;
	private static $sql_time;
	private static $dbh;

	/**
	 * Generates SLA reports.
	 *
	 * @param int   $server_id ID of server in config file
	 * @param array $tlds      array of TLD names; if empty, reports for all TLDs will be generated
	 * @param int   $year      year
	 * @param int   $month     month
	 *
	 * @static
	 *
	 * @return array|null Returns array of reports or NULL on error. Use CSlaReport::$error to get the erorr message.
	 */
	public static function generate($server_id, $tlds, $year, $month)
	{
		if (!is_int($server_id))
		{
			self::$error = "\$server_id must be integer";
			return null;
		}
		if (!is_array($tlds))
		{
			self::$error = "\$tlds must be array";
			return null;
		}
		if (!is_int($year))
		{
			self::$error = "\$year must be integer";
			return null;
		}
		if (!is_int($month))
		{
			self::$error = "\$month must be integer";
			return null;
		}

		$duplicate_tlds = array_keys(array_diff(array_count_values($tlds), array_count_values(array_unique($tlds))));
		if (count($duplicate_tlds) > 0)
		{
			self::$error = "\$tlds contains duplicate values: " . implode(", ", $duplicate_tlds);
			return null;
		}

		$time = time();
		$from = gmmktime(0, 0, 0, $month, 1, $year);
		$till = gmmktime(0, 0, -1, $month + 1, 1, $year);
		$till = min($till, $time);

		if ($from > $time)
		{
			self::$error = sprintf("%d-%02d seems to be a future date", $year, $month);
			return null;
		}

		self::$error = NULL;

		$default_timezone = date_default_timezone_get();
		date_default_timezone_set("UTC");

		if (defined("DEBUG") && DEBUG === true)
		{
			printf("(DEBUG) %s() server_id - %d\n", __method__, $server_id);
			printf("(DEBUG) %s() tlds  - %s\n", __method__, implode(", ", $tlds));
			printf("(DEBUG) %s() year  - %d\n", __method__, $year);
			printf("(DEBUG) %s() month - %d\n", __method__, $month);
			printf("(DEBUG) %s() from  - %s\n", __method__, date("c", $from));
			printf("(DEBUG) %s() till  - %s\n", __method__, date("c", $till));
		}

		$error_handler = function($severity, $message, $file, $line)
		{
			throw new \Exception($message);
		};
		set_error_handler($error_handler);

		try
		{
			/*
				$data = [
					$hostid => [
						"host" => string,
						"dns"  => [
							"availability" => int,
							"ns" => [
								$itemid => [
									"hostname"     => string,
									"ipAddress"    => string,
									"availability" => int,
									"from"         => int,
									"to"           => int,
								],
								...
							],
							"rttUDP" => float,
							"rttTCP" => float,
						],
						"rdds" => [
							"enabled"      => true|false,
							"availability" => int,
							"rtt"          => float,
						],
					],
					...
				];
			*/

			// TODO: how to handle cases when there's no data? e.g., "--year 2017"

			self::dbConnect($server_id);

			$data = self::collectData($tlds, $from, $till);

			self::validateData($data);

			$reports = self::generateXml($tlds, $data, $time, $from, $till);
		}
		catch (Exception $e)
		{
			$reports = null;

			self::$error = $e->getMessage();

			if (defined("DEBUG") && DEBUG === true)
			{
				self::$error .= "\n" . $e->getTraceAsString();
			}
		}

		restore_error_handler();

		self::dbDisconnect();

		date_default_timezone_set($default_timezone);

		return $reports;
	}

	private static function collectData($tlds, $from, $till)
	{
		$data = [];

		// get hostid of TLDs

		$rows = self::getTldHostIds($tlds);

		foreach ($rows as $row)
		{
			list($hostid, $host) = $row;

			$data[$hostid] = [
				"host" => $host,
				"dns"  => [
					"availability" => null,
					"ns" => [],
					"rttUDP" => null,
					"rttTCP" => null,
				],
				"rdds" => [
					"enabled"      => null,
					"availability" => null,
					"rtt"          => null,
				],
			];
		}

		if (count($tlds) > 0 && count($tlds) != count($data))
		{
			$existing_tlds = [];
			foreach ($data as $tld)
			{
				array_push($existing_tlds, $tld["host"]);
			}

			$missing_tlds = array_diff($tlds, $existing_tlds);
			$missing_tlds = preg_filter("/^.*$/", "'\\0'", $missing_tlds);
			$missing_tlds = implode(", ", $missing_tlds);

			throw new Exception("Could not find TLD(s): {$missing_tlds}");
		}

		if (count($tlds) === 0)
		{
			foreach ($data as $tld)
			{
				array_push($tlds, $tld["host"]);
			}
		}

		// get RDDS status (enabled/disabled)

		$rows = self::getRddsStatus($tlds, $from, $till);

		$rddsStatus = [];
		foreach ($rows as $row)
		{
			list($tld, $status) = $row;
			$rddsStatus[$tld] = $status === "1";
		}

		foreach ($data as $hostid => $tld)
		{
			$data[$hostid]["rdds"]["enabled"] = $rddsStatus[$tld["host"]];
		}

		// get itemid of relevant items

		$all_hostids = array_keys($data);
		$rdds_hostids = [];

		foreach ($data as $hostid => $tld)
		{
			if ($tld["rdds"]["enabled"])
			{
				array_push($rdds_hostids, $hostid);
			}
		}

		$rows = self::getItemIds($all_hostids, $rdds_hostids);

		$itemkeys = [];
		$itemhostids = [];
		$itemids_float = [];
		$itemids_uint = [];

		foreach ($rows as $row)
		{
			list($itemid, $hostid, $key, $type) = $row;

			$itemkeys[$itemid] = $key;
			$itemhostids[$itemid] = $hostid;

			if ((int)$type === 0)
			{
				array_push($itemids_float, $itemid);
			}
			elseif ((int)$type === 3)
			{
				array_push($itemids_uint, $itemid);
			}
			else
			{
				throw new Exception("Unhandled item type: '{$type}' (hostid: {$hostid}, key: {$key})");
			}
		}

		// get monthly lastvalue

		$rows = array_merge(
			self::getLastValue($itemids_float, "history"     , $from, $till),
			self::getLastValue($itemids_uint , "history_uint", $from, $till)
		);

		foreach ($rows as $row)
		{
			list($itemid, $value) = $row;
			$hostid = $itemhostids[$itemid];
			$key = $itemkeys[$itemid];

			switch ($key)
			{
				case "rsm.slv.dns.downtime":
					$data[$hostid]["dns"]["availability"] = $value;
					break;

				case "rsm.slv.dns.udp.rtt.pfailed":
					$data[$hostid]["dns"]["rttUDP"] = 100.0 - $value;
					break;

				case "rsm.slv.dns.tcp.rtt.pfailed":
					$data[$hostid]["dns"]["rttTCP"] = 100.0 - $value;
					break;

				case "rsm.slv.rdds.downtime":
					$data[$hostid]["rdds"]["availability"] = $value;
					break;

				case "rsm.slv.rdds.rtt.pfailed":
					$data[$hostid]["rdds"]["rtt"] = 100.0 - $value;
					break;

				default:
					/*
					TODO: this might be NS availability item.

					$ns_host = $key;
					$ns_ip   = $key;
					$data[$hostid]["dns"]["ns"][$itemid] = [
						"hostname"     => $ns_host,
						"ipAddress"    => $ns_ip,
						"availability" => $value,
						"from"         => null,
						"to"           => null,
					];
					*/
					throw new Exception("Unhandled item key: '{$key}'");
					break;
			}
		}

		// get monthly min and max clocks

		// TODO: min(clock) and max(clock) is needed for NS availability items only.

		$rows = self::getMinMaxClock($itemids_uint, "history_uint", $from, $till);

		return $data;
	}

	private static function validateData(&$data)
	{
		foreach ($data as $hostid => $tld)
		{
			if (is_null($tld["host"]))
			{
				throw new Exception("\$data[{$hostid}]['host'] is null");
			}
			if (is_null($tld["dns"]["availability"]))
			{
				throw new Exception("\$data[{$hostid}]['dns']['availability'] is null (TLD: '{$tld["host"]}')");
			}
			if (!is_array($tld["dns"]["ns"]))
			{
				throw new Exception("\$data[{$hostid}]['dns']['ns'] is not an array (TLD: '{$tld["host"]}')");
			}
			if (count($tld["dns"]["ns"]) === 0)
			{
				// TODO: uncomment
				//throw new Exception("\$data[{$hostid}]['dns']['ns'] is empty array (TLD: '{$tld["host"]}')");
			}
			foreach ($tld["dns"]["ns"] as $i => $ns)
			{
				if (is_null($ns["hostname"]))
				{
					throw new Exception("\$data[{$hostid}]['dns']['ns'][{$i}]['hostname'] is null (TLD: '{$tld["host"]}')");
				}
				if (is_null($ns["ipAddress"]))
				{
					throw new Exception("\$data[{$hostid}]['dns']['ns'][{$i}]['ipAddress'] is null (TLD: '{$tld["host"]}')");
				}
				// TODO: "availability", "from", "till" - what if NS was disabled for whole month?
				if (is_null($ns["availability"]))
				{
					throw new Exception("\$data[{$hostid}]['dns']['ns'][{$i}]['availability'] is null (TLD: '{$tld["host"]}')");
				}
				if (is_null($ns["from"]))
				{
					throw new Exception("\$data[{$hostid}]['dns']['ns'][{$i}]['from'] is null (TLD: '{$tld["host"]}')");
				}
				if (is_null($ns["to"]))
				{
					throw new Exception("\$data[{$hostid}]['dns']['ns'][{$i}]['to'] is null (TLD: '{$tld["host"]}')");
				}
			}
			if (!is_float($tld["dns"]["rttUDP"]))
			{
				throw new Exception("\$data[{$hostid}]['dns']['rttUDP'] is not float (TLD: '{$tld["host"]}')");
			}
			if (!is_float($tld["dns"]["rttTCP"]))
			{
				throw new Exception("\$data[{$hostid}]['dns']['rttTCP'] is not float (TLD: '{$tld["host"]}')");
			}
			if (!is_bool($tld["rdds"]["enabled"]))
			{
				throw new Exception("\$data[{$hostid}]['rdds']['enabled'] is not bool (TLD: '{$tld["host"]}')");
			}
			if ($tld["rdds"]["enabled"])
			{
				if (is_null($tld["rdds"]["availability"]))
				{
					throw new Exception("\$data[{$hostid}]['rdds']['availability'] is null (TLD: '{$tld["host"]}')");
				}
				if (!is_float($tld["rdds"]["rtt"]))
				{
					throw new Exception("\$data[{$hostid}]['rdds']['rtt'] is not float (TLD: '{$tld["host"]}')");
				}
			}
			else
			{
				if (!is_null($tld["rdds"]["availability"]))
				{
					throw new Exception("\$data[{$hostid}]['rdds']['availability'] is not null (TLD: '{$tld["host"]}')");
				}
				if (!is_null($tld["rdds"]["rtt"]))
				{
					throw new Exception("\$data[{$hostid}]['rdds']['rtt'] is not null (TLD: '{$tld["host"]}')");
				}
			}
		}
	}

	private static function generateXml(&$tlds, &$data, $generationDateTime, $reportPeriodFrom, $reportPeriodTo)
	{
		$reports = array_fill_keys($tlds, null); // for sorting, based on $tlds

		foreach ($data as $tldid => $tld)
		{
			$xml = new SimpleXMLElement("<reportTLD/>");
			$xml->addAttribute("id", $tld["host"]);
			$xml->addAttribute("generationDateTime", $generationDateTime);
			$xml->addAttribute("reportPeriodFrom", $reportPeriodFrom);
			$xml->addAttribute("reportPeriodTo", $reportPeriodTo);

			$xml_dns = $xml->addChild("DNS");
			$xml_dns->addChild("serviceAvailability", $tld["dns"]["availability"]);
			foreach ($tld["dns"]["ns"] as $ns)
			{
				$xml_ns = $xml_dns->addChild("nsAvailability", $ns["availability"]);
				$xml_ns->addAttribute("hostname", $ns["hostname"]);
				$xml_ns->addAttribute("ipAddress", $ns["ipAddress"]);
				$xml_ns->addAttribute("from", $ns["from"]);
				$xml_ns->addAttribute("to", $ns["to"]);
			}
			$xml_dns->addChild("rttUDP", $tld["dns"]["rttUDP"]);
			$xml_dns->addChild("rttTCP", $tld["dns"]["rttTCP"]);

			$xml_rdds = $xml->addChild("RDDS");

			if ($tld["rdds"]["enabled"])
			{
				$xml_rdds->addChild("serviceAvailability", $tld["rdds"]["availability"]);
				$xml_rdds->addChild("rtt", $tld["rdds"]["rtt"]);
			}
			else
			{
				$xml_rdds->addChild("serviceAvailability", "disabled");
				$xml_rdds->addChild("rtt", "disabled");
			}

			$dom = dom_import_simplexml($xml)->ownerDocument;
			$dom->formatOutput = true;

			$reports[$tld["host"]] = [
				"hostid" => $tldid,
				"host"   => $tld["host"],
				"report" => $dom->saveXML(),
			];
		}

		return array_values($reports);
	}

	################################################################################
	# Data retrieval methods
	################################################################################

	// TODO: replace "foo bar baz" NS availability key pattern
	private static function getItemIds($all_hostids, $rdds_hostids)
	{
		$hostids_placeholder = substr(str_repeat("?,", count($all_hostids)), 0, -1);
		$sql = "select itemid,hostid,key_,value_type" .
			" from items" .
			" where (" .
					"hostid in ({$hostids_placeholder}) and" .
					" (" .
						"key_ in ('rsm.slv.dns.downtime','rsm.slv.dns.udp.rtt.pfailed','rsm.slv.dns.tcp.rtt.pfailed') or" .
						" key_ like 'foo bar baz'" .
					")" .
				")";
		$params = $all_hostids;

		if (count($rdds_hostids) > 0)
		{
			$hostids_placeholder = substr(str_repeat("?,", count($rdds_hostids)), 0, -1);
			$sql .= " or (" .
					"hostid in ({$hostids_placeholder}) and" .
					" key_ in ('rsm.slv.rdds.downtime','rsm.slv.rdds.rtt.pfailed')" .
				")";
			$params = array_merge($params, $rdds_hostids);
		}

		return self::dbSelect($sql, $params);
	}

	private static function getRddsStatus($tlds, $from, $till)
	{
		$tlds_subquery = "";
		foreach ($tlds as $tld)
		{
			if ($tlds_subquery === "")
			{
				$tlds_subquery .= "select ? as tld";
			}
			else
			{
				$tlds_subquery .= " union all select ?";
			}
		}

		$sql = "select" .
				" tlds.tld," .
				" exists (" .
					"select * from" .
						" items" .
						" left join hosts on hosts.hostid=items.hostid" .
						" left join hosts_groups on hosts_groups.hostid=hosts.hostid" .
						" left join history_uint on history_uint.itemid=items.itemid" .
					" where" .
						" items.key_ in ('rdap.enabled','rdds.enabled') and" .
						" hosts.host like concat(tlds.tld,' %') and" .
						" hosts_groups.groupid=190 and" .
						" history_uint.clock between ? and ? and" .
						" history_uint.value=1" .
				") as status" .
			" from ({$tlds_subquery}) as tlds";

		$params = array_merge([$from, $till], $tlds);

		return self::dbSelect($sql, $params);
	}

	private static function getTldHostIds($tlds)
	{
		$sql = "select hosts.hostid,hosts.host" .
			" from hosts" .
				" left join hosts_groups on hosts_groups.hostid=hosts.hostid" .
			" where hosts_groups.groupid=140";
		$params = [];

		if (count($tlds) === 0)
		{
			$sql .= " order by hosts.host asc";
		}
		else
		{
			$tlds_placeholder = substr(str_repeat("?,", count($tlds)), 0, -1);
			$sql .= " and hosts.host in ({$tlds_placeholder})";
			$params = array_merge($params, $tlds);
		}

		return self::dbSelect($sql, $params);
	}

	private static function getLastValue($itemids, $history_table, $from, $till)
	{
		if (count($itemids) === 0)
		{
			return [];
		}

		$itemids_placeholder = substr(str_repeat("?,", count($itemids)), 0, -1);
		$sql = "select {$history_table}.itemid,{$history_table}.value" .
			" from {$history_table}," .
				" (" .
					"select itemid,max(clock) as clock" .
					" from {$history_table}" .
					" where itemid in ({$itemids_placeholder}) and" .
						" clock between ? and ?" .
					" group by itemid" .
				") as history_max_clock" .
			" where history_max_clock.itemid={$history_table}.itemid and" .
				" history_max_clock.clock={$history_table}.clock";
		$params = array_merge($itemids, [$from, $till]);
		return self::dbSelect($sql, $params);
	}

	private static function getMinMaxClock($itemids, $history_table, $from, $till)
	{
		if (count($itemids) === 0)
		{
			return [];
		}

		$itemids_placeholder = substr(str_repeat("?,", count($itemids)), 0, -1);
		$sql = "select itemid,min(clock),max(clock)" .
			" from {$history_table}" .
			" where itemid in ({$itemids_placeholder}) and" .
				" clock between ? and ?" .
			" group by itemid";
		$params = array_merge($itemids, [$from, $till]);
		return self::dbSelect($sql, $params);
	}

	################################################################################
	# DB methods
	################################################################################

	public static function dbSelect($sql, $input_parameters = NULL)
	{
		if (defined("DEBUG") && DEBUG === true)
		{
			$params = is_null($input_parameters) ? "NULL" : "[" . implode(", ", $input_parameters) . "]";
			printf("(DEBUG) %s() query  - %s\n", __method__, $sql);
			printf("(DEBUG) %s() params - %s\n", __method__, $params);
		}

		if (defined("STATS") && STATS === true)
		{
			$time = microtime(true);
		}

		$sth = self::$dbh->prepare($sql);
		$sth->execute($input_parameters);
		$rows = $sth->fetchAll();

		if (defined("STATS") && STATS === true)
		{
			self::$sql_time += microtime(true) - $time;
			self::$sql_count++;
		}

		if (defined("DEBUG") && DEBUG === true)
		{
			$result = count($rows) === 1 ? "[" . implode(", ", $rows[0]) . "]" : count($rows) . " row(s)";
			printf("(DEBUG) %s() result - %s\n", __method__, $result);
		}

		return $rows;
	}

	public static function dbExecute($sql, $input_parameters = NULL)
	{
		if (defined("DEBUG") && DEBUG === true)
		{
			$params = is_null($input_parameters) ? "NULL" : "[" . implode(", ", $input_parameters) . "]";
			printf("(DEBUG) %s() query  - %s\n", __method__, $sql);
			printf("(DEBUG) %s() params - %s\n", __method__, $params);
		}

		if (defined("STATS") && STATS === true)
		{
			$time = microtime(true);
		}

		$sth = self::$dbh->prepare($sql);
		$sth->execute($input_parameters);
		$rows = $sth->rowCount();

		if (defined("STATS") && STATS === true)
		{
			self::$sql_time += microtime(true) - $time;
			self::$sql_count++;
		}

		if (defined("DEBUG") && DEBUG === true)
		{
			printf("(DEBUG) %s() result - %s row(s)\n", __method__, $rows);
		}

		return $rows;
	}

	public static function dbBeginTransaction()
	{
		self::$dbh->beginTransaction();
	}

	public static function dbRollBack()
	{
		self::$dbh->rollBack();
	}

	public static function dbCommit()
	{
		self::$dbh->commit();
	}

	public static function dbConnect($server_id)
	{
		self::$sql_count = 0;
		self::$sql_time = 0.0;

		$conf = self::getDbConfig($server_id);
		$hostname = $conf["hostname"];
		$username = $conf["username"];
		$password = $conf["password"];
		$database = $conf["database"];
		$ssl_conf = $conf["ssl_conf"];

		self::$dbh = new PDO("mysql:host={$hostname};dbname={$database}", $username, $password, $ssl_conf);
		self::$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		self::$dbh->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_NUM);
		self::$dbh->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
		self::$dbh->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
	}

	public static function dbDisconnect()
	{
		self::$dbh = NULL;

		if (defined("STATS") && STATS === true)
		{
			printf("(STATS) SQL count - %d\n", self::$sql_count);
			printf("(STATS) SQL time  - %.6f\n", self::$sql_time);
		}
	}

	private static function getDbConfig($server_id)
	{
		if (array_key_exists('REQUEST_METHOD', $_SERVER))
		{
			return self::getDbConfigFromFrontend($server_id);
		}
		else
		{
			return self::getDbConfigFromRsmConf($server_id);
		}
	}

	private static function getDbConfigFromFrontend($server_id)
	{
		global $DB;

		if (!isset($DB))
		{
			throw new Exception("Failed to get DB config");
		}
		if (!array_key_exists($server_id, $DB["SERVERS"]))
		{
			throw new Exception("Invalid server ID: {$server_id}");
		}

		$server_conf = $DB["SERVERS"][$server_id];

		$ssl_conf = [];

		if (isset($server_conf["DB_KEY_FILE"]))
		{
			$ssl_conf[PDO::MYSQL_ATTR_SSL_KEY] = $server_conf["DB_KEY_FILE"];
		}
		if (isset($server_conf["DB_CERT_FILE"]))
		{
			$ssl_conf[PDO::MYSQL_ATTR_SSL_CERT] = $server_conf["DB_CERT_FILE"];
		}
		if (isset($server_conf["DB_CA_FILE"]))
		{
			$ssl_conf[PDO::MYSQL_ATTR_SSL_CA] = $server_conf["DB_CA_FILE"];
		}
		if (isset($server_conf["DB_CA_PATH"]))
		{
			$ssl_conf[PDO::MYSQL_ATTR_SSL_CAPATH] = $server_conf["DB_CA_PATH"];
		}
		if (isset($server_conf["DB_CA_CIPHER"]))
		{
			$ssl_conf[PDO::MYSQL_ATTR_SSL_CIPHER] = $server_conf["DB_CA_CIPHER"];
		}

		return [
			"hostname" => $server_conf["SERVER"],
			"username" => $server_conf["USER"],
			"password" => $server_conf["PASSWORD"],
			"database" => $server_conf["DATABASE"],
			"ssl_conf" => $ssl_conf,
		];
	}

	private static function getDbConfigFromRsmConf($server_id)
	{
		$conf_file = "/opt/zabbix/scripts/rsm.conf";

		if (!is_file($conf_file))
		{
			throw new Exception("File not found: {$conf_file}");
		}
		if (!is_readable($conf_file))
		{
			throw new Exception("File is not readable: {$conf_file}");
		}

		// PHP 5.3.0 - Hash marks (#) should no longer be used as comments and will throw a deprecation warning if used.
		// PHP 7.0.0 - Hash marks (#) are no longer recognized as comments.

		$conf_string = file_get_contents($conf_file);
		$conf_string = preg_replace("/^\s*#.*$/m", "", $conf_string);

		$conf = parse_ini_string($conf_string, true);

		if ($conf === false)
		{
			throw new Exception("Failed to parse {$conf_file}");
		}

		if (!array_key_exists("server_{$server_id}", $conf))
		{
			throw new Exception("Invalid server ID: {$server_id}");
		}

		$server_conf = $conf["server_{$server_id}"];

		$ssl_conf = [];

		if (array_key_exists("db_ca_file", $server_conf))
		{
			$ssl_conf[PDO::MYSQL_ATTR_SSL_CA] = $server_conf["db_ca_file"];
		}
		if (array_key_exists("db_ca_path", $server_conf))
		{
			$ssl_conf[PDO::MYSQL_ATTR_SSL_CAPATH] = $server_conf["db_ca_path"];
		}
		if (array_key_exists("db_cert_file", $server_conf))
		{
			$ssl_conf[PDO::MYSQL_ATTR_SSL_CERT] = $server_conf["db_cert_file"];
		}
		if (array_key_exists("db_cipher", $server_conf))
		{
			$ssl_conf[PDO::MYSQL_ATTR_SSL_CIPHER] = $server_conf["db_cipher"];
		}
		if (array_key_exists("db_key_file", $server_conf))
		{
			$ssl_conf[PDO::MYSQL_ATTR_SSL_KEY] = $server_conf["db_key_file"];
		}

		return [
			"hostname" => $server_conf["db_host"],
			"username" => $server_conf["db_user"],
			"password" => $server_conf["db_password"],
			"database" => $server_conf["db_name"],
			"ssl_conf" => $ssl_conf,
		];
	}
}
