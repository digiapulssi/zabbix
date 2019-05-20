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
use RSM;
use RSMSLV;

parse_opts('tld=s', 'probe=s', 'from=n', 'till=n');

setopt('nolog');
setopt('dry-run');

if (!opt('tld'))
{
	print("usage: $0 --tld <tld> [--from <from> --till <till> --probe <probe>]\n");
	exit(1);
}

$tld = getopt('tld');

my $now = time();

my $probe = getopt('probe');
my $from = getopt('from') || cycle_start($now - 120, 300);
my $till = getopt('till') || cycle_end($now - 120, 300);

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
		"select h.clock,h.value,i2.key_".
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

		printf("%-30s%-70s %s\n", "CLOCK", "ITEM", "VALUE");
		print("------------------------------------------------------------------------------------------------------------\n");

		foreach my $row_ref (@$rows_ref)
		{
			my $clock = $row_ref->[0];
			my $value = $row_ref->[1];
			my $key = $row_ref->[2];

			$key = (length($key) > 60 ? substr($key, 0, 60) . " ..." : $key);

			printf("%s  %-70s %s\n", ts_full($clock), $key, $value);
		}
	}

	my @results;

	foreach my $t ('history', 'history_str')
	{
		$rows_ref = db_select(
			"select h.clock,h.value,i2.key_".
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
			my $value = $row_ref->[1];
			my $key = $row_ref->[2];

			$key = (length($key) > 60 ? substr($key, 0, 60) . " ..." : $key);

			push(@results, [$clock, $key, $value]);
		}
	}

	if (scalar(@results) != 0)
	{
		print("\n** $probe TESTS **\n\n");

		printf("%-30s%-70s %s\n", "CLOCK", "ITEM", "VALUE");
		print("------------------------------------------------------------------------------------------------------------\n");

		foreach my $r (sort {$a->[0] <=> $b->[0] || $a->[2] cmp $b->[2]} (@results))
		{
			printf("%s  %-70s %s\n", ts_full($r->[0]), $r->[1], $r->[2]);
		}
	}
}

db_disconnect();
