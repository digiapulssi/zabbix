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

# TODO: REMOVE ME
use constant SLV_UNAVAILABILITY_LIMIT => 49;

use constant TARGET_PLACEHOLDER => 'TARGET_PLACEHOLDER';

sub calculate_cycle($$$$);

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

my %lastvalues;

my $from = time() - 600;

db_connect();

my $cfg_minonline = get_macro_dns_probe_online();
my $cfg_minns = get_macro_minns();

fail("number of required working Name Servers is configured as $cfg_minns") if (1 > $cfg_minns);

#my %valuemaps;
#$valuemaps{+AH_INTERFACE_DNS} = $valuemaps{+AH_INTERFACE_DNSSEC} = get_valuemaps('dns');
#$valuemaps{+AH_INTERFACE_RDDS43} = $valuemaps{+AH_INTERFACE_RDDS80} = get_valuemaps('rdds');
#$valuemaps{+AH_INTERFACE_RDAP} = get_valuemaps('rdap');

my %delays;
$delays{'dns'} = $delays{'dnssec'} = get_dns_udp_delay($from);
$delays{'rdds'} = get_rdds_delay($from);

db_disconnect();

# TODO
my %rtt_limits;

foreach (@server_keys)
{
	$server_key = $_;

	%lastvalues = ();

	db_connect($server_key);

	get_lastvalues_from_db();

	# TODO
	$rtt_limits{'dns'} = get_history_by_itemid(
		CONFIGVALUE_DNS_UDP_RTT_HIGH_ITEMID,
		cycle_start($from, $delays{'dns'}),
		cycle_end($from, $delays{'dns'})
	);

	calculate_cycle($tld, 'rdds', $from, $delays{'rdds'});
	calculate_cycle($tld, 'dns', $from, $delays{'dns'});

	db_disconnect();
}

sub get_service_from_key
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

sub get_lastvalues_from_db
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

		#TODO: get service from key
		$lastvalues{$tld}{$probe}{$itemid} = {'key' => $key, 'value_type' => $value_type, 'clock' => $clock, 'service' => $service};

#		print(ts_str($clock), " $tld,$probe ($host) | $key\n");
	}
}

# TODO: REMOVE ME
sub __get_history_table_by_value_type
{
	my $value_type = shift;

	return "history_uint" if (!defined($value_type) || $value_type == ITEM_VALUE_TYPE_UINT64);	# default
	return "history" if ($value_type == ITEM_VALUE_TYPE_FLOAT);
	return "history_str" if ($value_type == ITEM_VALUE_TYPE_STR);

	fail("THIS_SHOULD_NEVER_HAPPEN");
}

