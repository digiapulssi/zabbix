#!/usr/bin/perl
#
# Minutes of DNS downtime during running month for particular nameservers

BEGIN { our $MYDIR = $0; $MYDIR =~ s,(.*)/.*/.*,$1,; $MYDIR = '..' if ($MYDIR eq $0); }
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

my $month_first_cycle = current_month_first_cycle();
my $month_latest_cycle = current_month_latest_cycle();

init_values();
process_values();
send_values();

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

	# extract nsip pair and process relevant rtt items
	if ($slv_itemkey =~ /\[(.+,.+)\]$/)
	{
		process_rtt_items($tld, $slv_itemid, $slv_itemkey, $1);
	}
	else
	{
		fail("missing ns,ip pair in item key '$slv_itemkey'");
	}
}

sub rtt_itemids
{
	my $items = shift;
	my $itemids = [];

	foreach (@{$items})
	{
		push($itemids, $_->[0]);
	}

	return $itemids;
}

sub process_rtt_items # for a particular slv item
{
	my $tld = shift;
	my $slv_itemid = shift;
	my $slv_itemkey = shift;
	my $nsip = shift;

	my ($slv_lastvalue, $slv_clock) = @{get_slv_lastvalue_and_clock_by_itemid($slv_itemid)};

	print "$slv_clock : $month_latest_cycle\n";
	if ($slv_clock > $month_latest_cycle)
	{
		print "No cycles!\n";
		return;
	}

	my $rtt_items = get_dns_udp_rtt_items_by_nsip_pairs($nsip); # one per probe
	my $rtt_item_count = scalar(@{$rtt_items});

	my $rtt_item_history = get_rtt_item_history(rtt_itemids($rtt_items), $slv_clock, $slv_clock + 60);
	my $cycle_down;

	if (!defined($rtt_item_history) or 0 == scalar(@{$rtt_item_history}))
	{
		# assuming up if no rtt data
		$cycle_down = 0;
	}
	else
	{
		my $bad_probe_count = 0;

		foreach (@{$rtt_item_history})
		{
			my $value = int($_->[0]);

			if ($value <= -200)
			{
				$bad_probe_count++;
			}
		}

		# down if more than half of probes returned down
		my $unavail_limit = (SLV_UNAVAILABILITY_LIMIT * 0.01) * $rtt_item_count;
		$cycle_down = ($bad_probe_count > $unavail_limit) ? 1 : 0;
	}

	my $new_value = $slv_lastvalue + $cycle_down;

	print "$cycle_down, $new_value, $slv_clock\n";
	push_value($tld, $slv_itemkey, $slv_clock + 60, $new_value);
}

sub get_slv_lastvalue_and_clock_by_itemid
{
	my $itemid = shift;

	my $rows_ref = db_select("select value,clock from lastvalue where itemid=$itemid");

	# start with the latest cycle of the current month if there are no previous slv values
	return $rows_ref->[0] // [0, $month_first_cycle];
}

sub get_rtt_item_history
{
	my $itemids = shift;
	my $from = shift;
	my $till = shift;

	return db_select("select value from history where itemid in (".join(',', @{$itemids}).")".
		" and clock between $from and $till");
}

sub get_dns_udp_rtt_items_by_nsip_pairs
{
	my $nsip = shift;

	return db_select("select itemid,key_ from items".
		" where key_ like '$rtt_item_key_pattern\[\%$nsip]' and templateid is not null");
}

sub current_month_first_cycle
{
	my $dt = DateTime->now();
	$dt->truncate('to' => 'month');
	return cycle_start($dt->epoch, 60);
}

sub current_month_latest_cycle
{
	# we don't know the rollweek bounds yet so we assume it ends at least few minutes back
	return cycle_start(time(), 60) - ROLLWEEK_SHIFT_BACK;
}
