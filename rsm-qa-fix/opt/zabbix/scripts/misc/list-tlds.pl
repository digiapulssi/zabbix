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

parse_opts('verbose!');

setopt('nolog');
setopt('dry-run');

my $config = get_rsm_config();

set_slv_config($config);

my @server_keys = get_rsm_server_keys($config);
my $total_tlds = 0;
my $total_servers = 0;
foreach (@server_keys)
{
	$server_key = $_;

	$total_servers++;

	db_connect($server_key);

	my $tlds_ref = get_tlds();

	my $tlds = scalar(@{$tlds_ref});

	$total_tlds += $tlds;

	foreach my $t (@{$tlds_ref})
	{
		printf("    $t\n") if (opt('verbose'));
	}

	db_disconnect();

	printf("  %d TLDs on %s\n", $tlds, $server_key);
}
printf("total %d TLDs on %d servers\n", $total_tlds, $total_servers);

