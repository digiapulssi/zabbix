#!/usr/bin/perl
#
# RDDS minutes of downtime at current month

BEGIN
{
	our $MYDIR = $0; $MYDIR =~ s,(.*)/.*/.*,$1,; $MYDIR = '..' if ($MYDIR eq $0);
}
use lib $MYDIR;

use strict;
use warnings;
use RSM;
use RSMSLV;

my $cfg_key_in = 'rsm.slv.rdds.avail';
my $cfg_key_out = 'rsm.slv.rdds.downtime';

parse_opts('tld=s', 'now=n');
exit_if_running();

set_slv_config(get_rsm_config());

db_connect();

my $now = getopt('now') // time();

my $delay = get_rdds_delay($now - ROLLWEEK_SHIFT_BACK);

my ($from, $till, $value_ts) = get_downtime_bounds($delay, getopt('now'));	# do not pass $now here

my %tld_items;

my $tlds_ref;
if (opt('tld'))
{
        fail("TLD ", getopt('tld'), " does not exist.") if (tld_exists(getopt('tld')) == 0);

        $tlds_ref = [ getopt('tld') ];
}
else
{
	$tlds_ref = get_tlds('RDDS', $till);
}

# just collect itemids
foreach (@$tlds_ref)
{
	$tld = $_; # set global variable here

	next if (!opt('dry-run') && uint_value_exists($value_ts, get_itemid_by_host($tld, $cfg_key_out)));

	# for future calculation of downtime
	$tld_items{$tld} = get_itemid_by_host($tld, $cfg_key_in);
}

init_values();

# use bind for faster execution of the same SQL query
my $sth = get_downtime_prepare();

foreach (keys(%tld_items))
{
	$tld = $_; # set global variable here

	my $itemid = $tld_items{$tld};

	my $downtime = get_downtime_execute($sth, $itemid, $from, $till, 1); # ignore incidents

	push_value($tld, $cfg_key_out, $value_ts, $downtime, ts_str($from), " - ", ts_str($till));
}

# unset TLD (for the logs)
$tld = undef;

send_values();

slv_exit(SUCCESS);
