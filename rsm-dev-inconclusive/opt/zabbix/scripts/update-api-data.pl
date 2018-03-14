#!/usr/bin/perl

BEGIN
{
	our $MYDIR = $0; $MYDIR =~ s,(.*)/.*,$1,; $MYDIR = '.' if ($MYDIR eq $0);
}
use lib $MYDIR;

use strict;
use warnings;
use RSM;
use RSMSLV;
use TLD_constants qw(:ec :api :items);
use ApiHelper;
use Parallel;

use constant JSON_RDDS_SUBSERVICE => 'subService';
use constant JSON_RDDS_43 => 'RDDS43';
use constant JSON_RDDS_80 => 'RDDS80';

use constant JSON_VALUE_UP => 'Up';
use constant JSON_VALUE_DOWN => 'Down';
use constant JSON_VALUE_ALARMED_YES => 'Yes';
use constant JSON_VALUE_ALARMED_NO => 'No';
use constant JSON_VALUE_ALARMED_DISABLED => 'Disabled';

use constant JSON_OBJECT_NORESULT_PROBE => {
	'status'	=> 'No result'
};

use constant CONFIGVALUE_DNS_UDP_RTT_HIGH_ITEMID	=> 100011;	# itemid of rsm.configvalue[RSM.DNS.UDP.RTT.HIGH] item

use constant AUDIT_RESOURCE_INCIDENT => 32;

use constant MAX_CONTINUE_PERIOD => 30;	# minutes (NB! make sure to update this number in the help message)

sub get_history_by_itemid($$$);
sub get_historical_value_by_time($$);
sub fill_test_data_dns($$$);
sub fill_test_data_rdds($$);
sub match_clocks_with_results($$);

parse_opts('tld=s', 'service=s', 'period=n', 'from=n', 'continue!', 'print-period!', 'ignore-file=s', 'probe=s', 'limit=n', 'maxproc=n');

# do not write any logs
setopt('nolog');

exit_if_running();

if (opt('debug'))
{
	dbg("command-line parameters:");
	dbg("$_ => ", getopt($_)) foreach (optkeys());
}

__validate_input();	# needs to be connected to db

if (!opt('dry-run') && (my $error = rsm_targets_prepare(AH_TMP_DIR, AH_BASE_DIR)))
{
	fail($error);
}

my $config = get_rsm_config();
set_slv_config($config);

my @server_keys = get_rsm_server_keys($config);

db_connect();

my $opt_from = getopt('from');

if (defined($opt_from))
{
	$opt_from = truncate_from($opt_from);	# use the whole minute
	dbg("option \"from\" truncated to the start of a minute: $opt_from") if ($opt_from != getopt('from'));
}

my %services;
if (opt('service'))
{
	$services{lc(getopt('service'))} = undef;
}
else
{
	foreach my $service ('dns', 'dnssec', 'rdds', 'epp')
	{
		$services{$service} = undef;
	}
}

my %ignore_hash;

if (opt('ignore-file'))
{
	my $ignore_file = getopt('ignore-file');

	my $handle;
	fail("cannot open ignore file \"$ignore_file\": $!") unless open($handle, '<', $ignore_file);

	chomp(my @lines = <$handle>);

	close($handle);

	%ignore_hash = map { $_ => 1 } @lines;
}

my $cfg_dns_delay = undef;
my $cfg_dns_minns;
my $cfg_dns_valuemaps;

# todo phase 1: changed from get_statusmaps('dns')
my $cfg_avail_valuemaps = get_avail_valuemaps();

foreach my $service (keys(%services))
{
	if ($service eq 'dns' || $service eq 'dnssec')
	{
		if (!$cfg_dns_delay)
		{
			$cfg_dns_delay = get_macro_dns_udp_delay();
			$cfg_dns_minns = get_macro_minns();
			$cfg_dns_valuemaps = get_valuemaps('dns');
		}

		$services{$service}{'delay'} = $cfg_dns_delay;
		$services{$service}{'minns'} = $cfg_dns_minns;
		$services{$service}{'valuemaps'} = $cfg_dns_valuemaps;
		$services{$service}{'key_status'} = 'rsm.dns.udp[{$RSM.TLD}]'; # 0 - down, 1 - up
		$services{$service}{'key_rtt'} = 'rsm.dns.udp.rtt[{$RSM.TLD},';
	}
	elsif ($service eq 'rdds')
	{
		$services{$service}{'delay'} = get_macro_rdds_delay();
		$services{$service}{'valuemaps'} = get_valuemaps($service);
		$services{$service}{'key_status'} = 'rsm.rdds[{$RSM.TLD}'; # 0 - down, 1 - up, 2 - only 43, 3 - only 80
		$services{$service}{'key_43_rtt'} = 'rsm.rdds.43.rtt[{$RSM.TLD}]';
		$services{$service}{'key_43_ip'} = 'rsm.rdds.43.ip[{$RSM.TLD}]';
		$services{$service}{'key_43_upd'} = 'rsm.rdds.43.upd[{$RSM.TLD}]';
		$services{$service}{'key_80_rtt'} = 'rsm.rdds.80.rtt[{$RSM.TLD}]';
		$services{$service}{'key_80_ip'} = 'rsm.rdds.80.ip[{$RSM.TLD}]';

	}
	elsif ($service eq 'epp')
	{
		$services{$service}{'delay'} = get_macro_epp_delay();
		$services{$service}{'valuemaps'} = get_valuemaps($service);
		$services{$service}{'key_status'} = 'rsm.epp[{$RSM.TLD},'; # 0 - down, 1 - up
		$services{$service}{'key_ip'} = 'rsm.epp.ip[{$RSM.TLD}]';
		$services{$service}{'key_rtt'} = 'rsm.epp.rtt[{$RSM.TLD},';
	}

	$services{$service}{'avail_key'} = "rsm.slv.$service.avail";
	$services{$service}{'rollweek_key'} = "rsm.slv.$service.rollweek";
}

my $now = time();

# in order to make sure all data is saved in Zabbix we move 1 minute back
my $max_till = max_avail_time($now) - 60;

my ($check_from, $check_till, $continue_file);

if (opt('continue'))
{
	$continue_file = ah_get_continue_file();

	if (! -e $continue_file)
	{
		if (!defined($check_from = __get_config_minclock()))
		{
			info("no data from Probe nodes yet");
			slv_exit(SUCCESS);
		}
	}
	else
	{
		my $handle;

		fail("cannot open continue file $continue_file\": $!") unless (open($handle, '<', $continue_file));

		chomp(my @lines = <$handle>);

		close($handle);

		my $ts = $lines[0];

		if (!$ts)
		{
			# last_update file exists but is empty, this means something went wrong
			fail("The last update file \"$continue_file\" exists but is empty.".
				" Please set the timestamp of the last update in it and run the script again.");
		}

		dbg("last update time: ", ts_full($ts));

		my $next_ts = $ts + 1;	# continue with the next minute
		$check_from = truncate_from($next_ts);

		if ($check_from != $next_ts)
		{
			wrn(sprintf("truncating last update value (%s) to %s", ts_str($ts), ts_str($check_from)));
		}
	}

	if ($check_from == 0)
	{
		fail("no data from probes in the database yet");
	}

	my $period = (opt('period') ? getopt('period') : MAX_CONTINUE_PERIOD);

	$check_till = $check_from + $period * 60 - 1;
	$check_till = $max_till if ($check_till > $max_till);
}
elsif (opt('from'))
{
	$check_from = $opt_from;
	$check_till = (opt('period') ? $check_from + getopt('period') * 60 - 1 : $max_till);
}
elsif (opt('period'))
{
	# only period specified
	$check_till = $max_till;
	$check_from = $check_till - getopt('period') * 60 + 1;
}

fail("cannot get the beginning of calculation period") unless(defined($check_from));
fail("cannot get the end of calculation period") unless(defined($check_till));

dbg("check_from:", ts_full($check_from), " check_till:", ts_full($check_till), " max_till:", ts_full($max_till));

if ($check_till < $check_from)
{
	info("no new data yet, we are up-to-date");
	slv_exit(SUCCESS);
}

if ($check_till > $max_till)
{
	my $left = ($check_till - $max_till) / 60;
	my $left_str;

	if ($left == 1)
	{
		$left_str = "1 minute";
	}
	else
	{
		$left_str = "$left minutes";
	}

	wrn(sprintf("the specified period (%s) is in the future, please wait for %s", selected_period($check_from, $check_till), $left_str));

	slv_exit(SUCCESS);
}

my ($from, $till) = get_real_services_period(\%services, $check_from, $check_till, 1);	# consider last cycle

if (opt('print-period'))
{
	info("selected period: ", selected_period($from, $till));
}
else
{
	dbg("real services period: ", selected_period($from, $till));
}

if (!$from)
{
	info("no full test periods within specified time range: ", selected_period($check_from, $check_till));

	slv_exit(SUCCESS);
}

