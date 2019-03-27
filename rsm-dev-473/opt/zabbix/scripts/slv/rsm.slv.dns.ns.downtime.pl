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

	my $items = get_ns_items($tld, $hostid);

	print Dumper($items);
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

	my $items = {};
	
	foreach my $row (@{$rows_avail})
	{
		my $itemid = $row->[0];
		my $itemkey = $row->[1];

		if ($itemkey =~ /\[(.+,.+)\]$/)
		{
			$items->{$1} = {'avail_itemid' => $itemid};
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
			if (defined($items->{$1}))
			{
				$items->{$1}{'downtime_itemid'} = $itemid
			}
			else
			{
				fail("no ns avail items for ns,ip pair '$1'");
			}
		}
		else
		{
			fail("cannot extract ns,ip pair from ns downtime item key '$itemkey'");
		}
	}

	return $items;
}
