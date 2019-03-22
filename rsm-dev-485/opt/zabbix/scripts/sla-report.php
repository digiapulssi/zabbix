#!/usr/bin/php
<?php

require_once dirname(__FILE__) . "/CSlaReport.php";

main($argv);

function main($argv)
{
	date_default_timezone_set("UTC");

	$start_time = microtime(true);

	$args = parseArgs($argv);

	if (!$args["dry_run"])
	{
		$curr_year  = (int)date("Y");
		$curr_month = (int)date("n");
		if ($args["year"] >= $curr_year && $args["month"] >= $curr_month)
		{
			fail(sprintf("Cannot generate reports for %04d-%02d, month hasn't ended yet", $args["year"], $args["month"]));
		}
	}

	$reports = CSlaReport::generate($args["server_id"], $args["tlds"], $args["year"], $args["month"]);
	if (is_null($reports))
	{
		fail(CSlaReport::$error);
	}

	if ($args["dry_run"])
	{
		foreach ($reports as $report)
		{
			print(str_pad(" {$report["host"]} ", 120, "=", STR_PAD_BOTH) . "\n");
			echo $report["report"];
		}
		print(str_repeat("=", 120) . "\n");
	}
	else
	{
		try
		{
			CSlaReport::dbConnect($args["server_id"]);

			CSlaReport::dbBeginTransaction();

			$sql = "insert into sla_reports (hostid, year, month, report) values (?,?,?,?) on duplicate key update report = ?";

			foreach ($reports as $report)
			{
				$params = [
					$report["hostid"],
					$args["year"],
					$args["month"],
					$report["report"],
					$report["report"],
				];

				CSlaReport::dbExecute($sql, $params);
			}

			CSlaReport::dbCommit();

			CSlaReport::dbDisconnect();
		}
		catch (Exception $e)
		{
			CSlaReport::dbRollBack();

			CSlaReport::dbDisconnect();

			$error = $e->getMessage();

			if (defined("DEBUG") && DEBUG === true)
			{
				$error .= "\n" . $e->getTraceAsString();
			}

			fail($error);
		}
	}

	if (defined("STATS") && STATS === true)
	{
		printf("(STATS) Report count - %d\n", count($reports));
		printf("(STATS) Total time   - %.6f\n", microtime(true) - $start_time);
		printf("(STATS) Mem usage    - %.2f MB\n", memory_get_peak_usage(true) / 1024 / 1024);
	}
}

function parseArgs($argv)
{
	$args = [
		"dry_run"   => false,
		"server_id" => null,
		"tlds"      => [],
		"year"      => null,
		"month"     => null,
	];

	$script = array_shift($argv);

	while ($arg = array_shift($argv))
	{
		switch ($arg)
		{
			case "--help":
				usage($script);
				break;

			case "--server-id":
				$args["server_id"] = array_shift($argv);
				if (!ctype_digit($args["server_id"]))
				{
					usage($script, "Value of --server-id must be a number, got: {$args["server_id"]}");
				}
				$args["server_id"] = (int)$args["server_id"];
				break;

			case "--tld":
				$tld = array_shift($argv);
				if ($tld[0] === "-")
				{
					usage($script, "Value of --tld must be a TLD name, got: {$tld}");
				}
				if (in_array($tld, $args["tlds"]))
				{
					usage($script, "TLD was specified multiple times: {$tld}");
				}
				array_push($args["tlds"], $tld);
				break;

			case "--year":
				$args["year"] = array_shift($argv);
				if (!ctype_digit($args["year"]))
				{
					usage($script, "Value of --year must be a number, got: {$args["year"]}");
				}
				$args["year"] = (int)$args["year"];
				break;

			case "--month":
				$args["month"] = array_shift($argv);
				if (!ctype_digit($args["month"]))
				{
					usage($script, "Value of --month must be a number, got: {$args["month"]}");
				}
				$args["month"] = (int)$args["month"];
				if ($args["month"] < 1 || $args["month"] > 12)
				{
					usage($script, "Invalid value of --month: {$args["month"]}");
				}
				break;

			case "--debug":
				define("DEBUG", true);
				break;

			case "--stats":
				define("STATS", true);
				break;

			case "--dry-run":
				$args["dry_run"] = true;
				break;

			default:
				usage($script, "Invalid argument: {$arg}");
				break;
		}
	}

	if (is_null($args["server_id"]))
	{
		usage($script, "Missing argument: --server-id");
	}
	if (is_null($args["year"]))
	{
		usage($script, "Missing argument: --year");
	}
	if (is_null($args["month"]))
	{
		usage($script, "Missing argument: --month");
	}

	return $args;
}

function usage($script, $error_message = NULL)
{
	if (!is_null($error_message))
	{
		echo "Error:\n";
		echo "        {$error_message}\n";
		echo "\n";
	}

	echo "Usage:\n";
	echo "        {$script} [--help] [--debug] [--stats] [--dry-run] --server-id <server_id> --tld <tld> --year <year> --month <month>\n";
	echo "\n";

	echo "Options:\n";
	echo "        --help\n";
	echo "                Print a brief help message and exit.\n";
	echo "\n";
	echo "        --debug\n";
	echo "                Run the script in debug mode. This means printing more information.\n";
	echo "\n";
	echo "        --stats\n";
	echo "                Print some statistics that are collected during runtime.\n";
	echo "\n";
	echo "        --dry-run\n";
	echo "                Print data to the screen, do not write anything to the filesystem.\n";
	echo "\n";
	echo "        --server-id <server_id>\n";
	echo "                Specify ID of Zabbix server.\n";
	echo "\n";
	echo "        --tld <tld>\n";
	echo "                Specify TLD name.\n";
	echo "\n";
	echo "        --year <year>\n";
	echo "                Specify the year of the report.\n";
	echo "\n";
	echo "        --month <month>\n";
	echo "                Specify the month of the report (1 through 12).\n";
	echo "\n";

	if (!is_null($error_message))
	{
		fail($error_message);
	}
	else
	{
		exit(0);
	}
}

function fail($error_message)
{
	error_log("(ERROR) " . $error_message);
	exit(1);
}
