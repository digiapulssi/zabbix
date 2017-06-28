#!/usr/bin/perl

BEGIN
{
	our $MYDIR = $0; $MYDIR =~ s,(.*)/.*,$1/../pm,;
}
use lib $MYDIR;

use strict;
use warnings;
use RSM;
use RSMSLV;

parse_opts('tld=s', 'probe=s', 'from=n', 'till=n');

setopt('nolog');
setopt('dry-run');

my $tld = getopt('tld');
my $probe = getopt('probe');
my $from = getopt('from');
my $till = getopt('till');

foreach my $opt ($tld, $from, $till)
{
	if (!defined($opt))
	{
		print("usage: $0 --tld <tld> --from <from> --till <till> [--probe <probe>]\n");
		exit(1);
	}
}

my $config = get_rsm_config();

set_slv_config($config);

my @server_keys = get_rsm_server_keys($config);
foreach (@server_keys)
{
	$server_key = $_;

	db_connect($server_key);

	if (tld_exists(getopt('tld')) == 0)
	{
		db_disconnect();
		fail("TLD ", getopt('tld'), " does not exist.") if ($server_keys[-1] eq $server_key);
		next;
	}

	last;
}

my @probes;

if ($probe)
{
	push(@probes, $probe);
}
else
{
	my $p = get_probes();

	foreach (keys(%$p))
	{
		push(@probes, $_);
	}
}

foreach my $probe (@probes)
{
	my $host = "$tld $probe";

	my $rows_ref = db_select(
		"select h.clock,h.ns,h.value,i2.key_".
		" from history_uint h,items i2".
		" where i2.itemid=h.itemid".
	        	" and i2.itemid in".
				" (select i3.itemid".
				" from items i3,hosts ho".
				" where i3.hostid=ho.hostid".
					" and i3.key_ not like 'probe.configvalue%'".
					" and ho.host='$host')".
	        	" and h.clock between $from and $till".
	        " order by h.clock,i2.key_");

	if (scalar(@$rows_ref) != 0)
	{
		print("\n** $probe CYCLES **\n\n");

		printf("%-34s%-11s%-80s %s\n", "CLOCK", "NANOSEC", "ITEM", "VALUE");
		print("------------------------------------------------------------------------------------------------------------------------------------------------------------\n");

		foreach my $row_ref (@$rows_ref)
		{
			my $clock = $row_ref->[0];
			my $ns = $row_ref->[1];
			my $value = $row_ref->[2];
			my $key = $row_ref->[3];

			printf("%s  %-9s  %-80s %s\n", ts_full($clock), $ns, $key, $value);
		}
	}

	my @results;

	foreach my $t ('history', 'history_str')
	{
		$rows_ref = db_select(
			"select h.clock,h.ns,h.value,i2.key_".
			" from $t h,items i2".
			" where i2.itemid=h.itemid".
				" and i2.itemid in".
					" (select i3.itemid".
					" from items i3,hosts ho".
					" where i3.hostid=ho.hostid".
	                			" and i3.key_ not like 'probe.configvalue%'".
	                			" and ho.host='$host')".
				" and h.clock between $from and $till".
			" order by h.clock,i2.key_");

		foreach my $row_ref (@$rows_ref)
		{
			my $clock = $row_ref->[0];
			my $ns = $row_ref->[1];
			my $value = $row_ref->[2];
			my $key = $row_ref->[3];

			push(@results, [$clock, $ns, $key, $value]);
		}
	}

	if (scalar(@results) != 0)
	{
		print("\n** $probe TESTS **\n\n");

		printf("%-34s%-11s%-80s %s\n", "CLOCK", "NANOSEC", "ITEM", "VALUE");
		print("------------------------------------------------------------------------------------------------------------------------------------------------------------\n");

		foreach my $r (sort {$a->[0] <=> $b->[0] || $a->[2] cmp $b->[2]} (@results))
		{
			printf("%s  %-9s  %-80s %s\n", ts_full($r->[0]), $r->[1], $r->[2], $r->[3]);
		}
	}
}

db_disconnect();
