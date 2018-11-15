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
use TLD_constants qw(:api);
use RSM;
use RSMSLV;

my @months = (
	qw/Jan Feb Mar Apr May Jun Jul Aug Sep Oct Nov Dec/
);

parse_opts('tld=s', 'from=n', 'till=n');

setopt('nolog');
setopt('dry-run');

$tld = getopt('tld');
my $from = getopt('from');
my $till = getopt('till');

usage() unless ($tld);

if (!$from || substr(getopt('from'), 0, length("-")) eq "-")
{
	my $t = time();

	print("Current time: ", __ts_human($t), "\n");

	$from = cycle_start($t - 180 - 300, 300);

	if (substr(getopt('from'), 0, length("-")) eq "-")
	{
		my $mult = substr(getopt('from'), 1);

		$from -= ($mult * 300);
	}
}

if (!$till)
{
	$till = $from + 299;	# 5 minutes
}

set_slv_config(get_rsm_config());

db_connect();

fail("TLD \"" . getopt('tld') . "\" not found") unless (tld_exists(getopt('tld')));

my %uint_itemids;

my $rows_ref = db_select(
	"select i.itemid,i.key_".
	" from items i,hosts h".
	" where i.hostid=h.hostid".
		" and h.host='$tld'".
		" and i.value_type=" . ITEM_VALUE_TYPE_UINT64);

fail("cannot find SLV items like Service availability") if (scalar(@{$rows_ref}) == 0);

map {$uint_itemids{$_->[0]} = $_->[1]} (@{$rows_ref});

my %float_itemids;

$rows_ref = db_select(
	"select i.itemid,i.key_".
	" from items i,hosts h".
	" where i.hostid=h.hostid".
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

my $prev_cycle_clock = 0;
my $cycle_values;

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

	my $cycle_clock = cycle_start($clock, 60);

	if ($cycle_clock != $prev_cycle_clock)
	{
		printf("%30s\n", "calculated values: $cycle_values") if (defined($cycle_values));

		$cycle_values = 0;
		__print_header($cycle_clock) unless ($cycle_clock == $prev_cycle_clock);
	}

	$cycle_values++;

	printf("%-40s %s\n", "$service $type", $value);

	$prev_cycle_clock = $cycle_clock;
}

printf("%30s\n", "calculated values: $cycle_values") if (defined($cycle_values));

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

	print("----------------------------------------------------------------------------------------------------\n");
	print("Cycle: ", __ts_human($clock), "\n");
	print("----------------------------------------------------------------------------------------------------\n");
}

__END__

=head1 NAME

slv-results.pl - show accumulated results stored by cron

=head1 SYNOPSIS

slv-results.pl --tld <tld> [--from <unixtime>] [--till <unixtime>] [--debug] [--help]

=head1 OPTIONS

=over 8

=item B<--tld> tld

Show results of specified TLD.

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
