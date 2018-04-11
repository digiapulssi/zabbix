#!/usr/bin/perl

BEGIN
{
	our $MYDIR = $0; $MYDIR =~ s,(.*)/.*,$1,; $MYDIR = '.' if ($MYDIR eq $0);
	our $MYDIR2 = $0; $MYDIR2 =~ s,(.*)/.*/.*,$1,; $MYDIR2 = '..' if ($MYDIR2 eq $0);
}
use lib $MYDIR;
use lib $MYDIR2;

use warnings;
use strict;

use RSM;
use RSMSLV;
use DaWa;
use Data::Dumper;
use Time::Local;
use POSIX qw(floor);
use Time::HiRes qw(time);
use TLD_constants qw(:ec :api);
use Parallel;

use constant RDDS_SUBSERVICE => 'sub';
use constant AUDIT_RESOURCE_INCIDENT => 32;

use constant PROBE_STATUS_UP => 'Up';
use constant PROBE_STATUS_DOWN => 'Down';
use constant PROBE_STATUS_UNKNOWN => 'Unknown';

use constant PROTO_UDP	=> 0;				# todo phase 1: taken from phase 2
use constant PROTO_TCP	=> 1;				# todo phase 1: taken from phase 2
use constant JSON_INTERFACE_DNS		=> 'DNS';	# todo phase 1: taken from phase 2
use constant JSON_INTERFACE_DNSSEC	=> 'DNSSEC';	# todo phase 1: taken from phase 2
use constant TRIGGER_SEVERITY_NOT_CLASSIFIED	=> 0;	# todo phase 1: taken from phase 2
use constant TRIGGER_VALUE_FALSE	=> 0;		# todo phase 1: taken from phase 2
use constant SEC_PER_WEEK	=> 604800;		# todo phase 1: taken from phase 2
use constant EVENT_OBJECT_TRIGGER	=> 0;		# todo phase 1: taken from phase 2
use constant EVENT_SOURCE_TRIGGERS	=> 0;		# todo phase 1: taken from phase 2
use constant TRIGGER_VALUE_TRUE		=> 1;		# todo phase 1: taken from phase 2
use constant JSON_TAG_RTT		=> 'rtt';	# todo phase 1: taken from phase 2
use constant JSON_TAG_TARGET_IP		=> 'targetIP';	# todo phase 1: taken from phase 2
use constant JSON_TAG_CLOCK		=> 'clock';	# todo phase 1: taken from phase 2
use constant JSON_TAG_DESCRIPTION	=> 'description';# todo phase 1: taken from phase 2
use constant JSON_TAG_UPD		=> 'upd';	# todo phase 1: taken from phase 2
use constant JSON_INTERFACE_RDDS43	=> 'RDDS43';	# todo phase 1: taken from phase 2
use constant JSON_INTERFACE_RDDS80	=> 'RDDS80';	# todo phase 1: taken from phase 2
use constant ROOT_ZONE_READABLE		=> 'zz--root';	# todo phase 1: taken from phase 2

use constant SERVICE_DNS_TCP	=> 'dns-tcp';	# todo phase 1: Export DNS-TCP tests, not a real service

use constant AH_STATUS_UP	=> 'Up';	# todo phase 1: taken from ApiHelper.pm phase 2
use constant AH_STATUS_DOWN	=> 'Down';	# todo phase 1: taken from ApiHelper.pm phase 2

use constant true => 1;	# todo phase 1: taken from TLD_constants.pm phase 2
			# todo phase 1: taken from TLD_constants.pm phase 2
use constant rsm_rdds_probe_result => [
	{},								# 0 - down
	{JSON_INTERFACE_RDDS43 => true, JSON_INTERFACE_RDDS80 => true},	# 1 - up
	{JSON_INTERFACE_RDDS43 => true},				# 2 - only 43
	{JSON_INTERFACE_RDDS80 => true}					# 3 - only 80
];

# todo phase 1: this must be available in phase 2
use constant TARGETS_TMP_DIR => '/opt/zabbix/export-tmp';
use constant TARGETS_TARGET_DIR => '/opt/zabbix/export';

use constant EXPORT_MAX_CHILDREN_DEFAULT => 24;
use constant EXPORT_MAX_CHILDREN_FLOOR => 1;
use constant EXPORT_MAX_CHILDREN_CEIL => 128;

use constant EXPORT_LOOP_SLEEP => 2;

parse_opts('probe=s', 'service=s', 'tld=s', 'date=s', 'day=n', 'shift=n', 'force!', 'max-children=n');
setopt('nolog');

my $config = get_rsm_config();
set_slv_config($config);

db_connect();

__validate_input() unless (opt('force'));

my ($d, $m, $y) = split('/', getopt('date'));

usage() unless ($d && $m && $y);

dw_set_date($y, $m, $d);

if (!opt('dry-run') && (my $error = rsm_targets_prepare(TARGETS_TMP_DIR, TARGETS_TARGET_DIR)))
{
	fail($error);
}

my $services;
if (opt('service'))
{
	$services->{getopt('service')} = undef;
}
else
{
	foreach my $service ('dns', 'dnssec', SERVICE_DNS_TCP, 'rdds', 'epp')	# todo phase 1: Export DNS-TCP tests
	{
		$services->{$service} = undef;
	}
}

# todo phase 1: changed from get_statusmaps('dns')
my $cfg_avail_valuemaps = get_avail_valuemaps();

# todo phase 1: changed from get_result_string($cfg_dns_statusmaps, UP/Down)
my $general_status_up = 'Up';
my $general_status_down = 'Down';

__get_delays($services);
__get_keys($services);
__get_valuemaps($services);

my $date = timelocal(0, 0, 0, $d, $m - 1, $y);

my $shift = opt('shift') ? getopt('shift') : 0;
$date += $shift;

my $day = opt('day') ? getopt('day') : 86400;

my $check_till = $date + $day - 1;
my ($from, $till) = get_real_services_period($services, $date, $check_till);

if (opt('debug'))
{
	dbg("from: ", ts_full($from));
	dbg("till: ", ts_full($till));
}

# todo phase 1: make sure this check exists in phase 2
my $max = __cycle_end(time() - 240, 60);
fail("cannot export data: selected time period is in the future") if ($till > $max);

