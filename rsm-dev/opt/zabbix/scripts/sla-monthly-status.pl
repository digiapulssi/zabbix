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
use TLD_constants qw(:api :groups);
use Data::Dumper;
use DateTime;

use constant SLV_ITEM_KEY_DNS_DOWNTIME    => "rsm.slv.dns.downtime";
use constant SLV_ITEM_KEY_DNS_NS_DOWNTIME => "rsm.slv.dns.ns.downtime[%,%]";
use constant SLV_ITEM_KEY_RDDS_DOWNTIME   => "rsm.slv.rdds.downtime";
use constant SLV_ITEM_KEY_DNS_UDP_PFAILED => "rsm.slv.dns.udp.rtt.pfailed";
use constant SLV_ITEM_KEY_DNS_TCP_PFAILED => "rsm.slv.dns.tcp.rtt.pfailed";
use constant SLV_ITEM_KEY_RDDS_PFAILED    => "rsm.slv.rdds.rtt.pfailed";

sub main()
{
	parse_opts("year=i", "month=i");
	fail_if_running();
	set_slv_config(get_rsm_config());

	my ($from, $till) = get_time_limits();

	my %tlds = ();

	db_connect();

	my $slr = get_slrs();

	my ($items, $itemids_float, $itemids_uint) = get_items($slr);

	my $data_uint  = get_data($itemids_uint , "history_uint", $from, $till);
	my $data_float = get_data($itemids_float, "history"     , $from, $till);

	my @data = (@{$data_uint}, @{$data_float});

	foreach my $row (@data)
	{
		my ($itemid, $value, $clock) = @{$row};
		my ($tld, $itemkey) = @{$items->{$itemid}};


		if ($itemkey eq SLV_ITEM_KEY_DNS_DOWNTIME)
		{
			push(@{$tlds{$tld}}, ["DNS Service Availability", $value, $clock]);
		}
		elsif ($itemkey eq SLV_ITEM_KEY_RDDS_DOWNTIME)
		{
			push(@{$tlds{$tld}}, ["RDDS availability", $value, $clock]);
		}
		elsif ($itemkey eq SLV_ITEM_KEY_DNS_UDP_PFAILED)
		{
			push(@{$tlds{$tld}}, ["UDP DNS Resolution RTT", 100 - $value, $clock]);
		}
		elsif ($itemkey eq SLV_ITEM_KEY_DNS_TCP_PFAILED)
		{
			push(@{$tlds{$tld}}, ["TCP DNS Resolution RTT", 100 - $value, $clock]);
		}
		elsif ($itemkey eq SLV_ITEM_KEY_RDDS_PFAILED)
		{
			push(@{$tlds{$tld}}, ["RDDS query RTT", 100 - $value, $clock]);
		}
		else # if ($itemkey eq SLV_ITEM_KEY_DNS_NS_DOWNTIME
		{
			push(@{$tlds{$tld}}, ["DNS name server availability ($itemkey)", $value, $clock]);
		}
	}

	db_disconnect();

	foreach my $tld (keys(%tlds))
	{
		foreach my $data (@{$tlds{$tld}})
		{
			my ($item, $value, $clock) = @{$data};
			alert($tld, $item, $value, $clock);
		}
	}

	slv_exit(SUCCESS);
}

sub get_time_limits()
{
	my $year = getopt("year");
	my $month = getopt("month");

	if (defined($year) || defined($month))
	{
		if (!defined($year))
		{
			fail("--year is not specified");
		}
		if (!defined($month))
		{
			fail("--month is not specified");
		}
	}
	else
	{
		my $dt = DateTime->now();
		$dt->truncate('to' => 'month');
		$dt->subtract('months' => 1);

		$year = $dt->year();
		$month = $dt->month();
	}

	my $from = DateTime->new('year' => $year, 'month' => $month);
	my $till = DateTime->last_day_of_month('year' => $year, 'month' => $month, 'hour' => 23, 'minute' => 59, 'second' => 59);

	return $from->epoch(), $till->epoch();
}

