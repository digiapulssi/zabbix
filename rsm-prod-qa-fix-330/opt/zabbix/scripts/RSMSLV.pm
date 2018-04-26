package RSMSLV;

use strict;
use warnings;

use DBI;
use Getopt::Long;
use Pod::Usage;
use Exporter qw(import);
use Zabbix;
use Alerts;
use TLD_constants qw(:api :items);
use File::Pid;
use POSIX qw(floor);
use Sys::Syslog;
use Data::Dumper;
use Time::HiRes;
use RSM;
use Pusher qw(push_to_trapper);

use constant SUCCESS => 0;
use constant E_FAIL => -1;
use constant E_ID_NONEXIST => -2;
use constant E_ID_MULTIPLE => -3;

use constant UP => 1;
use constant DOWN => 0;
use constant ONLINE => 1;	# todo phase 1: check where these are used
use constant OFFLINE => 0;	# todo phase 1: check where these are used
use constant SLV_UNAVAILABILITY_LIMIT => 49; # NB! must be in sync with frontend

use constant MAX_SERVICE_ERROR => -200; # -200, -201 ...
use constant RDDS_UP => 2; # results of input items: 0 - RDDS down, 1 - only RDDS43 up, 2 - both RDDS43 and RDDS80 up
use constant MIN_LOGIN_ERROR => -205;
use constant MAX_LOGIN_ERROR => -203;
use constant MIN_INFO_ERROR => -211;
use constant MAX_INFO_ERROR => -209;

use constant TRIGGER_SEVERITY_NOT_CLASSIFIED => 0;
use constant EVENT_OBJECT_TRIGGER => 0;
use constant EVENT_SOURCE_TRIGGERS => 0;
use constant TRIGGER_VALUE_FALSE => 0;
use constant TRIGGER_VALUE_TRUE => 1;
use constant INCIDENT_FALSE_POSITIVE => 1; # NB! must be in sync with frontend
use constant PROBE_LASTACCESS_ITEM => 'zabbix[proxy,{$RSM.PROXY_NAME},lastaccess]';
use constant PROBE_GROUP_NAME => 'Probes';
use constant PROBE_KEY_MANUAL => 'rsm.probe.status[manual]';
use constant PROBE_KEY_AUTOMATIC => 'rsm.probe.status[automatic,%]'; # match all in SQL

use constant RSM_CONFIG_DNS_UDP_DELAY_ITEMID => 100008;	# rsm.configvalue[RSM.DNS.UDP.DELAY]
use constant RSM_CONFIG_RDDS_DELAY_ITEMID => 100009;	# rsm.configvalue[RSM.RDDS.DELAY]
use constant RSM_CONFIG_EPP_DELAY_ITEMID => 100010;	# rsm.configvalue[RSM.EPP.DELAY]

# In order to do the calculation we should wait till all the results
# are available on the server (from proxies). We shift back 2 minutes
# in case of "availability" and 3 minutes in case of "rolling week"
# calculations.
# NB! These numbers must be in sync with Frontend (details page)!
use constant PROBE_ONLINE_SHIFT		=> 120;	# seconds (must be divisible by 60) to go back for Probe online status calculation
use constant AVAIL_SHIFT_BACK		=> 120;	# seconds (must be divisible by 60) to go back for Service Availability calculation
use constant ROLLWEEK_SHIFT_BACK	=> 180;	# seconds (must be divisible by 60) to go back for Rolling Week calculation

use constant RESULT_TIMESTAMP_SHIFT => 29; # seconds (shift back from upper time bound of the period for the value timestamp)

use constant PROBE_ONLINE_STR => 'Online';
use constant PROBE_OFFLINE_STR => 'Offline';
use constant PROBE_NORESULT_STR => 'No result';

our ($result, $dbh, $tld, $server_key);

our %OPTS; # specified command-line options

our @EXPORT = qw($result $dbh $tld $server_key
		SUCCESS E_FAIL E_ID_NONEXIST E_ID_MULTIPLE UP DOWN RDDS_UP SLV_UNAVAILABILITY_LIMIT MIN_LOGIN_ERROR
		MAX_LOGIN_ERROR MIN_INFO_ERROR MAX_INFO_ERROR RESULT_TIMESTAMP_SHIFT PROBE_ONLINE_STR PROBE_OFFLINE_STR
		PROBE_NORESULT_STR AVAIL_SHIFT_BACK PROBE_ONLINE_SHIFT
		ONLINE OFFLINE
		get_macro_minns get_macro_dns_probe_online get_macro_rdds_probe_online get_macro_dns_rollweek_sla
		get_macro_rdds_rollweek_sla get_macro_dns_udp_rtt_high get_macro_dns_udp_rtt_low
		get_macro_dns_tcp_rtt_low get_macro_rdds_rtt_low get_macro_dns_udp_delay get_macro_dns_tcp_delay
		get_macro_rdds_delay get_macro_epp_delay get_macro_epp_probe_online get_macro_epp_rollweek_sla
		get_macro_dns_update_time get_macro_rdds_update_time get_tld_items get_hostid
		get_macro_epp_rtt_low get_macro_probe_avail_limit get_item_data get_itemid_by_key get_itemid_by_host
		get_itemid_by_hostid get_itemid_like_by_hostid get_itemids_by_host_and_keypart get_lastclock get_tlds
		get_probes get_nsips get_nsip_items tld_exists tld_service_enabled db_connect db_disconnect
		get_templated_nsips db_exec
		db_select db_select_binds set_slv_config get_interval_bounds get_rollweek_bounds get_downtime_bounds
		max_avail_time get_probe_times probe_offline_at probes2tldhostids
		get_probe_online_key_itemid
		init_values push_value send_values get_nsip_from_key is_service_error
		process_slv_avail avail_value_exists
		rollweek_value_exists
		sql_time_condition get_incidents get_downtime get_downtime_prepare get_downtime_execute
		get_current_value get_itemids_by_hostids get_nsip_values get_valuemaps get_statusmaps get_detailed_result
		get_avail_valuemaps slv_stats_reset
		get_result_string get_tld_by_trigger truncate_from truncate_till alerts_enabled get_test_start_time
		uint_value_exists
		get_real_services_period dbg info wrn fail
		format_stats_time slv_finalize slv_exit exit_if_running trim parse_opts
		parse_avail_opts parse_rollweek_opts opt getopt setopt unsetopt optkeys ts_str ts_full selected_period
		write_file
		cycle_start
		cycle_end
		usage);

# configuration, set in set_slv_config()
my $config = undef;

# this will be used for making sure only one copy of script runs (see function exit_if_running())
my $pidfile;
use constant PID_DIR => '/tmp';

my $_sender_values;	# used to send values to Zabbix server

my $POD2USAGE_FILE;	# usage message file

my ($_global_sql, $_global_sql_bind_values, $_lock_fh);

my $start_time;
my $sql_time = 0.0;
my $sql_count = 0;

sub get_macro_minns
{
	return __get_macro('{$RSM.DNS.AVAIL.MINNS}');
}

sub get_macro_dns_probe_online
{
	return __get_macro('{$RSM.DNS.PROBE.ONLINE}');
}

sub get_macro_rdds_probe_online
{
	return __get_macro('{$RSM.RDDS.PROBE.ONLINE}');
}

sub get_macro_dns_rollweek_sla
{
	return __get_macro('{$RSM.DNS.ROLLWEEK.SLA}');
}

sub get_macro_rdds_rollweek_sla
{
	return __get_macro('{$RSM.RDDS.ROLLWEEK.SLA}');
}

sub get_macro_dns_udp_rtt_high
{
	return __get_macro('{$RSM.DNS.UDP.RTT.HIGH}');
}

sub get_macro_dns_udp_rtt_low
{
	return __get_macro('{$RSM.DNS.UDP.RTT.LOW}');
}

sub get_macro_dns_tcp_rtt_low
{
	return __get_macro('{$RSM.DNS.TCP.RTT.LOW}');
}

sub get_macro_rdds_rtt_low
{
	return __get_macro('{$RSM.RDDS.RTT.LOW}');
}

sub get_macro_dns_udp_delay
{
	my $value_time = (shift or time() - AVAIL_SHIFT_BACK);

	my $value = __get_configvalue(RSM_CONFIG_DNS_UDP_DELAY_ITEMID, $value_time);

	return $value if (defined($value));

	return __get_macro('{$RSM.DNS.UDP.DELAY}');
}

sub get_macro_dns_tcp_delay
{
	my $value_time = (shift or time() - AVAIL_SHIFT_BACK);

	# todo phase 1: Export DNS-TCP tests
	# todo phase 1: if we really need DNS-TCP history the item must be added (to db schema and upgrade patch)
#	my $value = __get_configvalue(RSM_CONFIG_DNS_TCP_DELAY_ITEMID, $value_time);
#
#	return $value if (defined($value));

	return __get_macro('{$RSM.DNS.TCP.DELAY}');
}

sub get_macro_rdds_delay
{
	my $value_time = (shift or time() - AVAIL_SHIFT_BACK);

	my $value = __get_configvalue(RSM_CONFIG_RDDS_DELAY_ITEMID, $value_time);

	return $value if (defined($value));

	return __get_macro('{$RSM.RDDS.DELAY}');
}

sub get_macro_epp_delay
{
	my $value_time = (shift or time() - AVAIL_SHIFT_BACK);

	my $value = __get_configvalue(RSM_CONFIG_EPP_DELAY_ITEMID, $value_time);

	return $value if (defined($value));

	return __get_macro('{$RSM.EPP.DELAY}');
}

sub get_macro_dns_update_time
{
	return __get_macro('{$RSM.DNS.UPDATE.TIME}');
}

sub get_macro_rdds_update_time
{
	return __get_macro('{$RSM.RDDS.UPDATE.TIME}');
}

sub get_macro_epp_probe_online
{
	return __get_macro('{$RSM.EPP.PROBE.ONLINE}');
}

sub get_macro_epp_rollweek_sla
{
	return __get_macro('{$RSM.EPP.ROLLWEEK.SLA}');
}

