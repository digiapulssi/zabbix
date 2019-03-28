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

use constant MAX_CYCLES_TO_PROCESS => 5;

my $avail_key_pattern = 'rsm.slv.dns.ns.avail';
my $downtime_key_pattern = 'rsm.slv.dns.ns.downtime';

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

	my $rows_avail = db_select("select itemid,key_ from items".
		" where hostid=$hostid and key_ like '$avail_key_pattern\[%'");

	if (!defined($rows_avail))
	{
		fail("failed to obtain ns avail items");
	}

	my $rows_downtime = db_select("select itemid,key_ from items".
		" where hostid=$hostid and key_ like '$downtime_key_pattern\[%'");

	if (!defined($rows_downtime))
	{
		fail("failed to obtain ns downtime items");
	}

	if (scalar(@{$rows_avail}) != scalar(@{$rows_downtime}))
	{
		fail("got different number of ns avail and downtime items for tld $tld");
	}

	my $items_by_nsip = {};
	
	foreach my $row (@{$rows_avail})
	{
		my $itemid = $row->[0];
		my $itemkey = $row->[1];

		if ($itemkey =~ /\[(.+,.+)\]$/)
		{
			$items_by_nsip->{$1} = {'avail' => $itemid};
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
				$items_by_nsip->{$1}{'downtime'} = $itemid;
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

	for my $nsip (keys($items_by_nsip))
	{
		my $items = $items_by_nsip->{$nsip};

		my $lastvalues = get_lastvalues_by_itemids([$items->{'avail'}, $items->{'downtime'}],
				ITEM_VALUE_TYPE_UINT64);

		process_avail_downtime_item_pair($tld, $nsip, $items->{'avail'}, $lastvalues->{$items->{'avail'}},
				$items->{'downtime'}, $lastvalues->{$items->{'downtime'}}, $items->{'downtime_key'});
	}
}

sub process_avail_downtime_item_pair
{
	my $tld = shift;
	my $nsip = shift;
	my $avail_itemid = shift;
	my $avail_item = shift;
	my $downtime_itemid = shift;
	my $downtime_item = shift;
	my $downtime_key = shift;

	fail("avail item not defined for $nsip") unless defined($avail_item);
	fail("downtime itemid not defined for $nsip") unless defined($downtime_itemid);

	if (!defined($downtime_item))
	{
		$downtime_item = {'clock' => current_month_first_cycle() - 60, 'value' => 0}
	}

	my $clock = $downtime_item->{'clock'} + 60;
	my $n = 0;

	while ($n < MAX_CYCLES_TO_PROCESS and $clock <= $avail_item->{'clock'})
	{
		my $month_changed = (month_start($clock) != month_start($downtime_item->{'clock'}) ? 1 : 0);
		my $new_downtime_value = ($month_changed ? 0 : $downtime_item->{'value'}) 
				+ ($avail_item->{'value'} == DOWN ? 1 : 0);

		push_value($tld, $downtime_key, $clock, $new_downtime_value);

		$clock += 60;
		$n++;
	}
}
