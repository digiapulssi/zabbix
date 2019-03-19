#!/usr/bin/php
<?php

require_once dirname(__FILE__) . "/CSlaReport.php";

main($argv);

function main($argv)
{
	date_default_timezone_set("UTC");

	$server_id = null;
	$tlds      = [];
	$year      = null;
	$month     = null;

	$script = array_shift($argv);

	while ($arg = array_shift($argv))
	{
		switch ($arg)
		{
			case "--help":
				usage($script);
				break;

			case "--server-id":
				$server_id = array_shift($argv);
				if (!ctype_digit($server_id))
				{
					usage($script, "Value of --server-id must be a number, got: {$server_id}");
				}
				$server_id = (int)$server_id;
				break;

			case "--tld":
				$tld = array_shift($argv);
				if ($tld[0] === "-")
				{
					usage($script, "Value of --tld must be a TLD name, got: {$tld}");
				}
				if (in_array($tld, $tlds))
				{
					usage($script, "TLD was specified multiple times: {$tld}");
				}
				array_push($tlds, $tld);
				break;

			case "--year":
				$year = array_shift($argv);
				if (!ctype_digit($year))
				{
					usage($script, "Value of --year must be a number, got: {$year}");
				}
				$year = (int)$year;
				break;

			case "--month":
				$month = array_shift($argv);
				if (!ctype_digit($month))
				{
					usage($script, "Value of --month must be a number, got: {$month}");
				}
				$month = (int)$month;
				if ($month < 1 || $month > 12)
				{
					usage($script, "Invalid value of --month: {$month}");
				}
				break;

			case "--debug":
				define("DEBUG", true);
				break;

			case "--stats":
				define("STATS", true);
				break;

			default:
				usage($script, "Invalid argument: {$arg}");
				break;
		}
	}

	if (is_null($server_id))
	{
		usage($script, "Missing argument: --server-id");
	}
	if (is_null($year))
	{
		usage($script, "Missing argument: --year");
	}
	if (is_null($month))
	{
		usage($script, "Missing argument: --month");
	}

	$reports = CSlaReport::generate($server_id, $tlds, $year, $month);
	if (is_null($reports))
	{
		error_log("(ERROR) " . CSlaReport::$error);
		exit(1);
	}

	foreach ($reports as $tld => $report)
	{
		printf("===================================== %s =====================================\n", $tld);
		echo $report;
		printf("================================================================================\n");
	}
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
	echo "        {$script} [--help] [--debug] [--stats] --server-id <server_id> --tld <tld> --year <year> --month <month>\n";
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

	if (is_null($error_message))
	{
		exit(0);
	}
	else
	{
		error_log("(ERROR) {$error_message}");
		exit(1);
	}
}
