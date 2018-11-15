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

parse_opts('tld=s', 'from=n', 'till=n');

setopt('nolog');
setopt('dry-run');

$tld = getopt('tld');
my $from = getopt('from');
my $till = getopt('till');

usage() unless ($tld);

if (!$from)
{
	my $t = time();

	$from = $t - 300 - ($t % 300);
}

if (!$till)
{
	$till = $from + 299;	# 5 minutes
}

set_slv_config(get_rsm_config());

db_connect();

my %uint_itemids;

my $rows_ref = db_select(
	"select i.itemid,i.key_".
	" from items i,hosts h".
	" where i.hostid=h.hostid".
		" and h.host='$tld'".
		" and i.value_type=" . ITEM_VALUE_TYPE_UINT64);

map {$uint_itemids{$_->[0]} = $_->[1]} (@{$rows_ref});

my %float_itemids;

$rows_ref = db_select(
	"select i.itemid,i.key_".
	" from items i,hosts h".
	" where i.hostid=h.hostid".
		" and h.host='$tld'".
		" and i.value_type=" . ITEM_VALUE_TYPE_FLOAT);

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
		print("calculated values: $cycle_values\n") if (defined($cycle_values));

		$cycle_values = 0;
		__print_header($cycle_clock) unless ($cycle_clock == $prev_cycle_clock);
	}

	$cycle_values++;

	printf("%-40s %s\n", "$service $type", "($clock) " . $value);

	$prev_cycle_clock = $cycle_clock;
}

print("calculated values: $cycle_values\n") if (defined($cycle_values));

sub __ts_human
{
	my $ts = shift;

	$ts = time() unless ($ts);

	# sec, min, hour, mday, mon, year, wday, yday, isdst
	my ($sec, $min, $hour, $mday, $mon, $year) = localtime($ts);

	return sprintf("%.2d-%.2d-%.4d %.2d:%.2d", $mday, $mon + 1, $year + 1900, $hour, $min);
}

sub __print_header
{
	my $clock = shift;

	print("----------------------------------------------------------------------------------------------------\n");
	print("                ", __ts_human($clock), "\n");
	print("----------------------------------------------------------------------------------------------------\n");
}

__END__

=head1 NAME

slv-results.pl - show accumulated results stored by cron

=head1 SYNOPSIS

slv-results.pl --tld <tld> --from <unixtime> --till <unixtime> [options] [--debug] [--help]

=head1 OPTIONS

=over 8

=item B<--tld> tld

Show results of specified TLD.

=item B<--from> timestamp

Specify Unix timestamp within the cycle.

=item B<--till> timestamp

Specify Unix timestamp within the cycle.

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