sub get_slrs()
{
	my %slr;

	my $sql = "select macro, value from globalmacro where macro in (?, ?, ?, ?, ?, ?)";
	my $params = [
		'{$RSM.SLV.DNS.DOWNTIME}',
		'{$RSM.SLV.NS.DOWNTIME}',
		'{$RSM.SLV.DNS.UDP.RTT}',
		'{$RSM.SLV.DNS.TCP.RTT}',
		'{$RSM.SLV.RDDS.DOWNTIME}',
		'{$RSM.SLV.RDDS.RTT}'
	];
	my $rows = db_select($sql, $params);

	foreach my $row (@{$rows})
	{
		my ($macro, $value) = @{$row};

		$slr{'dns_downtime'}    = $value if ($macro eq '{$RSM.SLV.DNS.DOWNTIME}');
		$slr{'dns_ns_downtime'} = $value if ($macro eq '{$RSM.SLV.NS.DOWNTIME}');
		$slr{'dns_udp_rtt'}     = $value if ($macro eq '{$RSM.SLV.DNS.UDP.RTT}');
		$slr{'dns_tcp_rtt'}     = $value if ($macro eq '{$RSM.SLV.DNS.TCP.RTT}');
		$slr{'rdds_downtime'}   = $value if ($macro eq '{$RSM.SLV.RDDS.DOWNTIME}');
		$slr{'rdds_rtt'}        = $value if ($macro eq '{$RSM.SLV.RDDS.RTT}');
	}

	fail('global macro {$RSM.SLV.DNS.DOWNTIME} was not found')  unless (exists($slr{'dns_downtime'}));
	fail('global macro {$RSM.SLV.NS.DOWNTIME} was not found')   unless (exists($slr{'dns_ns_downtime'}));
	fail('global macro {$RSM.SLV.DNS.UDP.RTT} was not found')   unless (exists($slr{'dns_udp_rtt'}));
	fail('global macro {$RSM.SLV.DNS.TCP.RTT} was not found')   unless (exists($slr{'dns_tcp_rtt'}));
	fail('global macro {$RSM.SLV.RDDS.DOWNTIME} was not found') unless (exists($slr{'rdds_downtime'}));
	fail('global macro {$RSM.SLV.RDDS.RTT} was not found')      unless (exists($slr{'rdds_rtt'}));

	return \%slr;
}

sub get_items($)
{
	my $slr = shift;

	my $sql = "select items.itemid, items.key_, items.value_type, hosts.host" .
		" from items" .
			" left join hosts on hosts.hostid = items.hostid" .
			" left join hosts_groups on hosts_groups.hostid = hosts.hostid" .
		" where (items.key_ in (?, ?, ?, ?, ?) or items.key_ like ?) and" .
			" hosts_groups.groupid = ?";

	my $params = [
		SLV_ITEM_KEY_DNS_DOWNTIME,
		SLV_ITEM_KEY_RDDS_DOWNTIME,
		SLV_ITEM_KEY_DNS_UDP_PFAILED,
		SLV_ITEM_KEY_DNS_TCP_PFAILED,
		SLV_ITEM_KEY_RDDS_PFAILED,
		SLV_ITEM_KEY_DNS_NS_DOWNTIME,
		TLDS_GROUPID
	];

	my $rows = db_select($sql, $params);

	my %items         = (); # $items{$itemid} = [$key, $tld];
	my %itemids_float = (); # $itemids_float{$slr} = [$itemid1, $itemid2, ...]
	my %itemids_uint  = (); # $itemids_uint{$slr}  = [$itemid1, $itemid2, ...]

	foreach my $row (@{$rows})
	{
		my ($itemid, $key, $type, $tld) = @{$row};

		$items{$itemid} = [$tld, $key];

		if ($key eq SLV_ITEM_KEY_DNS_DOWNTIME)
		{
			fail("Unexpected item type (key: '$key', type: '$type'") unless ($type == ITEM_VALUE_TYPE_UINT64);
			push(@{$itemids_uint{$slr->{'dns_downtime'}}}, $itemid);
		}
		elsif ($key eq SLV_ITEM_KEY_RDDS_DOWNTIME)
		{
			fail("Unexpected item type (key: '$key', type: '$type'") unless ($type == ITEM_VALUE_TYPE_UINT64);
			push(@{$itemids_uint{$slr->{'rdds_downtime'}}}, $itemid);
		}
		elsif ($key eq SLV_ITEM_KEY_DNS_UDP_PFAILED)
		{
			fail("Unexpected item type (key: '$key', type: '$type'") unless ($type == ITEM_VALUE_TYPE_FLOAT);
			push(@{$itemids_float{$slr->{'dns_udp_rtt'}}}, $itemid);
		}
		elsif ($key eq SLV_ITEM_KEY_DNS_TCP_PFAILED)
		{
			fail("Unexpected item type (key: '$key', type: '$type'") unless ($type == ITEM_VALUE_TYPE_FLOAT);
			push(@{$itemids_float{$slr->{'dns_tcp_rtt'}}}, $itemid);
		}
		elsif ($key eq SLV_ITEM_KEY_RDDS_PFAILED)
		{
			fail("Unexpected item type (key: '$key', type: '$type'") unless ($type == ITEM_VALUE_TYPE_FLOAT);
			push(@{$itemids_float{$slr->{'rdds_rtt'}}}, $itemid);
		}
		else # if ($key eq SLV_ITEM_KEY_DNS_NS_DOWNTIME
		{
			push(@{$itemids_uint{$slr->{'dns_ns_downtime'}}}, $itemid);
			fail("Unexpected item type (key: '$key', type: '$type'") unless ($type == ITEM_VALUE_TYPE_UINT64);
		}
	}

	return \%items, \%itemids_float, \%itemids_uint;
}

