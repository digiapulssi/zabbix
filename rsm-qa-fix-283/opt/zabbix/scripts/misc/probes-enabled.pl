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

	my $total = 0;
	my $rdds_num = 0;
	my $epp_num = 0;

	foreach my $proxyid (keys(%{$result}))
	{
		my $proxy = $result->{$proxyid};

		$total++;

		my $ph = $proxy->{'host'};
		my $ip = $proxy->{'interface'}->{'ip'};
		my $port = $proxy->{'interface'}->{'port'};

		my ($tname, $result2, $hostid, $macro);

		my $rdds = "no";
		my $epp = "no";

		$tname = 'Template '.$ph;
		$result2 = $zabbix->get('template', {'output' => ['templateid'], 'filter' => {'host' => $tname}});

		$hostid = $result2->{'templateid'};

		$macro = '{$RSM.RDDS.ENABLED}';
		$result2 = $zabbix->get('usermacro', {'output' => 'extend', 'hostids' => $hostid, 'filter' => {'macro' => $macro}});
		if (defined($result2->{'value'}) and $result2->{'value'} != 0)
		{
			$rdds_num++;
			$rdds = "yes";
		}

		$macro = '{$RSM.EPP.ENABLED}';
		$result2 = $zabbix->get('usermacro', {'output' => 'extend', 'hostids' => $hostid, 'filter' => {'macro' => $macro}});
		if (defined($result2->{'value'}) and $result2->{'value'} != 0)
		{
			$epp_num++;
			$epp = "yes";
		}

		print("  $ph ($ip:$port): RDDS:$rdds EPP:$epp\n");
	}

	if ($total == $rdds_num and $total == $epp_num)
	{
		print("Total $total probes, all with RDDS and EPP enabled\n");
	}
	elsif ($rdds_num == 0 and $epp_num == 0)
	{
		print("Total $total probes, all with RDDS and EPP disabled\n");
	}
	else
	{
		print("Total $total probes, $rdds_num with RDDS enabled, $epp_num with EPP enabled\n");
	}

	print("\n") unless ($server_key eq $server_keys[-1]);
}
