#!/usr/bin/perl
#
# Minutes of DNS downtime during running month for particular nameservers

BEGIN
{
	our $MYDIR = $0; $MYDIR =~ s,(.*)/.*/.*,$1,; $MYDIR = '..' if ($MYDIR eq $0);
}
use lib $MYDIR;

use strict;
use warnings;
use RSM;
use RSMSLV;
use TLD_constants qw(:groups :api);
use Data::Dumper;

parse_slv_opts();
fail_if_running();
set_slv_config(get_rsm_config());
db_connect();

my $slv_item_key_pattern = 'rsm.slv.dns.ns.downtime';
my $rtt_item_key_pattern = 'rsm.dns.udp.rtt';

# we don't know the rollweek bounds yet so we assume it ends at least few minutes back
my $delay = get_dns_udp_delay(getopt('now') // time() - ROLLWEEK_SHIFT_BACK);

my ($month_start, $till, $cycle_start) = get_downtime_bounds($delay, getopt('now'));

init_values();

foreach (@{get_tlds_and_hostids(opt('tld') ? getopt('tld') : undef)})
{
	process_tld(@$_);
}

send_values();

print "\n\n";
slv_exit(SUCCESS);

sub get_tlds_and_hostids
{
	my $tld = shift;
	my $tld_cond = '';
	if (defined($tld))
	{
		$tld_cond = " and h.host='$tld'"
	}

	return db_select(
		"select distinct h.host,h.hostid".
		" from hosts h,hosts_groups hg".
		" where h.hostid=hg.hostid".
			" and hg.groupid=".TLDS_GROUPID.
			" and h.status=0".
			$tld_cond.
		" order by h.host");
}

sub process_tld
{
	my $tld = shift;
	my $hostid = shift;

	foreach (@{get_slv_dns_ns_downtime_items_by_hostid($hostid)})
	{
		process_slv_item($tld, @$_);
	}
}

sub get_slv_dns_ns_downtime_items_by_hostid
{
	return db_select("select itemid,key_ from items".
		" where hostid=".(shift)." and key_ like '$slv_item_key_pattern\[%'");
}

sub process_slv_item
{
	my $tld = shift;
	my $slv_itemid = shift;
	my $slv_itemkey = shift;

	print "$slv_itemid, $slv_itemkey\n";

	if ($slv_itemkey =~ /\[(.+,.+)\]$/)
	{
		foreach (@{get_dns_udp_rtt_items_by_nsip_pairs($1)})
		{
			process_rtt_items($tld, $slv_itemid, $slv_itemkey, @$_);
		}
	}
	else
	{
		fail("missing ns,ip pair in item key '$slv_itemkey'");
	}

	print "\n";
}

sub get_dns_udp_rtt_items_by_nsip_pairs
{
	return db_select("select itemid,key_,hostid from items".
		" where key_ like '$rtt_item_key_pattern\[\%".(shift)."]' and templateid is not null");
}

sub process_rtt_items # for a particular slv item
{
	my $tld = shift;
	my $slv_itemid = shift;
	my $slv_itemkey = shift;
	my $rtt_itemid = shift;
	my $rtt_itemkey = shift;
	my $slv_value;
	my $slv_clock;
	my $rtt_value;
	my $rtt_clock;
	
	print ">>> $slv_itemid, $slv_itemkey\n";

	if (SUCCESS != get_lastvalue($rtt_itemid, ITEM_VALUE_TYPE_FLOAT, \$rtt_value, \$rtt_clock))
	{
		fail("cannot obtain lastvalue for item $rtt_itemid");
	}

	if (SUCCESS != get_lastvalue($slv_itemid, ITEM_VALUE_TYPE_UINT64, \$slv_value, \$slv_clock))
	{
		print "Reset slv_clock!\n";
		$slv_clock = $month_start;
		$slv_value = 0;
	}

	$rtt_clock = cycle_start($rtt_clock, 60) - ROLLWEEK_SHIFT_BACK;
	$slv_clock = cycle_start($slv_clock, 60);

	print "$slv_clock < $rtt_clock ".($slv_clock - $rtt_clock)."\n";
	if ($slv_clock < $rtt_clock)
	{
		my $failed_tests = get_failed_cycle_count($rtt_itemid, $slv_clock, $rtt_clock);

		push_value($tld, $slv_itemkey, $till, $slv_value + $failed_tests);
		print "$till -> $slv_value + $failed_tests = ".($slv_value + $failed_tests)."\n";
	}
	else
	{
		print "else\n";
	}
}

sub get_failed_cycle_count
{
	my $itemid = shift;
	my $from = shift;
	my $till = shift;

	my $rows_ref = db_select("select count(1) from history".
		" where itemid=$itemid and clock between $from and $till and value <= -200");

	if ($rows_ref > 0)
	{
		return int($rows_ref->[0]->[0]);
	}

	return 0;
}