set_max_children(getopt('maxproc')) if (opt('maxproc'));

# go through all the databases
foreach (@server_keys)
{
	$server_key = $_;

	dbg("getting probe statuses for period:", selected_period($from, $till));

	db_connect($server_key);

	my $dns_udp_rtt_high_history = get_history_by_itemid(CONFIGVALUE_DNS_UDP_RTT_HIGH_ITEMID, $from, $till);

	my $all_probes_ref = get_probes();

	if (opt('probe'))
	{
		my $probe = getopt('probe');
		my $valid = 0;

		foreach my $name (keys(%$all_probes_ref))
		{
			if ($name eq $probe)
			{
				$valid = 1;
				last;
			}
		}

		if ($valid == 0)
		{
			my $msg = "unknown probe \"$probe\"\n\nAvailable probes:\n";

			foreach my $name (keys(%$all_probes_ref))
			{
				$msg .= "  $name\n";
			}

			fail($msg);
		}

		my $temp = $all_probes_ref;

		undef($all_probes_ref);

		$all_probes_ref->{getopt('probe')} = $temp->{getopt('probe')};
	}

	my $probe_times_ref = get_probe_times($from, $till, $all_probes_ref);

	my $tlds_processed = 0;

	my $tlds_ref;
	if (opt('tld'))
	{
		if (tld_exists(getopt('tld')) == 0)
		{
			fail("TLD ", getopt('tld'), " does not exist.") if ($server_keys[-1] eq $server_key);
		}

		$tlds_ref = [ getopt('tld') ];
	}
	else
	{
		$tlds_ref = get_tlds();
	}

	db_disconnect();

	foreach (@$tlds_ref)
	{
		# NB! This is needed in order to set the value globally.
		$tld = $_;

		last if (opt('limit') && $tlds_processed == getopt('limit'));

		my $fork_successful = 0;
TRYFORK:
		my $pid = fork_without_pipe();

		if (!defined($pid))
		{
			# max children reached, make sure to handle_children()
			dbg("max children reached, please wait...");

			# sleep for 100 milliseconds
			select(undef, undef, undef, 0.1);
		}
		elsif ($pid)
		{
			# parent
			$fork_successful = 1;

			$tlds_processed++;
		}
		else
		{
			# child

			if (__tld_ignored($tld) == SUCCESS)
			{
				dbg("tld \"$tld\" found in IGNORE list");
				slv_exit(SUCCESS);
			}

			db_connect($server_key);

			my $ah_tld = ah_get_api_tld($tld);

			my $json_state_ref;

			$json_state_ref->{'tld'} = $tld;
			$json_state_ref->{'status'} = JSON_VALUE_UP;
			$json_state_ref->{'testedServices'} = {};

			# find out which services are disabled, for others get lastclock
			foreach my $service (keys(%services))
			{
				if (tld_service_enabled($tld, $service) != SUCCESS)
				{
					if (opt('dry-run'))
					{
						__prnt(uc($service), " DISABLED");
					}
					else
					{
						if (ah_save_alarmed($ah_tld, $service, JSON_VALUE_ALARMED_DISABLED) != AH_SUCCESS)
						{
							fail("cannot save alarmed: ", ah_get_error());
						}

						$json_state_ref->{'testedServices'}->{uc($service)} = JSON_OBJECT_DISABLED_SERVICE;
					}

					next;
				}

				my $lastclock_key = $services{$service}{'rollweek_key'};

				dbg("tld:$tld lastclock_key:$lastclock_key value_type:", ITEM_VALUE_TYPE_FLOAT);

				my $lastclock = get_lastclock($tld, $lastclock_key, ITEM_VALUE_TYPE_FLOAT);

				if ($lastclock == E_FAIL)
				{
					wrn(uc($service), ": configuration error, item $lastclock_key not found");

					if (opt('dry-run'))
					{
						__prnt(uc($service), " UP (configuration error)");
					}
					else
					{
						if (ah_save_alarmed($ah_tld, $service, JSON_VALUE_ALARMED_NO) != AH_SUCCESS)
						{
							fail("cannot save alarmed: ", ah_get_error());
						}

						$json_state_ref->{'testedServices'}->{uc($service)} = {
							'status' => JSON_VALUE_UP,
							'emergencyThreshold' => 0,
							'incidents' => []
						};
					}

					next;
				}

				if ($lastclock == 0)
				{
					wrn(uc($service), ": no rolling week data in the database yet");

					if (opt('dry-run'))
					{
						__prnt(uc($service), " UP (no rolling week data in the database)");
					}
					else
					{
						if (ah_save_alarmed($ah_tld, $service, JSON_VALUE_ALARMED_NO) != AH_SUCCESS)
						{
							fail("cannot save alarmed: ", ah_get_error());
						}

						$json_state_ref->{'testedServices'}->{uc($service)} = {
							'status' => JSON_VALUE_UP,
							'emergencyThreshold' => 0,
							'incidents' => []
						};
					}

					next;
				}

				dbg("lastclock:$lastclock");

				my $delay = $services{$service}{'delay'};
				my $service_from = $services{$service}{'from'};
				my $service_till = $services{$service}{'till'};
				my $avail_key = $services{$service}{'avail_key'};
				my $rollweek_key = $services{$service}{'rollweek_key'};

				if (!$service_from || !$service_till)
				{
					fail("INTERNAL ERROR, CANNOT CONTINUE WITHOUT DEFINED SERVICE CYCLE PERIOD");
				}

				my $hostid = get_hostid($tld);
				my $avail_itemid = get_itemid_by_hostid($hostid, $avail_key);

				if ($avail_itemid < 0)
				{
					if ($avail_itemid == E_ID_NONEXIST)
					{
						wrn("configuration error: service $service enabled but item \"$avail_key\" not found");
					}
					elsif ($avail_itemid == E_ID_MULTIPLE)
					{
						wrn("configuration error: multiple items with key \"$avail_key\" found");
					}
					else
					{
						wrn("cannot get ID of $service item ($avail_key): unknown error");
					}

					if (opt('dry-run'))
					{
						__prnt(uc($service), " UP (configuration error)");
					}
					else
					{
						if (ah_save_alarmed($ah_tld, $service, JSON_VALUE_ALARMED_NO) != AH_SUCCESS)
						{
							fail("cannot save alarmed: ", ah_get_error());
						}

						$json_state_ref->{'testedServices'}->{uc($service)} = {
							'status' => JSON_VALUE_UP,
							'emergencyThreshold' => 0,
							'incidents' => []
						};
					}

					next;
				}

				my $rollweek_itemid = get_itemid_by_hostid($hostid, $rollweek_key);

				if ($rollweek_itemid < 0)
				{
					if ($rollweek_itemid == E_ID_NONEXIST)
					{
						wrn("configuration error: service $service enabled but item \"$rollweek_key\" not found");
					}
					elsif ($rollweek_itemid == E_ID_MULTIPLE)
					{
						wrn("configuration error: multiple items with key \"$rollweek_key\" found");
					}
					else
					{
						wrn("cannot get ID of $service item ($rollweek_key): unknown error");
					}

					if (opt('dry-run'))
					{
						__prnt(uc($service), " UP (configuration error)");
					}
					else
					{
						if (ah_save_alarmed($ah_tld, $service, JSON_VALUE_ALARMED_NO) != AH_SUCCESS)
						{
							fail("cannot save alarmed: ", ah_get_error());
						}

						$json_state_ref->{'testedServices'}->{uc($service)} = {
							'status' => JSON_VALUE_UP,
							'emergencyThreshold' => 0,
							'incidents' => []
						};
					}

					next;
				}

				# we need down time in minutes, not percent, that's why we can't use "rsm.slv.$service.rollweek" value
				my ($rollweek_from, $rollweek_till) = get_rollweek_bounds();

				my $rollweek_incidents = get_incidents($avail_itemid, $rollweek_from, $rollweek_till);

				my $downtime = get_downtime($avail_itemid, $rollweek_from, $rollweek_till, 0, $rollweek_incidents);

				__prnt(uc($service), " period: ", selected_period($service_from, $service_till)) if (opt('dry-run') or opt('debug'));

				if (opt('dry-run'))
				{
					__prnt(uc($service), " downtime: $downtime (", ts_str($lastclock), ")");
				}
				else
				{
					ah_save_downtime($ah_tld, $service, $downtime, $lastclock);
				}

				dbg("getting current $service service availability (delay:$delay)");

				# get alarmed
				my $incidents = get_incidents($avail_itemid, $now);

				my $alarmed_status;

				if (scalar(@$incidents) != 0 && $incidents->[0]->{'false_positive'} == 0 &&
						!defined($incidents->[0]->{'end'}))
				{
					$alarmed_status = JSON_VALUE_ALARMED_YES;
					$json_state_ref->{'status'} = JSON_VALUE_DOWN;
				}
				else
				{
					$alarmed_status = JSON_VALUE_ALARMED_NO;
				}

				if (opt('dry-run'))
				{
					__prnt(uc($service), " alarmed:$alarmed_status");
				}
				else
				{
					if (ah_save_alarmed($ah_tld, $service, $alarmed_status, $lastclock) != AH_SUCCESS)
					{
						fail("cannot save alarmed: ", ah_get_error());
					}
				}

				my ($nsips_ref, $dns_items_ref, $rdds_dbl_items_ref, $rdds_str_items_ref, $epp_dbl_items_ref, $epp_str_items_ref);

				if ($service eq 'dns' || $service eq 'dnssec')
				{
					$nsips_ref = get_templated_nsips($tld, $services{$service}{'key_rtt'});
					$dns_items_ref = __get_dns_itemids($nsips_ref, $services{$service}{'key_rtt'}, $tld, getopt('probe'));
				}
				elsif ($service eq 'rdds')
				{
					$rdds_dbl_items_ref = __get_rdds_dbl_itemids($tld, getopt('probe'));
					$rdds_str_items_ref = __get_rdds_str_itemids($tld, getopt('probe'));
				}
				elsif ($service eq 'epp')
				{
					$epp_dbl_items_ref = __get_epp_dbl_itemids($tld, getopt('probe'));
					$epp_str_items_ref = __get_epp_str_itemids($tld, getopt('probe'));
				}

				my $rollweek;
				if (get_current_value($rollweek_itemid, ITEM_VALUE_TYPE_FLOAT, \$rollweek) != SUCCESS)
				{
					wrn(uc($service), ": no rolling week data in the database yet");

					if (opt('dry-run'))
					{
						__prnt(uc($service), " UP (no rolling week data in the database)");
					}
					else
					{
						if (ah_save_alarmed($ah_tld, $service, JSON_VALUE_ALARMED_NO) != AH_SUCCESS)
						{
							fail("cannot save alarmed: ", ah_get_error());
						}

						$json_state_ref->{'testedServices'}->{uc($service)} = {
							'status' => JSON_VALUE_UP,
							'emergencyThreshold' => 0,
							'incidents' => []
						};
					}

					next;
				}

				my $latest_avail_select = db_select(
						"select value from history_uint" .
							" where itemid=$avail_itemid" .
							" and clock<$service_till" .
						" order by clock desc limit 1");

				my $latest_avail_value = scalar(@{$latest_avail_select}) == 0 ?
						UP_INCONCLUSIVE_NO_DATA : $latest_avail_select->[0]->[0];

				if (opt('dry-run'))
				{
					unless (exists($cfg_avail_valuemaps->{int($latest_avail_value)}))
					{
						my $expected_list;

						while (my ($status, $description) = each(%{$cfg_avail_valuemaps}))
						{
							if (defined($expected_list))
							{
								$expected_list .= ", ";
							}
							else
							{
								$expected_list = "";
							}

							$expected_list .= "$status ($description)";
						}

						wrn("unknown availability result: $latest_avail_value (expected $expected_list)");
					}
				}

				$json_state_ref->{'testedServices'}->{uc($service)} = {
					'status' => get_result_string($cfg_avail_valuemaps, $latest_avail_value),
					'emergencyThreshold' => $rollweek,
					'incidents' => []
				};

				$incidents = get_incidents($avail_itemid, $service_from, $service_till);

				foreach (@$incidents)
				{
					my $eventid = $_->{'eventid'};
					my $event_start = $_->{'start'};
					my $event_end = $_->{'end'};
					my $false_positive = $_->{'false_positive'};

					my $start = $event_start;
					my $end = $event_end;

					if (defined($service_from) and $service_from > $event_start)
					{
						$start = $service_from;
					}

					if (defined($service_till))
					{
						if (not defined($event_end) or (defined($event_end) and $service_till < $event_end))
						{
							$end = $service_till;
						}
					}

					# get results within incidents
					my $rows_ref = db_select(
						"select value,clock".
						" from history_uint".
						" where itemid=$avail_itemid".
							" and ".sql_time_condition($start, $end).
						" order by clock");

					my @test_results;

					my $status_up = 0;
					my $status_down = 0;

					foreach my $row_ref (@$rows_ref)
					{
						my $value = $row_ref->[0];
						my $clock = $row_ref->[1];

						my $result;

						$result->{'tld'} = $tld;
						$result->{'cycleCalculationDateTime'} = $clock;

						# todo phase 1: make sure this uses avail valuemaps in phase1
						# todo: later rewrite to use valuemap ID from item
						$result->{'status'} = get_result_string($cfg_avail_valuemaps, $value);

						# We have the test resulting value (Up or Down) at "clock". Now we need to select the
						# time bounds (start/end) of all data points from all proxies.
						#
						#   +........................period (service delay)...........................+
						#   |                                                                         |
						# start                                 clock                                end
						#   |.....................................|...................................|
						#   0 seconds <--zero or more minutes--> 30                                  59
						#
						$result->{'start'} = $clock - $delay + RESULT_TIMESTAMP_SHIFT + 1; # we need to start at 0
						$result->{'end'} = $clock + RESULT_TIMESTAMP_SHIFT;

						if (opt('dry-run'))
						{
							unless (exists($cfg_avail_valuemaps->{int($value)}))
							{
								my $expected_list;

								while (my ($status, $description) = each(%{$cfg_avail_valuemaps}))
								{
									if (defined($expected_list))
									{
										$expected_list .= ", ";
									}
									else
									{
										$expected_list = "";
									}

									$expected_list .= "$status ($description)";
								}

								wrn("unknown availability result: $value (expected $expected_list)");
							}
						}

						push(@test_results, $result);
					}

					my $test_results_count = scalar(@test_results);

					if ($test_results_count == 0)
					{
						wrn("$service: no results within incident (id:$eventid clock:$event_start)");
						last;
					}

					if (opt('dry-run'))
					{
						__prnt(uc($service), " incident id:$eventid start:", ts_str($event_start), " end:" . ($event_end ? ts_str($event_end) : "ACTIVE") . " fp:$false_positive");
						__prnt(uc($service), " tests successful:$status_up failed:$status_down");
					}
					else
					{
						if (ah_save_incident($ah_tld, $service, $eventid, $event_start, $event_end, $false_positive, $lastclock) != AH_SUCCESS)
						{
							fail("cannot save incident: ", ah_get_error());
						}
					}

					my $values_from = $test_results[0]->{'start'};
					my $values_till = $test_results[$test_results_count - 1]->{'end'};

					if ($service eq 'dns' or $service eq 'dnssec')
					{
						my $minns = $services{$service}{'minns'};

						my $values_ref = __get_dns_test_values($dns_items_ref, $values_from, $values_till);

						# run through values from probes (ordered by clock)
						foreach my $probe (keys(%$values_ref))
						{
							my $nsips_ref = $values_ref->{$probe};

							dbg("probe:$probe");

							foreach my $nsip (keys(%$nsips_ref))
							{
								my $endvalues_ref = $nsips_ref->{$nsip};

								my ($ns, $ip) = split(',', $nsip);

								dbg("  values for $nsip:");

								my @clocks = keys(%{$endvalues_ref});
								my $matches = match_clocks_with_results(\@clocks, \@test_results);

								foreach my $clock (@clocks)
								{
									unless (exists($matches->{$clock}))
									{
										__no_status_result($service, $avail_key, $probe, $clock, $nsip);
										next;
									}

									my $tr_ref = $matches->{$clock};
									$tr_ref->{'probes'}->{$probe}->{'status'} = undef;	# the status is set later

									if (probe_offline_at($probe_times_ref, $probe, $clock) != 0)
									{
										$tr_ref->{'probes'}->{$probe}->{'status'} = PROBE_OFFLINE_STR;
										dbg("    ", ts_str($clock), ": OFFLINE");
									}
									else
									{
										push(@{$tr_ref->{'probes'}->{$probe}->{'details'}->{$ns}}, {'clock' => $clock, 'rtt' => $endvalues_ref->{$clock}, 'ip' => $ip});
										dbg("    ", ts_str($clock), ": ", $endvalues_ref->{$clock});
									}
								}
							}
						}

						# add probes that are missing results
						foreach my $probe (keys(%$all_probes_ref))
						{
							foreach my $tr_ref (@test_results)
							{
								next if (exists($tr_ref->{'probes'}->{$probe}));
								$tr_ref->{'probes'}->{$probe} = JSON_OBJECT_NORESULT_PROBE;
							}
						}

						# get results from probes: number of working Name Servers
						my $itemids_ref = __get_status_itemids($tld, $services{$service}{'key_status'});
						my $statuses_ref = __get_probe_statuses($itemids_ref, $values_from, $values_till);

						foreach my $tr_ref (@test_results)
						{
							# set status
							my $tr_start = $tr_ref->{'start'};
							my $tr_end = $tr_ref->{'end'};

							delete($tr_ref->{'start'});
							delete($tr_ref->{'end'});

							$tr_ref->{'service'} = uc($service);

							$tr_ref->{'testedInterface'} = [
								{
									'interface'	=> uc($service),
									'probes'	=> []
								}
							];

							my $probes_ref = $tr_ref->{'probes'};
							foreach my $probe (keys(%$probes_ref))
							{
								foreach my $status_ref (@{$statuses_ref->{$probe}})
								{
									next if ($status_ref->{'clock'} < $tr_start);
									last if ($status_ref->{'clock'} > $tr_end);

									if (not defined($probes_ref->{$probe}->{'status'}))
									{
										$probes_ref->{$probe}->{'status'} = ($status_ref->{'value'} >= $minns ? "Up" : "Down");
									}
								}

								my $probe_ref = {
									'city'		=> $probe,
									'status'	=> $probes_ref->{$probe}->{'status'},
									'testData'	=> []
								};

								if (exists($probes_ref->{$probe}->{'details'}))
								{
									fill_test_data_dns($probes_ref->{$probe}->{'details'},
										$probe_ref->{'testData'},
										$dns_udp_rtt_high_history);
								}

								push(@{$tr_ref->{'testedInterface'}->[0]->{'probes'}}, $probe_ref);
							}

							delete($tr_ref->{'probes'});

							if (opt('dry-run'))
							{
								__prnt_json($tr_ref);
							}
							else
							{
								if (ah_save_measurement($ah_tld, $service, $eventid, $event_start, $tr_ref, $tr_ref->{'cycleCalculationDateTime'}) != AH_SUCCESS)
								{
									fail("cannot save incident: ", ah_get_error());
								}
							}
						}
					}
					elsif ($service eq 'rdds')
					{
						my $values_ref = __get_rdds_test_values($rdds_dbl_items_ref, $rdds_str_items_ref, $values_from, $values_till);

						# run through values from probes (ordered by clock)
						foreach my $probe (keys(%$values_ref))
						{
							my $subservices_ref = $values_ref->{$probe};

							dbg("probe:$probe");

							foreach my $subservice (keys(%$subservices_ref))
							{
								my $test_result_index = 0;

								my @clocks = ();

								foreach my $endvalues_ref (@{$subservices_ref->{$subservice}})
								{
									push(@clocks, $endvalues_ref->{'clock'});
								}

								my $matches = match_clocks_with_results(\@clocks, \@test_results);

								foreach my $endvalues_ref (@{$subservices_ref->{$subservice}})
								{
									my $clock = $endvalues_ref->{'clock'};

									unless (exists($matches->{$clock}))
									{
										__no_status_result($subservice, $avail_key, $probe, $clock);
										next;
									}

									my $tr_ref = $matches->{$clock};
									$tr_ref->{+JSON_RDDS_SUBSERVICE}->{$subservice}->{$probe}->{'status'} = undef;	# the status is set later

									if (probe_offline_at($probe_times_ref, $probe, $clock) != 0)
									{
										$tr_ref->{+JSON_RDDS_SUBSERVICE}->{$subservice}->{$probe}->{'status'} = PROBE_OFFLINE_STR;
									}
									else
									{
										push(@{$tr_ref->{+JSON_RDDS_SUBSERVICE}->{$subservice}->{$probe}->{'details'}}, $endvalues_ref);
									}
								}
							}
						}

						# add probes that are missing results
						foreach my $probe (keys(%$all_probes_ref))
						{
							foreach my $tr_ref (@test_results)
							{
								my $subservices_ref = $tr_ref->{+JSON_RDDS_SUBSERVICE};

								foreach my $subservice (keys(%$subservices_ref))
								{
									next if (exists($subservices_ref->{$subservice}->{$probe}));
									$subservices_ref->{$subservice}->{$probe} = JSON_OBJECT_NORESULT_PROBE;
								}
							}
						}

						# get results from probes: working services (rdds43, rdds80)
						my $itemids_ref = __get_status_itemids($tld, $services{$service}{'key_status'});
						my $statuses_ref = __get_probe_statuses($itemids_ref, $values_from, $values_till);

						foreach my $tr_ref (@test_results)
						{
							# set status
							my $tr_start = $tr_ref->{'start'};
							my $tr_end = $tr_ref->{'end'};

							delete($tr_ref->{'start'});
							delete($tr_ref->{'end'});

							$tr_ref->{'service'} = uc($service);

							my $rdds43_ref = {
								'interface'	=> JSON_RDDS_43,
								'probes'	=> []
							};

							my $rdds80_ref = {
								'interface'	=> JSON_RDDS_80,
								'probes'	=> []
							};

							my $subservices_ref = $tr_ref->{+JSON_RDDS_SUBSERVICE};

							foreach my $subservice (keys(%$subservices_ref))
							{
								my $probes_ref = $subservices_ref->{$subservice};

								foreach my $probe (keys(%$probes_ref))
								{
									foreach my $status_ref (@{$statuses_ref->{$probe}})
									{
										next if ($status_ref->{'clock'} < $tr_start);
										last if ($status_ref->{'clock'} > $tr_end);

										if (not defined($probes_ref->{$probe}->{'status'}))
										{
											my $service_only = ($subservice eq JSON_RDDS_43 ? 2 : 3); # 0 - down, 1 - up, 2 - only 43, 3 - only 80

											$probes_ref->{$probe}->{'status'} = (($status_ref->{'value'} == 1 or $status_ref->{'value'} == $service_only) ? "Up" : "Down");
										}
									}

									my $probe_ref = {
										'city'		=> $probe,
										'status'	=> $probes_ref->{$probe}->{'status'},
										'testData'	=> []
									};

									if (exists($probes_ref->{$probe}->{'details'}))
									{
										fill_test_data_rdds($probes_ref->{$probe}->{'details'},
											$probe_ref->{'testData'});
									}

									push(@{($subservice eq JSON_RDDS_43 ? $rdds43_ref : $rdds80_ref)->{'probes'}}, $probe_ref);
								}
							}

							$tr_ref->{'testedInterface'} = [
								$rdds43_ref,
								$rdds80_ref
							];

							delete($tr_ref->{+JSON_RDDS_SUBSERVICE});

							if (opt('dry-run'))
							{
								__prnt_json($tr_ref);
							}
							else
							{
								if (ah_save_measurement($ah_tld, $service, $eventid, $event_start, $tr_ref, $tr_ref->{'cycleCalculationDateTime'}) != AH_SUCCESS)
								{
									fail("cannot save incident: ", ah_get_error());
								}
							}
						}
					}
					elsif ($service eq 'epp')
					{
						dbg("EPP results calculation is not implemented yet");

						my $values_ref = __get_epp_test_values($epp_dbl_items_ref, $epp_str_items_ref, $values_from, $values_till);

						foreach my $probe (keys(%$values_ref))
						{
							my $endvalues_ref = $values_ref->{$probe};

							my @clocks = keys(%{$endvalues_ref});
							my $matches = match_clocks_with_results(\@clocks, \@test_results);

							foreach my $clock (@clocks)
							{
								unless (exists($matches->{$clock}))
								{
									__no_status_result($service, $avail_key, $probe, $clock);
									next;
								}

								my $tr_ref = $matches->{$clock};
								$tr_ref->{'probes'}->{$probe}->{'status'} = undef;	# the status is set later

								if (probe_offline_at($probe_times_ref, $probe, $clock) != 0)
								{
									$tr_ref->{'probes'}->{$probe}->{'status'} = PROBE_OFFLINE_STR;
								}
								else
								{
									$tr_ref->{'probes'}->{$probe}->{'details'}->{$clock} = $endvalues_ref->{$clock};
								}
							}
						}

						# add probes that are missing results
						foreach my $probe (keys(%$all_probes_ref))
						{
							foreach my $tr_ref (@test_results)
							{
								next if (exists($tr_ref->{'probes'}->{$probe}));
								$tr_ref->{'probes'}->{$probe} = JSON_OBJECT_NORESULT_PROBE;
							}
						}

						# get results from probes: EPP down (0) or up (1)
						my $itemids_ref = __get_status_itemids($tld, $services{$service}{'key_status'});
						my $statuses_ref = __get_probe_statuses($itemids_ref, $values_from, $values_till);

						foreach my $tr_ref (@test_results)
						{
							# set status
							my $tr_start = $tr_ref->{'start'};
							my $tr_end = $tr_ref->{'end'};

							delete($tr_ref->{'start'});
							delete($tr_ref->{'end'});

							my $probes_ref = $tr_ref->{'probes'};

							foreach my $probe (keys(%$probes_ref))
							{
								foreach my $status_ref (@{$statuses_ref->{$probe}})
								{
									next if ($status_ref->{'clock'} < $tr_start);
									last if ($status_ref->{'clock'} > $tr_end);

									if (not defined($probes_ref->{$probe}->{'status'}))
									{
										$probes_ref->{$probe}->{'status'} = ($status_ref->{'value'} == 1 ? "Up" : "Down");
									}
								}
							}

							if (opt('dry-run'))
							{
								__prnt_json($tr_ref);
							}
							else
							{
								if (ah_save_measurement($ah_tld, $service, $eventid, $event_start, $tr_ref, $tr_ref->{'cycleCalculationDateTime'}) != AH_SUCCESS)
								{
									fail("cannot save incident: ", ah_get_error());
								}
							}
						}
					}
					else
					{
						fail("THIS SHOULD NEVER HAPPEN (unknown service \"$service\")");
					}
				} # foreach (@$incidents)

				foreach (@{$rollweek_incidents})
				{
					my $eventid = $_->{'eventid'};
					my $event_start = $_->{'start'};
					my $event_end = $_->{'end'};
					my $false_positive = $_->{'false_positive'};

					push(@{$json_state_ref->{'testedServices'}->{uc($service)}->{'incidents'}},
						ah_create_incident_json($eventid, $event_start, $event_end, $false_positive));
				}
			} # foreach my $service

			if (ah_save_state($ah_tld, $json_state_ref) != AH_SUCCESS)
			{
				fail("cannot save TLD state: ", ah_get_error());
			}

			# child exit
			slv_exit(SUCCESS);
		}

		# parent process, fork() either successful or failed
		handle_children();

		goto TRYFORK if ($fork_successful == 0);

		last if (opt('tld'));

	} # for each TLD

	# unset TLD (for the logs)
	undef($tld);

	# wait till children finish
	while (children_running() > 0)
	{
		handle_children();
	}

	db_connect($server_key);

	if (!opt('dry-run') && !opt('tld'))
	{
		__update_false_positives();
	}

	db_disconnect();
} # foreach (@server_keys)
undef($server_key);

