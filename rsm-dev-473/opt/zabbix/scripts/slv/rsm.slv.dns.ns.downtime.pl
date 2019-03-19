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
use DateTime;

my $slv_item_key_pattern = 'rsm.slv.dns.ns.downtime';
my $rtt_item_key_pattern = 'rsm.dns.udp.rtt';

parse_slv_opts();
fail_if_running();
set_slv_config(get_rsm_config());
db_connect();

# # we don't know the rollweek bounds yet so we assume it ends at least few minutes back
# my $delay = get_dns_udp_delay(getopt('now') // time() - ROLLWEEK_SHIFT_BACK);
# 
# my ($month_start, $till, $cycle_start) = get_downtime_bounds($delay, getopt('now'));

my $month_first_cycle = current_month_first_cycle();

init_values();
process_values();
send_values();
print "\n\n";
slv_exit(SUCCESS);

sub process_values
{
	foreach (@{get_tlds_and_hostids(opt('tld') ? getopt('tld') : undef)})
	{
		process_tld(@$_);
	}
}

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
		" where hostid=".shift()." and key_ like '$slv_item_key_pattern\[%'");
}

sub process_slv_item
{
	my $tld = shift;
	my $slv_itemid = shift;
	my $slv_itemkey = shift;

	print "$slv_itemid, $slv_itemkey\n";

	if ($slv_itemkey =~ /\[(.+,.+)\]$/)
	{
		process_rtt_items($tld, $slv_itemid, $slv_itemkey, get_dns_udp_rtt_items_by_nsip_pairs($1));
	}
	else
	{
		fail("missing ns,ip pair in item key '$slv_itemkey'");
	}

	print "\n";
}

sub get_dns_udp_rtt_items_by_nsip_pairs
{
	my $nsip = shift;

	return db_select("select itemid,key_ from items".
		" where key_ like '$rtt_item_key_pattern\[\%$nsip]' and templateid is not null");
}

sub process_rtt_items # for a particular slv item
{
	my $tld = shift;
	my $slv_itemid = shift;
	my $slv_itemkey = shift;
	my $rtt_items = shift;
	print ">>> $slv_itemid, $slv_itemkey\n";

	my ($slv_lastvalue, $slv_clock) = @{get_slv_lastvalue_and_clock_by_itemid($slv_itemid)};
	print "$slv_lastvalue, $slv_clock\n";

	my $rtt_lastvalues_and_clocks = get_rtt_lastvalues_and_clocks_by_items($rtt_items, $slv_clock);
	print Dumper($rtt_lastvalues_and_clocks)."\n";

# 	my $slv_value;
# 	my $slv_clock;
# 
# 	if (SUCCESS != get_lastvalue($slv_itemid, ITEM_VALUE_TYPE_UINT64, \$slv_value, \$slv_clock))
# 	{
# # 		fail("cannot obtain lastvalue for item $slv_itemid, $slv_itemkey");
# 		$slv_value = 0;
# 		$slv_clock = $month_start;
# 	}

# 	print ">>> slv lastvalue: $slv_value, $slv_clock\n";
# 
# 	my $from = cycle_start($slv_clock, 60);
# 	my $till = $from + 60;
# 
# 	print ">>> time: $from, $till\n";
# 
# 	my $rtt_itemids = [];
# 
# 	foreach (@{$rtt_items})
# 	{
# 		push($rtt_itemids, $_->[0]);
# 	}
# 
# 	my $history = get_rtt_items_history($rtt_itemids, $from, $till);
# 	my $success_count = 0;
# 	my $fail_count = 0;
# 
# 	foreach (@{$history})
# 	{
# 		if ($_->[0] >= -200)
# 		{
# 			$success_count++;
# 		}
# 		else
# 		{
# 			$fail_count++;
# 		}
# 	}
# 
# 	print ">>> $success_count/$fail_count\n";
# 
# # 	push_value($tld, $slv_itemkey, $from, ($success_count > $fail_count ? '1' : '0'), 'nsip downtime');
# 	push_value($tld, $slv_itemkey, time(), int(rand(1000000)));
}

sub get_slv_lastvalue_and_clock_by_itemid
{
	my $itemid = shift;

	my $rows_ref = db_select("select value,clock from lastvalue where itemid=$itemid".
		" and clock>=$month_first_cycle");

	return defined($rows_ref) ? $rows_ref->[0] : [0, $month_first_cycle];
}

sub get_rtt_lastvalues_and_clocks_by_items
{
	my $items = shift;
	my $from = shift;
	my $itemids = '';

	foreach (@{$items})
	{
		$itemids .= $_->[0];
		$itemids .= ','
	}
	$itemids = substr($itemids, 0, -1);

	return db_select("select value,clock from lastvalue where itemid in ($itemids)".
		" and clock>=$from");
}

sub current_month_first_cycle
{
	my $dt = DateTime->now();
	$dt->truncate('to' => 'month');
	return cycle_start($dt->epoch, 60);
}

sub get_rtt_items_history
{
	my $itemids = shift;
	my $from = shift;
	my $till = shift;

	return db_select("select value from history where itemid in (".join(',', @{$itemids}).")".
		" and clock between ".shift()." and ".shift());
}

