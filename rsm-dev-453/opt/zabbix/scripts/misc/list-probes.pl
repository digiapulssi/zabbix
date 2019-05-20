#!/usr/bin/perl

BEGIN
{
	our $MYDIR = $0; $MYDIR =~ s,(.*)/.*,$1,; $MYDIR = '.' if ($MYDIR eq $0);
	our $MYDIR2 = $0; $MYDIR2 =~ s,(.*)/.*/.*,$1,; $MYDIR2 = '..' if ($MYDIR2 eq $0);
}
use lib $MYDIR;
use lib $MYDIR2;

use strict;
use warnings;
use Zabbix;
use RSM;
use Data::Dumper;

my $config = get_rsm_config();

my @server_keys = get_rsm_server_keys($config);

foreach my $server_key (@server_keys)
{
	my $section = $config->{$server_key};

	print($server_key, "\n");

	my $zabbix = Zabbix->new({'url' => $section->{'za_url'}, user => $section->{'za_user'}, password => $section->{'za_password'}});

	my $result = $zabbix->get('proxy',{'output' => ['proxyid', 'host'], 'selectInterface' => ['ip', 'port'], 'preservekeys' => 1 });

	my $probes = {'total' => 0, 'disabled' => 0, 'features' => {}};

	foreach my $proxyid (keys(%{$result}))
	{
		my $proxy = $result->{$proxyid};

		my $ph = $proxy->{'host'};
		my ($ip, $port);

		$probes->{'total'}++;

		if (ref($proxy->{'interface'}) eq 'ARRAY')
		{
			# disabled probe
			$ip = "DISABLED";
			$port = "PASSIVE";

			$probes->{'disabled'}++;
		}
		else
		{
			$ip = $proxy->{'interface'}->{'ip'};
			$port = $proxy->{'interface'}->{'port'};
		}

		my ($tname, $result2, $hostid, $macro);

		$tname = 'Template '.$ph;
		$result2 = $zabbix->get('template', {'output' => ['templateid'], 'filter' => {'host' => $tname}});
		$hostid = $result2->{'templateid'};

		my %features;

		foreach my $feature ('RDDS', 'EPP', 'IP4', 'IP6')
		{
			$macro = "{\$RSM.$feature.ENABLED}";

			$probes->{'features'}->{$feature} //= 0;

			my $result = $zabbix->get('usermacro', {'output' => 'extend', 'hostids' => $hostid, 'filter' => {'macro' => $macro}});

			fail("macro \"$macro\" not defined on \"$tname\"") unless (defined($result->{'value'}));

			if ($result->{'value'} == 0)
			{
				$features{$feature} = "off";
			}
			else
			{
				$features{$feature} = "on";
				$probes->{'features'}->{$feature}++;
			}
		}

		print("  $ph ($ip:$port)\t");

		map {print("$_:$features{$_}\t")} (sort(keys(%features)));

		print("\n");
	}

	print("Total $probes->{'total'} probes, disabled:$probes->{'disabled'}\t");

	map {print("$_:$probes->{'features'}->{$_}\t")} (sort(keys(%{$probes->{'features'}})));

	print("\n");

	print("\n") unless ($server_key eq $server_keys[-1]);
}