if (defined($continue_file) and not opt('dry-run'))
{
	# todo phase 1: introduced new function ah_save_continue_file to get around tmp/target dir
	unless (ah_save_continue_file($till) == SUCCESS)
	{
		wrn("cannot save continue file \"$continue_file\": $!");
	}

	dbg("last update: ", ts_str($till));
}

if (!opt('dry-run') && (my $error = rsm_targets_apply()))
{
	fail($error);
}

slv_exit(SUCCESS);

# gets the history of item for a given period
sub get_history_by_itemid($$$)
{
	my $itemid = shift;
	my $timestamp_from = shift;
	my $timestamp_till = shift;

	# we need previous value to have at the time of @timestamp_from
	my $rows_ref = db_select("select delay from items where itemid=$itemid");

	$timestamp_from -= $rows_ref->[0]->[0];

	return db_select(
			"select clock,value" .
			" from history_uint" .
			" where itemid=$itemid" .
				" and " . sql_time_condition($timestamp_from, $timestamp_till) .
			" order by clock");
}

# gets the value of item at a given timestamp
sub get_historical_value_by_time($$)
{
	my $history = shift;
	my $timestamp = shift;

	# TODO implement binary search

	my $value;

	foreach my $row (@{$history})
	{
		last if ($timestamp < $row->[0]);	# stop iterating if history clock overshot the timestamp
	}
	continue
	{
		$value = $row->[1];	# keep the value preceeding overshooting
	}

	fail("timestamp $timestamp is out of bounds of selected historical data range") unless (defined($value));

	return $value;
}

