#!/usr/bin/perl

use strict;
use warnings;

use Path::Tiny;
use lib path($0)->parent->realpath()->stringify();

use Data::Dumper;

use Parallel::ForkManager;

use RSM;
use RSMSLV;
use TLD_constants qw(:api :config :groups :items);
use ApiHelper;

$Data::Dumper::Terse = 1;	# do not output names like "$VAR1 = "
$Data::Dumper::Pair = " : ";	# use separator instead of " => "
$Data::Dumper::Useqq = 1;	# use double quotes instead of single quotes
$Data::Dumper::Indent = 1;	# 1 provides less indentation instead of 2

use constant SLV_UNAVAILABILITY_LIMIT => 49;

use constant TARGET_PLACEHOLDER => 'TARGET_PLACEHOLDER';	# for non-DNS services

use constant MAX_PERIOD => 30 * 60;	# 30 minutes

use constant SUBSTR_KEY_LEN => 12;	# for logging

sub process_tld($$$$);
sub cycles_to_calculate($$$$$$$$);
sub get_lastvalues_from_db($$);
sub calculate_cycle($$$$$$$$);
sub get_interfaces($$$);
sub probe_online_at_init();
sub get_history_by_itemid($$$);
sub init_child_exit($);

parse_opts('tld=s', 'service=s', 'server-id=i', 'now=i', 'period=i', 'print-period!', 'max-children=i');

setopt('nolog');

usage() if (opt('help'));

exit_if_running();	# exit with 0 exit code

if (opt('debug'))
{
	dbg("command-line parameters:");
	dbg("$_ => ", getopt($_)) foreach (optkeys());
}

ah_set_debug(getopt('debug'));

my $config = get_rsm_config();

set_slv_config($config);

my @server_keys;

if (opt('server-id'))
{
	push(@server_keys, get_rsm_server_key(getopt('server-id')));
}
else
{
	@server_keys = get_rsm_server_keys($config);
}

validate_tld(getopt('tld'), \@server_keys) if (opt('tld'));
validate_service(getopt('service')) if (opt('service'));

