#!/usr/bin/perl
#
# DNSSEC rolling week

BEGIN
{
	our $MYDIR = $0; $MYDIR =~ s,(.*)/.*/.*,$1,; $MYDIR = '..' if ($MYDIR eq $0);
}
use lib $MYDIR;

use strict;
use warnings;
use RSM;
use RSMSLV;

use constant MAX_CYCLES	=> 5;

my $cfg_key_in = 'rsm.slv.dnssec.avail';
my $cfg_key_out = 'rsm.slv.dnssec.rollweek';

parse_rollweek_opts();
exit_if_running();

set_slv_config(get_rsm_config());

db_connect();

# we don't know the rollweek bounds yet so we assume it ends at least few minutes back
my $delay = get_dns_udp_delay(getopt('now') // time() - ROLLWEEK_SHIFT_BACK);

my (undef, undef, $max_clock) = get_cycle_bounds($delay, getopt('now'));

my $cfg_sla = get_macro_dns_rollweek_sla();

slv_exit(E_FAIL) unless ($cfg_sla > 0);

my $tlds_ref;
if (opt('tld'))
{
        fail("TLD ", getopt('tld'), " does not exist.") if (tld_exists(getopt('tld')) == 0);

        $tlds_ref = [ getopt('tld') ];
}
else
{
        $tlds_ref = get_tlds('DNSSEC', $max_clock);
}

slv_exit(SUCCESS) if (scalar(@{$tlds_ref}) == 0);

my $cycles_ref = collect_slv_cycles($tlds_ref, $delay, $cfg_key_out, $max_clock, MAX_CYCLES);

slv_exit(SUCCESS) if (scalar(keys(%{$cycles_ref})) == 0);

process_slv_rollweek_cycles($cycles_ref, $delay, $cfg_key_in, $cfg_key_out, $cfg_sla);

slv_exit(SUCCESS);
