#!/usr/bin/perl

BEGIN
{
	our $MYDIR = $0; $MYDIR =~ s,(.*)/.*,$1,; $MYDIR = '.' if ($MYDIR eq $0);
	our $MYDIR2 = $0; $MYDIR2 =~ s,(.*)/.*/.*,$1,; $MYDIR2 = '..' if ($MYDIR2 eq $0);
}
use lib $MYDIR;
use lib $MYDIR2;

use strict;
use warnings;

use Data::Dumper;
use TLD_constants qw(:api);
use RSM;
use RSMSLV;

my @months = (
	qw/Jan Feb Mar Apr May Jun Jul Aug Sep Oct Nov Dec/
);

parse_opts('tld=s', 'service=s', 'from=n', 'till=n');

setopt('nolog');
setopt('dry-run');

my $delay;

set_slv_config(get_rsm_config());

db_connect();

fail("TLD must be specified") unless (opt('tld'));
fail("TLD \"" . getopt('tld') . "\" not found") unless (tld_exists(getopt('tld')));

if (opt('service'))
{
	if (getopt('service') eq 'dns')
	{
		$delay = get_dns_udp_delay();
	}
	elsif (getopt('service') eq 'rdds')
	{
		$delay = get_rdds_delay();
	}
	else
	{
		fail("unknown service \"", getopt('service'), "\" expected: dns, rdds");
	}
}
else
{
	$delay = 60;
}

$tld = getopt('tld');
my $from = getopt('from');
my $till = getopt('till');

usage() unless ($tld);

my $now = time();

print("Current time: ", __ts_human($now), "\n");

if (!$from)
{
	$from = cycle_start($now - 5 * $delay, $delay);
}
elsif (substr($from, 0, length("-")) eq "-")
{
	my $mult = substr($from, length("-"));

	$from = cycle_start($now, $delay);

	$from -= ($mult * $delay);
}

if (!$till)
{
	$till = cycle_end($now, $delay);
}

my $incidents;

if (opt('service'))
{
	my $itemid = get_itemid_by_host(getopt('tld'), 'rsm.slv.rdds.avail');

	$incidents = get_incidents($itemid, get_rdds_delay(), $from, $till);
}

my $service_cond = (opt('service') ? " and i.key_ like '%." . getopt('service') . ".%'" : "");

my %uint_itemids;

my $rows_ref = db_select(
	"select i.itemid,i.key_".
	" from items i,hosts h".
	" where i.hostid=h.hostid".
		$service_cond.
		" and h.host='$tld'".
		" and i.value_type=" . ITEM_VALUE_TYPE_UINT64);

fail("cannot find SLV items like Service availability") if (scalar(@{$rows_ref}) == 0);

map {$uint_itemids{$_->[0]} = $_->[1]} (@{$rows_ref});

my %float_itemids;

$rows_ref = db_select(
	"select i.itemid,i.key_".
	" from items i,hosts h".
	" where i.hostid=h.hostid".
		$service_cond.
		" and h.host='$tld'".
		" and i.value_type=" . ITEM_VALUE_TYPE_FLOAT);

fail("cannot find SLV items like Service rolling week") if (scalar(@{$rows_ref}) == 0);

map {$float_itemids{$_->[0]} = $_->[1]} (@{$rows_ref});

$rows_ref = db_select(
	"select itemid,clock,value".
	" from ".
		"(select itemid,clock,value".
		" from history_uint".
		" where itemid in (" . join(',', keys(%uint_itemids)) . ")".
			" and clock between $from and $till".
		") as a".
	" union all ".
	"select itemid,clock,value".
	" from ".
		"(select itemid,clock,value".
		" from history".
		" where itemid in (" . join(',', keys(%float_itemids)) . ")".
			" and clock between $from and $till".
		") as b".
	" order by clock,itemid");

my %cycles;
my %uniq_items;

