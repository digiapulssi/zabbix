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
use DateTime;

use constant MAX_CYCLES_TO_PROCESS => 5;

parse_slv_opts();
fail_if_running();
set_slv_config(get_rsm_config());
db_connect();

my $slv_item_key_pattern = 'rsm.slv.dns.ns.avail';
my $rtt_item_key_pattern = 'rsm.dns.udp.rtt';
my $current_month_latest_cycle = current_month_latest_cycle();
my $cfg_minonline = get_macro_dns_probe_online();

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

	foreach (@{get_slv_dns_ns_downtime_items($hostid)})
	{
		process_slv_item($tld, @$_);
	}
}

sub get_slv_dns_ns_downtime_items
{
	my $hostid = shift;

	return db_select("select itemid,key_ from items".
		" where hostid=$hostid and key_ like '$slv_item_key_pattern\[%'");
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
	my $slv_clock = get_slv_last_clock($slv_itemid);

	my $n = 0;

	for (;;)
	{
		last if ($n >= MAX_CYCLES_TO_PROCESS);
		$n++;

		if (defined($slv_clock))
		{
			$slv_clock += 60;
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
		my $till = $slv_clock + 59;
		
		my $online_probe_count = scalar(keys(%{get_probe_times($from, $till, get_probes('DNS'))}));

		if ($online_probe_count < $cfg_minonline)
		{
			push_value($tld, $slv_itemkey, $from, UP_INCONCLUSIVE_NO_PROBES,
				"Up (not enough probes online, $online_probe_count while $cfg_minonline required)");
		}
		else
		{
			my $rtt_values = get_rtt_values($from, $till, $rtt_itemids);
			my $probes_with_results = scalar(@{$rtt_values});

			if ($probes_with_results < $cfg_minonline)
			{
				push_value($tld, $slv_itemkey, $from, UP_INCONCLUSIVE_NO_DATA,
					"Up (not enough probes with results, $probes_with_results while $cfg_minonline required)");
			}
			else
			{
				my $down_rtt_count = 0;

				foreach my $rtt_value (@{$rtt_values})
				{
					if ($rtt_value <= -200)
					{
						$down_rtt_count++;
					}
				}

				my $probe_count = scalar(@{$rtt_itemids});
				my $limit = (SLV_UNAVAILABILITY_LIMIT * 0.01) * $probe_count;

				push_value($tld, $slv_itemkey, $from, ($down_rtt_count > $limit) ? DOWN : UP);
			}
		}
	}
}

sub get_dns_udp_rtt_itemids
{
	my $nsip = shift;

	my $rows = db_select("select itemid,key_ from items".
		" where key_ like '$rtt_item_key_pattern\[\%$nsip]' and templateid is not null");

	my $itemids = [];

	foreach my $row (@{$rows})
	{
		push($itemids, $row->[0]);
	}

	return $itemids;
}

sub get_slv_last_clock
{
	my $itemid = shift;

	my $rows = db_select("select clock from lastvalue where itemid=$itemid");

	return defined($rows) ? $rows->[0][0] : undef;
}

sub get_rtt_values
{
	my $from = shift;
	my $till = shift;
	my $rtt_itemids = shift;

	my $rows = db_select("select value from history where itemid in (".join(',', @{$rtt_itemids}).")".
		" and clock between $from and $till");

	return [] unless defined($rows);

	my @values;

	foreach my $row (@{$rows})
	{
		push(\@values, $row->[0]);
	}

	return \@values;
}

sub current_month_latest_cycle
{
	my $now;

	if (opt('now'))
	{
		$now = getopt('now');
	}
	else
	{
		$now = time();
	}

	# we don't know the rollweek bounds yet so we assume it ends at least few minutes back
	return cycle_start($now, 60) - ROLLWEEK_SHIFT_BACK;
}
