#!/usr/bin/perl

use strict;
use warnings;

use Path::Tiny;
use lib path($0)->parent->realpath()->stringify();

use Data::Dumper;

use RSM;
use RSMSLV;
use TLD_constants qw(:api :config :groups :items);
use ApiHelper;

$Data::Dumper::Terse = 1;	# do not output names like "$VAR1 = "
$Data::Dumper::Pair = " : ";	# use separator instead of " => "
$Data::Dumper::Useqq = 1;	# use double quotes instead of single quotes
$Data::Dumper::Indent = 1;	# 1 provides less indentation instead of 2

# TODO: REMOVE ME
use constant SLV_UNAVAILABILITY_LIMIT => 49;

use constant TARGET_PLACEHOLDER => 'TARGET_PLACEHOLDER';

sub get_lastvalues_from_db();
sub calculate_cycle($$$$$$$$);
sub get_interfaces($$$);
sub probe_online_at_init();

# TODO: REMOVE ME
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

# TODO: REMOVE ME
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

parse_opts('tld=s', 'service=s', 'server-id=i');

setopt('nolog');

usage() if (opt('help'));

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

my $total_tlds = 0;

# last values actually contain entries from "lastvalue" table and represent:
# tld->probe->itemid->{
#     'clock' => ...,

# }
my %lastvalues;

# TODO: remove me
my $now = time() - 300;

db_connect();

my $cfg_minonline = get_macro_dns_probe_online();
my $cfg_minns = get_macro_minns();

fail("number of required working Name Servers is configured as $cfg_minns") if (1 > $cfg_minns);

my %delays;
$delays{'dns'} = $delays{'dnssec'} = get_dns_udp_delay($now);
$delays{'rdds'} = get_rdds_delay($now);

db_disconnect();

my %rtt_limits;

foreach (@server_keys)
{
	$server_key = $_;

	%lastvalues = ();

	db_connect($server_key);

	# initialize probe online cache
	probe_online_at_init();

	get_lastvalues_from_db();

	# rtt limits only considered for DNS currently
	$rtt_limits{'dns'} = get_history_by_itemid(
		CONFIGVALUE_DNS_UDP_RTT_HIGH_ITEMID,
		cycle_start($now, $delays{'dns'}),
		cycle_end($now, $delays{'dns'})
	);

	# probes available for every service
	my %probes;

	foreach (sort(keys(%lastvalues)))
	{
		$tld = $_;	# global variable

		foreach my $service (sort(keys(%{$lastvalues{$tld}})))
		{
			next unless (tld_service_enabled($tld, $service, $now));

			my $interfaces_ref = get_interfaces($tld, $service, $now);

			$probes{$service} = get_probes($service) unless (defined($probes{$service}));

			calculate_cycle($tld, $service, $lastvalues{$tld}{$service}, $now, $delays{$service}, $rtt_limits{$service}, $probes{$service}, $interfaces_ref);
		}
	}

	my $json;

	if (ah_get_recent_measurement("dummy2", "rdds", cycle_start($now - 120, $delays{'rdds'}), \$json) != AH_SUCCESS)
	{
		fail("cannot get recent measurement: ", ah_get_error());
	}

	print("SUCCESS:\n", Dumper($json));

	db_disconnect();
}

sub get_service_from_key($)
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

