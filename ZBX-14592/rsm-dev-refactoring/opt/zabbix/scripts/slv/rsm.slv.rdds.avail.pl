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

my $cfg_key_in = 'rsm.rdds[{$RSM.TLD}';
my $cfg_key_out = 'rsm.slv.rdds.avail';
my $cfg_value_type = ITEM_VALUE_TYPE_UINT64;

parse_avail_opts();
exit_if_running();

set_slv_config(get_rsm_config());

db_connect();

my $interval = get_macro_rdds_delay();
my $cfg_minonline = get_macro_rdds_probe_online();

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
	$tlds_ref = get_tlds('RDDS');	# todo phase 1: change to ENABLED_RDDS
}

while ($period > 0)
{
	my ($from, $till, $value_ts) = get_interval_bounds($interval, $clock);

	dbg("selecting period ", selected_period($from, $till), " (value_ts:", ts_str($value_ts), ")");

	$period -= $interval / 60;
	$clock += $interval;

	next if ($till > $max_avail_time);

	my @online_probe_names = keys(%{get_probe_times($from, $till, get_probes('RDDS'))});	# todo phase 1: change to ENABLED_RDDS

	init_values();

	foreach (@$tlds_ref)
	{
		$tld = $_;

		if (avail_value_exists($value_ts, get_itemid_by_host($tld, $cfg_key_out)) == SUCCESS)
		{
			# value already exists
			next unless (opt('dry-run'));
		}

		process_slv_avail($tld, $cfg_key_in, $cfg_key_out, $from, $till, $value_ts, $cfg_minonline,
			\@online_probe_names, \&check_item_values, $cfg_value_type);
	}

	# unset TLD (for the logs)
	$tld = undef;

	send_values();
}

slv_exit(SUCCESS);

# SUCCESS - no values or at least one successful value
# E_FAIL  - all values unsuccessful
sub check_item_values
{
	use constant RDDS_UP	=> 1;	# "Up" from "RSM RDDS result" value mapping

	my $values_ref = shift;

	return SUCCESS if (scalar(@$values_ref) == 0);

	foreach (@$values_ref)
	{
		return SUCCESS if ($_ == RDDS_UP);
	}

	return E_FAIL;
}
