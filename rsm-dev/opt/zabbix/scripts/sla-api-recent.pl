#!/usr/bin/perl

use strict;
use warnings;

use Path::Tiny;
use lib path($0)->parent->realpath()->stringify();

use Data::Dumper;

use RSM;
use RSMSLV;
use TLD_constants qw(:api :config);
use ApiHelper;

# TODO: REMOVE ME
use constant SLV_UNAVAILABILITY_LIMIT => 49;

sub calculate_cycle($$$$);

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

my %last_values;

my $from = truncate_from(time() - 600);

db_connect();

my $cfg_minonline = get_macro_dns_probe_online();
my $cfg_minns = get_macro_minns();

fail("number of required working Name Servers is configured as $cfg_minns") if (1 > $cfg_minns);

#my %valuemaps;
#$valuemaps{+AH_INTERFACE_DNS} = $valuemaps{+AH_INTERFACE_DNSSEC} = get_valuemaps('dns');
#$valuemaps{+AH_INTERFACE_RDDS43} = $valuemaps{+AH_INTERFACE_RDDS80} = get_valuemaps('rdds');
#$valuemaps{+AH_INTERFACE_RDAP} = get_valuemaps('rdap');

my %delays;
$delays{'dns'} = $delays{'dnssec'} = get_dns_udp_delay($from);;
$delays{'rdds'} = get_rdds_delay($from);;

db_disconnect();

foreach (@server_keys)
{
	$server_key = $_;

	%last_values = ();

	db_connect($server_key);

	get_last_values_from_db();

	calculate_cycle($tld, 'rdds', $from, $delays{'rdds'});

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

sub get_last_values_from_db
{
	my $rows_ref = db_select(
		"select h.host,i.itemid,i.key_,i.value_type,l.clock".
		" from lastvalue l,items i,hosts h,hosts_groups g".
		" where g.hostid=h.hostid".
			" and h.hostid=i.hostid".
			" and i.itemid=l.itemid".
			" and i.type in (".ITEM_TYPE_SIMPLE.",".ITEM_TYPE_TRAPPER.")".
			" and i.status=".ITEM_STATUS_ACTIVE.
			" and h.status=".HOST_STATUS_MONITORED.
			" and g.groupid=190");

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
		$last_values{$tld}{$probe}{$itemid} = {'key' => $key, 'value_type' => $value_type, 'clock' => $clock, 'service' => $service};

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

sub calculate_cycle($$$$)
{
	my $tld = shift;
	my $service = shift;
	my $clock = shift;
	my $delay = shift;

	# TODO: if service = "dns" also calculate dnssec!
	# TODO: consider probes offline
	# TODO: consider probes with results
	# TODO: add target support

	my $from = cycle_start($clock, $delay);
	my $till = $from + $delay - 1;

	my $json = {'tld' => $tld, 'service' => $service, 'cycleCalculationDateTime' => $from};

	# (
	#     'interface' => (
	#         'probe' => (
	#             'status' => 'Up',
	#             'testData' => {
	#                 'ns1.example.com' => (
	#                     'status' => 'Up',
	#                     'metrics' => [
	#                         {
	#                             'testDateTime' => 1423424234,
	#                             'targetIP' => '1.2.3.4',
	#                             'rtt' => 23,
	#                         },
	#                         {
	#                             'testDateTime' => 1423424234,
	#                             'targetIP' => '2001:DB8::1',
	#                             'rtt' => 42,
	#                         }
	#                     ]
	#                 )
	#             )
	#         )
	#     )
	# )
	my %tested_interfaces;

	print("$tld:\n");

	my $probes_with_results = 0;
	my $probes_with_positive = 0;

	foreach my $probe (keys(%{$last_values{$tld}}))
	{
		my (@itemids_uint, @itemids_float);

		# separate uint values from float
		map {
			my $i = $last_values{$tld}{$probe}{$_};

			if ($i->{'service'} eq $service)
			{
				$i->{'value_type'} == ITEM_VALUE_TYPE_UINT64 ?
					push(@itemids_uint, $_) :
					push(@itemids_float, $_)
			}
		} (keys(%{$last_values{$tld}{$probe}}));

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

		map {push(@{$values{$_->[0]}}, {'clock' => $_->[2], 'value' => int($_->[1])})} (@{$rows_ref});

		next if (scalar(keys(%values)) == 0);

		print("  $probe:\n");

		my $service_successful = 1;

		foreach my $itemid (keys(%values))
		{
			my $key = $last_values{$tld}{$probe}{$itemid}{'key'};

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
			my $i = $last_values{$tld}{$probe}{$itemid};

			foreach my $value_ref (@{$values{$itemid}})
			{
				my $interface = ah_get_interface($i->{'key'});

				#$tr_ref->{'testedInterface'} = [
				#	{
				#		'interface'	=> $interface,
				#		'probes'	=> []
				#	}
				#];

				my $metric = {
					'testDateTime' => $value_ref->{'clock'}
				};

				if ($value_ref->{'value'} < 0)
				{
					$metric->{'rtt'} = undef;
					$metric->{'result'} = $value_ref->{'value'};
				}
				else
				{
					$metric->{'rtt'} = $value_ref->{'value'};
					$metric->{'result'} = undef;
				}

				# TODO: target
				my $target = 'TODO-target';

				push(@{$tested_interfaces{$interface}{$probe}{'testData'}{$target}{'metrics'}}, $metric);
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
				'status'	=> $tested_interfaces{$interface}{$probe}{'status'},
				'testData'	=> []
			};

			foreach my $target (keys(%{$tested_interfaces{$interface}{$probe}{'testData'}}))
			{
				my $test_data_ref = {
					'target'	=> $target,
					'status'	=> undef,
					'metrics'	=> []
				};

				foreach my $metric (@{$tested_interfaces{$interface}{$probe}{'testData'}{$target}{'metrics'}})
				{
					push(@{$test_data_ref->{'metrics'}}, $metric);
				}

				push(@{$probe_ref->{'testData'}}, $test_data_ref);
			}

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