sub get_data($$$$)
{
	my $itemids       = shift; # $itemids = {$slr => [$itemid1, $itemid2, ...], ...}
	my $history_table = shift; # "history" or "history_uint"
	my $from          = shift; # timestamp
	my $till          = shift; # timestamp

	my @itemids_params = (); # all itemids for use in subquery
	my @filter_params = ();  # [<itemid1, itemid2, ..., slr>, <itemid3, ..., slr>, ...]
	my $filter_sql = "";

	foreach my $slr (keys(%{$itemids}))
	{
		if ($filter_sql)
		{
			$filter_sql .= " or ";
		}

		my $itemids_placeholder = join(",", ("?") x scalar(@{$itemids->{$slr}}));
		$filter_sql .= "($history_table.itemid in ($itemids_placeholder) and value > ?)";

		push(@itemids_params, @{$itemids->{$slr}});
		push(@filter_params, @{$itemids->{$slr}});
		push(@filter_params, $slr);
	}

	my $itemids_placeholder = join(",", ("?") x scalar(@itemids_params));
	my $sql = "select $history_table.itemid, $history_table.value, $history_table.clock" .
		" from $history_table," .
			" (" .
				"select itemid, max(clock) as max_clock" .
				" from $history_table" .
				" where clock between ? and ? and" .
					" itemid in ($itemids_placeholder)" .
				" group by itemid" .
			") as history_max_clock" .
		" where $history_table.itemid = history_max_clock.itemid and" .
			" $history_table.clock = history_max_clock.max_clock and" .
			" ($filter_sql)";

	my @params = ($from, $till, @itemids_params, @filter_params);

	return db_select($sql, \@params);
}

sub alert($$$$)
{
	my $tld   = shift;
	my $item  = shift;
	my $value = shift;
	my $clock = shift;

	my $cmd = "python";
	my @args = ();

	push(@args, "/opt/slam/library/alertcom/script.py");
	push(@args, "zabbix alert");
	push(@args, "tld#PROBLEM#$tld#Monthly SLV: $item#$value");
	push(@args, DateTime->from_epoch('epoch' => $clock)->strftime('%Y.%m.%d %H:%M:%S %Z'));

	my $args = join(' ', map('"' . $_ . '"', @args));

	if (opt("dry-run"))
	{
		print "$cmd $args\n";
	}
	else
	{
		dbg("executing $cmd $args");
		my $out = qx($cmd @args 2>&1);

		if ($out)
		{
			dbg("output of $cmd:\n" . $out);
		}

		if ($? == -1)
		{
			fail("failed to execute '$cmd $args[0]': $!");
		}
		if ($? != 0)
		{
			fail("command '$cmd $args[0]' exited with value " . ($? >> 8));
		}
	}
}

main();

__END__

=head1 NAME

sla-monthly-status.pl - get SLV entries that violate SLA.

=head1 SYNOPSIS

sla-monthly-status.pl [--year <year>] [--month <month>] [--dry-run] [--debug] [--help]

=head1 OPTIONS

=over 8

=item B<--year> int

Specify year. If year is specified, month also has to be specified.

=item B<--month> int

Specify month. If month is specified, year also has to be specified.

=item B<--dry-run>

Print data to the screen, do not change anything in the system.

=item B<--debug>

Run the script in debug mode. This means printing more information.

=item B<--help>

Print a brief help message and exit.

=back

=cut