sub fill_test_data_dns($$$)
{
	my $src = shift;
	my $dst = shift;
	my $hist = shift;

	foreach my $ns (keys(%{$src}))
	{
		my $test_data_ref = {
			'target'	=> $ns,
			'status'	=> undef,
			'metrics'	=> []
		};

		foreach my $test (@{$src->{$ns}})
		{
			dbg("ns:$ns ip:$test->{'ip'} clock:", $test->{'clock'} // "UNDEF", " rtt:", $test->{'rtt'} // "UNDEF");

			# if Name Server has no data yet clock and rtt should be both undefined
			if (defined($test->{'rtt'}) && !defined($test->{'clock'}) ||
					defined($test->{'clock'}) && !defined($test->{'rtt'}))
			{
				fail("Gleb/dimir were wrong, DNS test clock and rtt can be undefined independently");
			}

			my $metric = {
				'testDateTime'	=> $test->{'clock'},
				'targetIP'	=> $test->{'ip'}
			};

			if (!defined($test->{'rtt'}))
			{
				$metric->{'rtt'} = undef;
				$metric->{'result'} = 'no data';
			}
			elsif (substr($test->{'rtt'}, 0, 1) eq "-")
			{
				$metric->{'rtt'} = undef;
				$metric->{'result'} = $test->{'rtt'};

				$test_data_ref->{'status'} = "Down";
			}
			else
			{
				$metric->{'rtt'} = $test->{'rtt'};
				$metric->{'result'} = "ok";

				# skip threshold check if NS is already known to be down
				unless (defined($test_data_ref->{'status'}) && $test_data_ref->{'status'} eq "Down")
				{
					$test_data_ref->{'status'} =
							($test->{'rtt'} > get_historical_value_by_time($hist,
									$metric->{'testDateTime'}) ? "Down" : "Up");
				}
			}

			push(@{$test_data_ref->{'metrics'}}, $metric);
		}

		$test_data_ref->{'status'} //= "No result";

		push(@{$dst}, $test_data_ref);
	}
}

sub fill_test_data_rdds($$)
{
	my $src = shift;
	my $dst = shift;

	# sanity check, RDDS test data with more than one metric signifies data consistency problems upstream
	if (scalar(@{$src}) > 1)
	{
		use Data::Dumper;

		fail("Unexpected RDDS test data having more than one metric:\n", Dumper($src));
	}

	my $test_data_ref = {
		'target'	=> undef,
		'status'	=> undef,
		'metrics'	=> []
	};

	if (scalar(@{$src}) == 0)
	{
		my $metric = {
			'testDateTime'	=> undef,
			'targetIP'	=> undef,
			'rtt'		=> undef,
			'result'	=> 'no data'
		};

		push(@{$test_data_ref->{'metrics'}}, $metric);
	}
	else
	{
		my $test = $src->[0];

		my $metric = {
			'testDateTime'	=> $test->{'clock'},
			'targetIP'	=> exists($test->{'ip'}) ? $test->{'ip'} : undef
		};

		if (!defined($test->{'rtt'}))
		{
			$metric->{'rtt'} = undef;
			$metric->{'result'} = 'no data';
		}
		elsif (substr($test->{'rtt'}, 0, length(ZBX_EC_INTERNAL)) eq ZBX_EC_INTERNAL ||
				substr($test->{'rtt'}, 0, length(ZBX_EC_RDDS43_RES_NOREPLY)) eq ZBX_EC_RDDS43_RES_NOREPLY ||
				substr($test->{'rtt'}, 0, length(ZBX_EC_RDDS80_RES_NOREPLY)) eq ZBX_EC_RDDS80_RES_NOREPLY)
		{
			$test_data_ref->{'status'} = "Up";

			$metric->{'rtt'} = undef;
			$metric->{'result'} = $test->{'rtt'};
		}
		elsif (substr($test->{'rtt'}, 0, 1) eq "-")
		{
			$test_data_ref->{'status'} = "Down";

			$metric->{'rtt'} = undef;
			$metric->{'result'} = $test->{'rtt'};
		}
		else
		{
			$test_data_ref->{'status'} = "Up";

			$metric->{'rtt'} = $test->{'rtt'};
			$metric->{'result'} = "ok";
		}

		push(@{$test_data_ref->{'metrics'}}, $metric);
	}

	$test_data_ref->{'status'} //= "No result";

	push(@{$dst}, $test_data_ref);
}

# matches clocks with tests they correspond to (assuming that tests are sorted by time)
sub match_clocks_with_results($$)
{
	my $clocks = shift();
	my $test_results = shift();

	my $matches = {};
	my $index = 0;
	my $total = scalar(@{$test_results});

	foreach my $clock (sort(@{$clocks}))
	{
		$index++ while ($index < $total && $clock > $test_results->[$index]->{'end'});
		last unless ($index < $total);
		next if ($clock < $test_results->[$index]->{'start'});
		$matches->{$clock} = $test_results->[$index];
	}

	return $matches;
}

# values are organized like this:
# {
#           'WashingtonDC' => {
#                               'ns1,192.0.34.201' => {
#                                                       '1418994681' => '-204.0000',
#                                                       '1418994621' => '-204.0000'
#                                                     },
#                               'ns2,2620:0:2d0:270::1:201' => {
#                                                                '1418994681' => '-204.0000',
#                                                                '1418994621' => '-204.0000'
#                                                              }
#                             },
# ...
sub __get_dns_test_values
{
	my $dns_items_ref = shift;
	my $start = shift;
	my $end = shift;

	my $result = {};

	# generate list if itemids
	my @itemids;
	push(@itemids, keys(%{$_})) foreach (values(%{$dns_items_ref}));

	if (scalar(@itemids) != 0)
	{
		my $rows_ref = db_select(
			"select itemid,value,clock".
			" from history".
			" where itemid in (" . join(',', @itemids) . ")".
				" and " . sql_time_condition($start, $end).
			" order by clock");

		foreach my $row_ref (@$rows_ref)
		{
			my $itemid = $row_ref->[0];
			my $value = $row_ref->[1];
			my $clock = $row_ref->[2];

			my ($nsip, $probe);

			foreach my $pr (keys(%$dns_items_ref))
			{
				if (defined($dns_items_ref->{$pr}->{$itemid}))
				{
					$nsip = $dns_items_ref->{$pr}->{$itemid};
					$probe = $pr;

					last;
				}
			}

			unless (defined($nsip))
			{
				my $rows_ref = db_select("select key_ from items where itemid=$itemid");
				my $key = $rows_ref->[0]->[0];

				fail("internal error: Name Server,IP of item $key (itemid:$itemid) not found");
			}

			$result->{$probe}->{$nsip}->{$clock} = get_detailed_result($services{'dns'}{'valuemaps'}, $value);
		}
	}

	return $result;
}

sub __find_probe_key_by_itemid
{
	my $itemid = shift;
	my $items_ref = shift;

	my ($probe, $key);
	my $last = 0;

	foreach my $pr (keys(%$items_ref))
	{
		my $itemids_ref = $items_ref->{$pr};

		foreach my $i (keys(%$itemids_ref))
		{
			if ($i == $itemid)
			{
				$probe = $pr;
				$key = $items_ref->{$pr}->{$i};
				$last = 1;
				last;
			}
		}
		last if ($last == 1);
	}

	return ($probe, $key);
}

# values are organized like this:
# {
#           'WashingtonDC' => {
#                               '80' => {
#                                         '1418994206' => {
#                                                           'ip' => '192.0.34.201',
#                                                           'rtt' => '127.0000'
#                                                         },
#                                         '1418994086' => {
#                                                           'ip' => '192.0.34.201',
#                                                           'rtt' => '127.0000'
#                                                         },
#                               '43' => {
#                                         '1418994206' => {
#                                                           'ip' => '192.0.34.201',
#                                                           'rtt' => '127.0000'
#                                                         },
#                                         '1418994086' => {
#                                                           'ip' => '192.0.34.201',
#                                                           'rtt' => '127.0000'
#                                                         },
# ...
sub __get_rdds_test_values
{
	my $rdds_dbl_items_ref = shift;
	my $rdds_str_items_ref = shift;
	my $start = shift;
	my $end = shift;

	# generate list if itemids
	my $dbl_itemids_str = '';
	foreach my $probe (keys(%$rdds_dbl_items_ref))
	{
		my $itemids_ref = $rdds_dbl_items_ref->{$probe};

		foreach my $itemid (keys(%$itemids_ref))
		{
			$dbl_itemids_str .= ',' unless ($dbl_itemids_str eq '');
			$dbl_itemids_str .= $itemid;
		}
	}

	my $str_itemids_str = '';
	foreach my $probe (keys(%$rdds_str_items_ref))
	{
		my $itemids_ref = $rdds_str_items_ref->{$probe};

		foreach my $itemid (keys(%$itemids_ref))
		{
			$str_itemids_str .= ',' unless ($str_itemids_str eq '');
			$str_itemids_str .= $itemid;
		}
	}

	my %result;

	return \%result if ($dbl_itemids_str eq '' or $str_itemids_str eq '');

	# we need pre_result to combine IP and RTT to single test result
	my %pre_result;

	my $dbl_rows_ref = db_select("select itemid,value,clock from history where itemid in ($dbl_itemids_str) and " . sql_time_condition($start, $end). " order by clock");

	foreach my $row_ref (@$dbl_rows_ref)
	{
		my $itemid = $row_ref->[0];
		my $value = $row_ref->[1];
		my $clock = $row_ref->[2];

		my ($probe, $key) = __find_probe_key_by_itemid($itemid, $rdds_dbl_items_ref);

		fail("internal error: cannot get Probe-key pair by itemid:$itemid") unless (defined($probe) and defined($key));

		my $port = __get_rdds_port($key);
		my $type = __get_rdds_dbl_type($key);

		my $subservice;
		if ($port eq '43')
		{
			$subservice = JSON_RDDS_43;
		}
		elsif ($port eq '80')
		{
			$subservice = JSON_RDDS_80;
		}
		else
		{
			fail("unknown RDDS port in item (id:$itemid)");
		}

		$pre_result{$probe}->{$subservice}->{$clock}->{$type} = ($type eq 'rtt') ? get_detailed_result($services{'rdds'}{'valuemaps'}, $value) : int($value);
	}

	my $str_rows_ref = db_select("select itemid,value,clock from history_str where itemid in ($str_itemids_str) and " . sql_time_condition($start, $end). " order by clock");

	foreach my $row_ref (@$str_rows_ref)
	{
		my $itemid = $row_ref->[0];
		my $value = $row_ref->[1];
		my $clock = $row_ref->[2];

		my ($probe, $key) = __find_probe_key_by_itemid($itemid, $rdds_str_items_ref);

		fail("internal error: cannot get Probe-key pair by itemid:$itemid") unless (defined($probe) and defined($key));

		my $port = __get_rdds_port($key);
		my $type = __get_rdds_str_type($key);

		my $subservice;
                if ($port eq '43')
                {
                        $subservice = JSON_RDDS_43;
                }
                elsif ($port eq '80')
                {
                        $subservice = JSON_RDDS_80;
                }
                else
                {
                        fail("unknown RDDS port in item (id:$itemid)");
                }

		$pre_result{$probe}->{$subservice}->{$clock}->{$type} = $value;
	}

	foreach my $probe (keys(%pre_result))
	{
		foreach my $subservice (keys(%{$pre_result{$probe}}))
		{
			foreach my $clock (sort(keys(%{$pre_result{$probe}->{$subservice}})))	# must be sorted by clock
			{
				my $h;
				my $clock_ref = $pre_result{$probe}->{$subservice}->{$clock};
				foreach my $key (keys(%{$pre_result{$probe}->{$subservice}->{$clock}}))
				{
					$h->{$key} = $clock_ref->{$key};
				}
				$h->{'clock'} = $clock;

				push(@{$result{$probe}->{$subservice}}, $h);
			}
		}
	}

	return \%result;
}

# values are organized like this:
# {
#         'WashingtonDC' => {
#                 '1418994206' => {
#                               'ip' => '192.0.34.201',
#                               'login' => '127.0000',
#                               'update' => '366.0000'
#                               'info' => '366.0000'
#                 },
#                 '1418994456' => {
#                               'ip' => '192.0.34.202',
#                               'login' => '121.0000',
#                               'update' => '263.0000'
#                               'info' => '321.0000'
#                 },
# ...
sub __get_epp_test_values
{
	my $epp_dbl_items_ref = shift;
	my $epp_str_items_ref = shift;
	my $start = shift;
	my $end = shift;

	my %result;

	# generate list if itemids
	my $dbl_itemids_str = '';
	foreach my $probe (keys(%$epp_dbl_items_ref))
	{
		my $itemids_ref = $epp_dbl_items_ref->{$probe};

		foreach my $itemid (keys(%$itemids_ref))
		{
			$dbl_itemids_str .= ',' unless ($dbl_itemids_str eq '');
			$dbl_itemids_str .= $itemid;
		}
	}

	my $str_itemids_str = '';
	foreach my $probe (keys(%$epp_str_items_ref))
	{
		my $itemids_ref = $epp_str_items_ref->{$probe};

		foreach my $itemid (keys(%$itemids_ref))
		{
			$str_itemids_str .= ',' unless ($str_itemids_str eq '');
			$str_itemids_str .= $itemid;
		}
	}

	return \%result if ($dbl_itemids_str eq '' or $str_itemids_str eq '');

	my $dbl_rows_ref = db_select("select itemid,value,clock from history where itemid in ($dbl_itemids_str) and " . sql_time_condition($start, $end). " order by clock");

	foreach my $row_ref (@$dbl_rows_ref)
	{
		my $itemid = $row_ref->[0];
		my $value = $row_ref->[1];
		my $clock = $row_ref->[2];

		my ($probe, $key) = __find_probe_key_by_itemid($itemid, $epp_dbl_items_ref);

		fail("internal error: cannot get Probe-key pair by itemid:$itemid") unless (defined($probe) and defined($key));

		my $type = __get_epp_dbl_type($key);

		$result{$probe}->{$clock}->{$type} = get_detailed_result($services{'epp'}{'valuemaps'}, $value);
	}

	my $str_rows_ref = db_select("select itemid,value,clock from history_str where itemid in ($str_itemids_str) and " . sql_time_condition($start, $end). " order by clock");

	foreach my $row_ref (@$str_rows_ref)
	{
		my $itemid = $row_ref->[0];
		my $value = $row_ref->[1];
		my $clock = $row_ref->[2];

		my ($probe, $key) = __find_probe_key_by_itemid($itemid, $epp_str_items_ref);

		fail("internal error: cannot get Probe-key pair by itemid:$itemid") unless (defined($probe) and defined($key));

		my $type = __get_epp_str_type($key);

		$result{$probe}->{$clock}->{$type} = $value;
	}

	return \%result;
}

# return itemids grouped by Probes:
#
# {
#    'Amsterdam' => {
#         'itemid1' => 'ns2,2620:0:2d0:270::1:201',
#         'itemid2' => 'ns1,192.0.34.201'
#    },
#    'London' => {
#         'itemid3' => 'ns2,2620:0:2d0:270::1:201',
#         'itemid4' => 'ns1,192.0.34.201'
#    }
# }
sub __get_dns_itemids
{
	my $nsips_ref = shift; # array reference of NS,IP pairs
	my $key = shift;
	my $tld = shift;
	my $probe = shift;

	my @keys;
	push(@keys, "'" . $key . $_ . "]'") foreach (@$nsips_ref);

	my $keys_str = join(',', @keys);

	my $host_value = ($probe ? "$tld $probe" : "$tld %");

	my $rows_ref = db_select(
		"select h.host,i.itemid,i.key_".
		" from items i,hosts h".
		" where i.hostid=h.hostid".
			" and h.host like '$host_value'".
			" and i.templateid is not null".
			" and i.key_ in ($keys_str)");

	my %result;

	my $tld_length = length($tld) + 1; # white space
	foreach my $row_ref (@$rows_ref)
	{
		my $host = $row_ref->[0];
		my $itemid = $row_ref->[1];
		my $key = $row_ref->[2];

		# remove TLD from host name to get just the Probe name
		my $_probe = ($probe ? $probe : substr($host, $tld_length));

		$result{$_probe}->{$itemid} = get_nsip_from_key($key);
	}

	fail("cannot find items ($keys_str) at host ($tld *)") if (scalar(keys(%result)) == 0);

	return \%result;
}

sub __get_rdds_port
{
	my $key = shift;

	# rsm.rdds.43... <-- returns 43 or 80
	return substr($key, 9, 2);
}

sub __get_rdds_dbl_type
{
	my $key = shift;

	# rsm.rdds.43.rtt... rsm.rdds.43.upd[... <-- returns "rtt" or "upd"
	return substr($key, 12, 3);
}

sub __get_rdds_str_type
{
	# NB! This is done for consistency, perhaps in the future there will be more string items, not just "ip".
	return 'ip';
}

sub __get_epp_dbl_type
{
	my $key = shift;

	chop($key); # remove last char ']'

	# rsm.epp.rtt[{$RSM.TLD},login <-- returns "login" (other options: "update", "info")
        return substr($key, 23);
}

sub __get_epp_str_type
{
	# NB! This is done for consistency, perhaps in the future there will be more string items, not just "ip".
	return 'ip';
}

# return itemids of dbl items grouped by Probes:
#
# {
#    'Amsterdam' => {
#         'itemid1' => 'rsm.rdds.43.rtt...',
#         'itemid2' => 'rsm.rdds.43.upd...',
#         'itemid3' => 'rsm.rdds.80.rtt...'
#    },
#    'London' => {
#         'itemid4' => 'rsm.rdds.43.rtt...',
#         'itemid5' => 'rsm.rdds.43.upd...',
#         'itemid6' => 'rsm.rdds.80.rtt...'
#    }
# }
sub __get_rdds_dbl_itemids
{
	my $tld = shift;
	my $probe = shift;

	return __get_itemids_by_complete_key($tld, $probe, $services{'rdds'}{'key_43_rtt'}, $services{'rdds'}{'key_80_rtt'}, $services{'rdds'}{'key_43_upd'});
}

# return itemids of string items grouped by Probes:
#
# {
#    'Amsterdam' => {
#         'itemid1' => 'rsm.rdds.43.ip...',
#         'itemid2' => 'rsm.rdds.80.ip...'
#    },
#    'London' => {
#         'itemid3' => 'rsm.rdds.43.ip...',
#         'itemid4' => 'rsm.rdds.80.ip...'
#    }
# }
sub __get_rdds_str_itemids
{
	my $tld = shift;
	my $probe = shift;

	return __get_itemids_by_complete_key($tld, $probe, $services{'rdds'}{'key_43_ip'}, $services{'rdds'}{'key_80_ip'});
}

sub __get_epp_dbl_itemids
{
	my $tld = shift;
	my $probe = shift;

	return __get_itemids_by_incomplete_key($tld, $probe, $services{'epp'}{'key_rtt'});
}

sub __get_epp_str_itemids
{
	my $tld = shift;
	my $probe = shift;

	return __get_itemids_by_complete_key($tld, $probe, $services{'epp'}{'key_ip'});
}

# $keys_str - list of complete keys
sub __get_itemids_by_complete_key
{
	my $tld = shift;
	my $probe = shift;

	my $keys_str = "'" . join("','", @_) . "'";

	my $host_value = ($probe ? "$tld $probe" : "$tld %");

	my $rows_ref = db_select(
		"select h.host,i.itemid,i.key_".
		" from items i,hosts h".
		" where i.hostid=h.hostid".
			" and h.host like '$host_value'".
			" and i.key_ in ($keys_str)".
			" and i.templateid is not null");

	my %result;

	my $tld_length = length($tld) + 1; # white space
	foreach my $row_ref (@$rows_ref)
	{
		my $host = $row_ref->[0];
		my $itemid = $row_ref->[1];
		my $key = $row_ref->[2];

		# remove TLD from host name to get just the Probe name
		my $_probe = ($probe ? $probe : substr($host, $tld_length));

		$result{$_probe}->{$itemid} = $key;
	}

	fail("cannot find items ($keys_str) at host ($tld *)") if (scalar(keys(%result)) == 0);

	return \%result;
}

# call this function with list of incomplete keys after $tld, e. g.:
# __get_itemids_by_incomplete_key("example", "aaa[", "bbb[", ...)
sub __get_itemids_by_incomplete_key
{
	my $tld = shift;
	my $probe = shift;

	my $keys_cond = "(key_ like '" . join("%' or key_ like '", @_) . "%')";

	my $host_value = ($probe ? "$tld $probe" : "$tld %");

	my $rows_ref = db_select(
		"select h.host,i.itemid,i.key_".
		" from items i,hosts h".
		" where i.hostid=h.hostid".
			" and h.host like '$host_value'".
			" and i.templateid is not null".
			" and $keys_cond");

	my %result;

	my $tld_length = length($tld) + 1; # white space
	foreach my $row_ref (@$rows_ref)
	{
		my $host = $row_ref->[0];
		my $itemid = $row_ref->[1];
		my $key = $row_ref->[2];

		# remove TLD from host name to get just the Probe name
		my $_probe = ($probe ? $probe : substr($host, $tld_length));

		$result{$_probe}->{$itemid} = $key;
	}

	fail("cannot find items ('", join("','", @_), "') at host ($tld *)") if (scalar(keys(%result)) == 0);

	return \%result;
}

# returns hash reference of Probe=>itemid of specified key
#
# {
#    'Amsterdam' => 'itemid1',
#    'London' => 'itemid2',
#    ...
# }
sub __get_status_itemids
{
	my $tld = shift;
	my $key = shift;

	my $key_condition = (substr($key, -1) eq ']' ? "i.key_='$key'" : "i.key_ like '$key%'");

	my $sql =
		"select h.host,i.itemid".
		" from items i,hosts h".
		" where i.hostid=h.hostid".
			" and i.templateid is not null".
			" and $key_condition".
			" and h.host like '$tld %'".
		" group by h.host,i.itemid";

	my $rows_ref = db_select($sql);

	fail("no items matching '$key' found at host '$tld %'") if (scalar(@$rows_ref) == 0);

	my %result;

	my $tld_length = length($tld) + 1; # white space
	foreach my $row_ref (@$rows_ref)
	{
		my $host = $row_ref->[0];
		my $itemid = $row_ref->[1];

		# remove TLD from host name to get just the Probe name
		my $probe = substr($host, $tld_length);

		$result{$probe} = $itemid;
	}

	return \%result;
}

#
# {
#     'Probe1' =>
#     [
#         {
#             'clock' => 1234234234,
#             'value' => 'Up'
#         },
#         {
#             'clock' => 1234234294,
#             'value' => 'Up'
#         }
#     ],
#     'Probe2' =>
#     [
#         {
#             'clock' => 1234234234,
#             'value' => 'Down'
#         },
#         {
#             'clock' => 1234234294,
#             'value' => 'Up'
#         }
#     ]
# }
#
sub __get_probe_statuses
{
	my $itemids_ref = shift;
	my $from = shift;
	my $till = shift;

	my %result;

	# generate list if itemids
	my $itemids_str = '';
	foreach my $probe (keys(%$itemids_ref))
	{
		$itemids_str .= ',' unless ($itemids_str eq '');
		$itemids_str .= $itemids_ref->{$probe};
	}

	if ($itemids_str ne '')
	{
		my $rows_ref = db_select("select itemid,value,clock from history_uint where itemid in ($itemids_str) and " . sql_time_condition($from, $till). " order by clock");

		foreach my $row_ref (@$rows_ref)
		{
			my $itemid = $row_ref->[0];
			my $value = $row_ref->[1];
			my $clock = $row_ref->[2];

			my $probe;
			foreach my $pr (keys(%$itemids_ref))
			{
				my $i = $itemids_ref->{$pr};

				if ($i == $itemid)
				{
					$probe = $pr;

					last;
				}
			}

			fail("internal error: Probe of item (itemid:$itemid) not found") unless (defined($probe));

			push(@{$result{$probe}}, {'value' => $value, 'clock' => $clock});
		}
	}

	return \%result;
}

sub __prnt
{
	my $server_str = ($server_key ? "\@$server_key " : "");
	print($server_str, (defined($tld) ? "$tld: " : ''), join('', @_), "\n");
}

sub __prnt_json
{
	my $tr_ref = shift;

	if (opt('debug'))
	{
		dbg(ah_encode_pretty_json($tr_ref), "-----------------------------------------------------------");
	}
	else
	{
		__prnt(ts_str($tr_ref->{'clock'}), " ", $tr_ref->{'status'});
	}
}

sub __tld_ignored
{
	my $tld = shift;

	return SUCCESS if (exists($ignore_hash{$tld}));

	return E_FAIL;
}

sub __update_false_positives
{
	my $last_audit = ah_get_last_audit($server_key);

	# now check for possible false_positive change in front-end
	my $maxclock = 0;

	# should we update false positiveness later? (incident state file does not exist yet)
	my $later = 0;

	my $rows_ref = db_select(
		"select details,max(clock)".
		" from auditlog".
		" where resourcetype=".AUDIT_RESOURCE_INCIDENT.
			" and clock>$last_audit".
		" group by details");

	foreach my $row_ref (@$rows_ref)
	{
		my $details = $row_ref->[0];
		my $clock = $row_ref->[1];

		# ignore old "details" format (dropped in December 2014)
		next if ($details =~ '.*Incident \[.*\]');

		my $eventid = $details;
		$eventid =~ s/^([0-9]+): .*/$1/;

		$maxclock = $clock if ($clock > $maxclock);

		my $rows_ref2 = db_select("select objectid,clock,false_positive from events where eventid=$eventid");

		if (scalar(@$rows_ref2) != 1)
		{
			wrn("looks like event ID $eventid found in auditlog does not exist any more");
			next;
		}

		my $triggerid = $rows_ref2->[0]->[0];
		my $event_clock = $rows_ref2->[0]->[1];
		my $false_positive = $rows_ref2->[0]->[2];

		my ($tld, $service) = get_tld_by_trigger($triggerid);

		if (!$tld)
		{
			dbg("looks like trigger ID $triggerid found in auditlog does not exist any more");
			next;
		}

		dbg("auditlog: service:$service evnetid:$eventid start:[".ts_str($event_clock)."] changed:[".ts_str($clock)."] false_positive:$false_positive");

		unless (ah_save_false_positive($tld, $service, $eventid, $event_clock,
				$false_positive, $clock, \$later) == AH_SUCCESS)
		{
			if ($later == 1)
			{
				wrn(ah_get_error());
			}
			else
			{
				fail("cannot update false_positive state: ", ah_get_error());
			}
		}
	}

	# If the "later" flag is non-zero it means the incident for which we would like to change
	# false positiveness was not processed yet and there is no incident state file. We cannot
	# modify falsePositive file without making sure incident state file is also updated.
	if ($maxclock != 0 && $later == 0)
	{
		ah_save_audit($server_key, $maxclock);
	}
}

sub __validate_input
{
	if (opt('service'))
	{
		if (getopt('service') ne 'dns' and getopt('service') ne 'dnssec' and getopt('service') ne 'rdds' and getopt('service') ne 'epp')
		{
			print("Error: \"", getopt('service'), "\" - unknown service\n");
			usage();
		}
	}

	if (opt('tld') and opt('ignore-file'))
	{
		print("Error: options --tld and --ignore-file cannot be used together\n");
		usage();
	}

	if (opt('continue') and opt('from'))
        {
                print("Error: options --continue and --from cannot be used together\n");
                usage();
        }

	if (opt('probe'))
	{
		if (not opt('dry-run'))
		{
			print("Error: option --probe can only be used together with --dry-run\n");
			usage();
		}
        }
}

sub __sql_arr_to_str
{
	my $rows_ref = shift;

	my @arr;
	foreach my $row_ref (@$rows_ref)
        {
                push(@arr, $row_ref->[0]);
	}

	return join(',', @arr);
}

sub __no_status_result
{
	my $service = shift;
	my $avail_key = shift;
	my $probe = shift;
	my $clock = shift;
	my $details = shift;

	wrn("Service availability result is missing for ", uc($service), " test ", ($details ? "($details) " : ''),
		"performed at ", ts_str($clock), " ($clock) on probe $probe. This means the test period was not" .
		" handled by SLV availability cron job ($avail_key). This may happen e. g. if cron was not running" .
		" at some point. In order to fix this problem please run".
		"\n  $avail_key.pl --from $clock".
		"\nmanually to add missing service availability result.");
}

# todo phase 1: this function was modified to allow earlier run on freshly installed database
sub __get_config_minclock
{
	my $minclock;

	foreach (@server_keys)
	{
		$server_key = $_;
		db_connect($server_key);

		my $rows_ref = db_select(
				"select min(clock)".
				" from history_uint".
				" where itemid in".
					" (select itemid".
					" from items".
					" where key_='" . PROBE_KEY_ONLINE.
						"' and templateid is not null)");

		next unless (defined($rows_ref->[0]->[0]));

		my $newclock = int($rows_ref->[0]->[0]);
		dbg("min(clock): $newclock");

		$minclock = $newclock if (!defined($minclock) || $newclock < $minclock);
		db_disconnect();
	}
	undef($server_key);

	return undef if (!defined($minclock));

	dbg("oldest data found: ", ts_full($minclock));

	# todo phase 1: remove moving 1 day back, this was needed for Data Export, not SLA API!
	# move a day back since this is collected once a day
	#$minclock -= 86400;

	return truncate_from($minclock);
}

__END__

=head1 NAME

update-api-data.pl - save information about the incidents to a filesystem

=head1 SYNOPSIS

update-api-data.pl [--service <dns|dnssec|rdds|epp>] [--tld <tld>|--ignore-file <file>] [--from <timestamp>|--continue] [--print-period] [--period minutes] [--dry-run [--probe name]] [--warnslow <seconds>] [--debug] [--help]

=head1 OPTIONS

=over 8

=item B<--service> service

Process only specified service. Service must be one of: dns, dnssec, rdds or epp.

=item B<--tld> tld

Process only specified TLD. If not specified all TLDs will be processed.

This option cannot be used together with option --ignore-file.

=item B<--ignore-file> file

Specify file containing the list of TLDs that should be ignored. TLDs are specified one per line.

This option cannot be used together with option --tld.

=item B<--period> minutes

Specify number minutes of the period to handle during this run. The first cycle to handle can be specified
using options --from or --continue (continue from the last time when --continue was used) (see below).

=item B<--from> timestamp

Specify Unix timestamp within the oldest test cycle to handle in this run. You don't need to specify the
first second of the test cycle, any timestamp within it will work. Number of test cycles to handle within
this run can be specified using option --period otherwise all completed test cycles available in the
database up till now will be handled.

This option cannot be used together with option --continue.

=item B<--continue>

Continue calculation from the timestamp of the last run with --continue. In case of first run with
--continue the oldest available data will be used as starting point. You may specify the end point
of the period with --period option (see above). Default end point is as much data as available in the database
but not more than 30 minutes.

Note, that continue token is not updated if this option was specified together with --dry-run or when you use
--from option.

=item B<--print-period>

Print selected period on the screen.

=item B<--probe> name

Only calculate data from specified probe.

This option can only be used for debugging purposes and must be used together with option --dry-run .

=item B<--dry-run>

Print data to the screen, do not write anything to the filesystem.

=item B<--warnslow> seconds

Issue a warning in case an SQL query takes more than specified number of seconds. A floating-point number
is supported as seconds (i. e. 0.5, 1, 1.5 are valid).

=item B<--debug>

Run the script in debug mode. This means printing more information.

=item B<--help>

Print a brief help message and exit.

=back

=head1 DESCRIPTION

B<This program> will run through all the incidents found at optionally specified time bounds
and store details about each on the filesystem. This information will be used by external
program to provide it for users in convenient way.

=head1 EXAMPLES

./update-api-data.pl --tld example --period 10

This will update API data of the last 10 minutes of DNS, DNSSEC, RDDS and EPP services of TLD example.

=cut
