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

# we don't know the rollweek bounds yet so we assume it ends at least few minutes back
my $delay = get_dns_udp_delay(getopt('now') // time() - AVAIL_SHIFT_BACK);

my ($from, $till, $value_ts) = get_cycle_bounds($delay, getopt('now'));

dbg("selecting period ", selected_period($from, $till), " (value_ts:", ts_str($value_ts), ")");

my $cfg_minonline = get_macro_dns_probe_online();
my $cfg_minns = get_macro_minns();

my $tlds_ref;
if (opt('tld'))
{
        fail("TLD ", getopt('tld'), " does not exist.") if (tld_exists(getopt('tld')) == 0);

        $tlds_ref = [ getopt('tld') ];
}
else
{
        $tlds_ref = get_tlds('DNS', $till);
}

my @online_probe_names = keys(%{get_probe_times($from, $till, get_probes('DNS'))});

init_values();

foreach (@$tlds_ref)
{
	$tld = $_;	# set global variable here

	next if (!opt('dry-run') && uint_value_exists($value_ts, get_itemid_by_host($tld, $cfg_key_out)));

	process_slv_avail($tld, $cfg_keys_in, $cfg_key_out, $from, $till, $value_ts, $cfg_minonline,
		\@online_probe_names, \&check_probe_values, $cfg_value_type);
}

# unset TLD (for the logs)
$tld = undef;

send_values();

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
