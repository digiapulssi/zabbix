#!/usr/bin/perl
#

BEGIN
{
	our $MYDIR = $0; $MYDIR =~ s,(.*)/externalscripts/.*,$1/scripts,; $MYDIR = '../scripts' if ($MYDIR eq $0);
}
use lib $MYDIR;

use strict;
use RSM;
use RSMSLV;
use TLD_constants qw(:api);
use Data::Dumper;

parse_opts();

use constant ENABLE_LOGFILE => 0;
use constant LOGFILE => '/tmp/online.nodes.debug.log';

use constant PROBE_KEY_ONLINE	=> 'rsm.probe.online';	# todo phase 1: taken from RSMSLV.pm, define in single place in phase 2

my $command = shift || 'total';
my $type = shift || 'dns';

die("$command: invalid command") if ($command ne 'total' and $command ne 'online');
die("$type: invalid type") if ($type ne 'dns' and $type ne 'epp' and $type ne 'rdds' and $type ne 'ipv4' and $type ne 'ipv6');

sub dbg
{
	if (opt('debug'))
	{
		print(join('', @_), "\n");
	}

	return unless (ENABLE_LOGFILE == 1);

	my $msg = join('', @_);

	my $OUTFILE;

	open $OUTFILE, '>>', LOGFILE or die("cannot open file ", LOGFILE, ": $!");
	print {$OUTFILE} ts_str(), " ", $msg, "\n" or die("cannot write to file ", LOGFILE, ": $!");
	close $OUTFILE or die("cannot close file ", LOGFILE, ": $!");
}

set_slv_config(get_rsm_config());

db_connect();

my $probes_ref = get_probes($type);

my $total = 0;
my $online = 0;

foreach my $probe (keys(%{$probes_ref}))
{
	my $itemid = get_itemid_by_host("$probe - mon", PROBE_KEY_ONLINE);
	my $value;

	next if (get_current_value($itemid, ITEM_VALUE_TYPE_UINT64, \$value) != SUCCESS);

	$online++ if $value == ONLINE;
	$total++;
}

dbg($total, " total probes available for $type tests") if ($command eq 'total');
dbg($online, " online probes available for $type tests") if ($command eq 'online');

print $total if $command eq 'total';
print $online if $command eq 'online';
print 0 if $command ne 'total' and $command ne 'online';

sub usage
{
	print("usage: $0 [--debug|--help]\n");
	exit(-1);
}
