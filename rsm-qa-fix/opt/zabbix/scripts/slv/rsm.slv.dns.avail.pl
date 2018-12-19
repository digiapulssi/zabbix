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

use constant MAX_CYCLES	=> 5;

my $cfg_keys_in = ['rsm.dns.udp[{$RSM.TLD}]'];
my $cfg_key_out = 'rsm.slv.dns.avail';
my $cfg_value_type = ITEM_VALUE_TYPE_UINT64;

parse_avail_opts();
exit_if_running();

set_slv_config(get_rsm_config());

db_connect();

# we don't know the rollweek bounds yet so we assume it ends at least few minutes back
my $delay = get_dns_udp_delay(getopt('now') // time() - AVAIL_SHIFT_BACK);

my (undef, undef, $max_clock) = get_cycle_bounds($delay, getopt('now'));

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
        $tlds_ref = get_tlds('DNS', $max_clock);
}

slv_exit(SUCCESS) if (scalar(@{$tlds_ref}) == 0);

my $cycles_ref = collect_slv_cycles($tlds_ref, $delay, $cfg_key_out, $max_clock, MAX_CYCLES);

slv_exit(SUCCESS) if (scalar(keys(%{$cycles_ref})) == 0);

my $probes_ref = get_probes('DNS');

process_slv_avail_cycles(
	$cycles_ref,
	$probes_ref,
	$delay,
	$cfg_keys_in,
	undef,			# callback to get input keys, ignored
	$cfg_key_out,
	$cfg_minonline,
	\&check_probe_values,
	$cfg_value_type
);

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
