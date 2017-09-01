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

foreach (@server_keys)
{
	$server_key = $_;

	db_connect($server_key);

	my $tlds_ref = get_tlds();

	my $tlds = scalar(@{$tlds_ref});

	$total_tlds += $tlds;

	if (opt('verbose'))
	{
		foreach my $tld (@{$tlds_ref})
		{
			print("    $tld\n");
		}
	}

	db_disconnect();

	print("  $tlds TLDs on $server_key\n");
}

print("total $total_tlds TLDs on " . scalar(@server_keys) . " servers\n");
