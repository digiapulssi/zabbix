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

use constant SLV_ITEM_KEY_DNS_TCP_PERFORMED     => "rsm.slv.dns.tcp.rtt.performed";
use constant SLV_ITEM_KEY_DNS_TCP_FAILED        => "rsm.slv.dns.tcp.rtt.failed";
use constant SLV_ITEM_KEY_DNS_TCP_PFAILED       => "rsm.slv.dns.tcp.rtt.pfailed";

use constant RTT_ITEM_KEY_PATTERN_DNS_TCP_IPV4  => 'rsm.dns.tcp.rtt[{$RSM.TLD},%,%.%]';
use constant RTT_ITEM_KEY_PATTERN_DNS_TCP_IPV6  => 'rsm.dns.tcp.rtt[{$RSM.TLD},%,%:%]';

use constant RTT_TIMEOUT_ERROR_DNS_TCP          => -600;

my $rtt_low_dns_tcp = get_rtt_low("dns", PROTO_TCP);

update_slv_rtt_monthly_stats(
	getopt('now') // time(),
	opt('cycles') ? getopt('cycles') : slv_max_cycles('dns'),
	$single_tld,
	SLV_ITEM_KEY_DNS_TCP_PERFORMED,
	SLV_ITEM_KEY_DNS_TCP_FAILED,
	SLV_ITEM_KEY_DNS_TCP_PFAILED,
	get_dns_tcp_delay(),
	[
		{
			'probes'                  => get_probes("IP4"),
			'tlds_service'            => "dns",
			'rtt_item_key_pattern'    => RTT_ITEM_KEY_PATTERN_DNS_TCP_IPV4,
			'timeout_error_value'     => RTT_TIMEOUT_ERROR_DNS_TCP,
			'timeout_threshold_value' => $rtt_low_dns_tcp
		},
		{
			'probes'                  => get_probes("IP6"),
			'tlds_service'            => "dns",
			'rtt_item_key_pattern'    => RTT_ITEM_KEY_PATTERN_DNS_TCP_IPV6,
			'timeout_error_value'     => RTT_TIMEOUT_ERROR_DNS_TCP,
			'timeout_threshold_value' => $rtt_low_dns_tcp
		}
	]
);

slv_exit(SUCCESS);
