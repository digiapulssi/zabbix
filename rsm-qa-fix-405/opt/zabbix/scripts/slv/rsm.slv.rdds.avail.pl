#!/usr/bin/perl
#
# RDDS availability

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

my $cfg_keys_in_pattern = 'rsm.rdds[{$RSM.TLD}';
my $cfg_rdap_key_in = 'rdap[';
my $cfg_key_out = 'rsm.slv.rdds.avail';
my $cfg_value_type = ITEM_VALUE_TYPE_UINT64;

parse_avail_opts();
exit_if_running();

set_slv_config(get_rsm_config());

db_connect();

my $delay = get_macro_rdds_delay();
my $cfg_minonline = get_macro_rdds_probe_online();

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
	$tlds_ref = get_tlds('RDDS', $from, $till);
}

my $rdap_items = get_templated_items_like("RDAP", $cfg_rdap_key_in);;

while ($period > 0)
{
	my ($period_from, $period_till, $value_ts) = get_interval_bounds($delay, $from);

	dbg("selecting period ", selected_period($period_from, $period_till), " (value_ts:", ts_str($value_ts), ")");

	$period -= $delay / 60;
	$from += $delay;

	next if ($period_till > $max_avail_time);

	my @online_probe_names = keys(%{get_probe_times($period_from, $period_till, get_probes('RDDS'))});	# todo phase 1: change to ENABLED_RDDS

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
		push(@{$cfg_keys_in}, $_) foreach (@{$rdap_items});

		process_slv_avail($tld, $cfg_keys_in, $cfg_key_out, $period_from, $period_till, $value_ts, $cfg_minonline,
			\@online_probe_names, \&check_probe_values, $cfg_value_type);
	}

	# unset TLD (for the logs)
	$tld = undef;

	send_values();
}

slv_exit(SUCCESS);

# SUCCESS - no values or at least one successful value
# E_FAIL  - all values unsuccessful
sub check_probe_values
{
	my $values_ref = shift;

	# E. g.:
	#
	# {
	#       rsm.rdds[{$RSM.TLD},"rdds43.example.com","web.whois.example.com"] => [1],
	#       rdap[...] => [0, 0],
	# }

	if (scalar(keys(%{$values_ref})) == 0)
	{
		fail("THIS SHOULD NEVER HAPPEN rsm.slv.rdds.avail.pl:check_probe_values()");
	}

	# all of received items (rsm.rdds, rdap) must have status UP in order for RDDS to be considered UP
	foreach my $statuses (values(%{$values_ref}))
	{
		foreach (@{$statuses})
		{
			return E_FAIL if ($_ != UP);
		}
	}

	return SUCCESS;
}