sub get_macro_epp_rtt_low
{
	return __get_macro('{$RSM.EPP.'.uc(shift).'.RTT.LOW}');
}

sub get_macro_probe_avail_limit
{
	return __get_macro('{$RSM.PROBE.AVAIL.LIMIT}');
}

sub get_item_data
{
	my $host = shift;
	my $cfg_key_in = shift;
	my $cfg_key_out = shift;
	my $value_type = shift;

	my $sql;

	if ("[" eq substr($cfg_key_out, -1))
	{
		$sql =
			"select i.key_,i.itemid".
			" from items i,hosts h".
			" where i.hostid=h.hostid".
				" and h.host='$host'".
				" and i.status=0".
				" and (i.key_='$cfg_key_in' or i.key_ like '$cfg_key_out%')";
	}
	else
	{
		$sql =
			"select i.key_,i.itemid".
			" from items i,hosts h".
			" where i.hostid=h.hostid".
				" and h.host='$host'".
				" and i.status=0".
				" and i.key_ in ('$cfg_key_in','$cfg_key_out')";
	}

	$sql .= " order by i.key_";

	my $rows_ref = db_select($sql);

	my $rows = scalar(@$rows_ref);

	fail("cannot find items ($cfg_key_in and $cfg_key_out) at host ($host)") if ($rows < 2);

	my $itemid_in = undef;
	my $itemid_out = undef;
	my $lastclock = undef;

	foreach my $row_ref (@$rows_ref)
	{
		if ($row_ref->[0] eq $cfg_key_in)
		{
			$itemid_in = $row_ref->[1];
		}
		else
		{
			$itemid_out = $row_ref->[1];
			if (get_current_value($itemid_out, $value_type, undef, \$lastclock) != SUCCESS)
			{
				$lastclock = 0;
			}
		}

		last if (defined($itemid_in) and defined($itemid_out));
	}

	fail("cannot find items (need $cfg_key_in and $cfg_key_out) at host ($host)")
		unless (defined($itemid_in) and defined($itemid_out));

	return ($itemid_in, $itemid_out, $lastclock);
}

sub get_itemid_by_key
{
	my $key = shift;

	return __get_itemid_by_sql("select itemid from items where key_='$key'");
}

sub get_itemid_by_host
{
	my $host = shift;
	my $key = shift;

	my $rows_ref = db_select(
		"select i.itemid".
		" from items i,hosts h".
		" where i.hostid=h.hostid".
	    		" and h.host='$host'".
			" and i.key_='$key'");

	fail("cannot find item ($key) at host ($host)") if (scalar(@$rows_ref) == 0);
	fail("more than one item ($key) at host ($host)") if (scalar(@$rows_ref) > 1);

	return $rows_ref->[0]->[0];
}

sub get_itemid_by_hostid
{
	my $hostid = shift;
	my $key = shift;

	return __get_itemid_by_sql("select itemid from items where hostid=$hostid and key_='$key'");
}

sub get_itemid_like_by_hostid
{
	my $hostid = shift;
	my $key = shift;

	return __get_itemid_by_sql("select itemid from items where hostid=$hostid and key_ like '$key'");
}

sub __get_itemid_by_sql
{
	my $sql = shift;

	my $rows_ref = db_select($sql);

	return E_ID_NONEXIST if (scalar(@$rows_ref) == 0);
        return E_ID_MULTIPLE if (scalar(@$rows_ref) > 1);

        return $rows_ref->[0]->[0];
}

sub get_itemids_by_host_and_keypart
{
	my $host = shift;
	my $key_part = shift;

	my $rows_ref = db_select(
		"select i.itemid,i.key_".
		" from items i,hosts h".
		" where i.hostid=h.hostid".
	    		" and h.host='$host'".
			" and i.key_ like '$key_part%'");

	fail("cannot find items ($key_part%) at host ($host)") if (scalar(@$rows_ref) == 0);

	my $result = {};

	foreach my $row_ref (@$rows_ref)
	{
		my $itemid = $row_ref->[0];
		my $key = $row_ref->[1];

		my $nsip = get_nsip_from_key($key);

		$result->{$nsip} = $itemid;
	}

	return $result;
}

# returns:
# E_FAIL - if item was not found
#      0 - if lastclock is NULL
#      * - lastclock
sub get_lastclock
{
	my $host = shift;
	my $key = shift;
	my $value_type = shift;

	my $sql;

	if ("[" eq substr($key, -1))
	{
		$sql =
			"select i.itemid".
			" from items i,hosts h".
			" where i.hostid=h.hostid".
			" and i.status=0".
			" and h.host='$host'".
			" and i.key_ like '$key%'".
			" limit 1";
	}
	else
	{
		$sql =
			"select i.itemid".
			" from items i,hosts h".
			" where i.hostid=h.hostid".
			" and i.status=0".
			" and h.host='$host'".
			" and i.key_='$key'";
	}

	my $rows_ref = db_select($sql);

	return E_FAIL if (scalar(@$rows_ref) == 0);

	my $itemid = $rows_ref->[0]->[0];
	my $lastclock;

	if (get_current_value($itemid, $value_type, undef, \$lastclock) != SUCCESS)
	{
		$lastclock = 0;
	}

	return $lastclock;
}

sub get_tlds
{
	my $service = shift;

	$service = defined($service) ? uc($service) : 'DNS';

	my $sql;

	if ($service eq 'DNS')
	{
		$sql =
			"select h.host".
			" from hosts h,hosts_groups hg,groups g".
			" where h.hostid=hg.hostid".
				" and hg.groupid=g.groupid".
				" and g.name='TLDs'".
				" and h.status=0";
	}
	else
	{
		$sql =
			"select h.host".
			" from hosts h,hosts_groups hg,groups g,hosts h2,hostmacro hm".
			" where h.hostid=hg.hostid".
				" and hg.groupid=g.groupid".
				" and h2.name=concat('Template ', h.host)".
				" and g.name='TLDs'".
				" and h2.hostid=hm.hostid".
				" and hm.macro='{\$RSM.TLD.$service.ENABLED}'".
				" and hm.value!=0".
				" and h.status=0";
	}

	$sql .= " order by h.host";

	my $rows_ref = db_select($sql);

	my @tlds;
	foreach my $row_ref (@$rows_ref)
	{
		push(@tlds, $row_ref->[0]);
	}

	return \@tlds;
}

# Returns a reference to hash of all probes (host => hostid).
# todo phase 1: this function changed in phase 2, please check callers
sub get_probes
{
	my $service = shift;
	my $name = shift;

	$service = defined($service) ? uc($service) : 'DNS';

	my $name_cond = "";

	$name_cond = " and h.host='$name'" if ($name);

	my $rows_ref = db_select(
		"select h.host,h.hostid".
		" from hosts h, hosts_groups hg, groups g".
		" where h.hostid=hg.hostid".
			" and hg.groupid=g.groupid".
			" and h.status=0".
			$name_cond.
			" and g.name='".PROBE_GROUP_NAME."'");

	my $result = {};

	foreach my $row_ref (@$rows_ref)
	{
		my $host = $row_ref->[0];
		my $hostid = $row_ref->[1];

		if ($service ne 'DNS')
		{
			$rows_ref = db_select(
				"select hm.value".
				" from hosts h,hostmacro hm".
				" where h.hostid=hm.hostid".
					" and h.host='Template $host'".
					" and hm.macro='{\$RSM.$service.ENABLED}'");

			next if (scalar(@$rows_ref) != 0 and $rows_ref->[0]->[0] == 0);
		}

		$result->{$host} = $hostid;
	}

	return $result;
}

# get array of key nameservers ('i.ns.se,130.239.5.114', ...)
sub get_nsips
{
	my $host = shift;
	my $key = shift;

	my $rows_ref = db_select(
		"select key_".
		" from items i,hosts h".
		" where i.hostid=h.hostid".
			" and i.status<>".ITEM_STATUS_DISABLED.
			" and h.host='$host'".
			" and i.key_ like '$key%'");

	my @nss;
	foreach my $row_ref (@$rows_ref)
	{
		push(@nss, get_nsip_from_key($row_ref->[0]));
	}

	fail("cannot find items ($key*) at host ($host)") if (scalar(@nss) == 0);

	return \@nss;
}

sub get_templated_nsips
{
	my $host = shift;
	my $key = shift;

	return get_nsips("Template $host", $key);
}

# return itemids grouped by hosts:
#
# {
#    'hostid1' => {
#         'itemid1' => 'ns2,2620:0:2d0:270::1:201',
#         'itemid2' => 'ns1,192.0.34.201'
#    },
#    'hostid2' => {
#         'itemid3' => 'ns2,2620:0:2d0:270::1:201',
#         'itemid4' => 'ns1,192.0.34.201'
#    }
# }
sub get_nsip_items
{
	my $nsips_ref = shift; # array reference of NS,IP pairs
	my $cfg_key_in = shift;
	my $tld = shift;

	my @keys;
	push(@keys, "'" . $cfg_key_in . $_ . "]'") foreach (@$nsips_ref);

	my $keys_str = join(',', @keys);

	my $rows_ref = db_select(
		"select h.hostid,i.itemid,i.key_ ".
		"from items i,hosts h ".
		"where i.hostid=h.hostid".
			" and h.host like '$tld %'".
			" and i.templateid is not null".
			" and i.key_ in ($keys_str)");

	my $result = {};

	foreach my $row_ref (@$rows_ref)
	{
		$result->{$row_ref->[0]}{$row_ref->[1]} = get_nsip_from_key($row_ref->[2]);
	}

	fail("cannot find items ($keys_str) at host ($tld *)") if (scalar(keys(%{$result})) == 0);

	return $result;
}

# returns a reference to a hash of itemid => hostid pairs
sub get_items_by_hostids
{
	my $hostids_ref = shift;
	my $cfg_key = shift;
	my $complete = shift;

	my $hostids_str = join(',', @$hostids_ref);

	my $rows_ref;
	if ($complete)
	{
		$rows_ref = db_select("select itemid,hostid from items where hostid in ($hostids_str) and key_='$cfg_key'");
	}
	else
	{
		$rows_ref = db_select("select itemid,hostid from items where hostid in ($hostids_str) and key_ like '$cfg_key%'");
	}

	my $result = {};

	foreach my $row_ref (@$rows_ref)
	{
		$result->{$row_ref->[0]} = $row_ref->[1];
	}

	return $result;
}

