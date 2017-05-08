#!/usr/bin/perl -w

BEGIN
{
	our $MYDIR = $0; $MYDIR =~ s,(.*)/.*,$1,; $MYDIR = '.' if ($MYDIR eq $0);
}
use lib $MYDIR;

use strict;
use warnings;
use RSM;
use RSMSLV;
use TLD_constants qw(:api);
use Data::Dumper;

# todo phase 1: use these 3 from RSMSLV.pm, e. g. create function there that will do what's done in this script
use constant ONLINE => 1;
use constant OFFLINE => 0;
use constant PROBE_KEY_MANUAL => 'rsm.probe.status[manual]';

parse_opts('server-id=s', 'probe=s', 'set=n');

# do not write any logs
setopt('nolog');

if (opt('debug'))
{
	dbg("command-line parameters:");
	dbg("$_ => ", getopt($_)) foreach (optkeys());
}

__validate_input();

my $config = get_rsm_config();

set_slv_config($config);

my $server_id = getopt('server-id');

$server_key = get_rsm_server_key($server_id);

my $section = $config->{$server_key};

fail("Error: server-id \"$server_id\" not found in configuration file") if (!defined($section));

db_connect($server_key);

my $probe = getopt('probe');

my $rows_ref = db_select("select hostid from hosts where host='$probe' and status=".HOST_STATUS_MONITORED);

fail("Error: Probe \"$probe\" not found on Server with ID $server_id.") if (scalar(@{$rows_ref}) != 1);

my $hostid = $rows_ref->[0]->[0];

if (opt('set'))
{
	my $zabbix = Zabbix->new({'url' => $section->{'za_url'}, user => $section->{'za_user'}, password => $section->{'za_password'}});

	my $result = $zabbix->get('proxy',{'output' => ['proxyid', 'host'], 'filter' => {'host' => $probe}, 'selectInterface' => ['ip', 'port'], 'preservekeys' => 1 });

	fail("Probe \"$probe\" not found on Server ID $server_id (did you forget to reload configuration cache?)") if (scalar(keys(%{$result})) == 0);

	my ($ip, $port);

	foreach my $proxyid (keys(%{$result}))
	{
		my $proxy = $result->{$proxyid};

		$ip = $proxy->{'interface'}->{'ip'};
		$port = $proxy->{'interface'}->{'port'};
	}

	__send_to_probe($ip, $port, $probe, PROBE_KEY_MANUAL, time(), getopt('set'));
}
else
{
	my $itemid = get_itemid_by_hostid($hostid, PROBE_KEY_MANUAL);

	$rows_ref = db_select(
		"select value".
		" from history_uint".
		" where itemid=$itemid".
		" order by clock desc".
		" limit 1");

	if (scalar(@{$rows_ref}) == 0)
	{
		print("Probe manual online status is not currently set for Probe \"$probe\".\n");
	}
	else
	{
		my $status;

		if ($rows_ref->[0]->[0] == OFFLINE)
		{
			$status = "Offline";
		}
		elsif ($rows_ref->[0]->[0] == ONLINE)
		{
			$status = "Online";
		}
		else
		{
			$status = "Unknown";
		}

		print("Current Probe manual online status on \"$probe\" is: $status.\n");
	}
}

db_disconnect();

sub __validate_input
{
	if (!opt('server-id') || !opt('probe'))
	{
		usage();
	}

	if (opt('set') && getopt('set') ne "0" && getopt('set') ne "1")
	{
		print("acceptable options for \"--set\": 0/1\n");
		exit(1);
	}
}

sub __send_to_probe
{
	my $ip = shift;
	my $port = shift;
	my $hostname = shift;
	my $key = shift;
	my $timestamp = shift;
	my $value = shift;

	my $section = $config->{$server_key};

	my $sender = Zabbix::Sender->new({
		'server' => $ip,
		'port' => $port,
		'timeout' => 10,
		'retries' => 5,
		'debug' => getopt('debug') });

	
	fail("Cannot connect to Probe ($ip:$port)") if (!defined($sender));

	my @hashes;

	push(@hashes, {'host' => $hostname, 'key' => $key, 'clock' => $timestamp, 'value' => $value});

	fail("Cannot send data to Zabbix server: " . $sender->sender_err()) unless (defined($sender->send_arrref(\@hashes)));
}

__END__

=head1 NAME

probe-manual.pl - set Probe status to Online/Offline

=head1 SYNOPSIS

probe-manual.pl --server-id <num> --probe <name> [--set <0/1>] [--debug] [--help]

=head1 OPTIONS

=over 8

=item B<--server-id> num

Specify ID of Zabbix server.

=item B<--probe> name

Specify the name of the Probe.

=item B<--set> num

Specify 0 to set Probe as Offline, specify 1 to set it Online.

=item B<--probe> name

Process only specified probe.

=item B<--debug>

Run the script in debug mode. This means printing more information.

=item B<--help>

Print a brief help message and exit.

=back

=head1 DESCRIPTION

B<This program> will print information about Probe availability at a specified period.

=head1 EXAMPLES

./probe-avail.pl --from 1443015000 --period 10

This will output Probe availability for all service tests that fall under period 23.09.2015 16:30:00-16:40:00 .

=cut