my $real_now = time();
my $now = (getopt('now') // $real_now);

my $max_period = (opt('period') ? getopt('period') * 60 : MAX_PERIOD);

db_connect();

my $cfg_minonline = get_macro_dns_probe_online();
my $cfg_minns = get_macro_minns();

fail("number of required working Name Servers is configured as $cfg_minns") if (1 > $cfg_minns);

my %delays;
$delays{'dns'} = $delays{'dnssec'} = get_dns_udp_delay($now);
$delays{'rdds'} = get_rdds_delay($now);

db_disconnect();

my %service_keys = (
	'dns' => 'rsm.slv.dns.avail',
	'dnssec' => 'rsm.slv.dnssec.avail',
	'rdds' => 'rsm.slv.rdds.avail'
);

# keep to avoid reading multiple times
my $global_lastclock;

my %rtt_limits;

my $fm = new Parallel::ForkManager(opt('max-children') ? getopt('max-children') : 64);

init_child_exit($fm);

my $child_failed = 0;
my $signal_sent = 0;
my %tldmap;

foreach (@server_keys)
{
	$server_key = $_;

	# Last values from the "lastvalue" (uint, float) and "lastvalue_str" tables.
	#
	# {
	#     'tlds' => {
	#         tld => {
	#             service => {
	#                 'probes' => {
	#                     probe => {
	#                         itemid = {
	#                             'key' => key,
	#                             'value_type' => value_type,
	#                             'clock' => clock
	#                         }
	#                     }
	#                 }
	#             }
	#         }
	#     }
	# }
	my $lastvalues_db = {'tlds' => {}};

	my $lastvalues_cache;

	if (ah_get_recent_cache($server_key, \$lastvalues_cache) != AH_SUCCESS)
	{
		dbg("there's no recent measurements cache file yet, but no worries");
		$lastvalues_cache->{'tlds'} = {};
	}


	# initialize probe online cache
	probe_online_at_init();

	db_connect($server_key);
	get_lastvalues_from_db($lastvalues_db, \%delays);
	db_disconnect();

	$fm->run_on_wait( sub () { dbg("max children reached, please wait..."); } );

	# probes available for every service
	my %probes;

	foreach (sort(keys(%{$lastvalues_db->{'tlds'}})))
	{
		$tld = $_;	# global variable

		child_failed() if ($child_failed);

		my $pid = $fm->start();

		if ($pid == 0)
		{
			db_connect($server_key);
			process_tld($tld, \%probes, $lastvalues_db, $lastvalues_cache);
			db_disconnect();
			$fm->finish(SUCCESS);
			last;
		}
		else
		{
			$tldmap{$pid} = $tld;
			next;
		}
		
	}

	$fm->run_on_wait(undef);
	$fm->wait_all_children();

	if (!opt('dry-run') && !opt('now'))
	{
		if (ah_save_recent_cache($server_key, $lastvalues_cache) != AH_SUCCESS)
		{
			fail("cannot save recent measurements cache: ", ah_get_error());
		}
	}
}

sub process_tld($$$$)
{
	my $tld = shift;
	my $probes = shift;
	my $lastvalues_db = shift;
	my $lastvalues_cache = shift;

	foreach my $service (sort(keys(%{$lastvalues_db->{'tlds'}{$tld}})))
	{
		next if (opt('service') && $service ne getopt('service'));

		undef($global_lastclock);	# is used in the following function (should be used per service)

		my @cycles_to_calculate;

		# get actual cycle times to calculate
		if (cycles_to_calculate(
				$tld,
				$service,
				$delays{$service},
				$max_period,
				$service_keys{$service},
				$lastvalues_db->{'tlds'},
				$lastvalues_cache->{'tlds'},
				\@cycles_to_calculate) == E_FAIL)
		{
			next;
		}

		if (opt('debug'))
		{
			dbg("$service cycles to calculate: ", join(',', map {ts_str($_)} (@cycles_to_calculate)));
		}

		next if (scalar(@cycles_to_calculate) == 0);

		my $cycles_from = $cycles_to_calculate[0];
		my $cycles_till = $cycles_to_calculate[-1];

		next unless (tld_service_enabled($tld, $service, $cycles_from));

		if (opt('print-period'))
		{
			info("selected $service period: ", selected_period(
				$cycles_from,
				cycle_end($cycles_till, $delays{$service})
			));
		}

		my $interfaces_ref = get_interfaces($tld, $service, $now);

		$probes->{$service} = get_probes($service) unless (defined($probes->{$service}));

		if ($service eq 'dns')
		{
			# rtt limits only considered for DNS currently
			$rtt_limits{'dns'} = get_history_by_itemid(
				CONFIGVALUE_DNS_UDP_RTT_HIGH_ITEMID,
				$cycles_from,
				$cycles_till
			);
		}

		# these are cycles we are going to recalculate for this tld-service
		foreach my $clock (@cycles_to_calculate)
		{
			calculate_cycle(
				$tld,
				$service,
				$lastvalues_db->{'tlds'}{$tld}{$service}{'probes'},
				$clock,
				$delays{$service},
				$rtt_limits{$service},
				$probes->{$service},
				$interfaces_ref
			);
		}
	}
}

sub get_global_lastclock($$$)
{
	my $tld = shift;
	my $service_key = shift;
	my $delay = shift;

	my $lastclock;

	if (opt('now'))
	{
		$lastclock = cycle_start(getopt('now'), $delay);

		dbg("using specified last clock: ", ts_str($lastclock));

		return $lastclock;
	}

	# see if we have last_update.txt in SLA API directory

	my $continue_file = ah_get_continue_file();

	if (-e $continue_file)
	{
		my $error;

		if (read_file($continue_file, \$lastclock, \$error) != SUCCESS)
		{
			fail("cannot read file \"$continue_file\": $error");
		}

		while (chomp($lastclock)) {}

		dbg("using last clock from SLA API directory, file $continue_file: ", ts_str($lastclock));

		$lastclock++;

		return $lastclock;
	}

	# if not, get the oldest from the database

	$lastclock = get_oldest_clock($tld, $service_key, ITEM_VALUE_TYPE_UINT64);

	if (!defined($lastclock))
	{
		dbg("cannot yet calculate, item ", substr($service_key, 0, SUBSTR_KEY_LEN), "has no data in the database yet");
		return;
	}

	fail("unexpected error: item \"$service_key\" not found on TLD $tld") if ($lastclock == E_FAIL);

	dbg("using last clock from the database: ", ts_str($lastclock));

	return $lastclock;
}

sub add_cycles($$$$$$$$$$)
{
	my $tld = shift;
	my $service = shift;
	my $probe = shift;
	my $itemid = shift;
	my $lastclock_cache = shift;
	my $lastclock_db = shift;
	my $delay = shift;
	my $max_period = shift;
	my $cycles_ref = shift;
	my $lastvalues_cache = shift;

	my $max_clock = $lastclock_cache + $max_period;

	while ($lastclock_cache < $max_clock && $lastclock_cache <= $lastclock_db)
	{
		$cycles_ref->{$lastclock_cache} = 1;

		$lastvalues_cache->{$tld}{$service}{'probes'}{$probe}{$itemid}{'clock'} = $lastclock_cache;

		$lastclock_cache += $delay;
	}
}

#
# TODO: This function currently updates cache, which is not reflected in the name.
#
sub cycles_to_calculate($$$$$$$$)
{
	my $tld = shift;
	my $service = shift;
	my $delay = shift;
	my $max_period = shift;	# seconds
	my $service_key = shift;
	my $lastvalues_db = shift;
	my $lastvalues_cache = shift;
	my $cycles_ref = shift;	# result

	my %cycles;

	foreach my $probe (keys(%{$lastvalues_db->{$tld}{$service}{'probes'}}))
	{
		foreach my $itemid (keys(%{$lastvalues_db->{$tld}{$service}{'probes'}{$probe}}))
		{
			my $lastclock_db = cycle_start(
				$lastvalues_db->{$tld}{$service}{'probes'}{$probe}{$itemid}{'clock'},
				$delay
			);

			my $lastclock_cache;

			if (opt('now'))
			{
				# time bounds were specified on the command line
				$global_lastclock //= get_global_lastclock($tld, $service_key, $delay);

				$lastclock_cache = $global_lastclock;
			}
			elsif (!defined($lastvalues_cache->{$tld}{$service}{'probes'}{$probe}{$itemid}))
			{
				# this partilular item is not in cache yet, get the time starting point for it
				$global_lastclock //= get_global_lastclock($tld, $service_key, $delay);

				if (!defined($global_lastclock))
				{
					dbg("$service: no data in the database yet, so nothing to do");

					return E_FAIL;
				}

				$lastclock_cache = $global_lastclock;
			}
			else
			{
				$lastclock_cache = $lastvalues_cache->{$tld}{$service}{'probes'}{$probe}{$itemid}{'clock'};

				if ($lastclock_cache > $lastclock_db)
				{
					fail("dimir was wrong, item ($itemid) clock ($lastclock_cache)".
						" in cache can be newer than in database ($lastclock_db)");
				}

				dbg("using lastclock from cache: ", ts_str($lastclock_cache));

				# what's in the cache is already calculated
				$lastclock_cache += $delay;
			}

			if (opt('debug'))
			{
				my $key = substr($lastvalues_db->{$tld}{$service}{'probes'}{$probe}{$itemid}{'key'}, 0, SUBSTR_KEY_LEN) . '...';

				dbg("$probe [$key] itemid:$itemid lastclock in db: ", ts_str($lastclock_db));
			}

			add_cycles(
				$tld,
				$service,
				$probe,
				$itemid,
				$lastclock_cache,
				$lastclock_db,
				$delay,
				$max_period,
				\%cycles,
				$lastvalues_cache
			);

		}
	}



	@{$cycles_ref} = sort(keys(%cycles));

	return SUCCESS;
}

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
			" order by clock"
	);
}