sub get_tld_items
{
	my $tld = shift;
	my $cfg_key = shift;

	my $rows_ref = db_select(
		"select i.itemid,i.key_".
		" from items i,hosts h".
		" where i.hostid=h.hostid".
			" and h.host='$tld'".
			" and i.key_ like '$cfg_key%'");

	my @items;
	foreach my $row_ref (@$rows_ref)
	{
		push(@items, $row_ref);
	}

	fail("cannot find items ($cfg_key*) at host ($tld)") if (scalar(@items) == 0);

	return \@items;
}

sub get_hostid
{
	my $host = shift;

	my $rows_ref = db_select("select hostid from hosts where host='$host'");

	fail("host \"$host\" not found") if (scalar(@$rows_ref) == 0);
	fail("multiple hosts \"$host\" found") if (scalar(@$rows_ref) > 1);

	return $rows_ref->[0]->[0];
}

sub tld_exists
{
	my $tld = shift;

	my $rows_ref = db_select(
		"select 1".
		" from hosts h,hosts_groups hg,groups g".
		" where h.hostid=hg.hostid".
			" and hg.groupid=g.groupid".
			" and g.name='TLDs'".
			" and h.status=0".
			" and h.host='$tld'");

	return 0 if (scalar(@$rows_ref) == 0);

	return 1;
}

sub tld_service_enabled
{
	my $tld = shift;
	my $service_type = shift;

	$service_type = uc($service_type) if (defined($service_type));

	return SUCCESS if (not defined($service_type) or $service_type eq 'DNS');

	my $host = "Template $tld";
	my $macro = "{\$RSM.TLD.$service_type.ENABLED}";

	my $rows_ref = db_select(
		"select hm.value".
		" from hosts h,hostmacro hm".
		" where h.hostid=hm.hostid".
			" and h.host='$host'".
			" and hm.macro='$macro'");

	if (scalar(@$rows_ref) == 0)
	{
		wrn("macro \"$macro\" does not exist at host \"$host\", assuming feature disabled");
		return E_FAIL;
	}

	return ($rows_ref->[0]->[0] == 0 ? E_FAIL : SUCCESS);
}

sub handle_db_error
{
	my $msg = shift;

	my $prefix = "";

	$prefix = "[tld:$tld] " if ($tld);

	my $bind_values_str = "";

	$bind_values_str = ' bind values: ' . join(',', @{$_global_sql_bind_values}) if (defined($_global_sql_bind_values));

	fail($prefix . "database error: $msg (query was: [$_global_sql]$bind_values_str)");
}

sub db_connect
{
	$server_key = shift;

	dbg("server_key:", ($server_key ? $server_key : "UNDEF"));

	fail("Error: no database configuration") unless (defined($config));

	db_disconnect() if (defined($dbh));

	$server_key = get_rsm_local_key($config) unless ($server_key);

	fail("Configuration error: section \"$server_key\" not found") unless (defined($config->{$server_key}));

	my $section = $config->{$server_key};

	foreach my $key ('db_name', 'db_user')
	{
		fail("configuration error: database $key not specified in section \"$server_key\"")
			unless (defined($section->{$key}));
	}

	my $db_tls_settings = get_db_tls_settings($section);

	$_global_sql = "DBI:mysql:database=$section->{'db_name'};host=$section->{'db_host'};$db_tls_settings";

	dbg($_global_sql);

	$dbh = DBI->connect($_global_sql, $section->{'db_user'}, $section->{'db_password'},
		{
			PrintError  => 0,
			HandleError => \&handle_db_error,
			mysql_auto_reconnect => 1
		}) or handle_db_error(DBI->errstr);

	# verify that established database connection uses TLS if there was any hint that it is required in the config
	unless ($db_tls_settings eq "mysql_ssl=0")
	{
		my $rows_ref = db_select("show status like 'Ssl_cipher';");

		fail("established connection is not secure") if ($rows_ref->[0]->[1] eq "");

		dbg("established connection uses \"" . $rows_ref->[0]->[1] . "\" cipher");
	}
	else
	{
		dbg("established connection is unencrypted");
	}

	# improve performance of selects, see
	# http://search.cpan.org/~capttofu/DBD-mysql-4.028/lib/DBD/mysql.pm
	# for details
	$dbh->{'mysql_use_result'} = 1;
}

sub db_disconnect
{
	dbg("connection: ", (defined($dbh) ? 'defined' : 'UNDEF'));

	if (defined($dbh))
	{
		$dbh->disconnect() || wrn($dbh->errstr);
		undef($dbh);
	}
}