# TODO: MOVE ME TO ApiHelper.pm
sub fill_test_data_dns($$$)
{
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

#		foreach my $test (@{$src->{$ns}})
		foreach my $clock (keys(%{$src->{$ns}{'metrics'}}))
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
			elsif (is_service_error_desc('dns', $test->{'rtt'}))
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

sub calculate_cycle($$$$)
{
	my $tld = shift;
	my $service = shift;
	my $cycle_clock = shift;
	my $delay = shift;

	# TODO: if service = "dns" also calculate dnssec!
	# TODO: consider probes offline
	# TODO: consider probes with results
	# TODO: add target support

	my $from = cycle_start($cycle_clock, $delay);
	my $till = cycle_end($cycle_clock, $delay);

	my $json = {'tld' => $tld, 'service' => $service, 'cycleCalculationDateTime' => $cycle_clock};

	my %tested_interfaces;

	print("$tld:\n");

	my $probes_with_results = 0;
	my $probes_with_positive = 0;

	foreach my $probe (keys(%{$lastvalues{$tld}}))
	{
		my (@itemids_uint, @itemids_float, @itemids_str);

		# separate itemids by value_type
		map {
			my $i = $lastvalues{$tld}{$probe}{$_};

			if ($i->{'service'} eq $service)
			{
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
			}
		} (keys(%{$lastvalues{$tld}{$probe}}));

		next if (@itemids_uint == 0);

		# and fetch them separately
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

		print("  $probe:\n");

		my $service_successful = 1;

		foreach my $itemid (keys(%values))
		{
			my $key = $lastvalues{$tld}{$probe}{$itemid}{'key'};

			# TODO: add support for results:
			# - Offline
			# - No result

			if (substr($key, 0, length("rsm.rdds")) eq "rsm.rdds")
			{
				foreach my $value (@{$values{$itemid}})
				{
					$service_successful = 0 unless ($value == RDDS_UP);

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

				$service_successful = 0 if ($city_status eq AH_CITY_DOWN);

				$tested_interfaces{$interface}{$probe}{'status'} = $city_status;

			}
			elsif (substr($key, 0, length("rsm.dns.udp")) eq "rsm.dns.udp")
			{
				my $interface = AH_INTERFACE_DNS;

				my $city_status;

				foreach my $value (@{$values{$itemid}})
				{
					last if (defined($city_status) && $city_status eq AH_CITY_UP);

					$city_status = ($value >= $cfg_minns ? AH_CITY_UP : AH_CITY_DOWN);
				}

				$service_successful = 0 if ($city_status eq AH_CITY_DOWN);

				$tested_interfaces{$interface}{$probe}{'status'} = $city_status;
			}
			else
			{
				fail("unexpected key \"$key\" when trying to identify Service interface");
			}
		}

		if ($service_successful)
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
			my $i = $lastvalues{$tld}{$probe}{$itemid};

			foreach my $value_ref (@{$values{$itemid}})
			{
				my $interface = ah_get_interface($i->{'key'});

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
			my $i = $lastvalues{$tld}{$probe}{$itemid};

			foreach my $value_ref (@{$values{$itemid}})
			{
				my $interface = ah_get_interface($i->{'key'});

				my $target = TARGET_PLACEHOLDER;	# for non-DNS service "target" is NULL, but use placeholder

				$tested_interfaces{$interface}{$probe}{'testData'}{$target}{'metrics'}{$value_ref->{'clock'}}{'ip'} = $value_ref->{'value'};
			}
		}
	}

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

			fill_test_data_dns(
				$tested_interfaces{$interface}{$probe}{'testData'},
				$probe_ref->{'testData'},
				$rtt_limits{$service}
			);

			# foreach my $target (keys(%{$tested_interfaces{$interface}{$probe}{'testData'}}))
			# {
			# 	my $test_data_ref = {
			# 		'target'	=> ($target eq TARGET_PLACEHOLDER ? undef : $target),	# for non-DNS service "target" is NULL
			# 		'status'	=> undef,	# TODO: Target status
			# 		'metrics'	=> []
			# 	};

			# 	foreach my $clock (keys(%{$tested_interfaces{$interface}{$probe}{'testData'}{$target}{'metrics'}}))
			# 	{
			# 		my $clock_metric = $tested_interfaces{$interface}{$probe}{'testData'}{$target}{'metrics'}{$clock};

			# 		my ($rtt, $result);

			# 		if ($clock_metric->{'rtt'} < 0)
			# 		{
			# 			# rtt must be left undefined
			# 			$result = $clock_metric->{'rtt'};
			# 		}
			# 		else
			# 		{
			# 			# result must be left undefined
			# 			$rtt = $clock_metric->{'rtt'};
			# 		}

			# 		my $metric_ref = {
			# 			'rtt'		=> $rtt,
			# 			'ip'		=> $clock_metric->{'ip'},
			# 			'result'	=> $result,
			# 			'testDateTime'	=> $clock
			# 		};

			# 		push(@{$test_data_ref->{'metrics'}}, $metric_ref);
			# 	}

			# 	push(@{$probe_ref->{'testData'}}, $test_data_ref);
			# }

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

	my $detailed_info = sprintf("%d/%d positive, %.3f%%", $probes_with_positive, $probes_with_results, $perc);

	if ($perc > SLV_UNAVAILABILITY_LIMIT)
	{
		# TODO: Up-inconclusive-no-data
		# TODO: Up-inconclusive-no-probes
		print("cycle: Up ($detailed_info)\n");
		$json->{'status'} = 'Up';
	}
	else
	{
		print("cycle: Down ($detailed_info)\n");
		$json->{'status'} = 'Down';
	}

	print(Dumper($json));
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