foreach my $row_ref (@$rows_ref)
{
	my $itemid = $row_ref->[0];
	my $clock = $row_ref->[1];
	my $value = $row_ref->[2];

	my $key;

	if ($uint_itemids{$itemid})
	{
		$key = $uint_itemids{$itemid};
	}
	else
	{
		$key = $float_itemids{$itemid};
	}

	my $service;

	if ($key =~ '.dns.')
	{
		$service = 'DNS';
	}
	elsif ($key =~ '.dnssec.')
	{
		$service = 'DNSSEC';
	}
	elsif ($key =~ '.rdds.')
	{
		$service = 'RDDS';
	}
	else
	{
		$service = "*UNKNOWN* ($key)";
	}

	my $type;

	if ($key =~ '.avail')
	{
		$type = 'Availability';
	}
	elsif ($key =~ '.downtime')
	{
		$type = 'Downtime';
	}
	elsif ($key =~ '.rollweek')
	{
		$type = 'Rolling week';
	}
	else
	{
		if (!($service =~ "$key"))
		{
			$type = "*UNKNOWN* ($key)";
		}
		else
		{
			$type = 'alr';
		}
	}

	my $cycle_clock = cycle_start($clock, $delay);

	$value = "($clock) $value" if (opt('debug'));
	$service = "($itemid) $service" if (opt('debug'));

	my $item = "$service $type";

	$cycles{$cycle_clock}{$item} = $value;

	$uniq_items{$item} = 1;
}

foreach my $cycle_clock (sort {$b <=> $a} (keys(%cycles)))
{
	__print_header($cycle_clock, __in_incident($cycle_clock));

	foreach my $key (sort(keys(%uniq_items)))
	{
		printf("%-30s %s\n", $key, $cycles{$cycle_clock}{$key} // "");
	}
}

#printf("%30s\n", "calculated values: $cycle_values") if (defined($cycle_values));

sub __ts_human
{
	my $ts = shift;

	$ts = time() unless ($ts);

	# sec, min, hour, mday, mon, year, wday, yday, isdst
	my ($sec, $min, $hour, $mday, $mon, $year) = localtime($ts);

	return sprintf("%.2d %s %.4d %.2d:%.2d", $mday, $months[$mon], $year + 1900, $hour, $min);
}

sub __print_header
{
	my $clock = shift;
	my $in_incident = shift;

	my $incident_str;

	if ($in_incident == 1)
	{
		$incident_str = ", INCIDENT";
	}
	elsif ($in_incident == 2)
	{
		$incident_str = ", incident (FP)";
	}
	else
	{
		$incident_str = "";
	}

	print("====================================================================================================\n");
	print("Cycle ", __ts_human($clock), " ($clock)$incident_str\n");
	print("----------------------------------------------------------------------------------------------------\n");
}

# 0 - clock not in incident
# 1 - clock in incident
# 2 - clock in false_positive incident
sub __in_incident
{
	my $clock = shift || die("__in_incident() called without specifying clock");

	foreach my $i (@{$incidents})
	{
		if ($clock >= $i->{'start'} && (!defined($i->{'end'}) || $clock <= $i->{'end'}))
		{
			return 2 if ($i->{'false_positive'});

			return 1;
		}
	}

	return 0;
}

__END__

=head1 NAME

slv-results.pl - show accumulated results stored by cron

=head1 SYNOPSIS

slv-results.pl --tld <tld> [--service <service>] [--from <unixtime>] [--till <unixtime>] [--debug] [--help]

=head1 OPTIONS

=over 8

=item B<--tld> tld

Show results of specified TLD.

=item B<--service> tld

Show results of specified service.

=item B<--from> timestamp

There are 2 types of value you can specify with --from:

  - Unix timestamp within the cycle
  - negative number representing number of cycles to go back from @from

By default @from is the last complete 5-minute cycle. E. g.

--from -2

tells the script to move 2 cycles back from the last complete 5-minute cycle.

=item B<--till> timestamp

Specify Unix timestamp within the cycl (default: end of 5-minute cycle since @from).

=item B<--debug>

Run the script in debug mode. This means printing more information.

=item B<--help>

Print a brief help message and exit.

=back

=head1 DESCRIPTION

B<This program> will show results of a TLD stored by cron job.

=head1 EXAMPLES

./slv-results.pl --tld example --from $(date +%s -d '-1 day') --till $(date +%s -d '-1 day + 59 seconds')

This will update API data of the last 10 minutes of DNS, DNSSEC, RDDS and EPP services of TLD example.

=cut
