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

parse_slv_opts();
fail_if_running();
set_slv_config(get_rsm_config());
db_connect();

use constant AVAIL_KEY_PATTERN => 'rsm.slv.dns.ns.avail';
use constant DOWNTIME_KEY_PATTERN => 'rsm.slv.dns.ns.downtime';

my $max_cycles = (opt('cycles') ? getopt('cycles') : slv_max_cycles('dns'));

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

	process_ns_items($tld, $hostid, get_ns_items($tld, $hostid));
}

sub get_ns_items
{
	my $tld = shift;
	my $hostid = shift;

	my $rows_avail = db_select(
		"select itemid,key_".
		" from items".
		" where hostid=$hostid".
			" and key_ like '" . AVAIL_KEY_PATTERN . "[%'"
	);

	fail("failed to obtain ns avail items") unless (scalar(@{$rows_avail}));

	my $rows_downtime = db_select(
		"select itemid,key_".
		" from items".
		" where hostid=$hostid".
			" and key_ like '" . DOWNTIME_KEY_PATTERN . "[%'"
	);

	fail("failed to obtain ns downtime items") unless (scalar(@{$rows_downtime}));

	if (scalar(@{$rows_avail}) != scalar(@{$rows_downtime}))
	{
		fail("got different number of ns avail and downtime items");
	}

	my $items_by_nsip = {};

	foreach my $row (@{$rows_avail})
	{
		my $itemid = $row->[0];
		my $itemkey = $row->[1];

		if ($itemkey =~ /\[(.+,.+)\]$/)
		{
			$items_by_nsip->{$1} = {'avail_itemid' => $itemid};
		}
		else
		{
			fail("cannot extract ns,ip pair from ns avail item key '$itemkey'");
		}
	}

	foreach my $row (@{$rows_downtime})
	{
		my $itemid = $row->[0];
		my $itemkey = $row->[1];

		if ($itemkey =~ /\[(.+,.+)\]$/)
		{
			if (defined($items_by_nsip->{$1}))
			{
				$items_by_nsip->{$1}{'downtime_itemid'} = $itemid;
				$items_by_nsip->{$1}{'downtime_key'} = $itemkey;
			}
			else
			{
				fail("no ns avail item for ns,ip pair '$1'");
			}
		}
		else
		{
			fail("cannot extract ns,ip pair from ns downtime item key '$itemkey'");
		}
	}

	return $items_by_nsip;
}

sub process_ns_items
{
	my $tld = shift;
	my $hostid = shift;
	my $items_by_nsip = shift;

	for my $nsip (keys(%{$items_by_nsip}))
	{
		my $items = $items_by_nsip->{$nsip};

		calculate_downtime_values($tld, $nsip,
				$items->{'avail_itemid'}, $items->{'downtime_itemid'}, $items->{'downtime_key'});
	}
}

sub calculate_downtime_values
{
	my $tld = shift;
	my $nsip = shift;
	my $avail_itemid = shift;
	my $downtime_itemid = shift;
	my $downtime_key = shift;

	my $avail_clock;
	my $avail_value;

	if (SUCCESS != get_lastvalue($avail_itemid, ITEM_VALUE_TYPE_UINT64, \$avail_value, \$avail_clock))
	{
		fail("cannot get lastvalue for avail item $avail_itemid");
	}

	my $downtime_value;
	my $downtime_clock;

	if (SUCCESS != get_lastvalue($downtime_itemid, ITEM_VALUE_TYPE_UINT64, \$downtime_value, \$downtime_clock))
	{
		$downtime_value = 0;

		$downtime_clock = db_select_value("select min(clock)-60 from history_uint where itemid=?", [$avail_itemid]);

		fail("no name server availability data yet") unless (defined($downtime_clock));
	}

	if ($downtime_clock >= $avail_clock)
	{
		dbg("no new data for nsip '$nsip'");
		return;
	}

	my $clock_first = $downtime_clock + 60;
	my $clock_last = $downtime_clock + (60 * $max_cycles);

	if ($clock_last > $avail_clock)
	{
		$clock_last = $avail_clock;
	}

	my $rows = db_select(
		"select clock,value".
		" from history_uint".
		" where itemid=$avail_itemid".
			" and " . sql_time_condition($clock_first, $clock_last)
	);

	fail("cannot obtain values for ns '$nsip' avail item") unless (scalar(@{$rows}));

	foreach my $row (@{$rows})
	{
		my $clock = $row->[0];
		my $avail_value = $row->[1];

		my $prev_clock = $clock - 60;
		my $month_changed = (month_start($prev_clock) != month_start($clock) ? 1 : 0);

		my $new_downtime_value = ($month_changed ? 0 : $downtime_value) + ($avail_value == DOWN ? 1 : 0);

		push_value($tld, $downtime_key, $clock, $new_downtime_value);

		$downtime_value = $new_downtime_value;
		$clock += 60;
	}
}
