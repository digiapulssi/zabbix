#!/usr/bin/perl
#
# DNS availability

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

my $cfg_keys_in = ['rsm.dns.udp[{$RSM.TLD}]'];
my $cfg_key_out = 'rsm.slv.dns.avail';
my $cfg_value_type = ITEM_VALUE_TYPE_UINT64;

parse_avail_opts();
exit_if_running();

set_slv_config(get_rsm_config());

db_connect();

my $interval = get_macro_dns_udp_delay();
my $cfg_minonline = get_macro_dns_probe_online();

my $cfg_minns = get_macro_minns();

my $now = time();

my $clock = (opt('from') ? getopt('from') : $now - $interval - AVAIL_SHIFT_BACK);
my $period = (opt('period') ? getopt('period') : 1);

# in normal operation mode
if (!opt('period') && !opt('from'))
{
	# only calculate once a cycle
	if (truncate_from($clock) % $interval != 0)
	{
		dbg("will NOT calculate");
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
        $tlds_ref = get_tlds('DNS');	# todo phase 1: change to ENABLED_DNS
}

while ($period > 0)
{
	my ($from, $till, $value_ts) = get_interval_bounds($interval, $clock);

	dbg("selecting period ", selected_period($from, $till), " (value_ts:", ts_str($value_ts), ")");

	$period -= $interval / 60;
	$clock += $interval;

	next if ($till > $max_avail_time);

	my @online_probe_names = keys(%{get_probe_times($from, $till, get_probes('DNS'))});	# todo phase 1: change to ENABLED_DNS

	init_values();

	foreach (@$tlds_ref)
	{
		$tld = $_; # set global variable here

		if (avail_value_exists($value_ts, get_itemid_by_host($tld, $cfg_key_out)) == SUCCESS)
		{
			# value already exists
			next unless (opt('dry-run'));
		}

		process_slv_avail($tld, $cfg_keys_in, $cfg_key_out, $from, $till, $value_ts, $cfg_minonline,
			\@online_probe_names, \&check_probe_values, $cfg_value_type);
	}

	# unset TLD (for the logs)
	$tld = undef;

	send_values();
}

slv_exit(SUCCESS);

# SUCCESS - more than or equal to $cfg_minns Name Servers were tested successfully
# E_FAIL  - otherwise
sub check_probe_values
{
	my $values_ref = shift;

	# E. g.:
	#
	# {
	#	'rsm.dns.udp[{$RSM.TLD}]' => [3]
	# }

	if (scalar(keys(%{$values_ref})) == 0)
	{
		fail("THIS SHOULD NEVER HAPPEN rsm.slv.dns.avail.pl:check_probe_values()");
	}

	if (1 > $cfg_minns)
	{
		wrn("number of required working Name Servers is configured as $cfg_minns");
		return SUCCESS;
	}

	# stay on the safe side: if more than one value in cycle, use the positive one
	foreach my $rtts (values(%{$values_ref}))
	{
		foreach (@{$rtts})
		{
			return SUCCESS if ($_ >= $cfg_minns);
		}
	}

	return E_FAIL;
}