# gets the value of item at a given timestamp
sub get_historical_value_by_time($$)
{
	my $history = shift;
	my $timestamp = shift;

	fail("internal error") unless ($timestamp);

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

sub get_service_from_probe_key($)
{
	my $key = shift;

	# remove possible "rsm."
	$key = substr($key, length("rsm.")) if (substr($key, 0, length("rsm.")) eq "rsm.");

	my $service;

	if (substr($key, 0, length("dns")) eq "dns")
	{
		$service = "dns";
	}
	elsif (substr($key, 0, length("rdds")) eq "rdds")
	{
		$service = "rdds";
	}
	elsif (substr($key, 0, length("rdap")) eq "rdap")
	{
		$service = "rdds";
	}

	return $service;
}

sub get_service_from_slv_key($)
{
	my $key = shift;

	# remove possible "rsm.slv."
	$key = substr($key, length("rsm.slv.")) if (substr($key, 0, length("rsm.slv.")) eq "rsm.slv.");

	my $service;

	if (substr($key, 0, length("dns.")) eq "dns.")
	{
		$service = "dns";
	}
	elsif (substr($key, 0, length("dnssec.")) eq "dnssec.")
	{
		$service = "dns";
	}
	elsif (substr($key, 0, length("rdds.")) eq "rdds.")
	{
		$service = "rdds";
	}
	else
	{
		fail("cannot extract service from item \"$key\"");
	}

	return $service;
}

sub get_lastvalues_from_db($$)
{
	my $lastvalues_db = shift;
	my $delays = shift;

	my $host_cond = '';

	if (opt('tld'))
	{
		$host_cond = " and (h.host like '".getopt('tld')." %' or h.host='".getopt('tld')."')";
	}

	# join lastvalue and lastvalue_str tables
	my $rows_ref = db_select(
		"select h.host,i.itemid,i.key_,i.value_type,l.clock".
		" from lastvalue l,items i,hosts h,hosts_groups g".
		" where g.hostid=h.hostid".
			" and h.hostid=i.hostid".
			" and i.itemid=l.itemid".
			" and i.type in (".ITEM_TYPE_SIMPLE.",".ITEM_TYPE_TRAPPER.")".
			" and i.status=".ITEM_STATUS_ACTIVE.
			" and h.status=".HOST_STATUS_MONITORED.
			" and (g.groupid=".TLD_PROBE_RESULTS_GROUPID." or g.groupid=".TLDS_GROUPID." and i.key_ like '%.avail')".
			$host_cond.
		" union ".
		"select h.host,i.itemid,i.key_,i.value_type,l.clock".
		" from lastvalue_str l,items i,hosts h,hosts_groups g".
		" where g.hostid=h.hostid".
			" and h.hostid=i.hostid".
			" and i.itemid=l.itemid".
			" and i.type in (".ITEM_TYPE_SIMPLE.",".ITEM_TYPE_TRAPPER.")".
			" and i.status=".ITEM_STATUS_ACTIVE.
			" and h.status=".HOST_STATUS_MONITORED.
			" and g.groupid=".TLD_PROBE_RESULTS_GROUPID.
			$host_cond
	);

	foreach my $row_ref (@{$rows_ref})
	{
		my $host = $row_ref->[0];
		my $itemid = $row_ref->[1];
		my $key = $row_ref->[2];
		my $value_type = $row_ref->[3];
		my $clock = $row_ref->[4];

		next if (substr($key, 0, length("rsm.dns.tcp")) eq "rsm.dns.tcp");

		my $index = index($host, ' ');	# "<TLD> <Probe>" separator

		my ($probe, $key_service);

		if ($index == -1)
		{
			# this host just represents TLD
			$tld = $host;	# $tld is global variable
			$probe = '';

			$key_service = get_service_from_slv_key($key);
		}
		else
		{
			$tld = substr($host, 0, $index);	# $tld is global variable
			$probe = substr($host, $index + 1);

			$key_service = get_service_from_probe_key($key);
		}

		foreach my $service ($key_service eq 'dns' ? ('dns', 'dnssec') : ($key_service))
		{
			$lastvalues_db->{'tlds'}{$tld}{$service}{'probes'}{$probe}{$itemid} = {
				'key' => $key,
				'value_type' => $value_type,
				'clock' => $clock
			};
		}
	}
}

sub fill_test_data($$$$)
{
	my $service = shift;
	my $src = shift;
	my $dst = shift;
	my $hist = shift;

	foreach my $ns (keys(%{$src}))
	{
		my $test_data_ref = {
			'target'	=> ($ns eq TARGET_PLACEHOLDER ? undef : $ns),
			'status'	=> undef,
			'metrics'	=> []
		};

		foreach my $item_ref (values(%{$src->{$ns}}))
		{
			foreach my $clock (sort(keys(%{$item_ref})))
			{
				my $test = $item_ref->{$clock};

				my $metric = {
					'testDateTime'	=> int($clock),
					'targetIP'	=> $test->{'ip'}
				};

				if (!defined($test->{'rtt'}))
				{
					$metric->{'rtt'} = undef;
					$metric->{'result'} = 'no data';
				}
				elsif (is_internal_error_desc($test->{'rtt'}))
				{
					$metric->{'rtt'} = undef;
					$metric->{'result'} = $test->{'rtt'};

					# don't override NS status with "Up" if NS is already known to be down
					if (!defined($test_data_ref->{'status'}) || $test_data_ref->{'status'} ne "Down")
					{
						$test_data_ref->{'status'} = "Up";
					}
				}
				elsif (is_service_error_desc($service, $test->{'rtt'}))
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
					if ($hist)
					{
						if  (!defined($test_data_ref->{'status'}) || $test_data_ref->{'status'} eq "Up")
						{
							$test_data_ref->{'status'} =
								($test->{'rtt'} > get_historical_value_by_time($hist,
									$metric->{'testDateTime'}) ? "Down" : "Up");
						}
					}
					else
					{
						$test_data_ref->{'status'} = "Up";
					}
				}

				push(@{$test_data_ref->{'metrics'}}, $metric);
			}

		}

		$test_data_ref->{'status'} //= AH_CITY_NO_RESULT;

		push(@{$dst}, $test_data_ref);
	}
}