# consider only tests that started within given period
my $cfg_dns_minns;
my $cfg_dns_minonline;
foreach my $service (sort(keys(%{$services})))
{
	dbg("$service") if (opt('debug'));

	if ($service eq 'dns' || $service eq 'dnssec')
	{
		if (!$cfg_dns_minns)
		{
			$cfg_dns_minns = get_macro_minns();
			$cfg_dns_minonline = get_macro_dns_probe_online();
		}

		$services->{$service}->{'minns'} = $cfg_dns_minns;
		$services->{$service}->{'minonline'} = get_macro_dns_probe_online();
	}

	if ($services->{$service}->{'from'} && $services->{$service}->{'from'} < $date)
	{
		# exclude test that starts outside our period
		$services->{$service}->{'from'} += $services->{$service}->{'delay'};
	}

	if ($services->{$service}->{'till'} && $services->{$service}->{'till'} < $check_till)
	{
		# include test that overlaps on the next period
		$services->{$service}->{'till'} += $services->{$service}->{'delay'};
	}

	if (opt('debug'))
	{
		dbg("  delay\t : ", $services->{$service}->{'delay'});
		dbg("  from\t : ", ts_full($services->{$service}->{'from'}));
		dbg("  till\t : ", ts_full($services->{$service}->{'till'}));
		dbg("  avail\t : ", $services->{$service}->{'key_avail'} // 'UNDEF');
	}
}

my $probes_data;

if (opt('max-children'))
{
	set_max_children(getopt('max-children'));
}
else
{
	set_max_children(EXPORT_MAX_CHILDREN_DEFAULT);
}

my ($time_start, $time_get_test_data, $time_load_ids, $time_process_records, $time_write_csv);

# go through all the databases
my @server_keys = get_rsm_server_keys($config);
foreach (@server_keys)
{
$server_key = $_;

db_disconnect();
db_connect($server_key);

my $probes_ref = get_probes();
my $check_probes_from = $from - 60;	# NB! We need previous value for probeChanges file (see __get_probe_changes())
$probes_data->{$server_key} = get_probe_times($check_probes_from, $till, $probes_ref);

#if (opt('debug'))
#{
#	foreach my $probe (keys(%{$probes_data->{$server_key}}))
#	{
#		for (my $idx = 0; defined($probes_data->{$server_key}->{$probe}->[$idx]); $idx++)
#		{
#			my $status = ($idx % 2 == 0 ? "ONLINE" : "OFFLINE");
#			dbg("$probe: $status ", ts_full($probes_data->{$server_key}->{$probe}->[$idx]));
#		}
#	}
#}

my $tlds_ref;
if (opt('tld'))
{
	if (tld_exists(getopt('tld')) == 0)
	{
		fail("TLD ", getopt('tld'), " does not exist.") if ($server_keys[-1] eq $server_key);
		next;
	}

	$tlds_ref = [ getopt('tld') ];
}
else
{
	$tlds_ref = get_tlds();
}

db_disconnect();

# unset TLD (for the logs)
undef($tld);

my $tld_index = 0;
my $tld_count = scalar(@$tlds_ref);

while ($tld_index < $tld_count)
{
	my $pid = fork_without_pipe();

	if (!defined($pid))
	{
		# max children reached, make sure to handle_children()
	}
	elsif ($pid)
	{
		# parent
		$tld_index++;
	}
	else
	{
		# child
		$tld = $tlds_ref->[$tld_index];

		slv_stats_reset();	# todo phase 1: this is part of phase 2

		db_connect($server_key);

		$time_start = time();

		my $result = __get_test_data($from, $till, $probes_data->{$server_key});

		$time_get_test_data = time();

		db_disconnect();

		db_connect();	# connect to the local node
		__save_csv_data($result);
		db_disconnect();

		info(sprintf("get data: %s, load ids: %s, process records: %s, write csv: %s",
				format_stats_time($time_get_test_data - $time_start),
				format_stats_time($time_load_ids - $time_get_test_data),
				format_stats_time($time_process_records - $time_load_ids),
				format_stats_time($time_write_csv - $time_process_records))) if (opt('stats'));

		slv_exit(SUCCESS);
	}

	handle_children();

	sleep(EXPORT_LOOP_SLEEP);
}

last if (opt('tld'));
}	# foreach (@server_keys)
undef($server_key) unless (opt('tld'));	# keep $server_key if --tld was specified (for __get_false_positives())

# wait till children finish
while (children_running() > 0)
{
	handle_children();

	sleep(EXPORT_LOOP_SLEEP);
}

# at this point there should be no child processes so we do not care about locking

my $false_positives = __get_false_positives($from, $till, $server_key);

undef($server_key);

my $probe_changes = __get_probe_changes($from);	# does not need db connection

db_connect();
dw_csv_init();
dw_load_ids_from_db();
foreach my $fp_ref (@$false_positives)
{
	dbg("writing false positive entry:");
	dbg("  eventid:", $fp_ref->{'eventid'} ? $fp_ref->{'eventid'} : "UNDEF");
	dbg("  clock:", $fp_ref->{'clock'} ? $fp_ref->{'clock'} : "UNDEF");
	dbg("  status:", $fp_ref->{'status'} ? $fp_ref->{'status'} : "UNDEF");

	dw_append_csv(DATA_FALSE_POSITIVE, [
			      $fp_ref->{'eventid'},
			      $fp_ref->{'clock'},
			      dw_get_id(ID_STATUS_MAP, $fp_ref->{'status'}),
			      ''	# reason is not implemented in front-end
		]);
}

foreach my $pc_ref (@{$probe_changes})
{
	dbg("writing probe changes entry:");
	dbg("  name:", $pc_ref->{'probe'});
	dbg("  changed at:", ts_full($pc_ref->{'clock'}));
	dbg("  new status:", $pc_ref->{'status'});

	dw_append_csv(DATA_PROBE_CHANGES, [
			      dw_get_id(ID_PROBE, $pc_ref->{'probe'}),
			      $pc_ref->{'clock'},
			      dw_get_id(ID_STATUS_MAP, $pc_ref->{'status'}),
			      ''	# reason is not implemented yet
		]);
}

dw_write_csv_files();
dw_write_csv_catalogs();

if (!opt('dry-run') && (my $error = rsm_targets_apply()))
{
	fail($error);
}

slv_exit(SUCCESS);

sub __validate_input
{
	my $error_found = 0;

	if (!opt('date'))
	{
		print("Error: you must specify the date using option --date\n");
		$error_found = 1;
	}
	elsif (getopt('date') !~ /^\d\d\/\d\d\/\d\d\d\d$/)
	{
		print("Error: ", getopt('date'), " -- invalid date, expected format: dd/mm/yyyy\n");
		$error_found = 1;
	}

	foreach my $opt ('probe', 'tld', 'service', 'day', 'shift')
	{
		if (opt($opt) && !opt('dry-run'))
		{
			print("Error: option --$opt can only be used together with --dry-run\n");
			$error_found = 1;
		}
	}

	if (opt('day') && (getopt('day') % 60) != 0)
	{
		print("Error: parameter of option --day must be multiple of 60\n");
		$error_found = 1;
	}

	if (opt('max-children') && (getopt('max-children') < EXPORT_MAX_CHILDREN_FLOOR ||
			EXPORT_MAX_CHILDREN_CEIL < getopt('max-children')))
	{
		usage(sprintf("allowed max-children: %d-%d", EXPORT_MAX_CHILDREN_FLOOR, EXPORT_MAX_CHILDREN_CEIL));
	}

	usage() unless ($error_found == 0);
}

sub __get_delays
{
	my $cfg_dns_delay = undef;
	my $services = shift;

	foreach my $service (sort(keys(%$services)))
	{
		if ($service eq 'dns' || $service eq 'dnssec')
		{
			if (!$cfg_dns_delay)
			{
				$cfg_dns_delay = get_macro_dns_udp_delay();
			}

			$services->{$service}->{'delay'} = $cfg_dns_delay;
		}
		elsif ($service eq SERVICE_DNS_TCP)	# todo phase 1: Export DNS-TCP tests
		{
			$services->{$service}->{'delay'} = get_macro_dns_tcp_delay();
		}
		elsif ($service eq 'rdds')
		{
			$services->{$service}->{'delay'} = get_macro_rdds_delay();
		}
		elsif ($service eq 'epp')
		{
			$services->{$service}->{'delay'} = get_macro_epp_delay();
		}

		fail("$service delay (", $services->{$service}->{'delay'}, ") is not multiple of 60") unless ($services->{$service}->{'delay'} % 60 == 0);
	}
}

sub __get_keys
{
	my $services = shift;

	foreach my $service (sort(keys(%$services)))
	{
		if ($service eq 'dns' || $service eq 'dnssec')
		{
			$services->{$service}->{'key_status'} = 'rsm.dns.udp[{$RSM.TLD}]';	# 0 - down, 1 - up
			$services->{$service}->{'key_rtt'} = 'rsm.dns.udp.rtt[{$RSM.TLD},';
		}
		elsif ($service eq SERVICE_DNS_TCP)	# todo phase 1: Export DNS-TCP tests
		{
			$services->{$service}->{'key_rtt'} = 'rsm.dns.tcp.rtt[{$RSM.TLD},';
		}
		elsif ($service eq 'rdds')
		{
			$services->{$service}->{'key_status'} = 'rsm.rdds[{$RSM.TLD}';	# 0 - down, 1 - up, 2 - only 43, 3 - only 80
			$services->{$service}->{'key_43_rtt'} = 'rsm.rdds.43.rtt[{$RSM.TLD}]';
			$services->{$service}->{'key_43_ip'} = 'rsm.rdds.43.ip[{$RSM.TLD}]';
			$services->{$service}->{'key_43_upd'} = 'rsm.rdds.43.upd[{$RSM.TLD}]';
			$services->{$service}->{'key_80_rtt'} = 'rsm.rdds.80.rtt[{$RSM.TLD}]';
			$services->{$service}->{'key_80_ip'} = 'rsm.rdds.80.ip[{$RSM.TLD}]';
		}
		elsif ($service eq 'epp')
		{
			$services->{$service}->{'key_status'} = 'rsm.epp[{$RSM.TLD},';	# 0 - down, 1 - up
			$services->{$service}->{'key_ip'} = 'rsm.epp.ip[{$RSM.TLD}]';
			$services->{$service}->{'key_rtt'} = 'rsm.epp.rtt[{$RSM.TLD},';
		}

		if ($service ne SERVICE_DNS_TCP)	# todo phase 1: Export DNS-TCP tests, they do not refer to service availability
		{
			$services->{$service}->{'key_avail'} = "rsm.slv.$service.avail";
			$services->{$service}->{'key_rollweek'} = "rsm.slv.$service.rollweek";
		}
	}
}

sub __get_valuemaps
{
	my $services = shift;

	my $cfg_dns_valuemaps;

	foreach my $service (sort(keys(%{$services})))
	{
		if ($service eq 'dns' || $service eq SERVICE_DNS_TCP || $service eq 'dnssec')	# todo phase 1: Export DNS-TCP tests
		{
			$cfg_dns_valuemaps = get_valuemaps('dns') unless ($cfg_dns_valuemaps);

			$services->{$service}->{'valuemaps'} = $cfg_dns_valuemaps;
		}
		else
		{
			$services->{$service}->{'valuemaps'} = get_valuemaps($service);
		}
	}
}

# CSV file	: nsTest
# Columns	: probeID,nsFQDNID,tldID,cycleTimestamp,status,cycleID,tldType,nsTestProtocol
#
# Note! cycleID is the concatenation of cycleDateMinute (timestamp) + serviceCategory (5) + tldID
# E. g. 1420070400-5-11
#
# How it works:
# - get list of items
# - get results:
#   "probe1" =>
#   	"ns1.foo.example" =>
#   		"192.0.1.2" =>
#   			"clock" => 1439154000,
#   			"rtt" => 120,
#   		"192.0.1.3"
#   			"clock" => 1439154000,
#   			"rtt" => 1603,
#   	"ns2.foo.example" =>
#   	...
sub __get_test_data
{
	my $from = shift;
	my $till = shift;
	my $probe_times_ref = shift;

	my ($nsips_ref, $dns_items_ref, $dns_tcp_items_ref, $rdds_dbl_items_ref, $rdds_str_items_ref, $epp_dbl_items_ref,
		$epp_str_items_ref, $probe_dns_results_ref, $result);

	foreach my $service (sort(keys(%{$services})))
	{
		if ($service ne SERVICE_DNS_TCP)	# todo phase 1: Export DNS-TCP tests
		{
			next if (tld_service_enabled($tld, $service) != SUCCESS);
		}

		my $delay = $services->{$service}->{'delay'};
		my $service_from = $services->{$service}->{'from'};
		my $service_till = $services->{$service}->{'till'};
		my $key_avail = $services->{$service}->{'key_avail'};
		my $key_rollweek = $services->{$service}->{'key_rollweek'};

		next if (!$service_from || !$service_till);

		my ($itemid_avail, $itemid_rollweek);

		my $hostid = get_hostid($tld);

		if ($key_avail)
		{
			$itemid_avail = get_itemid_by_hostid($hostid, $key_avail);
			if (!$itemid_avail)
			{
				wrn("configuration error: service $service enabled but ", rsm_slv_error());
				next;
			}

			$itemid_rollweek = get_itemid_by_hostid($hostid, $key_rollweek);
			if (!$itemid_rollweek)
			{
				wrn("configuration error: service $service enabled but ", rsm_slv_error());
				next;
			}
		}

		# $nsips_ref is used by services: dns, dnssec, dns-tcp
		# $dns_items_ref is used by services: dns, dnssec

		if ($service eq 'dns' || $service eq 'dnssec')
		{
			if (!$nsips_ref)
			{
				$nsips_ref = get_templated_nsips($tld, $services->{$service}->{'key_rtt'}, 1);	# templated
			}

			if (!$dns_items_ref)
			{
				$dns_items_ref = __get_dns_itemids($nsips_ref, $services->{$service}->{'key_rtt'}, $tld, getopt('probe'));
			}
		}
		elsif ($service eq SERVICE_DNS_TCP)	# todo phase 1: Export DNS-TCP tests
		{
			if (!$nsips_ref)
			{
				$nsips_ref = get_templated_nsips($tld, $services->{$service}->{'key_rtt'}, 1);  # templated
			}
			$dns_tcp_items_ref = __get_dns_itemids($nsips_ref, $services->{$service}->{'key_rtt'}, $tld, getopt('probe'));
		}
		elsif ($service eq 'rdds')
		{
			$rdds_dbl_items_ref = __get_rdds_dbl_itemids($tld, getopt('probe'),
				$services->{$service}->{'key_43_rtt'}, $services->{$service}->{'key_80_rtt'},
				$services->{$service}->{'key_43_upd'});
			$rdds_str_items_ref = __get_rdds_str_itemids($tld, getopt('probe'),
				$services->{$service}{'key_43_ip'}, $services->{$service}->{'key_80_ip'});
		}
		elsif ($service eq 'epp')
		{
			$epp_dbl_items_ref = get_epp_dbl_itemids($tld, getopt('probe'), $services->{$service}->{'key_rtt'});
			$epp_str_items_ref = get_epp_str_itemids($tld, getopt('probe'), $services->{$service}->{'key_ip'});
		}

		my (@empty_arr, $rows_ref, $incidents);

		if ($itemid_avail)
		{
			$incidents = __get_incidents2($itemid_avail, $delay, $service_from, $service_till);

			# SERVICE availability data
			$rows_ref = db_select(
				"select value,clock".
				" from history_uint".
				" where itemid=$itemid_avail".
					" and " . sql_time_condition($service_from, $service_till).
				" order by itemid,clock");	# NB! order is important, see how the result is used below
		}
		else
		{
			$incidents = \@empty_arr;
			$rows_ref = \@empty_arr;
		}

		my $cycles;
		my $last_avail_clock;

		foreach my $row_ref (@$rows_ref)
		{
			my $value = $row_ref->[0];
			my $clock = $row_ref->[1];

			next if ($last_avail_clock && $last_avail_clock == $clock);

			$last_avail_clock = $clock;

			#dbg("$service availability at ", ts_full($clock), ": $value");

			# todo phase 1: remove the cycle here that counts failed_incidents, it's already calculated in __get_incidents()
			# NB! REMOVED CODE HERE
			# todo phase 1: calculating number of failed incidents can be added as an option to get_incidents() of phase 2

			# todo phase 1: make sure UP_INCONCLUSIVE is added in phase 2
			wrn("unknown availability result: $value (expected ", DOWN, " (Down), ", UP, " (Up))")
				if ($value != UP && $value != DOWN);

			# We have the test resulting value (Up or Down) at "clock". Now we need to select the
			# time bounds (start/end) of all data points from all proxies.
			#
			#   +........................period (service delay)...........................+
			#   |                                                                         |
			# start                                 clock                                end
			#   |.....................................|...................................|
			#   0 seconds <--zero or more minutes--> 30                                  59
			#

			my $cycleclock = cycle_start($clock, $delay);

			# todo phase 1: make sure this uses avail valuemaps in phase1
			# todo: later rewrite to use valuemap ID from item
			$cycles->{$cycleclock}->{'status'} = get_result_string($cfg_avail_valuemaps, $value);
		}

		if ($itemid_rollweek)
		{
			# Rolling week data (is synced with availability data from above)
			$rows_ref = db_select(
				"select value,clock".
				" from history".
				" where itemid=$itemid_rollweek".
					" and " . sql_time_condition($service_from, $service_till).
				" order by clock");	# NB! order is important, see how the result is used below
		}
		else
		{
			$rows_ref = \@empty_arr;
		}

		foreach my $row_ref (@$rows_ref)
		{
			my $value = $row_ref->[0];
			my $clock = $row_ref->[1];

			#dbg("$service rolling week at ", ts_full($clock), ": $value");

			my $cycleclock = cycle_start($clock, $delay);

			$cycles->{$cycleclock}->{'rollweek'} = $value;
		}

		my $cycles_count = scalar(keys(%{$cycles}));

		if ($service ne SERVICE_DNS_TCP && $cycles_count == 0)	# todo phase 1: Export DNS-TCP tests, they do not refer to service availability
		{
			wrn("$service: no results");
			last;
		}

		my $tests_ref;

		if ($service eq 'dns')
		{
			$tests_ref = __get_dns_test_values($dns_items_ref, $service_from, $service_till,
				$services->{$service}->{'valuemaps'}, $delay, $service);
		}
		elsif ($service eq SERVICE_DNS_TCP)	# todo phase 1: Export DNS-TCP tests
		{
			$tests_ref = __get_dns_test_values($dns_tcp_items_ref, $service_from, $service_till,
				$services->{$service}->{'valuemaps'}, $delay, $service);
		}
		elsif ($service eq 'rdds')
		{
			$tests_ref = __get_rdds_test_values($rdds_dbl_items_ref, $rdds_str_items_ref,
				$service_from, $service_till, $services->{$service}->{'valuemaps'}, $delay);
		}
		elsif ($service eq 'epp')
		{
			$tests_ref = __get_epp_test_values($epp_dbl_items_ref, $epp_str_items_ref,
				$service_from, $service_till, $services->{$service}->{'valuemaps'}, $delay);
		}

		# add tests to appropriate cycles
		foreach my $cycleclock (sort(keys(%$tests_ref)))
		{
			if ($key_avail)
			{
				if (!$cycles->{$cycleclock})
				{
					__no_cycle_result($service, $key_avail, $cycleclock);
					next;
				}
			}

			foreach my $interface (keys(%{$tests_ref->{$cycleclock}}))
			{
				foreach my $probe (keys(%{$tests_ref->{$cycleclock}->{$interface}}))
				{
					# the status is set later
					$cycles->{$cycleclock}->{'interfaces'}->{$interface}->{'probes'}->{$probe}->{'status'} = undef;

					if (probe_offline_at($probe_times_ref, $probe, $cycleclock) == SUCCESS)
					{
						$cycles->{$cycleclock}->{'interfaces'}->{$interface}->{'probes'}->{$probe}->{'status'} = PROBE_OFFLINE_STR;
					}

					$cycles->{$cycleclock}->{'interfaces'}->{$interface}->{'probes'}->{$probe}->{'targets'} = $tests_ref->{$cycleclock}->{$interface}->{$probe};
				}
			}
		}

		my $probe_results_ref;

		# add availability results from probes, working services: (dns: number of NS, rdds: 43, 80)
		if ($service eq 'dns' || $service eq 'dnssec')
		{
			if (!$probe_dns_results_ref)
			{
				my $itemids_ref = __get_service_status_itemids($tld, $services->{$service}->{'key_status'});
				$probe_dns_results_ref = __get_probe_results($itemids_ref, $service_from, $service_till);
			}

			$probe_results_ref = $probe_dns_results_ref;
		}
		elsif ($service eq SERVICE_DNS_TCP)	# todo phase 1: Export DNS-TCP tests, they do not refer to service availability
		{
			$probe_results_ref = undef;
		}
		else
		{
			my $itemids_ref = __get_service_status_itemids($tld, $services->{$service}->{'key_status'});
			$probe_results_ref = __get_probe_results($itemids_ref, $service_from, $service_till);
		}

		if ($probe_results_ref)
		{
			foreach my $cycle (values(%{$cycles}))
			{
				# set status on particular probe
				while (my ($interface, $cycle_interface) = each(%{$cycle->{'interfaces'}}))
				{
					while (my ($probe, $cycle_interface_probe) = each(%{$cycle_interface->{'probes'}}))
					{
						next if (defined($cycle_interface_probe->{'status'}));

						foreach my $probe_result_ref (@{$probe_results_ref->{$probe}})
						{
							$cycle_interface_probe->{'status'} =
								__interface_status($interface, $probe_result_ref->{'value'}, $services->{$service});
						}
					}
				}
			}
		}

		$result->{$tld}->{'type'} = __get_tld_type($tld);
		$result->{$tld}->{'services'}->{$service}->{'cycles'} = $cycles;
		$result->{$tld}->{'services'}->{$service}->{'incidents'} = $incidents;
	}

	return $result;
}

sub __save_csv_data
{
	my $result = shift;

	# push data to CSV files
	foreach (sort(keys(%{$result})))
	{
		$tld = $_;	# set to global variable

		dw_csv_init();
		dw_load_ids_from_db();

		$time_load_ids = time();

		my $ns_service_category_id = dw_get_id(ID_SERVICE_CATEGORY, 'ns');
		my $dns_service_category_id = dw_get_id(ID_SERVICE_CATEGORY, 'dns');
		my $dnssec_service_category_id = dw_get_id(ID_SERVICE_CATEGORY, 'dnssec');
		my $rdds_service_category_id = dw_get_id(ID_SERVICE_CATEGORY, 'rdds');
		my $epp_service_category_id = dw_get_id(ID_SERVICE_CATEGORY, 'epp');
		my $udp_protocol_id = dw_get_id(ID_TRANSPORT_PROTOCOL, 'udp');
		my $tcp_protocol_id = dw_get_id(ID_TRANSPORT_PROTOCOL, 'tcp');

		my $tld_id = dw_get_id(ID_TLD, $tld);
		my $tld_type_id = dw_get_id(ID_TLD_TYPE, $result->{$tld}->{'type'});

		# RTT.LOW macros
		my $rtt_low;

		foreach my $service (sort(keys(%{$result->{$tld}->{'services'}})))
		{
			my $service_ref = $services->{$service};

			my ($service_category_id, $protocol_id, $proto);

			if ($service eq 'dns')
			{
				$service_category_id = $dns_service_category_id;
				$protocol_id = $udp_protocol_id;
				$proto = PROTO_UDP;
			}
			elsif ($service eq SERVICE_DNS_TCP)	# todo phase 1: Export DNS-TCP tests
			{
				$service_category_id = $dns_service_category_id;
				$protocol_id = $tcp_protocol_id;
				$proto = PROTO_TCP;
			}
			elsif ($service eq 'dnssec')
			{
				$service_category_id = $dnssec_service_category_id;
				$protocol_id = $udp_protocol_id;
				$proto = PROTO_UDP;
			}
			elsif ($service eq 'rdds')
			{
				$service_category_id = $rdds_service_category_id;
				$protocol_id = $tcp_protocol_id;
				$proto = PROTO_TCP;
			}
			elsif ($service eq 'epp')
			{
				$service_category_id = $epp_service_category_id;
				$protocol_id = $tcp_protocol_id;
				$proto = PROTO_TCP;
			}
			else
			{
				fail("THIS SHOULD NEVER HAPPEN");
			}

			my $incidents = $result->{$tld}->{'services'}->{$service}->{'incidents'};
			my $incidents_count = scalar(@{$incidents});
			my $inc_idx = 0;

			# test results
			foreach my $cycleclock (sort(keys(%{$result->{$tld}->{'services'}->{$service}->{'cycles'}})))
			{
				my $cycle_ref = $result->{$tld}->{'services'}->{$service}->{'cycles'}->{$cycleclock};

				if ($service ne SERVICE_DNS_TCP)	# todo phase 1: Export DNS-TCP tests, they do not refer to service availability
				{
					if (!defined($cycle_ref->{'status'}))
					{
						wrn("no status of $service cycle rolling week (", ts_full($cycleclock), ")!");
						next;
					}
				}

				my %nscycle;	# for Name Server cycle

				my $eventid = '';

				if ($inc_idx < $incidents_count)
				{
					while ($inc_idx < $incidents_count && $incidents->[$inc_idx]->{'end'} && $incidents->[$inc_idx]->{'end'} < $cycleclock)
					{
						$inc_idx++;
					}

					if ($inc_idx < $incidents_count && (!$incidents->[$inc_idx]->{'end'} || $cycleclock >= $incidents->[$inc_idx]->{'start'} && $incidents->[$inc_idx]->{'end'} >= $cycleclock))
					{
						$eventid = $incidents->[$inc_idx]->{'eventid'};
					}
				}

				# SERVICE cycle
				dw_append_csv(DATA_CYCLE, [
						      dw_get_cycle_id($cycleclock, $service_category_id, $tld_id),
						      $cycleclock,
						      $cycle_ref->{'rollweek'},
						      dw_get_id(ID_STATUS_MAP, $cycle_ref->{'status'}),
						      $eventid,
						      $tld_id,
						      $service_category_id,
						      '',
						      '',
						      '',
						      $tld_type_id,
						      $protocol_id
					]) if ($service ne SERVICE_DNS_TCP);	# todo phase 1: Export DNS-TCP tests, they do not refer to service availability

				foreach my $interface (keys(%{$cycle_ref->{'interfaces'}}))
				{
					foreach my $probe (keys(%{$cycle_ref->{'interfaces'}->{$interface}->{'probes'}}))
					{
						my $probe_id = dw_get_id(ID_PROBE, $probe);

						foreach my $target (keys(%{$cycle_ref->{'interfaces'}->{$interface}->{'probes'}->{$probe}->{'targets'}}))
						{
							my $target_status = $general_status_up;
							my $target_id = '';

							if ($interface eq 'DNS')
							{
								$target_id = dw_get_id(ID_NS_NAME, $target);
							}

							foreach my $metric_ref (@{$cycle_ref->{'interfaces'}->{$interface}->{'probes'}->{$probe}->{'targets'}->{$target}})
							{
								my $test_status;

								# TODO: EPP: it's not yet decided if 3 EPP RTTs
								# (login, info, update) are coming in one metric or 3
								# separate ones. Based on that decision in the future
								# the $rtt_low must be fetched for each command and
								# each of the metrics must be added by calling
								# __add_csv_test() 3 times, for each RTT.
								# NB! Sync with RSMSLV.pm function get_epp_test_values()!

								if (!defined($rtt_low) || !defined($rtt_low->{$tld}) || !defined($rtt_low->{$tld}->{$service})
									|| !defined($rtt_low->{$tld}->{$service}->{$proto}))
								{
									my $_service = ($service eq SERVICE_DNS_TCP ? 'dns' : $service);	# todo phase 1: Export DNS-TCP tests, __get_rtt_low() expects real service

									$rtt_low->{$tld}->{$service}->{$proto} = __get_rtt_low($_service, $proto);	# TODO: add third parameter (command) for EPP!
								}

								if (__check_test($interface, $metric_ref->{JSON_TAG_RTT()}, $metric_ref->{JSON_TAG_DESCRIPTION()},
										$rtt_low->{$tld}->{$service}->{$proto}) == SUCCESS)
								{
									$test_status = $general_status_up;
								}
								else
								{
									$test_status = $general_status_down;
								}

								if ($target_status eq $general_status_up)
								{
									if ($test_status eq $general_status_down)
									{
										$target_status = $general_status_down;
									}
								}

								my $testclock = $metric_ref->{JSON_TAG_CLOCK()};

								my ($ip, $ip_id, $ip_version_id, $rtt);

								if ($metric_ref->{JSON_TAG_TARGET_IP()})
								{
									$ip = $metric_ref->{JSON_TAG_TARGET_IP()};
									$ip_id = dw_get_id(ID_NS_IP, $ip);
									$ip_version_id = dw_get_id(ID_IP_VERSION, __ip_version($ip));
								}
								else
								{
									$ip = '';
									$ip_id = '';
									$ip_version_id = '';
								}

								if (defined($metric_ref->{JSON_TAG_RTT()}))
								{
									$rtt = $metric_ref->{JSON_TAG_RTT()};
								}
								else
								{
									if ($metric_ref->{JSON_TAG_DESCRIPTION()})
									{
										my @a = split(',', $metric_ref->{JSON_TAG_DESCRIPTION()});
										$rtt = $a[0];
									}
									else
									{
										$rtt = '';
									}
								}

								# TEST
								__add_csv_test(
									dw_get_cycle_id($cycleclock, $service_category_id, $tld_id, $target_id, $ip_id),
									$probe_id,
									$cycleclock,
									$testclock,
									$rtt,
									$service_category_id,
									$tld_id,
									$protocol_id,
									$ip_version_id,
									$ip_id,
									dw_get_id(ID_TEST_TYPE, lc($interface)),
									$target_id,
									$tld_type_id
									);

								if ($ip)
								{
									if (!defined($nscycle{$target}) || !defined($nscycle{$target}{$ip}))
									{
										$nscycle{$target}{$ip}{'total'} = 0;
										$nscycle{$target}{$ip}{'positive'} = 0;
									}

									$nscycle{$target}{$ip}{'total'}++;
									$nscycle{$target}{$ip}{'positive'}++ if ($test_status eq $general_status_up);
								}
							}

							if ($interface eq 'DNS')
							{

								if (!defined($target_status))
								{
									wrn("no status of $interface NS test (", ts_full($cycleclock), ")!");
									next;
								}

								# Name Server (target) test
								dw_append_csv(DATA_NSTEST, [
										      $probe_id,
										      $target_id,
										      $tld_id,
										      $cycleclock,
										      dw_get_id(ID_STATUS_MAP, $target_status),
										      $tld_type_id,
										      $protocol_id
									]);
							}
						}
					}


					if ($interface eq 'DNS' && $service ne SERVICE_DNS_TCP)	# todo phase 1: Export DNS-TCP tests
					{
						foreach my $ns (keys(%nscycle))
						{
							foreach my $ip (keys(%{$nscycle{$ns}}))
							{
								#dbg("NS $ns,$ip : positive ", $nscycle{$ns}{$ip}{'positive'}, "/", $nscycle{$ns}{$ip}{'total'});

								my $nscyclestatus;

								if ($nscycle{$ns}{$ip}{'total'} < $services->{$service}->{'minonline'})
								{
									$nscyclestatus = $general_status_up;
								}
								else
								{
									my $perc = $nscycle{$ns}{$ip}{'positive'} * 100 / $nscycle{$ns}{$ip}{'total'};
									$nscyclestatus = ($perc > SLV_UNAVAILABILITY_LIMIT ? $general_status_up : $general_status_down);
								}

								#dbg("get ip version, csv:ns_avail service:$service, ip:", (defined($ip) ? $ip : "UNDEF"));

								my $ns_id = dw_get_id(ID_NS_NAME, $ns);
								my $ip_id = dw_get_id(ID_NS_IP, $ip);

								if (!defined($nscyclestatus))
								{
									wrn("no status of $interface cycle (", ts_full($cycleclock), ")!");
									next;
								}

								# Name Server availability cycle
								dw_append_csv(DATA_CYCLE, [
										      dw_get_cycle_id($cycleclock, $ns_service_category_id, $tld_id, $ns_id, $ip_id),
										      $cycleclock,
										      '',	# TODO: emergency threshold not yet supported for NS Availability (todo phase 1: make sure this fix (0 -> '') exists in phase 2)
										      dw_get_id(ID_STATUS_MAP, $nscyclestatus),
										      '',	# TODO: incident ID not yet supported for NS Availability
										      $tld_id,
										      $ns_service_category_id,
										      $ns_id,
										      $ip_id,
										      dw_get_id(ID_IP_VERSION, __ip_version($ip)),
										      $tld_type_id,
										      $protocol_id
									]);
							}
						}
					}
				}
			}

			# incidents
			foreach (@$incidents)
			{
				my $eventid = $_->{'eventid'};
				my $event_start = $_->{'start'};
				my $event_end = $_->{'end'};
				my $failed_tests = $_->{'failed_tests'};
				my $false_positive = $_->{'false_positive'};

				if (opt('debug'))
				{
					dbg("incident id:$eventid start:" . ts_full($event_start) .
							" end:" . ts_full($event_end) . " fp:$false_positive" .
							" failed_tests:" . ($failed_tests // "(null)"));
				}

				# write event that resolves incident
				if ($event_end)
				{
					dw_append_csv(DATA_INCIDENT_END, [
							      $eventid,
							      $event_end,
							      $failed_tests
						]);
				}

				# report only incidents within given period
				if ($event_start > $from)
				{
					dw_append_csv(DATA_INCIDENT, [
							      $eventid,
							      $event_start,
							      $tld_id,
							      $service_category_id,
							      $tld_type_id
						]);
				}
			}
		}

		$time_process_records = time();

		my $real_tld = $tld;
		$tld = get_readable_tld($real_tld);
		dw_write_csv_files();
		$tld = $real_tld;

		$time_write_csv = time();
	}
	$tld = undef;
}

sub __add_csv_test
{
	my $cycle_id = shift;
	my $probe_id = shift;
	my $cycleclock = shift;
	my $testclock = shift;
	my $rtt = shift;
	my $service_category_id = shift;
	my $tld_id = shift;
	my $protocol_id = shift;
	my $ip_version_id = shift;
	my $ip_id = shift;
	my $test_type_id = shift;
	my $ns_id = shift;
	my $tld_type_id = shift;

	dw_append_csv(DATA_TEST, [
			      $probe_id,
			      $cycleclock,
			      $testclock,
			      __format_rtt($rtt),
			      $cycle_id,
			      $tld_id,
			      $protocol_id,
			      $ip_version_id,
			      $ip_id,
			      $test_type_id,
			      $ns_id,
			      $tld_type_id
		]);
}

sub __ip_version
{
	my $addr = shift;

	return 'IPv6' if ($addr =~ /:/);

	return 'IPv4';
}

sub __get_tld_type
{
	my $tld = shift;

	my $rows_ref = db_select(
		"select g.name".
		" from hosts_groups hg,groups g,hosts h".
		" where hg.groupid=g.groupid".
			" and hg.hostid=h.hostid".
			" and g.name like '%TLD'".
			" and h.host='$tld'");

	if (scalar(@$rows_ref) != 1)
	{
		fail("cannot get type of TLD $tld");
	}

	return $rows_ref->[0]->[0];
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
			" and h.status=".HOST_STATUS_MONITORED.
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
			" and h.status=".HOST_STATUS_MONITORED.
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

sub __check_dns_udp_rtt
{
	my $value = shift;
	my $max_rtt = shift;

	return (is_service_error($value) == SUCCESS or $value > $max_rtt) ? E_FAIL : SUCCESS;
}

sub __get_false_positives
{
	my $from = shift;
	my $till = shift;
	my $_server_key = shift;	# if --tld was specified

	my @local_server_keys;

	if ($_server_key)
	{
		push(@local_server_keys, $_server_key)
	}
	else
	{
		@local_server_keys = @server_keys;
	}

	my @result;

	# go through all the databases
	foreach (@local_server_keys)
	{
	$server_key = $_;
	db_connect($server_key);

	# check for possible false_positive changes made in front-end
	my $rows_ref = db_select(
		"select details,clock".
		" from auditlog".
		" where resourcetype=" . AUDIT_RESOURCE_INCIDENT.
			" and clock between $from and $till".
		" order by clock");

	foreach my $row_ref (@$rows_ref)
	{
		my $details = $row_ref->[0];
		my $clock = $row_ref->[1];
		my ($eventid) = ($details =~ /^([0-9]+): /);
		my $status = ($details =~ m/unmark/i ? 'Activated' : 'Deactivated');

		push(@result, {'clock' => $clock, 'eventid' => $eventid, 'status' => $status});
	}
	db_disconnect();
	}
	undef($server_key);

	return \@result;
}

sub __get_probe_changes
{
	my $from = shift;

	my @result;

	foreach my $_server_key (sort(keys(%{$probes_data})))
	{
		foreach my $probe (sort(keys(%{$probes_data->{$_server_key}})))
		{
			for (my $idx = 0; defined($probes_data->{$_server_key}->{$probe}->[$idx]); $idx++)
			{
				my $clock = $probes_data->{$_server_key}->{$probe}->[$idx];
				my $status = ($idx % 2 == 0 ? PROBE_ONLINE_STR : PROBE_OFFLINE_STR);

				if ($idx == 0 && $clock < $from)
				{
					# ignore previous minute status
					next;
				}

				if ($idx + 1 == scalar(@{$probes_data->{$_server_key}->{$probe}}) && ($clock % 60 != 0))
				{
					# ignore last second status
					next;
				}

				push(@result, {'probe' => $probe, 'status' => $status, 'clock' => $clock});
			}
		}
	}

	return \@result;
}

sub __format_rtt
{
	my $rtt = shift;

	return "UNDEF" unless (defined($rtt));		# it should never be undefined

	return $rtt unless ($rtt);			# allow empty string (in case of error)

	return int($rtt);
}

sub __check_test
{
	my $interface = shift;
	my $value = shift;
	my $description = shift;
	my $max_value = shift;

	if ($interface eq JSON_INTERFACE_DNSSEC)
	{
		if (defined($description))
		{
			my $error_code_len = length(ZBX_EC_DNS_NS_ERRSIG);
			my $error_code = substr($description, 0, $error_code_len);

			if ($error_code eq ZBX_EC_DNS_NS_ERRSIG || $error_code eq ZBX_EC_DNS_RES_NOADBIT)
			{
				return E_FAIL;
			}
		}

		return SUCCESS;
	}

	return E_FAIL unless ($value);

	return (is_service_error($value) == SUCCESS or $value > $max_value) ? E_FAIL : SUCCESS;
}

# todo phase 1: taken from RSMSLV.pm phase 2
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
			" and h.status=".HOST_STATUS_MONITORED.
			" and i.status<>".ITEM_STATUS_DISABLED.
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

sub __print_undef
{
	my $string = shift;

	return (defined($string) ? $string : "UNDEF");
}

# todo phase 1: taken from RSMSLV.pm phase 2
# NB! THIS IS FIXED VERSION WHICH MUST REPLACE EXISTING ONE
# (supports identifying service error)
sub __best_rtt
{
	my $cur_rtt = shift;
	my $cur_description = shift;
	my $new_rtt = shift;
	my $new_description = shift;

	if (!defined($cur_rtt) && !defined($cur_description))
	{
		return ($new_rtt, $new_description);
	}

	if (defined($new_rtt))
	{
		if (!defined($cur_rtt))
		{
			return ($new_rtt, $new_description);
		}

		if (is_service_error($cur_rtt) == SUCCESS)
		{
			if (is_service_error($new_rtt) != SUCCESS)
			{
				return ($new_rtt, $new_description);
			}
		}
		elsif (is_service_error($new_rtt) != SUCCESS && $cur_rtt > $new_rtt)
		{
			return ($new_rtt, $new_description);
		}
	}

	return ($cur_rtt, $cur_description);
}

# todo phase 1: taken from RSMSLV.pm phase 2
# NB! THIS IS FIXED VERSION WHICH MUST REPLACE EXISTING ONE
# 1. Fixes incorrect handling of set_idx: $set_idx = 0.
# 2. Improves speed by using itemid -> probe, target, ip hash.
sub __get_dns_test_values
{
	my $dns_items_ref = shift;
	my $start = shift;
	my $end = shift;
	my $valuemaps = shift;
	my $delay = shift;
	my $service = shift;

	my $interface;

	if ($service eq 'dns' || $service eq SERVICE_DNS_TCP)	# todo phase 1: Export DNS-TCP tests
	{
		$interface = JSON_INTERFACE_DNS;
	}
	else
	{
		$interface = JSON_INTERFACE_DNSSEC;
	}

	my $result;

	# hash of itemids -> probe, target, ip
	my %itemids;
	foreach my $probe (keys(%$dns_items_ref))
	{
		foreach my $itemid (keys(%{$dns_items_ref->{$probe}}))
		{
			my ($target, $ip) = split(',', $dns_items_ref->{$probe}->{$itemid});

			$itemids{$itemid}{'probe'} = $probe;
			$itemids{$itemid}{'target'} = $target;
			$itemids{$itemid}{'ip'} = $ip;
		}
	}

	if (scalar(keys(%itemids)) != 0)
	{
		my @itemids = keys(%itemids);

		my $rows_ref = db_select_binds("select itemid,value,clock from history where itemid=? and " . sql_time_condition($start, $end), \@itemids);

		foreach my $row_ref (sort { $a->[2] <=> $b->[2] } @$rows_ref)
		{
			my $itemid = $row_ref->[0];
			my $value = $row_ref->[1];
			my $clock = $row_ref->[2];

			my $probe = $itemids{$itemid}{'probe'};
			my $target = $itemids{$itemid}{'target'};
			my $ip = $itemids{$itemid}{'ip'};

			my ($new_value, $new_description, $set_idx);

			my $value_tag = JSON_TAG_RTT();

			$new_value = $value;

			if ($new_value < 0)
			{
				$new_description = $new_value;
				undef($new_value);
			}

			my $cycleclock = cycle_start($clock, $delay);

			# TODO: rename (in all functions):
			#
			# tests_ref -> target_ips_ref
			# test_ref -> target_ip_ref
			# idx -> target_ip_idx
			# set_idx -> replace_idx

			my $tests_ref = $result->{$cycleclock}->{$interface}->{$probe}->{$target};

			my $idx = 0;
			foreach my $test_ref (@$tests_ref)
			{
				if ($test_ref->{JSON_TAG_TARGET_IP()} eq $ip)
				{
					$set_idx = $idx;
					last;
				}

				$idx++;
			}

			if (!defined($set_idx))
			{
				$set_idx = $idx;
			}
			else
			{
				my $test_ref = $tests_ref->[$set_idx];

				($new_value, $new_description) = __best_rtt($test_ref->{$value_tag}, $test_ref->{JSON_TAG_DESCRIPTION()}, $new_value, $new_description);

				if (!defined($new_value) || (defined($test_ref->{$value_tag}) && $new_value == $test_ref->{$value_tag}))
				{
					undef($set_idx);
				}
			}

			if (defined($set_idx))
			{
				$result->{$cycleclock}->{$interface}->{$probe}->{$target}->[$set_idx] =
				{
					JSON_TAG_TARGET_IP() => $ip,
					$value_tag => $new_value,
					JSON_TAG_CLOCK() => $clock,
					JSON_TAG_DESCRIPTION() => get_detailed_result($valuemaps, $new_description)
				};

			}
		}
	}

	return $result;
}

# todo phase 1: taken from RSMSLV.pm phase 2
sub __make_incident
{
	my %h;

	$h{'eventid'} = shift;
	$h{'false_positive'} = shift;
	$h{'start'} = shift;
	$h{'end'} = shift;

	return \%h;
}

# todo phase 1: taken from RSMSLV.pm phase 2
# todo phase 1: NB! Contains the fix to recalculate number of failed tests, see below label "FIX-PH1"
sub __get_incidents2
{
	my $itemid = shift;
	my $delay = shift;
	my $from = shift;
	my $till = shift;

	my (@incidents, $rows_ref, $row_ref);

	$rows_ref = db_select(
		"select distinct t.triggerid".
		" from triggers t,functions f".
		" where t.triggerid=f.triggerid".
			" and t.status<>".TRIGGER_STATUS_DISABLED.
			" and f.itemid=$itemid".
			" and t.priority=".TRIGGER_SEVERITY_NOT_CLASSIFIED);

	my $rows = scalar(@$rows_ref);

	unless ($rows == 1)
	{
		wrn("configuration error: item $itemid must have one not classified trigger (found: $rows)");
		return \@incidents;
	}

	my $triggerid = $rows_ref->[0]->[0];

	my $last_trigger_value = TRIGGER_VALUE_FALSE;

	# FIX-PH1
	use constant SEC_PER_MONTH => 2592000;

	if (defined($from))
	{
		# First check for ongoing incident.

		my $attempts = 5;

		undef($row_ref);

		my $attempt = 0;

		my $clock_till = $from;
		# start FIX-PH1
		#my $clock_from = $clock_till - SEC_PER_WEEK;
		my $clock_from = $clock_till - SEC_PER_MONTH;
		# end FIX-PH1
		$clock_till--;

		while ($attempt++ < $attempts && !defined($row_ref))
		{
			$rows_ref = db_select(
				"select max(clock)".
				" from events".
				" where object=".EVENT_OBJECT_TRIGGER.
					" and source=".EVENT_SOURCE_TRIGGERS.
					" and objectid=$triggerid".
					" and " . sql_time_condition($clock_from, $clock_till));

			$row_ref = $rows_ref->[0];

			$clock_till = $clock_from - 1;
			$clock_from -= (SEC_PER_WEEK * $attempt * 2);
		}

		if (!defined($row_ref))
		{
			$rows_ref = db_select(
				"select max(clock)".
				" from events".
				" where object=".EVENT_OBJECT_TRIGGER.
					" and source=".EVENT_SOURCE_TRIGGERS.
					" and objectid=$triggerid".
					" and clock<$clock_from");

			$row_ref = $rows_ref->[0];
		}

		if (defined($row_ref) and defined($row_ref->[0]))
		{
			my $preincident_clock = $row_ref->[0];

			$rows_ref = db_select(
				"select eventid,clock,value,false_positive".
				" from events".
				" where object=".EVENT_OBJECT_TRIGGER.
					" and source=".EVENT_SOURCE_TRIGGERS.
					" and objectid=$triggerid".
					" and clock=$preincident_clock".
				" order by ns desc".
				" limit 1");

			$row_ref = $rows_ref->[0];

			my $eventid = $row_ref->[0];
			my $clock = $row_ref->[1];
			my $value = $row_ref->[2];
			my $false_positive = $row_ref->[3];

			dbg("reading pre-event $eventid: clock:" . ts_str($clock) . " ($clock), value:", ($value == 0 ? 'OK' : 'PROBLEM'), ", false_positive:$false_positive") if (opt('debug'));

			# do not add 'value=TRIGGER_VALUE_TRUE' to SQL above just for corner case of 2 events at the same second
			if ($value == TRIGGER_VALUE_TRUE)
			{
				push(@incidents, __make_incident($eventid, $false_positive, cycle_start($clock, $delay)));

				$last_trigger_value = TRIGGER_VALUE_TRUE;
			}
		}
	}

	# now check for incidents within given period
	$rows_ref = db_select(
		"select eventid,clock,value,false_positive".
		" from events".
		" where object=".EVENT_OBJECT_TRIGGER.
			" and source=".EVENT_SOURCE_TRIGGERS.
			" and objectid=$triggerid".
			" and ".sql_time_condition($from, $till).
		" order by clock,ns");

	foreach my $row_ref (@$rows_ref)
	{
		my $eventid = $row_ref->[0];
		my $clock = $row_ref->[1];
		my $value = $row_ref->[2];
		my $false_positive = $row_ref->[3];

		dbg("reading event $eventid: clock:" . ts_str($clock) . " ($clock), value:", ($value == 0 ? 'OK' : 'PROBLEM'), ", false_positive:$false_positive") if (opt('debug'));

		# ignore non-resolved false_positive incidents (corner case)
		if ($value == TRIGGER_VALUE_TRUE && $last_trigger_value == TRIGGER_VALUE_TRUE)
		{
			my $idx = scalar(@incidents) - 1;

			if ($incidents[$idx]->{'false_positive'} != 0)
			{
				# replace with current
				$incidents[$idx]->{'eventid'} = $eventid;
				$incidents[$idx]->{'false_positive'} = $false_positive;
				$incidents[$idx]->{'start'} = cycle_start($clock, $delay);
			}
		}

		next if ($value == $last_trigger_value);

		if ($value == TRIGGER_VALUE_FALSE)
		{
			# event that closes the incident
			my $idx = scalar(@incidents) - 1;

			$incidents[$idx]->{'end'} = __cycle_end($clock, $delay);

			# start FIX-PH1
			# count failed tests within resolved incident
			my $rows_ref2 = db_select(
				"select count(*)".
				" from history_uint".
				" where itemid=$itemid".
					" and value=".DOWN.
					" and ".sql_time_condition($incidents[$idx]->{'start'}, $incidents[$idx]->{'end'}));
			$incidents[$idx]->{'failed_tests'} = $rows_ref2->[0]->[0];
			# end FIX-PH1
		}
		else
		{
			# event that starts an incident
			push(@incidents, __make_incident($eventid, $false_positive, cycle_start($clock, $delay)));
		}

		$last_trigger_value = $value;
	}

	# DEBUG
	if (opt('debug'))
	{
		foreach (@incidents)
		{
			my $eventid = $_->{'eventid'};
			my $inc_from = $_->{'start'};
			my $inc_till = $_->{'end'};
			my $false_positive = $_->{'false_positive'};

			my $str = "$eventid";
			$str .= " (false positive)" if ($false_positive != 0);
			$str .= ": " . ts_str($inc_from) . " ($inc_from) -> ";
			$str .= $inc_till ? ts_str($inc_till) . " ($inc_till)" : "null";

			dbg($str);
		}
	}

	return \@incidents;
}

# todo phase 1: taken from RSMSLV.pm phase 2
sub __get_service_status_itemids
{
	my $tld = shift;
	my $key = shift;

	my $key_condition = (substr($key, -1) eq ']' ? "i.key_='$key'" : "i.key_ like '$key%'");

	my $sql =
		"select h.host,i.itemid".
		" from items i,hosts h".
		" where i.hostid=h.hostid".
			" and h.status=".HOST_STATUS_MONITORED.
			" and i.status<>".ITEM_STATUS_DISABLED.
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

# todo phase 1: taken from RSMSLV.pm phase 2
# NB! THIS IS FIXED VERSION WHICH MUST REPLACE EXISTING ONE
# 1. Improves speed by using itemid -> probe hash.
sub __get_probe_results
{
	my $itemids_ref = shift;
	my $from = shift;
	my $till = shift;

	my %result;

	# generate list if itemids
	my %itemids;
	foreach my $probe (keys(%$itemids_ref))
	{
		$itemids{$itemids_ref->{$probe}} = $probe;
	}

	if (scalar(keys(%itemids)) != 0)
	{
		my $rows_ref = db_select(
			"select itemid,value,clock".
			" from history_uint".
			" where itemid in (".join(',', keys(%itemids)).")".
				" and " . sql_time_condition($from, $till).
			" order by clock");

		foreach my $row_ref (@$rows_ref)
		{
			my $itemid = $row_ref->[0];
			my $value = $row_ref->[1];
			my $clock = $row_ref->[2];

			my $probe = $itemids{$itemid};

			fail("internal error: Probe of item (itemid:$itemid) not found") unless (defined($probe));

			push(@{$result{$probe}}, {'value' => $value, 'clock' => $clock});
		}
	}

	return \%result;
}

# todo phase 1: taken from RSMSLV.pm phase 2
sub __get_rdds_dbl_itemids
{
	my $tld = shift;
	my $probe = shift;
	my $key_43_rtt = shift;
	my $key_80_rtt = shift;
	my $key_43_upd = shift;

	return __get_itemids_by_complete_key($tld, $probe, $key_43_rtt, $key_80_rtt, $key_43_upd);
}

# todo phase 1: taken from RSMSLV.pm phase 2
sub __get_rdds_str_itemids
{
	my $tld = shift;
	my $probe = shift;
	my $key_43_ip = shift;
	my $key_80_ip = shift;

	return __get_itemids_by_complete_key($tld, $probe, $key_43_ip, $key_80_ip);
}

# todo phase 1: taken from RSMSLV.pm phase 2
sub __get_rdds_test_values
{
	my $rdds_dbl_items_ref = shift;
	my $rdds_str_items_ref = shift;
	my $start = shift;
	my $end = shift;
	my $valuemaps = shift;
	my $delay = shift;

	# generate list of itemids
	my @dbl_itemids;
	foreach my $probe (keys(%$rdds_dbl_items_ref))
	{
		foreach my $itemid (keys(%{$rdds_dbl_items_ref->{$probe}}))
		{
			push(@dbl_itemids, $itemid);
		}
	}

	my @str_itemids;
	foreach my $probe (keys(%$rdds_str_items_ref))
	{
		foreach my $itemid (keys(%{$rdds_str_items_ref->{$probe}}))
		{
			push(@str_itemids, $itemid);
		}
	}

	return undef if (scalar(@dbl_itemids) == 0 || scalar(@str_itemids) == 0);

	my $result;
	my $target = '';

	my $dbl_rows_ref = db_select_binds("select itemid,value,clock from history where itemid=? and " . sql_time_condition($start, $end), \@dbl_itemids);

	foreach my $row_ref (sort { $a->[2] <=> $b->[2] } @$dbl_rows_ref)
	{
		my $itemid = $row_ref->[0];
		my $value = $row_ref->[1];
		my $clock = $row_ref->[2];

		my ($probe, $key) = __find_probe_key_by_itemid($itemid, $rdds_dbl_items_ref);

		fail("internal error: cannot get Probe-key pair by itemid:$itemid") unless (defined($probe) and defined($key));

		my ($interface, $type) = __get_rdds_item_details($key);

		fail("unknown RDDS interface in item $key (id:$itemid)") if (!defined($interface));
		fail("unknown RDDS test type in item key $key (id:$itemid)") if (!defined($type));

		if ($type ne JSON_TAG_RTT && $type ne JSON_TAG_UPD)
		{
			fail("internal error: unknown item key (itemid:$itemid), expected 'rtt' or 'upd' value involved in $interface test");
		}

		my $description;
		$value = int($value);

		if ($value < 0)
		{
			$description = get_detailed_result($valuemaps, $value);
			undef($value);
		}

		my $cycleclock = cycle_start($clock, $delay);

		# todo phase 1: NB! Do not use references, that won't add data!
		$result->{$cycleclock}->{$interface}->{$probe}->{$target}->[0]->{$type} = $value;
		$result->{$cycleclock}->{$interface}->{$probe}->{$target}->[0]->{JSON_TAG_CLOCK()} = $clock;
		$result->{$cycleclock}->{$interface}->{$probe}->{$target}->[0]->{JSON_TAG_DESCRIPTION()} = $description;
	}

	my $str_rows_ref = db_select_binds("select itemid,value,clock from history_str where itemid=? and " . sql_time_condition($start, $end), \@str_itemids);

	foreach my $row_ref (sort { $a->[2] <=> $b->[2] } @$str_rows_ref)
	{
		my $itemid = $row_ref->[0];
		my $value = $row_ref->[1];
		my $clock = $row_ref->[2];

		my ($probe, $key) = __find_probe_key_by_itemid($itemid, $rdds_str_items_ref);

		fail("internal error: cannot get Probe-key pair by itemid:$itemid") unless (defined($probe) and defined($key));

		my ($interface, $type) = __get_rdds_item_details($key);

		fail("unknown RDDS interface in item $key (id:$itemid)") if (!defined($interface));
		fail("unknown RDDS test type in item key $key (id:$itemid)") if (!defined($type));

		if ($type ne JSON_TAG_TARGET_IP)
		{
			fail("internal error: unknown item key (itemid:$itemid), expected item key representing the IP involved in $interface test");
		}

		my $cycleclock = cycle_start($clock, $delay);

		# todo phase 1: NB! Do not use references, that won't add data!
		$result->{$cycleclock}->{$interface}->{$probe}->{$target}->[0]->{$type} = $value;
	}

	return $result;
}

# todo phase 1: taken from RSMSLV.pm phase 2
sub __get_epp_test_values
{
	my $epp_dbl_items_ref = shift;
	my $epp_str_items_ref = shift;
	my $start = shift;
	my $end = shift;
	my $valuemaps = shift;
	my $delay = shift;

	my %result;

	my @dbl_itemids;
	foreach my $probe (keys(%$epp_dbl_items_ref))
	{
		foreach my $itemid (keys(%{$epp_dbl_items_ref->{$probe}}))
		{
			push(@dbl_itemids, $itemid);
		}
	}

	my @str_itemids;
	foreach my $probe (keys(%$epp_str_items_ref))
	{
		foreach my $itemid (keys(%{$epp_str_items_ref->{$probe}}))
		{
			push(@str_itemids, $itemid);
		}
	}

	return undef if (scalar(@dbl_itemids) == 0 || scalar(@str_itemids) == 0);

	my $result;
	my $target = '';

	my $dbl_rows_ref = db_select_binds("select itemid,value,clock from history where itemid=? and " . sql_time_condition($start, $end), \@dbl_itemids);

	foreach my $row_ref (sort { $a->[2] <=> $b->[2] } @$dbl_rows_ref)
	{
		my $itemid = $row_ref->[0];
		my $value = $row_ref->[1];
		my $clock = $row_ref->[2];

		my ($probe, $key) = __find_probe_key_by_itemid($itemid, $epp_dbl_items_ref);

		fail("internal error: cannot get Probe-key pair by itemid:$itemid")
			unless (defined($probe) and defined($key));

		my $command = __get_epp_dbl_type($key);
		my $cycleclock = cycle_start($clock, $delay);

		# TODO: EPP: it's not yet decided if 3 EPP RTTs
		# (login, info, update) are coming in one metric or 3
		# separate ones. Based on that decision in the future
		# the $rtt_low must be fetched for each command and
		# each of the metrics must be added by calling
		# __add_csv_test() 3 times, for each RTT.
		# NB! Sync with export.pl part that calls this function!

		# todo phase 1: NB! Do not use references, that won't add data!
		$result->{$cycleclock}->{JSON_INTERFACE_EPP}->{$probe}->{$target}->[0]->{$command} = $value;
		$result->{$cycleclock}->{JSON_INTERFACE_EPP}->{$probe}->{$target}->[0]->{JSON_TAG_CLOCK()} = $clock;
		$result->{$cycleclock}->{JSON_INTERFACE_EPP}->{$probe}->{$target}->[0]->{JSON_TAG_DESCRIPTION()} = get_detailed_result($valuemaps, $value);
	}

	my $str_rows_ref = db_select_binds("select itemid,value,clock from history_str where itemid=? and " . sql_time_condition($start, $end), \@str_itemids);

	foreach my $row_ref (sort { $a->[2] <=> $b->[2] } @$str_rows_ref)
	{
		my $itemid = $row_ref->[0];
		my $ip = $row_ref->[1];
		my $clock = $row_ref->[2];

		my ($probe, $key) = __find_probe_key_by_itemid($itemid, $epp_str_items_ref);

		fail("internal error: cannot get Probe-key pair by itemid:$itemid") unless (defined($probe) and defined($key));

		my $type = __get_epp_str_type($key);

		if ($type ne 'ip')
		{
			fail("internal error: unknown item key \"$key\", expected item key representing the IP involved in EPP test");
		}

		my $cycleclock = cycle_start($clock, $delay);

		# todo phase 1: NB! Do not use references, that won't add data!
		$result->{$cycleclock}->{JSON_INTERFACE_EPP}->{$probe}->{$target}->[0]->{JSON_TAG_TARGET_IP()} = $ip;
	}

	return $result;
}

# todo phase 1: taken from RSMSLV.pm phase 2
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

# todo phase 1: taken from RSMSLV.pm phase 2
sub __get_rdds_item_details
{
	my $key = shift;

	my @keyparts = split(/\./, substr($key, 0, index($key, '[')));

	my $interface;
	my $type;

	if (defined($keyparts[2]))
	{
		if ($keyparts[2] eq '43')
		{
			$interface = JSON_INTERFACE_RDDS43;
		}
		elsif ($keyparts[2] eq '80')
		{
			$interface = JSON_INTERFACE_RDDS80;
		}
	}

	if (defined($keyparts[3]))
	{
		if ($keyparts[3] eq 'rtt')
		{
			$type = JSON_TAG_RTT;
		}
		elsif ($keyparts[3] eq 'upd')
		{
			$type = JSON_TAG_UPD;
		}
		elsif ($keyparts[3] eq 'ip')
		{
			$type = JSON_TAG_TARGET_IP;
		}
	}

	return ($interface, $type);
}

# todo phase 1: taken from RSMSLV.pm phase 2
sub __get_epp_dbl_type
{
	my $key = shift;

	chop($key); # remove last char ']'

	# rsm.epp.rtt[{$RSM.TLD},login <-- returns "login" (other options: "update", "info")
        return substr($key, 23);
}

# todo phase 1: taken from RSMSLV.pm phase 2
sub __get_epp_str_type
{
	# NB! This is done for consistency, perhaps in the future there will be more string items, not just "ip".
	return 'ip';
}

# todo phase 1: taken from RSMSLV.pm phase 2
sub __interface_status
{
	my $interface = shift;
	my $value = shift;
	my $service_ref = shift;

	my $status;

	if ($interface eq JSON_INTERFACE_DNS)
	{
		$status = ($value >= $service_ref->{'minns'} ? AH_STATUS_UP : AH_STATUS_DOWN);
	}
	elsif ($interface eq JSON_INTERFACE_DNSSEC)
	{
		# TODO: dnssec status on a particular probe is not supported currently,
		# make this calculation in function __create_cycle_hash() for now.
	}
	elsif ($interface eq JSON_INTERFACE_RDDS43 || $interface eq JSON_INTERFACE_RDDS80)
	{
		my $rsm_rdds_probe_result = rsm_rdds_probe_result;

		$status = (exists($rsm_rdds_probe_result->[$value]->{$interface}) ? AH_STATUS_UP : AH_STATUS_DOWN);
	}
	else
	{
		fail("$interface: unsupported interface");
	}

	return $status;
}

# todo phase 1: taken from RSMSLV.pm phase 2
sub __get_rtt_low
{
	my $service = shift;
	my $proto = shift;	# for DNS
	my $command = shift;	# for EPP: 'login', 'info' or 'update'

	if ($service eq 'dns' || $service eq 'dnssec')
	{
		if ($proto == PROTO_UDP)
		{
			return get_macro_dns_udp_rtt_low();	# can be per TLD
		}
		elsif ($proto == PROTO_TCP)
		{
			return get_macro_dns_tcp_rtt_low();	# can be per TLD
		}
		else
		{
			fail("THIS SHOULD NEVER HAPPEN");
		}
	}

	if ($service eq 'rdds')
	{
		return get_macro_rdds_rtt_low();
	}

	if ($service eq 'epp')
	{
		return get_macro_epp_rtt_low($command);	# can be per TLD
	}

	fail("THIS SHOULD NEVER HAPPEN");
}

# todo phase 1: taken from RSMSLV.pm phase 2
sub get_readable_tld
{
	my $tld = shift;

	return ROOT_ZONE_READABLE if ($tld eq ".");

	return $tld;
}

# todo phase 1: taken from RSMSLV.pm phase 2
# NB! THIS IS FIXED VERSION WHICH MUST REPLACE EXISTING ONE
# (improved log message)
sub __no_cycle_result
{
	my $service = shift;
	my $avail_key = shift;
	my $clock = shift;
	my $details = shift;

	wrn(uc($service), " service availability result is missing for timestamp ", ts_str($clock), " ($clock).",
		" This means that either script was not executed or Zabbix server was",
		" not running at that time. In order to fix this problem please connect",
		" to appropriate server (check @<server_key> in the beginning of this message)",
		" and run the following script:");
	wrn("  /opt/zabbix/scripts/slv/$avail_key.pl --from $clock");
}

__END__

=head1 NAME

export.pl - export data from Zabbix database in CSV format

=head1 SYNOPSIS

export.pl --date <dd/mm/yyyy> [--warnslow <seconds>] [--dry-run] [--debug] [--probe <name>] [--tld <name>] [--service <name>] [--day <seconds>] [--shift <seconds>] [--help]

=head1 OPTIONS

=over 8

=item B<--date> dd/mm/yyyy

Process data of the specified day. E. g. 01/10/2015 .

=item B<--dry-run>

Print data to the screen, do not write anything to the filesystem.

=item B<--warnslow> seconds

Issue a warning in case an SQL query takes more than specified number of seconds. A floating-point number
is supported as seconds (i. e. 0.5, 1, 1.5 are valid).

=item B<--debug>

Run the script in debug mode. This means printing more information.

=item B<--probe> name

Specify probe name. All other probes will be ignored.

Implies option --dry-run.

=item B<--tld> name

Specify TLD. All other TLDs will be ignored.

Implies option --dry-run.

=item B<--service> name

Specify service. All other services will be ignored. Known services are: dns, dnssec, rdds, epp.

Implies option --dry-run.

=item B<--day> seconds

Specify length of the day in seconds. By default 1 day equals 86400 seconds.

Implies option --dry-run.

=item B<--shift> seconds

Move forward specified number of seconds from the date specified with --date.

Implies option --dry-run.

=item B<--help>

Print a brief help message and exit.

=back

=head1 DESCRIPTION

B<This program> will collect monitoring data from Zabbix database and save it in CSV format in different files.

=head1 EXAMPLES

./export.pl --date 01/10/2015

This will process monitoring data of the 1st of October 2015.

=cut
