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
my $current_month_latest_cycle = current_month_latest_cycle();

parse_slv_opts();
fail_if_running();
set_slv_config(get_rsm_config());
db_connect();
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

	foreach (@{get_slv_dns_ns_downtime_items($hostid)})
	{
		process_slv_item($tld, @$_);
	}
}

sub get_slv_dns_ns_downtime_items
{
	return db_select("select itemid,key_ from items".
		" where hostid=".shift()." and key_ like '$slv_item_key_pattern\[%'");
}

sub process_slv_item
{
	my $tld = shift;
	my $slv_itemid = shift;
	my $slv_itemkey = shift;

	if ($slv_itemkey =~ /\[(.+,.+)\]$/)
	{
		process_cycles($tld, $slv_itemid, $slv_itemkey, $1);
	}
	else
	{
		fail("missing ns,ip pair in item key '$slv_itemkey'");
	}
}

sub process_cycles # for a particular slv item
{
	my $tld = shift;
	my $slv_itemid = shift;
	my $slv_itemkey = shift;
	my $nsip = shift;

	my $rtt_itemids = get_dns_udp_rtt_itemids($nsip); # one item per probe

	my $slv_old_value_and_clock = get_slv_lastvalue_and_clock($slv_itemid);

	my $n = 0;
	my $slv_old_value = 0;
	my $slv_new_clock = 0;

	for (;;)
	{
		last if ($n >= 5);
		$n++;

		if (defined($slv_old_value_and_clock))
		{
			$slv_old_value = $slv_old_value_and_clock->[0];

			my $slv_old_clock = $slv_old_value_and_clock->[1]; 
			$slv_new_clock = $slv_old_clock + 60;

			last if ($slv_new_clock > $current_month_latest_cycle);

			if (month_start($slv_new_clock) != month_start($slv_old_clock))
			{
				# new month - reset the downtime value
				$slv_old_value = 0;
			}
		}
		else
		{
			# no history for slv item
			# start somewhere

			my $oldest_rtt_clock_this_month = get_oldest_rtt_clock_this_month($rtt_itemids);

			if (defined($oldest_rtt_clock_this_month))
			{
				$slv_old_value = 0;
				print "$oldest_rtt_clock_this_month -> ".cycle_start($oldest_rtt_clock_this_month, 60)."\n";
				$slv_new_clock = cycle_start($oldest_rtt_clock_this_month, 60);
			}
			else
			{
				$slv_old_value = 0;
				$slv_new_clock = $current_month_latest_cycle;
			}
		}

		my $slv_new_value = $slv_old_value + cycle_is_down($rtt_itemids, $slv_new_clock);

		print "push_value: $tld, $slv_itemkey, $slv_new_clock, $slv_new_value\n";

		push_value($tld, $slv_itemkey, $slv_new_clock, $slv_new_value);
		$slv_old_value_and_clock = [$slv_new_value, $slv_new_clock];
	}
}

sub get_dns_udp_rtt_itemids
{
	my $nsip = shift;

	my $items = db_select("select itemid,key_ from items".
		" where key_ like '$rtt_item_key_pattern\[\%$nsip]' and templateid is not null");

	my $itemids = [];

	foreach (@{$items})
	{
		push($itemids, $_->[0]);
	}

	return $itemids;
}

sub get_slv_lastvalue_and_clock
{
	my $itemid = shift;

	my $rows = db_select("select value,clock from lastvalue where itemid=$itemid");

	return defined($rows) ? $rows->[0] : undef;
}

sub get_oldest_rtt_clock_this_month
{
	my $rtt_itemids = shift;

	my $rows = db_select("select min(clock) from history where itemid in (".join(',', @{$rtt_itemids}).")".
		" and clock>=".current_month_first_cycle());

	return undef unless defined($rows) and defined($rows->[0]);
	return $rows->[0][0];
}

sub cycle_is_down
{
	my $rtt_itemids = shift;
	my $cycle_start = shift;
	my $probe_count = scalar(@{$rtt_itemids});

	my $failed_rtt_value_count = get_failed_rtt_value_count($rtt_itemids, $cycle_start, $cycle_start + 60);
	my $limit = (SLV_UNAVAILABILITY_LIMIT * 0.01) * $probe_count;

	print "--> ".SLV_UNAVAILABILITY_LIMIT.", $probe_count => $failed_rtt_value_count :: $limit\n";

	return ($failed_rtt_value_count > $limit) ? 1 : 0;
}

sub get_failed_rtt_value_count
{
	my $rtt_itemids = shift;
	my $from = shift;
	my $till = shift;

	my $rows = db_select("select count(1) from history where itemid in (".join(',', @{$rtt_itemids}).")".
		" and clock between $from and $till and value<=-200");

	return $rows->[0][0];
}

sub current_month_latest_cycle
{
	# we don't know the rollweek bounds yet so we assume it ends at least few minutes back
	return cycle_start(time(), 60) - ROLLWEEK_SHIFT_BACK;
}

sub current_month_first_cycle
{
	return month_start(time());
}

sub month_start
{
	my $dt = DateTime->from_epoch('epoch' => shift());
	$dt->truncate('to' => 'month');
	return $dt->epoch();
}
