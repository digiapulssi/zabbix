#!/usr/bin/perl
#
# Availability of particular nameservers

BEGIN { our $MYDIR = $0; $MYDIR =~ s,(.*)/.*/.*,$1,; $MYDIR = '..' if ($MYDIR eq $0); }
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

my $slv_item_key_pattern = 'rsm.slv.dns.ns.avail';
my $rtt_item_key_pattern = 'rsm.dns.udp.rtt';

my $now;

if (opt('now'))
{
	$now = getopt('now');
}
else
{
	$now = time();
}


my $max_cycles = (opt('cycles') ? getopt('cycles') : slv_max_cycles('dns'));
my $cycle_delay = get_dns_udp_delay();
my $current_month_latest_cycle = current_month_latest_cycle();
my $cfg_minonline = get_macro_dns_probe_online();
my $dns_rtt_low = get_rtt_low('dns', PROTO_UDP);
my $rtt_itemids = get_all_dns_udp_rtt_itemids();

init_values();
process_values();
send_values();

slv_exit(SUCCESS);

sub process_values
{
	foreach my $tld (@{get_tlds_and_hostids(opt('tld') ? getopt('tld') : undef)})
	{
		process_tld(@{$tld});
	}
}

sub process_tld
{
	my $tld = shift;
	my $hostid = shift;

	foreach (@{get_slv_dns_ns_avail_items($hostid)})
	{
		process_slv_item($tld, @$_);
	}
}

sub get_slv_dns_ns_avail_items
{
	my $hostid = shift;

	return db_select("select itemid,key_ from items where hostid=$hostid and key_ like '$slv_item_key_pattern\[%'");
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

	my $slv_clock;

	get_lastvalue($slv_itemid, ITEM_VALUE_TYPE_UINT64, undef, \$slv_clock);

	for (my $n = 0; $n < $max_cycles; $n++)
	{
		if (defined($slv_clock))
		{
			$slv_clock += $cycle_delay;
		}
		else
		{
			$slv_clock = current_month_first_cycle(); #start from beginning of the current month if no slv data
		}

		if ($slv_clock >= $current_month_latest_cycle)
		{
			dbg("processed all available data");
			last;
		}

		my $from = $slv_clock;
		my $till = $slv_clock + $cycle_delay - 1;

		my $online_probe_count = get_online_probe_count($from, $till);

		if ($online_probe_count < $cfg_minonline)
		{
			push_value($tld, $slv_itemkey, $from, UP_INCONCLUSIVE_NO_PROBES,
				"Up (not enough probes online, $online_probe_count while $cfg_minonline required)");

			next;
		}

		my $rtt_values = get_rtt_values($from, $till, $rtt_itemids->{$tld}{$nsip});
		my $probes_with_results = scalar(@{$rtt_values});

		if ($probes_with_results < $cfg_minonline)
		{
			push_value($tld, $slv_itemkey, $from, UP_INCONCLUSIVE_NO_DATA,
				"Up (not enough probes with results, $probes_with_results while $cfg_minonline required)");

			next;
		}

		my $down_rtt_count = 0;

		foreach my $rtt_value (@{$rtt_values})
		{
			if (is_service_error('dns', $rtt_value, $dns_rtt_low))
			{
				$down_rtt_count++;
			}
		}

		my $probe_count = scalar(@{$rtt_itemids->{$tld}{$nsip}});
		my $limit = (SLV_UNAVAILABILITY_LIMIT * 0.01) * $probe_count;

		push_value($tld, $slv_itemkey, $from, ($down_rtt_count > $limit) ? DOWN : UP);
	}
}

sub get_all_dns_udp_rtt_itemids
{
	my $rows = db_select(
		"select substring_index(hosts.host,' ',1),items.itemid,items.key_" .
		" from" .
			" items" .
			" left join hosts on hosts.hostid = items.hostid" .
		" where" .
			" items.key_ like '$rtt_item_key_pattern\[%,%,%\]' and" .
			" items.templateid is not null and" .
			" hosts.host like '% %'"
	);

	my $itemids = {};

	foreach my $row (@{$rows})
	{
		my $tld    = $row->[0];
		my $itemid = $row->[1];
		my $key    = $row->[2];
		my $nsip   = $key =~ s/^.+\[.+,(.+,.+)\]$/$1/r;
		push(@{$itemids->{$tld}{$nsip}}, $itemid);
	}

	return $itemids;
}

my $online_probe_count_cache = {};

sub get_online_probe_count
{
	my $from = shift;
	my $till = shift;
	my $key = "$from-$till";

	if (!defined($online_probe_count_cache->{$key}))
	{
		$online_probe_count_cache->{$key} = scalar(keys(%{get_probe_times($from, $till, get_probes('DNS'))}));
	}

	return $online_probe_count_cache->{$key};
}

sub get_rtt_values
{
	my $from = shift;
	my $till = shift;
	my $rtt_itemids = shift;

	my $itemids_placeholder = join(",", ("?") x scalar(@{$rtt_itemids}));

	return db_select_col(
		"select value" .
		" from history" .
		" where itemid in ($itemids_placeholder) and clock between ? and ?",
		[@{$rtt_itemids}, $from, $till]
	);
}

sub current_month_latest_cycle
{
	# we don't know the rollweek bounds yet so we assume it ends at least few minutes back
	return cycle_start($now, $cycle_delay) - AVAIL_SHIFT_BACK;
}
