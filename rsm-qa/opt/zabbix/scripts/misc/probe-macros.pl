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

parse_opts();

setopt('nolog');
setopt('dry-run');

my @probes;

set_slv_config(get_rsm_config());

db_connect();

my $result = __get_probe_macros();

foreach my $probe (keys(%{$result}))
{
	print($probe, "\n-------------------------\n");

	foreach my $macro (keys(%{$result->{$probe}}))
	{
		print("  $macro\t: ", $result->{$probe}->{$macro}, "\n");
	}
}

# todo phase 1: taken from TLD_constants.pm of phase 2
use constant HOST_STATUS_PROXY_PASSIVE => 6;

# todo phase 1: taken from RSMSLV.pm of phase 2
sub __get_probe_macros
{
	my $rows_ref = db_select(
		"select host".
		" from hosts".
		" where status=".HOST_STATUS_PROXY_PASSIVE);

	my $result;

	foreach my $row_ref (@$rows_ref)
	{
		my $host = $row_ref->[0];

		my $rows_ref2 = db_select(
			"select hm.macro,hm.value".
			" from hosts h,hostmacro hm".
			" where h.hostid=hm.hostid".
				" and h.host='Template $host'");

		foreach my $row_ref2 (@$rows_ref2)
		{
			$result->{$host}->{$row_ref2->[0]} = $row_ref2->[1];
		}
	}

	return $result;
}
