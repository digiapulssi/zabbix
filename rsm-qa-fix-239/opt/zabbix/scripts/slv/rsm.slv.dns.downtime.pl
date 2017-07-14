#!/usr/bin/perl
#
# DNS minutes of downtime at current month

BEGIN
{
	our $MYDIR = $0; $MYDIR =~ s,(.*)/.*/.*,$1,; $MYDIR = '..' if ($MYDIR eq $0);
}
use lib $MYDIR;

use strict;
use warnings;
use RSM;
use RSMSLV;

my $cfg_key_in = 'rsm.slv.dns.avail';
my $cfg_key_out = 'rsm.slv.dns.downtime';

parse_opts();
exit_if_running();

set_slv_config(get_rsm_config());

db_connect();

my ($from, $till, $value_ts) = get_curmon_bounds();

my %tld_items;

my $tlds_ref = get_tlds();

# just collect itemids
foreach (@$tlds_ref)
{
	$tld = $_; # set global variable here

	if (uint_value_exists($value_ts, get_itemid_by_host($tld, $cfg_key_out)) == SUCCESS)
	{
		# value already exists
		next unless (opt('dry-run'));
	}

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

# todo phase 1: taken from RSMSLV1.pm
sub uint_value_exists
{
        my $clock = shift;
        my $itemid = shift;

        my $rows_ref = db_select("select 1 from history_uint where itemid=$itemid and clock=$clock");

        return SUCCESS if ($rows_ref->[0]->[0]);

        return E_FAIL;
}
