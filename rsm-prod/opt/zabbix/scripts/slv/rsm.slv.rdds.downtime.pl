#!/usr/bin/perl
#
# Minutes of RDDS downtime during running month

BEGIN
{
	our $MYDIR = $0; $MYDIR =~ s,(.*)/.*/.*,$1,; $MYDIR = '..' if ($MYDIR eq $0);
}
use lib $MYDIR;

use strict;
use warnings;
use RSM;
use RSMSLV;

use constant MAX_CYCLES	=> 2;

my $cfg_key_in = 'rsm.slv.rdds.avail';
my $cfg_key_out = 'rsm.slv.rdds.downtime';

parse_opts('tld=s', 'now=n');
exit_if_running();

set_slv_config(get_rsm_config());

db_connect();

# we don't know the rollweek bounds yet so we assume it ends at least few minutes back
my $delay = get_rdds_delay(getopt('now') // time() - ROLLWEEK_SHIFT_BACK);

my (undef, undef, $max_clock) = get_cycle_bounds($delay, getopt('now'));

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

process_slv_downtime_cycles($cycles_ref, $delay, $cfg_key_in, $cfg_key_out);

slv_exit(SUCCESS);