sub get_lastvalues_from_db()
{
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
			" and g.groupid=".TLD_PROBE_RESULTS_GROUPID.
		" union ".
		"select h.host,i.itemid,i.key_,i.value_type,l.clock".
		" from lastvalue_str l,items i,hosts h,hosts_groups g".
		" where g.hostid=h.hostid".
			" and h.hostid=i.hostid".
			" and i.itemid=l.itemid".
			" and i.type in (".ITEM_TYPE_SIMPLE.",".ITEM_TYPE_TRAPPER.")".
			" and i.status=".ITEM_STATUS_ACTIVE.
			" and h.status=".HOST_STATUS_MONITORED.
			" and g.groupid=".TLD_PROBE_RESULTS_GROUPID
	);

	foreach my $row_ref (@{$rows_ref})
	{
		my $host = $row_ref->[0];
		my $itemid = $row_ref->[1];
		my $key = $row_ref->[2];
		my $value_type = $row_ref->[3];
		my $clock = $row_ref->[4];

		next if (substr($key, 0, length("rsm.dns.tcp")) eq "rsm.dns.tcp");

		my $index = index($host, ' ');

		$tld = substr($host, 0, $index);	# $tld is global variable
		my $probe = substr($host, $index + 1);

		my $service = get_service_from_key($key);

		fail("cannot identify item \"$key\" at host \"$host\"") unless ($service);

#		dbg($tld, "-", $service);

		foreach my $serv ($service eq 'dns' ? ('dns', 'dnssec') : ($service))
		{
#			$lastvalues{$tld}{$probe}{$itemid} = {

			$lastvalues{$tld}{$serv}{$probe}{$itemid} = {
				'key' => $key,
				'value_type' => $value_type,
				'clock' => $clock
			};
		}

#		print(ts_str($clock), " $tld,$probe ($host) | $key\n");
	}
}

# TODO: REMOVE ME
sub __get_history_table_by_value_type($)
{
	my $value_type = shift;

	return "history_uint" if (!defined($value_type) || $value_type == ITEM_VALUE_TYPE_UINT64);	# default
	return "history" if ($value_type == ITEM_VALUE_TYPE_FLOAT);
	return "history_str" if ($value_type == ITEM_VALUE_TYPE_STR);

	fail("THIS_SHOULD_NEVER_HAPPEN");
}