#
# Probe status value cache. itemid - PROBE_KEY_ONLINE item
#
# {
#     probe => {
#         'itemid' => 1234,
#         'values' => {
#             'clock' => value,
#             ...
#         }
#     }
# }
#
my %probe_statuses;
sub probe_online_at_init()
{
	%probe_statuses = ();
}

sub probe_online_at($$)
{
	my $probe = shift;
	my $clock = shift;

	if (!defined($probe_statuses{$probe}{'itemid'}))
	{
		my $host = "$probe - mon";

		my $rows_ref = db_select(
			"select i.itemid,i.key_,h.host".
			" from items i,hosts h".
			" where i.hostid=h.hostid".
				" and h.host='$host'".
				" and i.key_='".PROBE_KEY_ONLINE."'"
		);

		fail("internal error: no \"$host\" item " . PROBE_KEY_ONLINE) unless (defined($rows_ref->[0]));

		$probe_statuses{$probe}{'itemid'} = $rows_ref->[0]->[0];
	}

	if (!defined($probe_statuses{$probe}{'values'}{$clock}))
	{
		my $rows_ref = db_select(
			"select value".
			" from " . history_table(ITEM_VALUE_TYPE_UINT64).
			" where itemid=" . $probe_statuses{$probe}{'itemid'}.
				" and clock=".$clock
		);

		# Online if no value in the database
		$probe_statuses{$probe}{'values'}{$clock} = (defined($rows_ref->[0]) ? $rows_ref->[0]->[0] : 1);
	}

	return $probe_statuses{$probe}{'values'}{$clock};
}