sub db_select
{
	$_global_sql = shift;

	undef($_global_sql_bind_values);

	my $sec;
	if (opt('stats'))
	{
		$sec = Time::HiRes::time();
	}

	my $sth = $dbh->prepare($_global_sql)
		or fail("cannot prepare [$_global_sql]: ", $dbh->errstr);

	dbg("[$_global_sql]");

	my ($start, $exe, $fetch, $total);
	if (opt('warnslow'))
	{
		$start = Time::HiRes::time();
	}

	$sth->execute()
		or fail("cannot execute [$_global_sql]: ", $sth->errstr);

	if (opt('warnslow'))
	{
		$exe = Time::HiRes::time();
	}

	my $rows_ref = $sth->fetchall_arrayref();

	if (opt('warnslow'))
	{
		my $now = Time::HiRes::time();
		$total = $now - $start;

		if ($total > getopt('warnslow'))
		{
			$fetch = $now - $exe;
			$exe = $exe - $start;
			wrn("slow query: [$_global_sql] took ", sprintf("%.3f seconds (execute:%.3f fetch:%.3f)", $total, $exe, $fetch));
		}
	}

	if (opt('debug'))
	{
		if (scalar(@{$rows_ref}) == 1)
		{
			dbg(join(',', map {$_ // 'UNDEF'} (@{$rows_ref->[0]})));
		}
		else
		{
			dbg(scalar(@{$rows_ref}), " rows");
		}
	}

	if (opt('stats'))
	{
		$sql_time += Time::HiRes::time() - $sec;
		$sql_count++;
	}

	return $rows_ref;
}

# todo phase 1: taken from RSMSLV.pm phase 2
sub db_select_binds
{
	$_global_sql = shift;
	$_global_sql_bind_values = shift;

	my $sth = $dbh->prepare($_global_sql)
		or fail("cannot prepare [$_global_sql]: ", $dbh->errstr);

	dbg("[$_global_sql] ", join(',', @{$_global_sql_bind_values}));

	my ($start, $exe, $fetch, $total);

	my @rows;
	foreach my $bind_value (@{$_global_sql_bind_values})
	{
		if (opt('warnslow'))
		{
			$start = time();
		}

		$sth->execute($bind_value)
			or fail("cannot execute [$_global_sql] bind_value:$bind_value: ", $sth->errstr);

		if (opt('warnslow'))
		{
			$exe = time();
		}

		while (my @row = $sth->fetchrow_array())
		{
			push(@rows, \@row);
		}

		if (opt('warnslow'))
		{
			my $now = time();
			$total = $now - $start;

			if ($total > getopt('warnslow'))
			{
				$fetch = $now - $exe;
				$exe = $exe - $start;

				wrn("slow query: [$_global_sql], bind values: [", join(',', @{$_global_sql_bind_values}), "] took ", sprintf("%.3f seconds (execute:%.3f fetch:%.3f)", $total, $exe, $fetch));
			}
		}
	}

	if (opt('debug'))
	{
		if (scalar(@rows) == 1)
		{
			dbg(join(',', map {$_ // 'UNDEF'} ($rows[0])));
		}
		else
		{
			dbg(scalar(@rows), " rows");
		}
	}

	return \@rows;
}

# todo phase 1: taken from RSMSLV.pm phase 2 for DaWa.pm::dw_get_id()
sub db_exec
{
	$_global_sql = shift;

	my $sec;
	if (opt('stats'))
	{
		$sec = time();
	}

	my $sth = $dbh->prepare($_global_sql)
		or fail("cannot prepare [$_global_sql]: ", $dbh->errstr);

	dbg("[$_global_sql]");

	my ($start, $total);
	if (opt('warnslow'))
	{
		$start = time();
	}

	$sth->execute()
		or fail("cannot execute [$_global_sql]: ", $sth->errstr);

	if (opt('warnslow'))
	{
		$total = time() - $start;

		if ($total > getopt('warnslow'))
		{
			wrn("slow query: [$_global_sql] took ", sprintf("%.3f seconds", $total));
		}
	}

	if (opt('stats'))
	{
		$sql_time += time() - $sec;
		$sql_count++;
	}

	return $sth->{mysql_insertid};
}

sub set_slv_config
{
	$config = shift;
}

# Get time bounds of the last test guaranteed to have all probe results.
sub get_interval_bounds
{
	my $delay = shift;
	my $clock = shift;

	$clock = time() unless ($clock);

	my $from = truncate_from($clock, $delay);
	my $till = $from + $delay - 1;

	return ($from, $till, $till - RESULT_TIMESTAMP_SHIFT);
}

# Get time bounds of the rolling week, shift back to guarantee all probe results.
sub get_rollweek_bounds
{
	my $from = shift;	# beginning of rolling week (till current time if not specified)

	my $rollweek_seconds = __get_macro('{$RSM.ROLLWEEK.SECONDS}');

	my $till;

	if ($from)
	{
		$from = truncate_from($from);
		$till = $from + $rollweek_seconds;
	}
	else
	{
		# select till current time
		$till = time() - ROLLWEEK_SHIFT_BACK;

		$till = truncate_from($till);
		$from = $till - $rollweek_seconds;
	}

	$till--;

	return ($from, $till, $till - RESULT_TIMESTAMP_SHIFT);
}

# todo phase 1: old name of this function was 'get_curmon_bounds'
# Get bounds for monthly downtime calculation. $till is the last second of the last elapsed minute.
# $from is the first second of the month (of the previous one if time() is within the fisrt minute of the month).
sub get_downtime_bounds
{
	require DateTime;

	my $till = truncate_from(time()) - 1;

	my $dt = DateTime->from_epoch('epoch' => $till);
	$dt->truncate('to' => 'month');
	my $from = $dt->epoch;

	return ($from, $till, $till - RESULT_TIMESTAMP_SHIFT);
}

# maximum timestamp for calculation of service availability
sub max_avail_time
{
	my $now = shift;

	# truncate to the end of previous minute
	return $now - ($now % 60) - 1 - AVAIL_SHIFT_BACK;
}

# todo phase 1: taken from RSMSLV.pm of phase 2
sub __print_probe_times
{
	my $probe_times_ref = shift;

	if (scalar(keys(%{$probe_times_ref})) == 0)
	{
		info("no probes were online at given period");
		return;
	}

	info("probe online times:");

	foreach my $probe (keys(%{$probe_times_ref}))
	{
		info("  $probe");

		my $idx = 0;
		my $count = scalar(@{$probe_times_ref->{$probe}});

		while ($idx < $count)
		{
			my $from = $probe_times_ref->{$probe}->[$idx++];
			my $till = $probe_times_ref->{$probe}->[$idx++];

			info("    ", selected_period($from, $till));
		}
	}
}

# Get online times of probe nodes.
#
# Returns hash of probe names as keys and array with online times as values:
#
# {
#   'probe' => [ from1, till1, from2, till2 ... ]
#   ...
# }
#
# NB! If a probe was down for the whole specified period it won't be in a hash.
sub get_probe_times($$$)
{
	my $from = shift;
	my $till = shift;
	my $probes_ref = shift; # { host => hostid, ... }

	my $result = {};

	return $result if (scalar(keys(%{$probes_ref})) == 0);

	my @probes = map {"'$_ - mon'"} (keys(%{$probes_ref}));

	my $items_ref = db_select(
		"select i.itemid,h.host".
		" from items i,hosts h".
		" where i.hostid=h.hostid".
			" and h.host in (".join(',', @probes).")".
			" and i.templateid is not null".
			" and i.status<>".ITEM_STATUS_DISABLED.
			" and i.key_='".PROBE_KEY_ONLINE."'");

	foreach my $item_ref (@{$items_ref})
	{
		my $itemid = $item_ref->[0];
		my $host = $item_ref->[1];

		my $probe = substr($host, 0, -length(' - mon'));	# get rid of " - mon"

		my $values_ref = db_select(
			"select clock,value".
			" from history_uint".
			" where itemid=$itemid".
				" and clock between $from and $till");

		next unless (scalar(@{$values_ref}));

		my ($values_hash_ref, $min_clock);
		foreach my $row_ref (sort {$a->[0] <=> $b->[0]} (@{$values_ref}))	# sort by clock
		{
			$values_hash_ref->{truncate_from($row_ref->[0])} = $row_ref->[1];
			$min_clock = $row_ref->[0] if (!defined($min_clock) || $row_ref->[0] < $min_clock);
		}

		my $step_clock = $min_clock;
		my $step = 60;	# seconds
		my $prev_value = DOWN;

		# check probe status every minute, if the value is missing consider the probe down
		while ($step_clock < $till)
		{
			my $value = (defined($values_hash_ref->{$step_clock}) ? $values_hash_ref->{$step_clock} : DOWN);

			if ($prev_value == DOWN && $value == UP)
			{
				push(@{$result->{$probe}}, $step_clock);
			}
			elsif ($prev_value == UP && $value == DOWN)
			{
				# went down, add last second of previous minute
				push(@{$result->{$probe}}, $step_clock - 1);
			}

			$step_clock += $step;
			$prev_value = $value;
		}

		# push "till" to @times if it contains odd number of elements
		if ($result->{$probe})
		{
			push(@{$result->{$probe}}, $till) if ($prev_value == UP);
		}
	}

	if (!defined($result))
	{
		dbg("Probes have no values yet.");
	}
	elsif (opt('dry-run'))
	{
		__print_probe_times($result);
	}

	return $result;
}

sub probe_offline_at
{
	my $probe_times_ref = shift;	# reference to a hash returned by get_probe_times()
	my $probe = shift;
	my $clock = shift;

	# if a probe was down for the whole period it won't be in a hash
	unless (exists($probe_times_ref->{$probe}))
	{
		return 1;	# offline
	}

	my $times_ref = $probe_times_ref->{$probe};

	my $clocks_count = scalar(@$times_ref);

	my $clock_index = 0;
	while ($clock_index < $clocks_count)
	{
		my $from = $times_ref->[$clock_index++];
		my $till = $times_ref->[$clock_index++];

		if ($from < $clock && $clock < $till)
		{
			return 0;	# online
		}
	}

	return 1;	# offline
}

# Translate probe names to hostids of appropriate tld hosts.
#
# E. g., we have hosts (host/hostid):
#   "Probe2"		1
#   "Probe12"		2
#   "org Probe2"	100
#   "org Probe12"	101
# calling
#   probes2tldhostids("org", ("Probe2", "Probe12"))
# will return
#  (100, 101)
sub probes2tldhostids
{
	my $tld = shift;
	my $probes_ref = shift;

	croak("Internal error: invalid argument to probes2tldhostids()") unless (ref($probes_ref) eq 'ARRAY');

	my $result = [];

	return $result if (scalar(@{$probes_ref}) == 0);

	my $hosts_str = '';
	foreach (@{$probes_ref})
	{
		$hosts_str .= ' or ' unless ($hosts_str eq '');
		$hosts_str .= "host='$tld $_'";
	}

	unless ($hosts_str eq "")
	{
		my $rows_ref = db_select("select hostid from hosts where $hosts_str");

		foreach my $row_ref (@$rows_ref)
		{
			push(@{$result}, $row_ref->[0]);
		}
	}

	return $result;
}

sub get_probe_online_key_itemid
{
	my $probe = shift;

	return get_itemid_by_host("$probe - mon", PROBE_KEY_ONLINE);
}

sub init_values
{
	$_sender_values->{'data'} = [];

	if (opt('dry-run'))
	{
		# data that helps format the output nicely
		$_sender_values->{'maxhost'} = 0;
		$_sender_values->{'maxkey'} = 0;
		$_sender_values->{'maxclock'} = 0;
		$_sender_values->{'maxvalue'} = 0;
	}
}

sub push_value
{
	my $hostname = shift;
	my $key = shift;
	my $clock = shift;
	my $value = shift;

	my $info = join('', @_);

	push(@{$_sender_values->{'data'}},
		{
			'tld' => $tld,
			'data' =>
			{
				'host' => $hostname,
				'key' => $key,
				'value' => "$value",
				'clock' => $clock
			},
			'info' => $info,
		});

	if (opt('dry-run'))
	{
		my $hostlen = length($hostname);
		my $keylen = length($key);
		my $clocklen = length($clock);
		my $valuelen = length($value);

		$_sender_values->{'maxhost'} = $hostlen if (!$_sender_values->{'maxhost'} || $hostlen > $_sender_values->{'maxhost'});
		$_sender_values->{'maxkey'} = $keylen if (!$_sender_values->{'maxkey'} || $keylen > $_sender_values->{'maxkey'});
		$_sender_values->{'maxclock'} = $clocklen if (!$_sender_values->{'maxclock'} || $clocklen > $_sender_values->{'maxclock'});
		$_sender_values->{'maxvalue'} = $valuelen if (!$_sender_values->{'maxvalue'} || $valuelen > $_sender_values->{'maxvalue'});
	}
}

#
# send previously collected values:
#
# [
#   {'host' => 'host1', 'key' => 'item1', 'value' => '5', 'clock' => 1391790685},
#   {'host' => 'host2', 'key' => 'item1', 'value' => '4', 'clock' => 1391790685},
#   ...
# ]
#
sub send_values
{
	if (opt('dry-run'))
	{
		my $mh = $_sender_values->{'maxhost'};
		my $mk = $_sender_values->{'maxkey'};
		my $mv = $_sender_values->{'maxvalue'};
		my $mc = $_sender_values->{'maxclock'};

		my $fmt = "%-${mh}s | %${mk}s | %-${mv}s | %-${mc}s | %s";

		# $tld is a global variable which is used in info()
		foreach my $h (@{$_sender_values->{'data'}})
		{
			my $msg = sprintf($fmt,
				$h->{'data'}->{'host'},
				$h->{'data'}->{'key'},
				$h->{'data'}->{'value'},
				ts_str($h->{'data'}->{'clock'}),
				$h->{'info'});

			info($msg);
		}

		return;
	}

	my $total_values = scalar(@{$_sender_values->{'data'}});

	if ($total_values == 0)
	{
		wrn(__script(), ": no data collected, nothing to send");
		return;
	}

	my $data = [];

	foreach my $sender_value (@{$_sender_values->{'data'}})
	{
		push(@{$data}, $sender_value->{'data'});
	}

	dbg("sending $total_values values");	# send everything in one batch since server should be local
	push_to_trapper($config->{'slv'}->{'zserver'}, $config->{'slv'}->{'zport'}, 10, 5, $data);

	# $tld is a global variable which is used in info()
	my $saved_tld = $tld;
	foreach my $h (@{$_sender_values->{'data'}})
	{
		$tld = $h->{'tld'};
		info(sprintf("%s:%s=%s | %s | %s",
				$h->{'data'}->{'host'},
				$h->{'data'}->{'key'},
				$h->{'data'}->{'value'},
				ts_str($h->{'data'}->{'clock'}),
				$h->{'info'}));
	}
	$tld = $saved_tld;
}

# Get name server details (name, IP) from item key.
#
# E. g.:
#
# rsm.dns.udp.rtt[{$RSM.TLD},i.ns.se.,194.146.106.22] -> "i.ns.se.,194.146.106.22"
# rsm.slv.dns.avail[i.ns.se.,194.146.106.22] -> "i.ns.se.,194.146.106.22"
sub get_nsip_from_key
{
	my $key = shift;

	my $offset = index($key, "[");

	return "" if ($offset == -1);

	if (substr($key, $offset + 1, 1) eq "{")
	{
		$offset = index($key, ",");

		return "" if ($offset == -1);
	}

	$offset++;

	my $endpos = index($key, "]");

	return "" if ($endpos == -1 || $endpos <= $offset);

	return substr($key, $offset, $endpos - $offset);
}

sub is_service_error
{
	my $error = shift;

	return SUCCESS if ($error <= MAX_SERVICE_ERROR);

	return E_FAIL;
}

sub process_slv_avail($$$$$$$$$$)
{
	my $tld = shift;
	my $cfg_key_in = shift;
	my $cfg_key_out = shift;
	my $from = shift;
	my $till = shift;
	my $value_ts = shift;
	my $cfg_minonline = shift;
	my $online_probe_names = shift;
	my $check_value_ref = shift;
	my $value_type = shift;

	croak("Internal error: invalid argument to process_slv_avail()") unless (ref($online_probe_names) eq 'ARRAY');

	my $online_probe_count = scalar(@{$online_probe_names});

	if ($online_probe_count < $cfg_minonline)
	{
		push_value($tld, $cfg_key_out, $value_ts, UP, "Up (not enough probes online, $online_probe_count while $cfg_minonline required)");
		add_alert(ts_str($value_ts) . "#system#zabbix#$cfg_key_out#PROBLEM#$tld (not enough probes online, $online_probe_count while $cfg_minonline required)") if (alerts_enabled() == SUCCESS);
		return;
	}

	my $hostids_ref = probes2tldhostids($tld, $online_probe_names);
	if (scalar(@$hostids_ref) == 0)
	{
		wrn("no probe hosts found");
		return;
	}

	my $complete_key = ("]" eq substr($cfg_key_in, -1)) ? 1 : 0;
	my $items_ref = get_items_by_hostids($hostids_ref, $cfg_key_in, $complete_key);
	if (scalar(keys(%{$items_ref})) == 0)
	{
		wrn("no items ($cfg_key_in) found");
		return;
	}

	my @itemids = keys(%{$items_ref});
	my $values_ref = __get_item_values(\@itemids, $from, $till, $value_type);
	my $probes_with_results = scalar(keys(%{$values_ref}));
	if ($probes_with_results < $cfg_minonline)
	{
		push_value($tld, $cfg_key_out, $value_ts, UP, "Up (not enough probes with results, $probes_with_results while $cfg_minonline required)");
		add_alert(ts_str($value_ts) . "#system#zabbix#$cfg_key_out#PROBLEM#$tld (not enough probes with results, $probes_with_results while $cfg_minonline required)") if (alerts_enabled() == SUCCESS);
		return;
	}

	my $probes_with_positive = 0;

	while (my ($itemid, $values) = each(%{$values_ref}))
	{
		my $result = $check_value_ref->($values);

		$probes_with_positive++ if (SUCCESS == $result);

		next unless (opt('debug'));

		dbg("i:$itemid (h:$items_ref->{$itemid}): ", (SUCCESS == $result ? "up" : "down"), " (values: ", join(', ', @{$values}), ")");
	}

	my $result = DOWN;
	my $perc = $probes_with_positive * 100 / $probes_with_results;
	$result = UP if ($perc > SLV_UNAVAILABILITY_LIMIT);

	push_value($tld, $cfg_key_out, $value_ts, $result,
			__avail_result_msg($result, $probes_with_positive, $probes_with_results, $perc));
}

# organize values from all hosts grouped by itemid and return itemid->values hash
#
# E. g.:
#
# '10010' => [1],
# '10011' => [2, 0]
# ...
sub __get_item_values($$$$)
{
	my $itemids = shift;
	my $from = shift;
	my $till = shift;
	my $value_type = shift;

	my $result = {};

	return $result if (0 == scalar(@{$itemids}));

	my $itemids_str = join(',', @{$itemids});

	my $rows_ref = db_select(
		"select itemid,value".
		" from " . __get_history_table_by_value_type($value_type).
		" where itemid in ($itemids_str)".
			" and clock between $from and $till".
		" order by clock");

	foreach my $row_ref (@$rows_ref)
	{
		my $itemid = $row_ref->[0];
		my $value = $row_ref->[1];

		$result->{$itemid} = [] unless (exists($result->{$itemid}));

		push(@{$result->{$itemid}}, $value);
	}

	return $result;
}

sub avail_value_exists
{
        my $clock = shift;
        my $itemid = shift;

        my $rows_ref = db_select("select 1 from history_uint where itemid=$itemid and clock=$clock");

        return SUCCESS if ($rows_ref->[0]->[0]);

        return E_FAIL;
}

sub rollweek_value_exists
{
        my $clock = shift;
        my $itemid = shift;

        my $rows_ref = db_select("select 1 from history where itemid=$itemid and clock=$clock");

        return SUCCESS if ($rows_ref->[0]->[0]);

        return E_FAIL;
}

sub __make_incident
{
	my %h;

	$h{'eventid'} = shift;
	$h{'false_positive'} = shift;
	$h{'start'} = shift;
	$h{'end'} = shift;

	return \%h;
}

sub sql_time_condition
{
	my $from = shift;
	my $till = shift;

	if (defined($from) and not defined($till))
	{
		return "clock>=$from";
	}

	if (not defined($from) and defined($till))
	{
		return "clock<=$till";
	}

	if (defined($from) and defined($till))
	{
		return "clock=$from" if ($from == $till);
		fail("invalid time conditions: from=$from till=$till") if ($from > $till);
		return "clock between $from and $till";
	}

	return "1=1";
}

# return incidents as an array reference (sorted by time):
#
# [
#     {
#         'eventid' => '5881',
#         'start' => '1418272230',
#         'end' => '1418273230',
#         'false_positive' => '0'
#     },
#     {
#         'eventid' => '6585',
#         'start' => '1418280000',
#         'false_positive' => '1'
#     }
# ]
#
# An incident is a period when the problem was active. This period is
# limited by 2 events, the PROBLEM event and the first OK event after
# that.
#
# Incidents are returned within time limits specified by $from and $till.
# If an incident is on-going at the $from time the event "start" time is
# used. In case event is on-going at time specified as $till it's "end"
# time is not defined.
sub get_incidents
{
	my $itemid = shift;
	my $delay = shift;
	my $from = shift;
	my $till = shift;

	my (@incidents, $rows_ref, $row_ref);

	$rows_ref = db_select(
		"select distinct t.triggerid".
		" from triggers t,functions f".
		" where t.triggerid=f.triggerid".
			" and f.itemid=$itemid".
			" and t.priority=".TRIGGER_SEVERITY_NOT_CLASSIFIED);

	my $rows = scalar(@$rows_ref);

	unless ($rows == 1)
	{
		wrn("configuration error: item $itemid must have one not classified trigger (found: $rows)");
		return \@incidents;
	}

	my $triggerid = $rows_ref->[0]->[0];

	my $last_trigger_value = TRIGGER_VALUE_FALSE;

	if (defined($from))
	{
		# first check for ongoing incident
		$rows_ref = db_select(
			"select max(clock)".
			" from events".
			" where object=".EVENT_OBJECT_TRIGGER.
				" and source=".EVENT_SOURCE_TRIGGERS.
				" and objectid=$triggerid".
				" and clock<$from");

		$row_ref = $rows_ref->[0];

		if (defined($row_ref) and defined($row_ref->[0]))
		{
			my $preincident_clock = $row_ref->[0];

			$rows_ref = db_select(
				"select eventid,clock,value,false_positive".
				" from events".
				" where object=".EVENT_OBJECT_TRIGGER.
					" and source=".EVENT_SOURCE_TRIGGERS.
					" and objectid=$triggerid".
					" and clock=$preincident_clock".
				" order by ns desc".
				" limit 1");

			$row_ref = $rows_ref->[0];

			my $eventid = $row_ref->[0];
			my $clock = $row_ref->[1];
			my $value = $row_ref->[2];
			my $false_positive = $row_ref->[3];

			if (opt('debug'))
			{
				my $type = ($value == TRIGGER_VALUE_FALSE ? 'closing' : 'opening');
				dbg("$type pre-event $eventid: clock:" . ts_str($clock) . " ($clock), false_positive:$false_positive");
			}

			# do not add 'value=TRIGGER_VALUE_TRUE' to SQL above just for corner case of 2 events at the same second
			if ($value == TRIGGER_VALUE_TRUE)
			{
				push(@incidents, __make_incident($eventid, $false_positive, cycle_start($clock, $delay)));

				$last_trigger_value = TRIGGER_VALUE_TRUE;
			}
		}
	}

	# now check for incidents within given period
	$rows_ref = db_select(
		"select eventid,clock,value,false_positive".
		" from events".
		" where object=".EVENT_OBJECT_TRIGGER.
			" and source=".EVENT_SOURCE_TRIGGERS.
			" and objectid=$triggerid".
			" and ".sql_time_condition($from, $till).
		" order by clock,ns");

	foreach my $row_ref (@$rows_ref)
	{
		my $eventid = $row_ref->[0];
		my $clock = $row_ref->[1];
		my $value = $row_ref->[2];
		my $false_positive = $row_ref->[3];

		# NB! Incident start/end times must not be truncated to first/last second
		# of a minute (do not use truncate_from and truncate_till) because they
		# can be used by a caller to identify an incident.

		if (opt('debug'))
		{
			my $type = ($value == TRIGGER_VALUE_FALSE ? 'closing' : 'opening');
			dbg("$type event $eventid: clock:" . ts_str($clock) . " ($clock), false_positive:$false_positive");
		}

		# ignore non-resolved false_positive incidents (corner case)
		if ($value == TRIGGER_VALUE_TRUE && $last_trigger_value == TRIGGER_VALUE_TRUE)
		{
			my $idx = scalar(@incidents) - 1;

			if ($incidents[$idx]->{'false_positive'} != 0)
			{
				# replace with current
				$incidents[$idx]->{'eventid'} = $eventid;
				$incidents[$idx]->{'false_positive'} = $false_positive;
				$incidents[$idx]->{'start'} = cycle_start($clock, $delay);
			}
		}

		next if ($value == $last_trigger_value);

		if ($value == TRIGGER_VALUE_FALSE)
		{
			# event that closes the incident
			my $idx = scalar(@incidents) - 1;

			$incidents[$idx]->{'end'} = cycle_end($clock, $delay);
		}
		else
		{
			# event that starts an incident
			push(@incidents, __make_incident($eventid, $false_positive, cycle_start($clock, $delay)));
		}

		$last_trigger_value = $value;
	}

	# DEBUG
	if (opt('debug'))
	{
		foreach (@incidents)
		{
			my $eventid = $_->{'eventid'};
			my $inc_from = $_->{'start'};
			my $inc_till = $_->{'end'};
			my $false_positive = $_->{'false_positive'};

			my $str = "$eventid";
			$str .= " (false positive)" if ($false_positive != 0);
			$str .= ": " . ts_str($inc_from) . " ($inc_from) -> ";
			$str .= $inc_till ? ts_str($inc_till) . " ($inc_till)" : "null";

			dbg($str);
		}
	}

	return \@incidents;
}

sub get_downtime
{
	my $itemid = shift;
	my $from = shift;
	my $till = shift;
	my $ignore_incidents = shift;	# if set check the whole period
	my $incidents_ref = shift;	# optional reference to array of incidents, ignored if $ignore_incidents is true
	my $delay = shift;		# only needed if incidents are not ignored and are not supplied by caller

	my $incidents;
	if ($ignore_incidents)
	{
		push(@$incidents, __make_incident(0, 0, $from, $till));
	}
	elsif ($incidents_ref)
	{
		$incidents = $incidents_ref;
	}
	else
	{
		$incidents = get_incidents($itemid, $delay, $from, $till);
	}

	my $count = 0;
	my $downtime = 0;

	my $sec;
	if (opt('stats'))
	{
		$sec = time();
	}

	my $fetches = 0;

	foreach (@$incidents)
	{
		my $false_positive = $_->{'false_positive'};
		my $period_from = $_->{'start'};
		my $period_till = $_->{'end'};

		if (($period_from < $from) && defined($period_till) && ($period_till < $from))
		{
			fail("internal error: incident outside time bounds, check function get_incidents()");
		}

		$period_from = $from if ($period_from < $from);
		$period_till = $till unless (defined($period_till)); # last incident may be ongoing

		next if ($false_positive != 0);

		my $rows_ref = db_select(
			"select value,clock".
			" from history_uint".
			" where itemid=$itemid".
				" and clock between $period_from and $period_till".
			" order by clock");

		my $prevvalue = UP;
		my $prevclock = 0;

		foreach my $row_ref (@$rows_ref)
		{
			$fetches++;

			my $value = $row_ref->[0];
			my $clock = $row_ref->[1];

			# In case of multiple values per second treat them as one. Up value prioritized.
			if ($prevclock == $clock)
			{
				# more than one value per second
				$prevvalue = UP if ($prevvalue == DOWN and $value == UP);
				next;
			}

			# todo phase 1: do not ignore the first downtime minute
			if ($value == DOWN && $prevclock == 0)
			{
				# first run
				$downtime += 60;
			}
			elsif ($prevvalue == DOWN)
			{
				$downtime += $clock - $prevclock;
			}

			$prevvalue = $value;
			$prevclock = $clock;
		}

		# leftover of downtime
		$downtime += $period_till - $prevclock if ($prevvalue == DOWN);
	}

	$downtime = int($downtime / 60);	# minutes;

	if (opt('stats'))
	{
		my $sec_cur = time() - $sec;
		$sql_time += $sec_cur;

		info(sprintf("down:%dm time:%.3fs fetches:%d", $downtime, $sec_cur, $fetches));
	}

	return $downtime;
}

sub get_downtime_prepare
{
	my $query =
		"select value,clock".
		" from history_uint".
		" where itemid=?".
			" and clock between ? and ?".
		" order by clock";

	my $sec;
	if (opt('stats'))
	{
		$sec = time();
	}

	my $sth = $dbh->prepare($query)
		or fail("cannot prepare [$query]: ", $dbh->errstr);

	if (opt('stats'))
	{
		$sql_time += time() - $sec;
	}

	dbg("[$query]");

	return $sth;
}

sub get_downtime_execute
{
	my $sth = shift;
	my $itemid = shift;
	my $from = shift;
	my $till = shift;
	my $ignore_incidents = shift;	# if set check the whole period
	my $delay = shift;		# only needed if incidents are not ignored

	my $incidents;
	if ($ignore_incidents)
	{
		my %h;

		$h{'start'} = $from;
		$h{'end'} = $till;
		$h{'false_positive'} = 0;

		push(@$incidents, \%h);
	}
	else
	{
		$incidents = get_incidents($itemid, $delay, $from, $till);
	}

	my $count = 0;
	my $downtime = 0;

	my $sec;
	if (opt('stats'))
	{
		$sec = time();
	}

	my $fetches = 0;

	foreach (@$incidents)
	{
		my $false_positive = $_->{'false_positive'};
		my $period_from = $_->{'start'};
		my $period_till = $_->{'end'};

		fail("internal error: incident outside time bounds, check function get_incidents()") if (($period_from < $from) and defined($period_till) and ($period_till < $from));

		$period_from = $from if ($period_from < $from);
		$period_till = $till unless (defined($period_till)); # last incident may be ongoing

		next if ($false_positive != 0);

		$sth->bind_param(1, $itemid);
		$sth->bind_param(2, $period_from);
		$sth->bind_param(3, $period_till);

		$sth->execute()
			or fail("cannot execute query: ", $sth->errstr);

		my ($value, $clock);
		$sth->bind_columns(\$value, \$clock);

		my $prevvalue = UP;
		my $prevclock = 0;

		while ($sth->fetch)
		{
			$fetches++;

			# In case of multiple values per second treat them as one. Up value prioritized.
			if ($prevclock == $clock)
			{
				# more than one value per second
				$prevvalue = UP if ($prevvalue == DOWN and $value == UP);
				next;
			}

			$downtime += $clock - $prevclock if ($prevvalue == DOWN);

			$prevvalue = $value;
			$prevclock = $clock;
		}

		# leftover of downtime
		$downtime += $period_till - $prevclock if ($prevvalue == DOWN);

		$sth->finish();
		$sql_count++;
	}

	$downtime = int($downtime / 60);	# minutes;

	if (opt('stats'))
	{
		my $sec_cur = time() - $sec;
		$sql_time += $sec_cur;

		info(sprintf("down:%dm time:%.3fs fetches:%d", $downtime, $sec_cur, $fetches));
	}

	return $downtime;
}

sub __avail_result_msg($$$$)
{
	my $test_result = shift;
	my $success_values = shift;
	my $total_results = shift;
	my $perc = shift;

	my $result_str = ($test_result == UP ? "Up" : "Down");

	return sprintf("$result_str (%d/%d positive, %.3f%%)", $success_values, $total_results, $perc);
}

sub __get_history_table_by_value_type
{
	my $value_type = shift;

	return "history_uint" if (!defined($value_type) || $value_type == ITEM_VALUE_TYPE_UINT64);	# default
	return "history" if ($value_type == ITEM_VALUE_TYPE_FLOAT);
	return "history_str" if ($value_type == ITEM_VALUE_TYPE_STR);

	fail("THIS_SHOULD_NEVER_HAPPEN");
}

#
# returns:
# SUCCESS - last clock and value found
# E_FAIL  - nothing found
# todo phase 1: rename to get_last_value
sub get_current_value
{
	my $itemid = shift;
	my $value_type = shift;
	my $value_ref = shift;
	my $clock_ref = shift;

	fail("THIS_SHOULD_NEVER_HAPPEN") unless ($clock_ref || $value_ref);

	my $t = __get_history_table_by_value_type($value_type);

	my @intervals = ("1 hour", "1 day", "1 month", "3 month");

	foreach my $interval (@intervals)
	{
		my $rows_ref = db_select("select clock,value from $t where itemid=$itemid and clock > unix_timestamp(current_timestamp() - interval $interval) order by clock desc limit 1");

		if (@{$rows_ref})
		{
			$$clock_ref = $rows_ref->[0]->[0] if ($clock_ref);
			$$value_ref = $rows_ref->[0]->[1] if ($value_ref);

			return SUCCESS;
		}
	}

	return E_FAIL;
}

#
# returns array of itemids: [itemid1, itemid2 ...]
#
sub get_itemids_by_hostids
{
	my $hostids_ref = shift;
	my $all_items = shift;

	my $result = [];

	foreach my $hostid (@$hostids_ref)
	{
		unless ($all_items->{$hostid})
		{
			dbg("\nhostid $hostid from:\n", Dumper($hostids_ref), "was not found in:\n", Dumper($all_items)) if (opt('debug'));
			fail("internal error: no hostid $hostid in input items");
		}

		foreach my $itemid (keys(%{$all_items->{$hostid}}))
		{
			push(@{$result}, $itemid);
		}
	}

	return $result;
}

# organize values from all probes grouped by nsip and return "nsip"->values hash
#
# {
#     'ns1,192.0.34.201' => {
#                   'itemid' => 23764,
#                   'values' => [
#                                 '-204.0000',
#                                 '-204.0000',
#                                 '-204.0000',
#                                 '-204.0000',
#                                 '-204.0000',
# ...
sub get_nsip_values
{
	my $itemids_ref = shift;
	my $times_ref = shift; # from, till, ...
	my $items_ref = shift;

	my $result = {};

	if (scalar(@$itemids_ref) != 0)
	{
		my $itemids_str = join(',', @{$itemids_ref});

		my $idx = 0;
		my $times_count = scalar(@$times_ref);
		while ($idx < $times_count)
		{
			my $from = $times_ref->[$idx++];
			my $till = $times_ref->[$idx++];

			my $rows_ref = db_select("select itemid,value from history where itemid in ($itemids_str) and " . sql_time_condition($from, $till). " order by clock");

			foreach my $row_ref (@$rows_ref)
			{
				my $itemid = $row_ref->[0];
				my $value = $row_ref->[1];

				my $nsip;
				my $last = 0;
				foreach my $hostid (keys(%$items_ref))
				{
					foreach my $i (keys(%{$items_ref->{$hostid}}))
					{
						if ($i == $itemid)
						{
							$nsip = $items_ref->{$hostid}{$i};
							$last = 1;
							last;
						}
					}
					last if ($last == 1);
				}

				fail("internal error: name server of item $itemid not found") unless (defined($nsip));

				unless (exists($result->{$nsip}))
				{
					$result->{$nsip} = {
						'itemid'	=> $itemid,
						'values'	=> []
					};
				}

				push(@{$result->{$nsip}->{'values'}}, $value);
			}
		}
	}

	return $result;
}

sub __get_valuemappings
{
	my $vmname = shift;

	my $rows_ref = db_select("select m.value,m.newvalue from valuemaps v,mappings m where v.valuemapid=m.valuemapid and v.name='$vmname'");

	my $result = {};

	foreach my $row_ref (@$rows_ref)
	{
		$result->{$row_ref->[0]} = $row_ref->[1];
	}

	return $result;
}

# todo phase 1: the $vmname's must be fixed accordingly in phase 2
# todo phase 1: also, consider renaming to something like get_rtt_valuemaps()
sub get_valuemaps
{
	my $service = shift;

	my $vmname;
	if ($service eq 'dns' or $service eq 'dnssec')
	{
		$vmname = 'RSM DNS rtt';
	}
	elsif ($service eq 'rdds')
	{
		$vmname = 'RSM RDDS rtt';
	}
	elsif ($service eq 'epp')
	{
		$vmname = 'RSM EPP rtt';
	}
	else
	{
		fail("service '$service' is unknown");
	}

	return __get_valuemappings($vmname);
}

# todo phase 1: the $vmname's must be fixed accordingly in phase 2
# todo phase 1: also, consider renaming to something like get_result_valuemaps()
sub get_statusmaps
{
	my $service = shift;

	my $vmname;
	if ($service eq 'dns' or $service eq 'dnssec')
	{
		# todo phase 1: this will be used in phase 2 (many statuses)
		#$vmname = 'RSM DNS result';
		return undef;
	}
	elsif ($service eq 'rdds')
	{
		$vmname = 'RSM RDDS result';
	}
	elsif ($service eq 'epp')
	{
		$vmname = 'RSM EPP result';
	}
	else
	{
		fail("service '$service' is unknown");
	}

	return __get_valuemappings($vmname);
}

# todo phase 1: add to phase 2
sub get_avail_valuemaps
{
	return __get_valuemappings('RSM Service Availability');
}

sub get_detailed_result
{
	my $maps = shift;
	my $value = shift;

	return undef unless (defined($value));

	my $value_int = int($value);

	return $value_int unless (exists($maps->{$value_int}));

	return "$value_int, " . $maps->{$value_int};
}

sub get_result_string
{
	my $maps = shift;
	my $value = shift;

	my $value_int = int($value);

	return $value_int unless ($maps);
	return $value_int unless (exists($maps->{$value_int}));

	return $maps->{$value_int};
}

# returns (tld, service)
sub get_tld_by_trigger
{
	my $triggerid = shift;

	my $rows_ref = db_select("select distinct itemid from functions where triggerid=$triggerid");

	my $itemid = $rows_ref->[0]->[0];

	return (undef, undef) unless ($itemid);

	dbg("itemid:$itemid");

	$rows_ref = db_select("select hostid,substring(key_,9,locate('.avail',key_)-9) as service from items where itemid=$itemid");

	my $hostid = $rows_ref->[0]->[0];
	my $service = $rows_ref->[0]->[1];

	fail("cannot get TLD by itemid $itemid") unless ($hostid);

	dbg("hostid:$hostid");

	$rows_ref = db_select("select host from hosts where hostid=$hostid");

	return ($rows_ref->[0]->[0], $service);
}

# truncate to the beginning of specified period
sub truncate_from
{
	my $ts = shift;
	my $delay = shift;	# by default 1 minute

	$delay = 60 unless ($delay);

	return $ts - ($ts % $delay);
}

# truncate to the end of specified period
sub truncate_till
{
	my $ts = shift;
	my $delay = shift;	# by default 1 minute

	$delay = 60 unless ($delay);

	return truncate_from($ts, $delay) + $delay - 1;
}

# whether additional alerts through Redis are enabled, disable in config passed with set_slv_config()
sub alerts_enabled
{
	return SUCCESS if ($config && $config->{'redis'} && $config->{'redis'}->{'enabled'} && ($config->{'redis'}->{'enabled'} ne "0"));

	return E_FAIL;
}

# returns beginning of the test period if specified upper bound is within it,
# 0 otherwise
sub get_test_start_time
{
	my $till = shift;	# must be :59 seconds
	my $delay = shift;	# service delay in seconds (e. g. DNS: 60)

	my $remainder = $till % 60;

	fail("internal error: first argument to get_test_start_time() must be :59 seconds") unless ($remainder == 59);

	$till++;

	$remainder = $till % $delay;

	return 0 if ($remainder != 0);

	return $till - $delay;
}

# todo phase 1: taken from RSMSLV1.pm
sub uint_value_exists
{
	my $clock = shift;
	my $itemid = shift;

	my $rows_ref = db_select("select 1 from history_uint where itemid=$itemid and clock=$clock");

	return SUCCESS if ($rows_ref->[0]->[0]);

	return E_FAIL;
}

# $services is a hash reference of services that need to be checked.
# For each service the delay must be provided. "from" and "till" values
# will be set for services whose tests fall under given time between
# $check_from and $check_till.
#
# Input:
#
# [
#   {'dns' => 60},
#   {'rdds' => 300}
# ]
#
# Output:
#
# [
#   {'dns' => 60, 'from' => 1234234200, 'till' => 1234234259},	# <- test period found
#   {'rdds' => 300}						# <- test period not found
# ]
#
# The return value is min($from), max($till) from all found periods
#
sub get_real_services_period
{
	my $services = shift;
	my $check_from = shift;
	my $check_till = shift;
	my $consider_last = shift;	# consider last cycle if there is none within given period

	my ($from, $till);

	# adjust test and probe periods we need to calculate for
	foreach my $service (values(%{$services}))
	{
		my $delay = $service->{'delay'};

		my ($loop_from, $loop_till);

		# go through the check period minute by minute selecting test cycles
		for ($loop_from = $check_from, $loop_till = $loop_from + 59;
				(!$service->{'from'} || $service->{'till'}) && $loop_from < $check_till;
				$loop_from += 60, $loop_till += 60)
		{
			my $test_from = get_test_start_time($loop_till, $delay);

			next if ($test_from == 0);

			if (!$from || $from > $test_from)
			{
				$from = $test_from;
			}

			if (!$till || $till < $loop_till)
			{
				$till = $loop_till;
			}

			if (!$service->{'from'})
			{
				$service->{'from'} = $test_from;
			}

			if (!$service->{'till'} || $service->{'till'} < $loop_till)
			{
				$service->{'till'} = $loop_till;
			}
		}

		if ($consider_last && !$service->{'from'})
		{
			my $last_cycle = $check_till - $delay + 1;

			$service->{'till'} = truncate_till($last_cycle, $delay);
			$service->{'from'} = truncate_from($last_cycle, $delay);
		}
	}

	return ($from, $till);
}

sub format_stats_time
{
	my $time = shift;

	my $m = int($time / 60);
	my $s = $time - $m * 60;

	return sprintf("%dm %ds", $m, $s) if ($m != 0);

	return sprintf("%.3lfs", $s);
}

sub slv_finalize
{
	my $rv = shift;

	db_disconnect();

	if (SUCCESS == $rv && opt('stats'))
	{
		my $prefix = $tld ? "$tld " : '';

		my $sql_str = format_stats_time($sql_time);

		$sql_str .= " ($sql_count queries)";

		my $total_str = format_stats_time(time() - $start_time);

		print($prefix, "total     : $total_str\n");
		print($prefix, "sql       : $sql_str\n");
	}

	closelog();
}

sub slv_exit
{
	my $rv = shift;

	slv_finalize($rv);

	exit($rv);
}

sub slv_stats_reset
{
	$start_time = time();
	$sql_time = 0.0;
	$sql_count = 0;
}

sub exit_if_running
{
	return if (opt('dry-run'));

	my $filename = __get_pidfile();

	$pidfile = File::Pid->new({ file => $filename });
	fail("cannot lock script") unless (defined($pidfile));

	$pidfile->write() or fail("cannot write to a pid file ", $pidfile->file);

	return if ($pidfile->pid == $$);

	# pid file exists and has valid pid
	my $pid = $pidfile->running();
	fail(__script() . " is already running (pid:$pid)") if ($pid);

	$pidfile->pid($$);
	$pidfile->write() or fail("cannot write to a pid file ", $pidfile->file);
}

sub dbg
{
	return unless (opt('debug'));

	__log('debug', join('', @_));
}

sub info
{
	__log('info', join('', @_));
}

sub wrn
{
	__log('warning', join('', @_));
}

sub fail
{
	__log('err', join('', @_));

	slv_exit(E_FAIL);
}

sub trim
{
	my $out = shift;

	$out =~ s/^\s+//;
	$out =~ s/\s+$//;

	return $out;
}

sub parse_opts
{
	if (!GetOptions(\%OPTS, 'help!', 'dry-run!', 'warnslow=f', 'nolog!', 'debug!', 'stats!', @_))
	{
		pod2usage(-verbose => 0, -input => $POD2USAGE_FILE);
	}

	if (opt('help'))
	{
		pod2usage(-verbose => 1, -input => $POD2USAGE_FILE);
	}

	setopt('nolog') if (opt('dry-run') || opt('debug'));

	$start_time = time() if (opt('stats'));
}

sub parse_avail_opts
{
	$POD2USAGE_FILE = '/opt/zabbix/scripts/slv/rsm.slv.avail.usage';

	parse_opts('tld=s', 'from=n', 'period=n');
}

sub parse_rollweek_opts
{
	$POD2USAGE_FILE = '/opt/zabbix/scripts/slv/rsm.slv.rollweek.usage';

	parse_opts('tld=s', 'from=n');
}

sub opt
{
	return defined($OPTS{shift()});
}

sub getopt
{
	return $OPTS{shift()};
}

sub setopt
{
	my $key = shift;
	my $value = shift;

	$value = 1 unless (defined($value));

	$OPTS{$key} = $value;
}

sub unsetopt
{
	$OPTS{shift()} = undef;
}

sub optkeys
{
	return keys(%OPTS);
}

sub ts_str
{
	my $ts = shift;

	$ts = time() unless ($ts);

	# sec, min, hour, mday, mon, year, wday, yday, isdst
	my ($sec, $min, $hour, $mday, $mon, $year) = localtime($ts);

	return sprintf("%.4d%.2d%.2d:%.2d%.2d%.2d", $year + 1900, $mon + 1, $mday, $hour, $min, $sec);
}

sub ts_full
{
	my $ts = shift;

	$ts = time() unless ($ts);

	my $str = ts_str($ts);

	return "$str ($ts)";
}

sub selected_period
{
	my $from = shift;
	my $till = shift;

	return "till " . ts_str($till) if (!$from and $till);
	return "from " . ts_str($from) if ($from and !$till);
	return "from " . ts_str($from) . " till " . ts_str($till) if ($from and $till);

	return "any time";
}

sub write_file
{
	my $full_path = shift;
	my $text = shift;

	my $OUTFILE;

	return E_FAIL unless (open($OUTFILE, '>', $full_path));

	my $rv = print { $OUTFILE } $text;

	close($OUTFILE);

	return E_FAIL unless ($rv);

	return SUCCESS;
}

sub cycle_start
{
	my $now = shift;
	my $delay = shift;

	return $now - ($now % $delay);
}

sub cycle_end
{
	my $now = shift;
	my $delay = shift;

	return cycle_start($now, $delay) + $delay - 1;
}

sub usage
{
	pod2usage(shift);
}

#################
# Internal subs #
#################

my $program = $0; $program =~ s,.*/,,g;
my $logopt = 'pid';
my $facility = 'user';
my $prev_tld = "";
my $log_open = 0;

sub __func
{
	my $depth = 3;

	my $func = (caller($depth))[3];

	$func =~ s/^[^:]*::(.*)$/$1/ if (defined($func));

	return "$func() " if (defined($func));

	return "";
}

sub __log
{
	my $syslog_priority = shift;
	my $msg = shift;

	my $priority;
	my $stdout = 1;

	if ($syslog_priority eq 'info')
	{
		$priority = 'INF';
	}
	elsif ($syslog_priority eq 'err')
	{
		$stdout = 0 unless (opt('debug') || opt('dry-run'));	# todo phase 1: add this line to RSMSLV.pm of phase 2!
		$priority = 'ERR';
	}
	elsif ($syslog_priority eq 'warning')
	{
		$stdout = 0 unless (opt('debug') || opt('dry-run'));	# todo phase 1: add this line to RSMSLV.pm of phase 2!
		$priority = 'WRN';
	}
	elsif ($syslog_priority eq 'debug')
	{
		$priority = 'DBG';
	}
	else
	{
		$priority = 'UND';
	}

	my $cur_tld = $tld // "";
	my $server_str = ($server_key ? "\@$server_key " : "");

	if (opt('dry-run') or opt('nolog'))
	{
		print {$stdout ? *STDOUT : *STDERR} (sprintf("%6d:", $$), ts_str(), " [$priority] ", $server_str, ($cur_tld eq "" ? "" : "$cur_tld: "), __func(), "$msg\n");
		return;
	}

	my $ident = ($cur_tld eq "" ? "" : "$cur_tld-") . $program;

	if ($log_open == 0)
	{
		openlog($ident, $logopt, $facility);
		$log_open = 1;
	}
	elsif ($cur_tld ne $prev_tld)
	{
		closelog();
		openlog($ident, $logopt, $facility);
	}

	syslog($syslog_priority, sprintf("%6d:", $$) . ts_str() . " [$priority] " . $server_str . $msg);	# second parameter is the log message

	$prev_tld = $cur_tld;
}

sub __get_macro
{
	my $m = shift;

	my $rows_ref = db_select("select value from globalmacro where macro='$m'");

	fail("cannot find macro '$m'") unless (1 == scalar(@$rows_ref));

	return $rows_ref->[0]->[0];
}

# return an array reference of values of items for the particular period
sub __get_dbl_values
{
	my $itemids_ref = shift;
	my $from = shift;
	my $till = shift;

	my $result = [];

	return $result if (scalar(@{$itemids_ref}) == 0);

	my $itemids_str = join(',', @{$itemids_ref});

	my $rows_ref = db_select("select value from history where itemid in ($itemids_str) and clock between $from and $till order by clock");

	foreach my $row_ref (@$rows_ref)
	{
		push(@{$result}, $row_ref->[0]);
	}

	return $result;
}

sub __script
{
	my $script = $0;

	$script =~ s,.*/([^/]*)$,$1,;

	return $script;
}

sub __get_pidfile
{
	return PID_DIR . '/' . __script() . '.pid';
}

# Times when probe "lastaccess" within $probe_avail_limit.
sub __get_reachable_times
{
	my $probe = shift;
	my $probe_avail_limit = shift;
	my $from = shift;
	my $till = shift;

	my $host = "$probe - mon";
	my $itemid = get_itemid_by_host($host, PROBE_LASTACCESS_ITEM);

	my ($rows_ref, @times, $last_status);

	# get the previous status
	$rows_ref = db_select(
		"select clock,value".
		" from history_uint".
		" where itemid=$itemid".
			" and clock between ".($from-3600)." and ".($from-1).
		" order by itemid desc,clock desc".
		" limit 1");

	$last_status = UP;
	if (scalar(@$rows_ref) != 0)
	{
		my $clock = $rows_ref->[0]->[0];
		my $value = $rows_ref->[0]->[1];

		dbg("clock:$clock value:$value");

		$last_status = DOWN if ($clock - $value > $probe_avail_limit);
	}

	push(@times, $from) if ($last_status == UP);

	$rows_ref = db_select(
		"select clock,value".
		" from history_uint".
		" where itemid=$itemid".
	    		" and clock between $from and $till".
	    		" and value!=0".
		" order by itemid,clock");

	foreach my $row_ref (@$rows_ref)
	{
		my $clock = $row_ref->[0];
		my $value = $row_ref->[1];

		my $status = ($clock - $value > $probe_avail_limit) ? DOWN : UP;

		if ($last_status != $status)
		{
			push(@times, $clock);

			dbg("clock:$clock diff:", ($clock - $value));

			$last_status = $status;
		}
	}

	# push "till" to @times if it contains odd number of elements
	if (scalar(@times) != 0)
	{
		push(@times, $till) if ($last_status == UP);
	}

	return \@times;
}

sub __get_probestatus_times
{
	my $probe = shift;
	my $hostid = shift;
	my $times_ref = shift; # input
	my $key = shift;

	my ($rows_ref, @times, $last_status);

	my $key_match = "i.key_";
	$key_match .= ($key =~ m/%/) ? " like '$key'" : "='$key'";

	my $itemid;
	if ($key =~ m/%/)
	{
		$itemid = get_itemid_like_by_hostid($hostid, $key);
	}
	else
	{
		$itemid = get_itemid_by_hostid($hostid, $key);
	}

	if ($itemid < 0)
	{
		fail("misconfiguration: no item \"$key\" at probe host \"$probe\"") if ($itemid == E_ID_NONEXIST);
		fail("misconfiguration: multiple items \"$key\" at probe host \"$probe\"") if ($itemid == E_ID_MULTIPLE);

		fail("cannot get ID of item \"$key\" at probe host \"$probe\": unknown error");
	}

	$rows_ref = db_select("select value from history_uint where itemid=$itemid and clock<" . $times_ref->[0] . " order by clock desc limit 1");

	$last_status = UP;
	if (scalar(@$rows_ref) != 0)
	{
		my $value = $rows_ref->[0]->[0];

		$last_status = DOWN if ($value == OFFLINE);
	}

	my $idx = 0;
	my $times_count = scalar(@$times_ref);
	while ($idx < $times_count)
	{
		my $from = $times_ref->[$idx++];
		my $till = $times_ref->[$idx++];

		$rows_ref = db_select("select clock,value from history_uint where itemid=$itemid and clock between $from and $till order by itemid,clock");

		push(@times, $from) if ($last_status == UP);

		foreach my $row_ref (@$rows_ref)
		{
			my $clock = $row_ref->[0];
			my $value = $row_ref->[1];

			my $status = ($value == OFFLINE) ? DOWN : UP;

			if ($last_status != $status)
			{
				push(@times, $clock);

				dbg("clock:$clock value:$value");

				$last_status = $status;
			}
		}

		# push "till" to @times if it contains odd number of elements
		if (scalar(@times) != 0)
		{
			push(@times, $till) if ($last_status == UP);
		}
	}

	return \@times;
}

sub __get_configvalue
{
	my $itemid = shift;
	my $value_time = shift;

	my $hour = 3600;
	my $day = $hour * 24;
	my $month = $day * 30;

	my $diff = $hour;
	my $value;

	while (!defined($value) && $diff < $month)
	{
		my $rows_ref = db_select("select value from history_uint where itemid=$itemid and clock between " . ($value_time - $diff) . " and $value_time order by clock desc limit 1");

		foreach my $row_ref (@$rows_ref)
		{
			$value = $row_ref->[0];
			last;
		}

		$diff = $day if ($diff == $hour);
		$diff = $month if ($diff == $day);
	}

	return $value;
}

1;