# TODO: MOVE ME TO ApiHelper.pm
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

		foreach my $clock (sort(keys(%{$src->{$ns}{'metrics'}})))
		{
			my $test = $src->{$ns}{'metrics'}{$clock};

			dbg("ns:$ns ip:", $test->{'targetIP'} // "UNDEF", " clock:", $test->{'testDateTime'} // "UNDEF", " rtt:", $test->{'rtt'} // "UNDEF");

			my $metric = {
				'testDateTime'	=> $clock,
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

		$test_data_ref->{'status'} //= AH_CITY_NO_RESULT;

		push(@{$dst}, $test_data_ref);
	}
}

#
# Probe status value cache, itemid - ID of PROBE_KEY_ONLINE item
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
			" from " . __get_history_table_by_value_type(ITEM_VALUE_TYPE_UINT64).
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
	my $probe_items = shift;
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

	foreach my $probe (keys(%{$probe_items}))
	{
		my (@itemids_uint, @itemids_float, @itemids_str);

		#
		# collect IDs of probe items, separate them by value_type to fetch values from history later
		#

		map {
			my $i = $probe_items->{$probe}{$_};

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
		} (keys(%{$probe_items->{$probe}}));

		next if (@itemids_uint == 0);

		#
		# fetch them separately
		#

		my $rows_ref = db_select(
			"select itemid,value".
			" from " . __get_history_table_by_value_type(ITEM_VALUE_TYPE_UINT64).
			" where itemid in (" . join(',', @itemids_uint) . ")".
				" and " . sql_time_condition($from, $till)
		);

		# {
		#     TODO: description of data structure
		# }
		my %values;

		map {push(@{$values{$_->[0]}}, int($_->[1]))} (@{$rows_ref});

		# skip cycles that do not have test result
		next if (scalar(keys(%values)) == 0);

#		print("  $probe:\n");

		my $service_up = 1;

		foreach my $itemid (keys(%values))
		{
			my $key = $probe_items->{$probe}{$itemid}{'key'};

			if (substr($key, 0, length("rsm.rdds")) eq "rsm.rdds")
			{
				foreach my $value (@{$values{$itemid}})
				{
					$service_up = 0 unless ($value == RDDS_UP);

					my $interface = AH_INTERFACE_RDDS43;

					if (!defined($tested_interfaces{$interface}{$probe}{'status'}) ||
						$tested_interfaces{$interface}{$probe}{'status'} == AH_CITY_DOWN)
					{
						$tested_interfaces{$interface}{$probe}{'status'} =
							($value == RDDS_UP || $value == RDDS_43_ONLY ? AH_CITY_UP : AH_CITY_DOWN);
					}

					$interface = AH_INTERFACE_RDDS80;

					if (!defined($tested_interfaces{$interface}{$probe}{'status'}) ||
						$tested_interfaces{$interface}{$probe}{'status'} == AH_CITY_DOWN)
					{
						$tested_interfaces{$interface}{$probe}{'status'} =
							($value == RDDS_UP || $value == RDDS_80_ONLY ? AH_CITY_UP : AH_CITY_DOWN);
					}
				}
			}
			elsif (substr($key, 0, length("rdap")) eq "rdap")
			{
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
		# collect clock->rtt keypairs (and clock->ip for DNS because for DNS metrics IP (and target) is taken from item key)
		#

		$rows_ref = db_select(
			"select itemid,value,clock".
			" from " . __get_history_table_by_value_type(ITEM_VALUE_TYPE_FLOAT).
			" where itemid in (" . join(',', @itemids_float) . ")".
				" and " . sql_time_condition($from, $till)
		);

		%values = ();

		map {push(@{$values{$_->[0]}}, {'clock' => $_->[2], 'value' => int($_->[1])})} (@{$rows_ref});

		foreach my $itemid (keys(%values))
		{
			my $i = $probe_items->{$probe}{$itemid};

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
					$target = TARGET_PLACEHOLDER;	# for non-DNS service "target" is NULL, but use placeholder
				}

				$tested_interfaces{$interface}{$probe}{'testData'}{$target}{'metrics'}{$value_ref->{'clock'}}{'rtt'} = $value_ref->{'value'};
				$tested_interfaces{$interface}{$probe}{'testData'}{$target}{'metrics'}{$value_ref->{'clock'}}{'ip'} = $ip;
			}
		}

		next if (@itemids_str == 0);

		#
		# collect clock->ip keypairs for non-DNS services
		#

		$rows_ref = db_select(
			"select itemid,value,clock".
			" from " . __get_history_table_by_value_type(ITEM_VALUE_TYPE_STR).
			" where itemid in (" . join(',', @itemids_str) . ")".
				" and " . sql_time_condition($from, $till)
		);

		%values = ();

		map {push(@{$values{$_->[0]}}, {'clock' => $_->[2], 'value' => $_->[1]})} (@{$rows_ref});

		foreach my $itemid (keys(%values))
		{
			my $i = $probe_items->{$probe}{$itemid};

			foreach my $value_ref (@{$values{$itemid}})
			{
				my $interface = ah_get_interface($i->{'key'});

				my $target = TARGET_PLACEHOLDER;	# for non-DNS service "target" is NULL, but use placeholder

				$tested_interfaces{$interface}{$probe}{'testData'}{$target}{'metrics'}{$value_ref->{'clock'}}{'ip'} = $value_ref->{'value'};
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

				undef($tested_interfaces{$interface}{$probe}{'testData'});
			}
			elsif (!defined($tested_interfaces{$interface}{$probe}{'status'}))
			{
				$tested_interfaces{$interface}{$probe}{'status'} = AH_CITY_NO_RESULT;

				undef($tested_interfaces{$interface}{$probe}{'testData'});
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

	my $detailed_info = sprintf("%d/%d positive, %.3f%%, %d online", $probes_with_positive, $probes_with_results, $perc, $probes_online);

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

	dbg("cycle: $json->{'status'} ($detailed_info)");

	if (opt('dry-run'))
	{
		print(Dumper($json));
		return;
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

__END__

=head1 NAME

sla-api-current.pl - generate recent SLA API measurement files for newly collected data

=head1 SYNOPSIS

sla-api-current.pl [--tld <tld>] [--service <name>] [--server-id <id>] [--debug] [--dry-run] [--help]

=head1 OPTIONS

=over 8

=item B<--tld> tld

Optionally specify TLD.

=item B<--service> name

Optionally specify service, one of: dns, dnssec, rdds

=item B<--server-id>

Optionally specify the server ID to query the data from.

=item B<--debug>

Run the script in debug mode. This means printing more information.

=item B<--help>

Print a brief help message and exit.

=back

=head1 DESCRIPTION

B<This program> will generate the most recent SLA API measurement files for newly collected monitoring data.

=head1 EXAMPLES

./$0 --tld example --dry-run

Print what would have been done to generate recent measurement files of tld "example".

=cut