sub calculate_cycle($$$$$$$$)
{
	my $tld = shift;
	my $service = shift;
	my $probes_data = shift;
	my $cycle_clock = shift;
	my $delay = shift;
	my $rtt_limit = shift;
	my $probes_ref = shift;	# probes ('name' => 'hostid') available for this service
	my $interfaces_ref = shift;

	my $from = cycle_start($cycle_clock, $delay);
	my $till = cycle_end($cycle_clock, $delay);

	my $json = {'tld' => $tld, 'service' => $service, 'cycleCalculationDateTime' => $from};

	my %tested_interfaces;

#	print("$tld:\n");

	my $probes_with_results = 0;
	my $probes_with_positive = 0;
	my $probes_online = 0;

	foreach my $probe (keys(%{$probes_data}))
	{
		my (@itemids_uint, @itemids_float, @itemids_str);

		#
		# collect itemids, separate them by value_type to fetch values from according history table later
		#

		map {
			my $i = $probes_data->{$probe}{$_};

			if ($i->{'value_type'} == ITEM_VALUE_TYPE_UINT64)
			{
				push(@itemids_uint, $_);
			}
			elsif ($i->{'value_type'} == ITEM_VALUE_TYPE_FLOAT)
			{
				push(@itemids_float, $_);
			}
			elsif ($i->{'value_type'} == ITEM_VALUE_TYPE_STR)
			{
				push(@itemids_str, $_);
			}
		} (keys(%{$probes_data->{$probe}}));

		next if (@itemids_uint == 0);

		#
		# Fetch availability (Integer) values (on a TLD level and Probe level):
		#
		# TLD level example	: rsm.slv.dns.avail
		# Probe level example	: rsm.dns.udp
		#

		my $rows_ref = db_select(
			"select itemid,value".
			" from " . history_table(ITEM_VALUE_TYPE_UINT64).
			" where itemid in (" . join(',', @itemids_uint) . ")".
				" and " . sql_time_condition($from, $till)
		);

		# {
		#     ITEMID => value (int),
		# }
		my %values;

		map {push(@{$values{$_->[0]}}, int($_->[1]))} (@{$rows_ref});

		# skip cycles that do not have test result
		next if (scalar(keys(%values)) == 0);

#		print("  $probe:\n");

		my $service_up = 1;

		foreach my $itemid (keys(%values))
		{
			my $key = $probes_data->{$probe}{$itemid}{'key'};

			dbg("trying to identify interfaces of $service key \"$key\"...");

			if (substr($key, 0, length("rsm.rdds")) eq "rsm.rdds")
			{
				#
				# RDDS Availability on the Probe level. This item contains Availability of interfaces:
				#
				# - RDDS43
				# - RDDS80
				#

				foreach my $value (@{$values{$itemid}})
				{
					$service_up = 0 unless ($value == RDDS_UP);

					my $interface = AH_INTERFACE_RDDS43;

					if (!defined($tested_interfaces{$interface}{$probe}{'status'}) ||
						$tested_interfaces{$interface}{$probe}{'status'} eq AH_CITY_DOWN)
					{
						$tested_interfaces{$interface}{$probe}{'status'} =
							($value == RDDS_UP || $value == RDDS_43_ONLY ? AH_CITY_UP : AH_CITY_DOWN);
					}

					$interface = AH_INTERFACE_RDDS80;

					if (!defined($tested_interfaces{$interface}{$probe}{'status'}) ||
						$tested_interfaces{$interface}{$probe}{'status'} eq AH_CITY_DOWN)
					{
						$tested_interfaces{$interface}{$probe}{'status'} =
							($value == RDDS_UP || $value == RDDS_80_ONLY ? AH_CITY_UP : AH_CITY_DOWN);
					}
				}
			}
			elsif (substr($key, 0, length("rdap")) eq "rdap")
			{
				#
				# RDAP Availability on the Probe level. This item contains Availability of interfaces:
				#
				# - RDAP
				#

				my $interface = AH_INTERFACE_RDAP;

				my $city_status;

				foreach my $value (@{$values{$itemid}})
				{
					last if (defined($city_status) && $city_status eq AH_CITY_UP);

					$city_status = ($value == UP ? AH_CITY_UP : AH_CITY_DOWN);
				}

				$service_up = 0 if ($city_status eq AH_CITY_DOWN);

				$tested_interfaces{$interface}{$probe}{'status'} = $city_status;

			}
			elsif (substr($key, 0, length("rsm.dns.udp")) eq "rsm.dns.udp")
			{
				#
				# DNS Availability on the Probe level. This item contains Availability of interfaces:
				#
				# - DNS
				# - DNSSEC
				#

				my $interface;

				if ($service eq 'dnssec')
				{
					$interface = AH_INTERFACE_DNSSEC;
				}
				else
				{
					$interface = AH_INTERFACE_DNS;
				}

				my $city_status;

				foreach my $value (@{$values{$itemid}})
				{
					last if (defined($city_status) && $city_status eq AH_CITY_UP);

					$city_status = ($value >= $cfg_minns ? AH_CITY_UP : AH_CITY_DOWN);
				}

				$service_up = 0 if ($city_status eq AH_CITY_DOWN);

				$tested_interfaces{$interface}{$probe}{'status'} = $city_status;
			}
			elsif (substr($key, 0, length("rsm.slv.")) eq "rsm.slv.")
			{
				#
				# Service Availability on a TLD level.
				#

				my $sub_key = substr($key, length("rsm.slv."));

				my $index = index($sub_key, '.');	# <SERVICE>.avail

				fail("cannot extract Service from item \"$key\"") if ($index == -1);

				my $key_service = substr($sub_key, 0, $index);

				next unless ($key_service eq $service);

				fail("dimir was wrong: $service status can be re-defined") if (defined($json->{'status'}));

				if (scalar(@{$values{$itemid}}) != 1)
				{
					fail("dimir was wrong: item \"$key\" can contain more than 1 value at ",
						selected_period($from, $till), ": ", join(',', @{$values{$itemid}}));
				}

				if ($values{$itemid}->[0] == UP)
				{
					$json->{'status'} = 'Up';
				}
				elsif ($values{$itemid}->[0] == DOWN)
				{
					$json->{'status'} = 'Down';
				}
				elsif ($values{$itemid}->[0] == UP_INCONCLUSIVE_NO_DATA)
				{
					$json->{'status'} = 'Up-inconclusive-no-data';
				}
				elsif ($values{$itemid}->[0] == UP_INCONCLUSIVE_NO_PROBES)
				{
					$json->{'status'} = 'Up-inconclusive-no-probes';
				}
				else
				{
					fail("dimir was wrong: item \"$key\" can contain unexpected value \"", $values{$itemid}->[0] , "\"");
				}
			}
			else
			{
				fail("unexpected key \"$key\" when trying to identify Service interface");
			}
		}

		if ($service_up)
		{
			$probes_with_positive++;
		}

		$probes_with_results++;

		next if (@itemids_float == 0);

		#
		# Fetch RTT (Float) values (on Probe level).
		#
		# Note, for DNS service we will also collect target and IPs
		# because currently it is provided in RTT items, e. g.:
		#
		# rsm.dns.udp.rtt["ns1.example.com",1.2.3.4]
		#

		$rows_ref = db_select(
			"select itemid,value,clock".
			" from " . history_table(ITEM_VALUE_TYPE_FLOAT).
			" where itemid in (" . join(',', @itemids_float) . ")".
				" and " . sql_time_condition($from, $till)
		);

		# for convenience convert the data to format:
		#
		# {
		#     ITEMID => [
		#         {
		#             'value' => value (float: RTT),
		#             'clock' => clock
		#         }
		#     ]
		# }
		%values = ();

		map {push(@{$values{$_->[0]}}, {'value' => int($_->[1]), 'clock' => $_->[2]})} (@{$rows_ref});

		foreach my $itemid (keys(%values))
		{
			my $i = $probes_data->{$probe}{$itemid};

			foreach my $value_ref (@{$values{$itemid}})
			{
				my $interface;

				if ($service eq 'dnssec')
				{
					$interface = AH_INTERFACE_DNSSEC;
				}
				else
				{
					$interface = ah_get_interface($i->{'key'});
				}

				my ($target, $ip);
				if (substr($i->{'key'}, 0, length("rsm.dns.udp.rtt")) eq "rsm.dns.udp.rtt")
				{
					($target, $ip) = split(',', get_nsip_from_key($i->{'key'}));
				}
				else
				{
					# for non-DNS service "target" is NULL, but we
					# can't use it as hash key so we use placeholder
					$target = TARGET_PLACEHOLDER;
				}

				dbg("found $service RTT: ", $value_ref->{'value'}, " IP: ", ($ip // 'UNDEF'), " (target: $target)");

				$tested_interfaces{$interface}{$probe}{'testData'}{$target}{$itemid}{$value_ref->{'clock'}}{'rtt'} = $value_ref->{'value'};
				$tested_interfaces{$interface}{$probe}{'testData'}{$target}{$itemid}{$value_ref->{'clock'}}{'ip'} = $ip;
			}
		}

		next if (@itemids_str == 0);

		#
		# Fetch IP (String) values (on Probe level) used in non-DNS tests.
		#
		# Note, this is because corrently items for storing IPs exist only for non-DNS services, e. g.:
		#
		# rsm.rdds.43.ip
		#

		$rows_ref = db_select(
			"select itemid,value,clock".
			" from " . history_table(ITEM_VALUE_TYPE_STR).
			" where itemid in (" . join(',', @itemids_str) . ")".
				" and " . sql_time_condition($from, $till)
		);

		# for convenience convert the data to format:
		#
		# {
		#     ITEMID => [
		#         {
		#             'value' => value (string: IP),
		#             'clock' => clock
		#         }
		#     ]
		# }
		%values = ();

		map {push(@{$values{$_->[0]}}, {'value' => $_->[1], 'clock' => $_->[2]})} (@{$rows_ref});

		foreach my $itemid (keys(%values))
		{
			my $i = $probes_data->{$probe}{$itemid};

			foreach my $value_ref (@{$values{$itemid}})
			{
				my $interface = ah_get_interface($i->{'key'});

				# for non-DNS service "target" is NULL, but we
				# can't use it as hash key so we use placeholder
				my $target = TARGET_PLACEHOLDER;

				dbg("found $service IP: ", $value_ref->{'value'}, " (target: $target)");

				$tested_interfaces{$interface}{$probe}{'testData'}{$target}{$itemid}{$value_ref->{'clock'}}{'ip'} = $value_ref->{'value'};
			}
		}
	}

	# add "Offline" and "No results"
	foreach my $probe (keys(%{$probes_ref}))
	{
		my $probe_online = probe_online_at($probe, $from);

		foreach my $interface (@{$interfaces_ref})
		{
			if (!$probe_online)
			{
				$tested_interfaces{$interface}{$probe}{'status'} = AH_CITY_OFFLINE;
			}
			elsif (!defined($tested_interfaces{$interface}{$probe}{'status'}))
			{
				$tested_interfaces{$interface}{$probe}{'status'} = AH_CITY_NO_RESULT;
			}
		}

		$probes_online++ if ($probe_online);
	}

	#
	# add data that was collected from history and calculated in previous cycle to JSON
	#

	foreach my $interface (keys(%tested_interfaces))
	{
		my $interface_json = {
			'interface'	=> $interface,
			'probes'	=> []
		};

		foreach my $probe (keys(%{$tested_interfaces{$interface}}))
		{
			my $probe_ref = {
				'city'		=> $probe,
				'status'	=> $tested_interfaces{$interface}{$probe}{'status'},	# Probe status
				'testData'	=> []
			};

			fill_test_data(
				$service,
				$tested_interfaces{$interface}{$probe}{'testData'},
				$probe_ref->{'testData'},
				$rtt_limit
			);

			push(@{$interface_json->{'probes'}}, $probe_ref);
		}

		push(@{$json->{'testedInterface'}}, $interface_json);
	}

	my $perc;
	if ($probes_with_results == 0)
	{
		$perc = 0;
	}
	else
	{
		$perc = $probes_with_positive * 100 / $probes_with_results;
	}

	my $detailed_info;

	if (defined($json->{'status'}))
	{
		$detailed_info = "taken from Service Availability";
	}
	else
	{
		$detailed_info = sprintf("%d/%d positive, %.3f%%, %d online", $probes_with_positive, $probes_with_results, $perc, $probes_online);

		if ($probes_online < $cfg_minonline)
		{
			$json->{'status'} = 'Up-inconclusive-no-probes';
		}
		elsif ($probes_with_results < $cfg_minonline)
		{
			$json->{'status'} = 'Up-inconclusive-no-data';
		}
		elsif ($perc > SLV_UNAVAILABILITY_LIMIT)
		{
			$json->{'status'} = 'Up';
		}
		else
		{
			$json->{'status'} = 'Down';
		}
	}

	dbg("cycle: $json->{'status'} ($detailed_info)");

	if (opt('dry-run'))
	{
		print(Dumper($json));
		return;
	}

	if (opt('debug'))
	{
		print(Dumper($json));
	}

	if (ah_save_recent_measurement(ah_get_api_tld($tld), $service, $json, $from) != AH_SUCCESS)
	{
		fail("cannot save recent measurement: ", ah_get_error());
	}
}

sub get_interfaces($$$)
{
	my $tld = shift;
	my $service = shift;
	my $now = shift;

	my @result;

	if ($service eq 'dns')
	{
		push(@result, AH_INTERFACE_DNS);
	}
	elsif ($service eq 'dnssec')
	{
		push(@result, AH_INTERFACE_DNSSEC);
	}
	elsif ($service eq 'rdds')
	{
		push(@result, AH_INTERFACE_RDDS43) if (tld_interface_enabled($tld, 'rdds43', $now));
		push(@result, AH_INTERFACE_RDDS80) if (tld_interface_enabled($tld, 'rdds80', $now));
		push(@result, AH_INTERFACE_RDAP) if (tld_interface_enabled($tld, 'rdap', $now));
	}

	return \@result;
}

sub init_child_exit($)
{
	my $fm = shift;

	$fm->run_on_finish( sub ($$$$$)
	{
		my $pid = shift;
		my $exit_code = shift;
		my $id = shift;
		my $exit_signal = shift;
		my $core_dump = shift;

		
		if ($core_dump == 1)
		{
			$child_failed = 1;
			info("child (PID:$pid) handling TLD ", $tldmap{$pid}, " core dumped");
		}
		elsif ($exit_code != SUCCESS)
		{
			$child_failed = 1;
			info("child (PID:$pid) handling TLD ", $tldmap{$pid},
					($exit_signal == 0 ? "" : " got signal " . sig_name($exit_signal) . " and"),
					" exited with code $exit_code");
		}
		elsif ($exit_code != SUCCESS)
		{
			$child_failed = 1;
			info("child (PID:$pid) handling TLD ", $tldmap{$pid}, " got signal ", sig_name($exit_signal));
		}
		else
		{
			dbg("child (PID:$pid) handling TLD ", $tldmap{$pid}, " exited successfully");
		}
	});
}

sub child_failed
{
	$fm->run_on_wait( sub () {
		# This callback ensures that before waiting for the next child to terminate we check the $child_failed
		# flag and send terminate all running children if needed. After sending SIGTERM we raise $signal_sent
		# flag to make sure that we don't do it multiple times.

		return unless ($child_failed);
		return if ($signal_sent);

		info("one of the child processes failed, terminating others...");

		$SIG{'TERM'} = 'IGNORE';	# ignore signal we will send to ourselves in the next step
		kill('TERM', 0);		# send signal to the entire process group
		$SIG{'TERM'} = 'DEFAULT';	# restore default signal handler

		$signal_sent = 1;
	});

	$fm->wait_all_children();

	slv_exit(E_FAIL) if ($child_failed);
}

__END__

=head1 NAME

sla-api-current.pl - generate recent SLA API measurement files for newly collected data

=head1 SYNOPSIS

sla-api-current.pl [--tld <tld>] [--service <name>] [--server-id <id>] [--now unixtimestamp] [--period minutes] [--print-period] [--max-children] [--debug] [--dry-run] [--help]

=head1 OPTIONS

=over 8

=item B<--tld> tld

Optionally specify TLD.

=item B<--service> name

Optionally specify service, one of: dns, dnssec, rdds

=item B<--server-id> ID

Optionally specify the server ID to query the data from.

=item B<--now> unixtimestamp

Optionally specify the time of the cycle to start from. Maximum 30 cycles will be processed.

=item B<--period> minutes

Optionally specify maximum period to handle (default: 30 minutes).

=item B<--print-period>

Print selected period on the screen.

=item B<--max-children> n

Specify maximum number of child processes to run in parallel.

=item B<--debug>

Run the script in debug mode. This means printing more information.

=item B<--dry-run>

Print data to the screen, do not write anything to the filesystem.

=item B<--help>

Print a brief help message and exit.

=back

=head1 DESCRIPTION

B<This program> will generate the most recent measurement files for newly collected monitoring data. The files will be
available under directory /opt/zabbix/sla-v2 . Each run the script would generate new measurement files for the period
from the last run till up to 30 minutes.

=head1 EXAMPLES

/opt/zabbix/scripts/sla-api-recent.pl

Generate recent measurement files for the period from last generated till up to 30 minutes.

=cut
