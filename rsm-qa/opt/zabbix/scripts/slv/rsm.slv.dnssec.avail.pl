#!/usr/bin/perl
#
# DNSSEC proper resolution

BEGIN
{
	our $MYDIR = $0; $MYDIR =~ s,(.*)/.*/.*,$1,; $MYDIR = '..' if ($MYDIR eq $0);
}
use lib $MYDIR;

use strict;
use warnings;
use RSM;
use RSMSLV;
use TLD_constants qw(:ec :api);

my $cfg_keys_in_pattern = 'rsm.dns.udp.rtt[';
my $cfg_key_out = 'rsm.slv.dnssec.avail';
my $cfg_value_type = ITEM_VALUE_TYPE_FLOAT;

parse_avail_opts();
exit_if_running();

set_slv_config(get_rsm_config());

db_connect();

my $delay = get_dns_udp_delay();
my $cfg_minonline = get_macro_dns_probe_online();

my $cfg_minns = get_macro_minns();

my $now = time();

my $from = truncate_from((opt('from') ? getopt('from') : $now - $delay - AVAIL_SHIFT_BACK));
my $period = (opt('period') ? getopt('period') : 1);

my $till = $from + ($period * 60) - 1;

# in normal operation mode
if (!opt('period') && !opt('from'))
{
	# only calculate once a cycle
	if ($from % $delay != 0)
	{
		dbg("not yet time to calculate");
		slv_exit(SUCCESS);
	}
}

my $max_avail_time = max_avail_time($now);

my $tlds_ref;
if (opt('tld'))
{
        fail("TLD ", getopt('tld'), " does not exist.") if (tld_exists(getopt('tld')) == 0);

        $tlds_ref = [ getopt('tld') ];
}
else
{
        $tlds_ref = get_tlds('DNSSEC', $till);
}

while ($period > 0)
{
	my ($period_from, $period_till, $value_ts) = get_cycle_bounds($delay, $from);

	dbg("selecting period ", selected_period($period_from, $period_till), " (value_ts:", ts_str($value_ts), ")");

	$period -= $delay / 60;
	$from += $delay;

	next if ($period_till > $max_avail_time);

	my @online_probe_names = keys(%{get_probe_times($period_from, $period_till, get_probes('DNSSEC'))});

	init_values();

	foreach (@$tlds_ref)
	{
		$tld = $_;

		if (avail_value_exists($value_ts, get_itemid_by_host($tld, $cfg_key_out)) == SUCCESS)
		{
			# value already exists
			next unless (opt('dry-run'));
		}

		# get all rtt items
		my $cfg_keys_in = get_templated_items_like($tld, $cfg_keys_in_pattern);

		process_slv_avail($tld, $cfg_keys_in, $cfg_key_out, $period_from, $period_till, $value_ts, $cfg_minonline,
			\@online_probe_names, \&check_probe_values, $cfg_value_type);
	}

	# unset TLD (for the logs)
	$tld = undef;

	send_values();
}

slv_exit(SUCCESS);

# SUCCESS - more than or equal to $cfg_minns Name Servers returned no DNSSEC errors
# E_FAIL  - otherwise
sub check_probe_values
{
	my $values_ref = shift;

	# E. g.:
	#
	# {
	# 	rsm.dns.udp.rtt[{$RSM.TLD},ns1.foo.com,1.2.3.4] => [3],
	# 	rsm.dns.udp.rtt[{$RSM.TLD},ns1.foo.com,12ff::20::10::] => [-204],
	# 	rsm.dns.udp.rtt[{$RSM.TLD},ns2.foo.com,5.6.7.8] => [5],
	# 	rsm.dns.udp.rtt[{$RSM.TLD},ns3.foo.com,10.11.12.13] => [-206]
	# }

	if (scalar(keys(%{$values_ref})) == 0)
	{
		fail("THIS SHOULD NEVER HAPPEN rsm.slv.dnssec.avail.pl:check_probe_values()");
	}

	if (1 > $cfg_minns)
	{
		wrn("number of required working Name Servers is configured as $cfg_minns");
		return SUCCESS;
	}

	my %name_servers;

	# stay on the safe side: if more than one value in cycle, use the positive one
	foreach my $key (keys(%{$values_ref}))
	{
		my $ns = $key;
		$ns =~ s/[^,]+,([^,]+),.*/$1/;	# 2nd parameter

		# check if Name Server already marked as Down
		next if (defined($name_servers{$ns}) && $name_servers{$ns} == DOWN);

		foreach my $rtt (@{$values_ref->{$key}})
		{
			$name_servers{$ns} = (is_service_error('dnssec', $rtt) ? DOWN : UP);
		}
	}

	my $name_servers_up = 0;

	foreach (values(%name_servers))
	{
		$name_servers_up++ if ($_ == UP);

		return SUCCESS if ($name_servers_up == $cfg_minns);
	}

	return E_FAIL;
}
