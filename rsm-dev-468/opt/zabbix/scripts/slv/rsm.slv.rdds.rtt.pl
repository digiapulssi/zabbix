#!/usr/bin/perl

BEGIN
{
	our $MYDIR = $0; $MYDIR =~ s,(.*)/.*/.*,$1,; $MYDIR = '..' if ($MYDIR eq $0);
}
use lib $MYDIR;

use strict;
use warnings;

use RSM;
use RSMSLV;
use TLD_constants qw(:api);

parse_slv_opts();
fail_if_running();

set_slv_config(get_rsm_config());

db_connect();

my $single_tld;

if (opt("tld"))
{
	$single_tld = getopt("tld");

	fail("TLD '$single_tld' not found") unless tld_exists($single_tld);
}

use constant SLV_ITEM_KEY_RDDS_PERFORMED        => "rsm.slv.rdds.rtt.performed";
use constant SLV_ITEM_KEY_RDDS_FAILED           => "rsm.slv.rdds.rtt.failed";
use constant SLV_ITEM_KEY_RDDS_PFAILED          => "rsm.slv.rdds.rtt.pfailed";

use constant RTT_ITEM_KEY_PATTERN_RDDS43        => 'rsm.rdds.43.rtt[{$RSM.TLD}]';
use constant RTT_ITEM_KEY_PATTERN_RDDS80        => 'rsm.rdds.80.rtt[{$RSM.TLD}]';
use constant RTT_ITEM_KEY_PATTERN_RDAP          => 'rdap.rtt';

use constant RTT_TIMEOUT_ERROR_RDDS43           => -227;
use constant RTT_TIMEOUT_ERROR_RDDS80           => -255;
use constant RTT_TIMEOUT_ERROR_RDAP             => -405;

my $rtt_low_rdds = get_rtt_low("rdds");

update_slv_rtt_monthly_stats(
	getopt('now') // time(),
	opt('cycles') ? getopt('cycles') : slv_max_cycles('rdds'),
	$single_tld,
	SLV_ITEM_KEY_RDDS_PERFORMED,
	SLV_ITEM_KEY_RDDS_FAILED,
	SLV_ITEM_KEY_RDDS_PFAILED,
	get_rdds_delay(),
	[
		{
			'probes'                  => get_probes("RDDS"),
			'tlds_service'            => "rdds43",
			'rtt_item_key_pattern'    => RTT_ITEM_KEY_PATTERN_RDDS43,
			'timeout_error_value'     => RTT_TIMEOUT_ERROR_RDDS43,
			'timeout_threshold_value' => $rtt_low_rdds
		},
		{
			'probes'                  => get_probes("RDDS"),
			'tlds_service'            => "rdds43",
			'rtt_item_key_pattern'    => RTT_ITEM_KEY_PATTERN_RDDS80,
			'timeout_error_value'     => RTT_TIMEOUT_ERROR_RDDS80,
			'timeout_threshold_value' => $rtt_low_rdds
		},
		{
			'probes'                  => get_probes("RDDS"),
			'tlds_service'            => "rdap",
			'rtt_item_key_pattern'    => RTT_ITEM_KEY_PATTERN_RDAP,
			'timeout_error_value'     => RTT_TIMEOUT_ERROR_RDAP,
			'timeout_threshold_value' => $rtt_low_rdds
		}
	]
);

slv_exit(SUCCESS);
