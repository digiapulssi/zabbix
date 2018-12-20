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

use constant MAX_CYCLES	=> 2;

my $cfg_keys_in_pattern = 'rsm.rdds[{$RSM.TLD}';
my $cfg_rdap_key_in = 'rdap[';
my $cfg_key_out = 'rsm.slv.rdds.avail';
my $cfg_value_type = ITEM_VALUE_TYPE_UINT64;

parse_avail_opts();
exit_if_running();

set_slv_config(get_rsm_config());

db_connect();

# we don't know the rollweek bounds yet so we assume it ends at least few minutes back
my $delay = get_rdds_delay(getopt('now') // time() - AVAIL_SHIFT_BACK);

my (undef, undef, $max_clock) = get_cycle_bounds($delay, getopt('now'));

my $cfg_minonline = get_macro_rdds_probe_online();

my $tlds_ref;
if (opt('tld'))
{
	fail("TLD ", getopt('tld'), " does not exist.") if (tld_exists(getopt('tld')) == 0);

	$tlds_ref = [ getopt('tld') ];
}
else
{
	$tlds_ref = get_tlds('RDDS', $max_clock);
}

slv_exit(SUCCESS) if (scalar(@{$tlds_ref}) == 0);

my $cycles_ref = collect_slv_cycles($tlds_ref, $delay, $cfg_key_out, $max_clock, MAX_CYCLES);

slv_exit(SUCCESS) if (scalar(keys(%{$cycles_ref})) == 0);

my $probes_ref = get_probes('RDDS');

process_slv_avail_cycles(
	$cycles_ref,
	$probes_ref,
	$delay,
	undef,			# input keys are unknown
	\&cfg_keys_in_cb,	# callback to get input keys
	$cfg_key_out,
	$cfg_minonline,
	\&check_probe_values,
	$cfg_value_type
);

slv_exit(SUCCESS);

my $rdap_items;

sub cfg_keys_in_cb($)
{
	my $tld = shift;

	$rdap_items = get_templated_items_like("RDAP", $cfg_rdap_key_in) unless (defined($rdap_items));

	# get all RDDS rtt items
	my $cfg_keys_in = get_templated_items_like($tld, $cfg_keys_in_pattern);

	# add RDAP rtt items
	push(@{$cfg_keys_in}, $_) foreach (@{$rdap_items});

	return $cfg_keys_in;
}

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
