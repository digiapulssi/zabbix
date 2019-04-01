package RSMSLV;

use strict;
use warnings;

use DBI;
use DBI qw(:sql_types);
use Getopt::Long;
use Pod::Usage;
use Exporter qw(import);
use Zabbix;
use Alerts;
use TLD_constants qw(:api :items :ec :groups);
use File::Pid;
use POSIX qw(floor);
use Sys::Syslog;
use Data::Dumper;
use Time::HiRes;
use RSM;
use Pusher qw(push_to_trapper);

use constant SUCCESS => 0;
use constant E_FAIL => -1;	# be careful when changing this, some functions depend on current value
use constant E_ID_NONEXIST => -2;
use constant E_ID_MULTIPLE => -3;

use constant PROTO_UDP	=> 0;
use constant PROTO_TCP	=> 1;

						# "RSM Service Availability" value mapping:
use constant DOWN			=> 0;	# Down
use constant UP				=> 1;	# Up
use constant UP_INCONCLUSIVE_NO_DATA	=> 2;	# Up-inconclusive-no-data
use constant UP_INCONCLUSIVE_NO_PROBES	=> 3;	# Up-inconclusive-no-probes

use constant ONLINE => 1;	# todo: check where these are used
use constant OFFLINE => 0;	# todo: check where these are used
use constant SLV_UNAVAILABILITY_LIMIT => 49; # NB! must be in sync with frontend

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
use constant ROLLWEEK_SHIFT_BACK	=> 180;	# seconds (must be divisible by 60) back when Service Availability is definitely calculated

use constant PROBE_ONLINE_STR => 'Online';

use constant DETAILED_RESULT_DELIM => ', ';

use constant DEFAULT_SLV_MAX_CYCLES => 10;	# maximum cycles to process by SLV scripts in 1 run, may be overriden
						# by rsm.conf 'max_cycles_dns' and 'max_cycles_rdds'

use constant USE_CACHE_FALSE => 0;
use constant USE_CACHE_TRUE  => 1;

use constant AUDIT_RESOURCE_INCIDENT => 32;

our ($result, $dbh, $tld, $server_key);

our %OPTS; # specified command-line options

our @EXPORT = qw($result $dbh $tld $server_key
		SUCCESS E_FAIL E_ID_NONEXIST E_ID_MULTIPLE UP DOWN SLV_UNAVAILABILITY_LIMIT MIN_LOGIN_ERROR
		UP_INCONCLUSIVE_NO_PROBES
		UP_INCONCLUSIVE_NO_DATA PROTO_UDP PROTO_TCP
		MAX_LOGIN_ERROR MIN_INFO_ERROR MAX_INFO_ERROR PROBE_ONLINE_STR
		AVAIL_SHIFT_BACK ROLLWEEK_SHIFT_BACK PROBE_ONLINE_SHIFT
		PROBE_KEY_MANUAL
		ONLINE OFFLINE
		USE_CACHE_FALSE USE_CACHE_TRUE
		get_macro_minns get_macro_dns_probe_online get_macro_rdds_probe_online get_macro_dns_rollweek_sla
		get_macro_rdds_rollweek_sla get_macro_dns_udp_rtt_high get_macro_dns_udp_rtt_low
		get_macro_dns_tcp_rtt_low get_macro_rdds_rtt_low get_dns_udp_delay get_dns_tcp_delay
		get_rdds_delay get_epp_delay get_macro_epp_probe_online get_macro_epp_rollweek_sla
		get_macro_dns_update_time get_macro_rdds_update_time get_tld_items get_hostid
		get_rtt_low
		get_macro_epp_rtt_low get_macro_probe_avail_limit
		get_macro_incident_dns_fail get_macro_incident_rdds_fail
		get_itemid_by_key get_itemid_by_host
		get_itemid_by_hostid get_itemid_like_by_hostid get_itemids_by_host_and_keypart get_lastclock get_tlds
		get_oldest_clock
		get_probes get_nsips get_nsip_items tld_exists tld_service_enabled db_connect db_disconnect
		validate_tld validate_service
		get_templated_nsips db_exec tld_interface_enabled
		tld_interface_enabled_create_cache tld_interface_enabled_delete_cache
		db_select db_select_col db_select_row db_select_value db_select_binds db_explain
		set_slv_config get_cycle_bounds get_rollweek_bounds get_downtime_bounds
		get_probe_times probe_offline_at probes2tldhostids
		slv_max_cycles
		get_probe_online_key_itemid
		init_values push_value send_values get_nsip_from_key is_service_error get_templated_items_like
		is_service_error_desc
		is_internal_error
		is_internal_error_desc
		collect_slv_cycles
		process_slv_avail_cycles
		process_slv_avail
		process_slv_rollweek_cycles
		process_slv_downtime_cycles
		uint_value_exists
		float_value_exists
		sql_time_condition get_incidents get_downtime get_downtime_prepare get_downtime_execute
		history_table
		get_lastvalue get_itemids_by_hostids get_nsip_values
		get_valuemaps get_statusmaps get_detailed_result
		get_avail_valuemaps
		get_result_string get_tld_by_trigger truncate_from truncate_till alerts_enabled
		get_real_services_period dbg info wrn fail set_on_fail
		format_stats_time
		init_process finalize_process
		slv_exit
		fail_if_running
		exit_if_running
		trim
		parse_opts parse_slv_opts
		opt getopt setopt unsetopt optkeys ts_str ts_full selected_period
		write_file read_file
		cycle_start
		cycle_end
		update_slv_rtt_monthly_stats
		recalculate_downtime
		usage);

# configuration, set in set_slv_config()
my $config = undef;

# this will be used for making sure only one copy of script runs (see function __is_already_running())
my $pidfile;
use constant PID_DIR => '/tmp';

my $_sender_values;	# used to send values to Zabbix server

my $POD2USAGE_FILE;	# usage message file

my ($_global_sql, $_global_sql_bind_values, $_lock_fh);

my $get_stats = 0;
my $start_time;
my $sql_start;
my $sql_end;
my $sql_send;
my $sql_time = 0.0;
my $sql_count = 0;

my $log_open = 0;

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

sub get_dns_udp_delay
{
	my $value_time = (shift or time() - AVAIL_SHIFT_BACK);

	my $value = __get_configvalue(RSM_CONFIG_DNS_UDP_DELAY_ITEMID, $value_time);

	return $value if (defined($value));

	return __get_macro('{$RSM.DNS.UDP.DELAY}');
}

sub get_dns_tcp_delay
{
	my $value_time = (shift or time() - AVAIL_SHIFT_BACK);

	# todo: Export DNS-TCP tests
	# todo: if we really need DNS-TCP history the item must be added (to db schema and upgrade patch)
#	my $value = __get_configvalue(RSM_CONFIG_DNS_TCP_DELAY_ITEMID, $value_time);
#
#	return $value if (defined($value));

	return __get_macro('{$RSM.DNS.TCP.DELAY}');
}

sub get_rdds_delay
{
	my $value_time = (shift or time() - AVAIL_SHIFT_BACK);

	my $value = __get_configvalue(RSM_CONFIG_RDDS_DELAY_ITEMID, $value_time);

	return $value if (defined($value));

	return __get_macro('{$RSM.RDDS.DELAY}');
}

sub get_epp_delay
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

sub get_rtt_low
{
	my $service = shift;
	my $proto = shift;	# for DNS
	my $command = shift;	# for EPP: 'login', 'info' or 'update'

	if ($service eq 'dns' || $service eq 'dnssec')
	{
		fail("internal error: get_rtt_low() called for $service without specifying protocol")
			unless (defined($proto));

		if ($proto == PROTO_UDP)
		{
			return get_macro_dns_udp_rtt_low();	# can be per TLD
		}
		elsif ($proto == PROTO_TCP)
		{
			return get_macro_dns_tcp_rtt_low();	# can be per TLD
		}
		else
		{
			fail("dimir was wrong, besides protocols ", PROTO_UDP, " and ", PROTO_TCP,
				" there is also ", $proto);
		}
	}

	if ($service eq 'rdds')
	{
		return get_macro_rdds_rtt_low();
	}

	if ($service eq 'epp')
	{
		return get_macro_epp_rtt_low($command);	# can be per TLD
	}

	fail("dimir was wrong, thinking the only known services are \"dns\", \"dnssec\", \"rdds\" and \"epp\",",
		" there is also \"$service\"");
}

sub get_slv_rtt($;$)
{
	my $service = shift;
	my $proto = shift;	# for DNS

	if ($service eq 'dns' || $service eq 'dnssec')
	{
		fail("internal error: get_slv_rtt() called for $service without specifying protocol")
			unless (defined($proto));

		return __get_macro('{$RSM.SLV.DNS.UDP.RTT}') if ($proto == PROTO_UDP);
		return __get_macro('{$RSM.SLV.DNS.TCP.RTT}') if ($proto == PROTO_TCP);

		fail("Unhandled protocol \"$proto\"");
	}

	return __get_macro('{$RSM.SLV.RDDS.RTT}')   if ($service eq 'rdds');
	return __get_macro('{$RSM.SLV.RDDS43.RTT}') if ($service eq 'rdds43');
	return __get_macro('{$RSM.SLV.RDDS80.RTT}') if ($service eq 'rdds80');

	fail("Unhandled service \"$service\"");
}

sub get_macro_epp_rtt_low
{
	return __get_macro('{$RSM.EPP.'.uc(shift).'.RTT.LOW}');
}

sub get_macro_probe_avail_limit
{
	return __get_macro('{$RSM.PROBE.AVAIL.LIMIT}');
}

sub get_macro_incident_dns_fail()
{
	return __get_macro('{$RSM.INCIDENT.DNS.FAIL}');
}

sub get_macro_incident_rdds_fail()
{
	return __get_macro('{$RSM.INCIDENT.RDDS.FAIL}');
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

	my $itemid = __get_itemid_by_sql(
		"select i.itemid".
		" from items i,hosts h".
		" where i.hostid=h.hostid".
	    		" and h.host='$host'".
			" and i.key_='$key'"
	);

	fail("item \"$key\" does not exist") if ($itemid == E_ID_NONEXIST);
	fail("more than one item \"$key\" found") if ($itemid == E_ID_MULTIPLE);

	return $itemid;
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

# Return itemids of Name Server items in form:
# {
#     ns1.example.com,10.20.30.40 => 32512,
#     ns2.example.com,10.20.30.41 => 32513,
#     ....
# }
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
sub get_lastclock($$$)
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

	if (get_lastvalue($itemid, $value_type, undef, \$lastclock) != SUCCESS)
	{
		$lastclock = 0;
	}

	return $lastclock;
}

# returns:
# E_FAIL - if item was not found
# undef  - if history table is empty
# *      - lastclock
sub get_oldest_clock($$$)
{
	my $host = shift;
	my $key = shift;
	my $value_type = shift;

	my $rows_ref = db_select(
		"select i.itemid".
		" from items i,hosts h".
		" where i.hostid=h.hostid".
			" and i.status=0".
			" and h.host='$host'".
			" and i.key_='$key'"
	);

	return E_FAIL if (scalar(@$rows_ref) == 0);

	my $itemid = $rows_ref->[0]->[0];

	$rows_ref = db_select(
		"select min(clock)".
		" from " . history_table($value_type).
		" where itemid=$itemid"
	);

	return $rows_ref->[0]->[0];
}

# $tlds_cache{$server_key}{$service}{$till} = ["tld1", "tld2", ...];
my %tlds_cache = ();

sub get_tlds(;$$$)
{
	my $service = shift;	# optionally specify service which must be enabled
	my $till = shift;	# used only if $service is defined
	my $use_cache = shift // USE_CACHE_FALSE;

	if ($use_cache != USE_CACHE_FALSE && $use_cache != USE_CACHE_TRUE)
	{
		fail("Invalid value for \$use_cache argument - '$use_cache'");
	}

	if ($use_cache == USE_CACHE_TRUE && exists($tlds_cache{$server_key}{$service // ''}{$till // 0}))
	{
		return $tlds_cache{$server_key}{$service // ''}{$till // 0};
	}

	my $rows_ref = db_select(
		"select distinct h.host".
		" from hosts h,hosts_groups hg".
		" where h.hostid=hg.hostid".
			" and hg.groupid=".TLDS_GROUPID.
			" and h.status=0".
		" order by h.host");

	my @tlds;
	foreach my $row_ref (@$rows_ref)
	{
		my $tld = $row_ref->[0];

		if (defined($service))
		{
			next unless (tld_service_enabled($tld, $service, $till));
		}

		push(@tlds, $tld);
	}

	if ($use_cache == USE_CACHE_TRUE)
	{
		$tlds_cache{$server_key}{$service // ''}{$till // 0} = \@tlds;
	}

	return \@tlds;
}

# $probes_cache{$server_key}{$name}{$service} = {$host => $hostid, ...}
my %probes_cache = ();

# Returns a reference to hash of all probes (host => {'hostid' => hostid, 'status' => status}).
sub get_probes(;$$)
{
	my $service = shift; # "IP4", "IP6", "RDDS" or any other
	my $name = shift;

	$service = defined($service) ? uc($service) : "ALL";
	$name //= "";

	if ($service ne "IP4" && $service ne "IP6" && $service ne "RDDS")
	{
		$service = "ALL";
	}

	if (!exists($probes_cache{$server_key}{$name}))
	{
		$probes_cache{$server_key}{$name} = __get_probes($name);
	}

	return $probes_cache{$server_key}{$name}{$service};
}
sub __get_probes($)
{
	my $name = shift;

	my $name_condition = ($name ? "name='$name' and" : "");

	my $rows = db_select(
		"select hosts.hostid,hosts.host,hostmacro.macro,hostmacro.value,hosts.status" .
		" from hosts" .
			" left join hosts_groups on hosts_groups.hostid=hosts.hostid" .
			" left join hosts_templates as hosts_templates_1 on hosts_templates_1.hostid=hosts.hostid" .
			" left join hosts_templates as hosts_templates_2 on hosts_templates_2.hostid=hosts_templates_1.templateid" .
			" left join hostmacro on hostmacro.hostid=hosts_templates_2.templateid" .
		" where $name_condition" .
			" hosts_groups.groupid=" . PROBES_GROUPID . " and" .
			" hostmacro.macro in ('{\$RSM.IP4.ENABLED}','{\$RSM.IP6.ENABLED}','{\$RSM.RDDS.ENABLED}')");

	my %result = (
		'ALL'  => {},
		'IP4'  => {},
		'IP6'  => {},
		'RDDS' => {}
	);

	foreach my $row (@{$rows})
	{
		my ($hostid, $host, $macro, $value, $status) = @{$row};

		if (!exists($result{'ALL'}{$host}))
		{
			$result{'ALL'}{$host} = {'hostid' => $hostid, 'status' => $status};
		}

		if ($macro eq '{$RSM.IP4.ENABLED}')
		{
			$result{'IP4'}{$host} = {'hostid' => $hostid, 'status' => $status} if ($value);
		}
		elsif ($macro eq '{$RSM.IP6.ENABLED}')
		{
			$result{'IP6'}{$host} = {'hostid' => $hostid, 'status' => $status} if ($value);
		}
		elsif ($macro eq '{$RSM.RDDS.ENABLED}')
		{
			$result{'RDDS'}{$host} = {'hostid' => $hostid, 'status' => $status} if ($value);
		}
	}

	if (opt("debug"))
	{
		dbg("number of probes - " . scalar(keys(%{$result{'ALL'}})));
		dbg("number of probes with IP4 support  - " . scalar(keys(%{$result{'IP4'}})));
		dbg("number of probes with IP6 support  - " . scalar(keys(%{$result{'IP6'}})));
		dbg("number of probes with RDDS support - " . scalar(keys(%{$result{'RDDS'}})));
	}

	return \%result;
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

# returns a reference to a hash:
# {
#     hostid => {
#         itemid => 'key_',
#         ...
#     },
#     ...
# }
sub __get_host_items
{
	my $hostids_ref = shift;
	my $keys_ref = shift;

	my $rows_ref = db_select(
		"select hostid,itemid,key_".
		" from items".
		" where hostid in (" . join(',', @{$hostids_ref}) . ")".
			" and key_ in (" . join(',', map {"'$_'"} (@{$keys_ref})) . ")");

	my $result = {};

	foreach my $row_ref (@$rows_ref)
	{
		$result->{$row_ref->[0]}->{$row_ref->[1]} = $row_ref->[2];
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

sub tld_exists_locally($)
{
	my $tld = shift;

	my $rows_ref = db_select(
		"select 1".
		" from hosts h,hosts_groups hg,groups g".
		" where h.hostid=hg.hostid".
			" and hg.groupid=g.groupid".
			" and g.name='TLDs'".
			" and h.status=0".
			" and h.host='$tld'"
	);

	return 0 if (scalar(@$rows_ref) == 0);

	return 1;
}

sub tld_exists($)
{
	return tld_exists_locally(shift);
}

sub validate_tld($$)
{
	my $tld = shift;
	my $server_keys = shift;

	foreach my $server_key (@{$server_keys})
	{
		db_connect($server_key);

		my $rv = tld_exists_locally($tld);

		db_disconnect();

		if ($rv)
		{
			dbg("tld $tld found on $server_key");

			return;
		}
	}

	fail("tld \"$tld\" does not exist");
}

sub validate_service($)
{
	my $service = shift;

	fail("service \"$service\" is unknown") if (!grep {/$service/} ('dns', 'dnssec', 'rdds', 'epp'));
}

sub tld_service_enabled
{
	my $tld = shift;
	my $service = shift;
	my $now = shift;

	$service = lc($service);

	return 1 if ($service eq 'dns');

	if ($service eq 'rdds')
	{
		return 1 if tld_interface_enabled($tld, 'rdds43', $now);

		return tld_interface_enabled($tld, 'rdap', $now);
	}

	return tld_interface_enabled($tld, $service, $now);
}

sub enabled_item_key_from_interface
{
	my $interface = shift;

	if ($interface eq 'rdds43' || $interface eq 'rdds80')
	{
		return 'rdds.enabled';
	}

	return "$interface.enabled";
}

# NB! When parallelization is used use this function to create cache in parent
# process to use functions tld_<service|interface>_enabled() in child processes.
#
# Collect the itemids of 'enabled' items in one SQL to improve performance of the function
#
# %enabled_items_cache =
# (
#     'rdds.enabled' => {
#         'tld1' => [
#             itemid1,
#             itemid2,
#             ...
#         ],
#         'tld2' => [
#             ...
#         ]
#     },
#     'dnssec.enabled' => {
#         'tld1' => [
#             itemid1,
#             itemid2,
#             ...
#         ],
#         'tld2' => [
#             ...
#         ]
#         ...
#     },
#     ...
# )
#
# These variables are initialized at db_connect()

my %enabled_hosts_cache;	# (hostid1 => tld1, ...)
my %enabled_items_cache;	# (key1 => {tld1 => [itemid1, itemid2, ...], ...}, ...)
my @tlds_cache;			# (tld1, tld2, ...)

sub uniq
{
	my %seen;

	grep(!$seen{$_}++, @_);
}

sub tld_interface_enabled_create_cache
{
	my @interfaces = @_;

	dbg(join(',', @interfaces));

	return if (scalar(@interfaces) == 0);

	if (scalar(keys(%enabled_hosts_cache)) == 0)
	{
		my $rows_ref = db_select(
			"select h.hostid,h.host".
			" from hosts h,hosts_groups hg".
			" where h.hostid=hg.hostid".
				" and h.status=0".
				" and hg.groupid=".TLD_PROBE_RESULTS_GROUPID);

		map {$enabled_hosts_cache{$_->[0]} = substr($_->[1], 0, index($_->[1], ' '))} (@{$rows_ref});

		@tlds_cache = uniq(values(%enabled_hosts_cache)) if (scalar(@tlds_cache) == 0);
	}

	return if (scalar(keys(%enabled_hosts_cache)) == 0);

	foreach my $interface (@interfaces)
	{
		$interface = lc($interface);

		my $item_key = enabled_item_key_from_interface($interface);

		next if ($interface eq 'dns');

		if (!defined($enabled_items_cache{$item_key}))
		{
			$enabled_items_cache{$item_key} = ();

			my $rows_ref = db_select(
				"select itemid,hostid".
				" from items".
				" where key_='$item_key'".
					" and hostid in (" . join(',', keys(%enabled_hosts_cache)) . ")");

			map {$enabled_items_cache{$item_key}{$_} = []} (@tlds_cache);

			foreach my $row_ref (@{$rows_ref})
			{
				my $itemid = $row_ref->[0];
				my $hostid = $row_ref->[1];

				my $_tld = $enabled_hosts_cache{$hostid};

				push(@{$enabled_items_cache{$item_key}{$_tld}}, $itemid);
			}
		}
	}
}

sub tld_interface_enabled_delete_cache()
{
	%enabled_items_cache = ();
	%enabled_hosts_cache = ();
	@tlds_cache = ();
}

sub tld_interface_enabled($$$)
{
	my $tld = shift;
	my $interface = shift;
	my $now = shift;

	$interface = lc($interface);

	return 1 if ($interface eq 'dns');

	my $item_key = enabled_item_key_from_interface($interface);

	if (!defined($enabled_items_cache{$item_key}))
	{
		tld_interface_enabled_create_cache($interface);
	}

	if (defined($enabled_items_cache{$item_key}{$tld}))
	{
		# find the latest value but make sure to specify time bounds, relatively to $now

		$now = time() - 120 unless ($now);	# go back 2 minutes if time unspecified

		my $till = cycle_end($now, 60);

		my @conditions = (
			[$till - 0 * 3600 -  1 * 60 + 1, $till            , "clock desc"],	# go back 1 minute
			[$till - 0 * 3600 - 30 * 60 + 1, $till            , "clock desc"],	# go back 30 minutes
			[$till - 6 * 3600 -  0 * 60 + 1, $till            , "clock desc"],	# go back 6 hours
			[$till + 1                     , $till + 24 * 3600, "clock asc"]	# go forward 1 day
		);

		my $condition_index = 0;

		while ($condition_index < scalar(@conditions))
		{
			my $from = $conditions[$condition_index]->[0];
			my $till = $conditions[$condition_index]->[1];
			my $order = $conditions[$condition_index]->[2];

			my $rows_ref = db_select_binds(
				"select value".
				" from history_uint".
				" where itemid=?".
					" and " . sql_time_condition($from, $till).
				" order by $order".
				" limit 1",
				$enabled_items_cache{$item_key}{$tld});

			my $found = 0;

			foreach my $row_ref (@{$rows_ref})
			{
				if (defined($row_ref->[0]))
				{
					$found = 1;

					return 1 if ($row_ref->[0]);
				}
			}

			return 0 if ($found);

			$condition_index++;
		}
	}

	# try the Template macro

	my $host = "Template $tld";

	my $macro;

	if ($interface eq 'rdap')
	{
		$macro = '{$RDAP.TLD.ENABLED}';
	}
	elsif ($interface eq 'rdds43' || $interface eq 'rdds80')
	{
		$macro = '{$RSM.TLD.RDDS.ENABLED}';
	}
	else
	{
		$macro = '{$RSM.TLD.' . uc($interface) . '.ENABLED}';
	}

	my $rows_ref = db_select(
		"select hm.value".
		" from hosts h,hostmacro hm".
		" where h.hostid=hm.hostid".
			" and h.host='$host'".
			" and hm.macro='$macro'");

	if (scalar(@{$rows_ref}) != 0)
	{
		return $rows_ref->[0]->[0];
	}

	wrn("macro \"$macro\" does not exist at \"$host\", assuming $interface disabled");

	return 0;
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

sub db_select($;$)
{
	$_global_sql = shift;
	$_global_sql_bind_values = shift; # optional; reference to an array

	if ($get_stats)
	{
		$sql_start = Time::HiRes::time();
	}

	my $sth = $dbh->prepare($_global_sql)
		or fail("cannot prepare [$_global_sql]: ", $dbh->errstr);

	if (defined($_global_sql_bind_values))
	{
		dbg("[$_global_sql] ", join(',', @{$_global_sql_bind_values}));

		$sth->execute(@{$_global_sql_bind_values})
			or fail("cannot execute [$_global_sql]: ", $sth->errstr);
	}
	else
	{
		dbg("[$_global_sql]");

		$sth->execute()
			or fail("cannot execute [$_global_sql]: ", $sth->errstr);
	}

	if (opt('warnslow'))
	{
		$sql_send = Time::HiRes::time();
	}

	my $rows_ref = $sth->fetchall_arrayref();

	if ($get_stats)
	{
		$sql_end = Time::HiRes::time();
		$sql_time += ($sql_end - $sql_start);
		$sql_count++;
	}

	if (opt('warnslow') && (($sql_end - $sql_start) > getopt('warnslow')))
	{
		wrn("slow query: [$_global_sql] took ", sprintf("%.3f seconds (execute:%.3f fetch:%.3f)",
			($sql_end - $sql_start), ($sql_send - $sql_start), ($sql_end - $sql_send)));
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

	return $rows_ref;
}

sub db_select_col($;$)
{
	my $sql = shift;
	my $bind_values = shift; # optional; reference to an array

	my $rows = db_select($sql, $bind_values);

	fail("query returned more than one column") if (scalar(@{$rows}) > 0 && scalar(@{$rows->[0]}) > 1);

	return [map($_->[0], @{$rows})];
}

sub db_select_row($;$)
{
	my $sql = shift;
	my $bind_values = shift; # optional; reference to an array

	my $rows = db_select($sql, $bind_values);

	fail("query did not return any row") if (scalar(@{$rows}) == 0);
	fail("query returned more than one row") if (scalar(@{$rows}) > 1);

	return $rows->[0];
}

sub db_select_value($;$)
{
	my $sql = shift;
	my $bind_values = shift; # optional; reference to an array

	my $row = db_select_row($sql, $bind_values);

	fail("query returned more than one value") if (scalar(@{$row}) > 1);

	return $row->[0];
}

sub db_explain($$)
{
	my $sql = shift;
	my $bind_values = shift; # optional; reference to an array

	my $rows = db_select("explain $sql", $bind_values);

	my @header = (
		"id",
		"select_type",
		"table",
		"partitions",
		"type",
		"possible_keys",
		"key",
		"key_len",
		"ref",
		"rows",
		"filtered",
		"Extra"
	);

	my @col_widths = map(length, @header);

	foreach my $row (@{$rows})
	{
		for (my $i = 0; $i < scalar(@{$row}); $i++)
		{
			$row->[$i] //= "NULL";
			if ($col_widths[$i] < length($row->[$i]))
			{
				$col_widths[$i] = length($row->[$i]);
			}
		}
	}

	my $line_width = 0;
	my $line_format = "";
	for (my $i = 0; $i < scalar(@header); $i++)
	{
		$line_width += 2 + $col_widths[$i] + 1;
		$line_format .= "| %-${col_widths[$i]}s ";
	}
	$line_width += 2;
	$line_format .= " |\n";

	print("-" x $line_width . "\n");
	printf($line_format, @header);
	print("-" x $line_width . "\n");
	foreach my $row (@{$rows})
	{
		printf($line_format, @{$row});
	}
	print("-" x $line_width . "\n");
}

sub db_select_binds
{
	$_global_sql = shift;
	$_global_sql_bind_values = shift;

	my $sth = $dbh->prepare($_global_sql)
		or fail("cannot prepare [$_global_sql]: ", $dbh->errstr);

	dbg("[$_global_sql] ", join(',', @{$_global_sql_bind_values}));

	my ($total);

	my @rows;
	foreach my $bind_value (@{$_global_sql_bind_values})
	{
		if (opt('stats'))
		{
			$sql_start = Time::HiRes::time();
		}

		$sth->execute($bind_value)
			or fail("cannot execute [$_global_sql] bind_value:$bind_value: ", $sth->errstr);

		if (opt('warnslow'))
		{
			$sql_send = Time::HiRes::time();
		}

		while (my @row = $sth->fetchrow_array())
		{
			push(@rows, \@row);
		}

		if ($get_stats)
		{
			$sql_end = Time::HiRes::time();
			$sql_time += ($sql_end - $sql_start);
			$sql_count++;
		}

		if (opt('warnslow') && (($sql_end - $sql_start) > getopt('warnslow')))
		{
			wrn("slow query: [$_global_sql] took ", sprintf("%.3f seconds (execute:%.3f fetch:%.3f)",
				($sql_end - $sql_start), ($sql_send - $sql_start), ($sql_end - $sql_send)));
		}
	}

	if (opt('debug'))
	{
		if (scalar(@rows) == 1)
		{
			dbg(join(',', map {$_ // 'UNDEF'} (@{$rows[0]})));
		}
		else
		{
			dbg(scalar(@rows), " rows");
		}
	}

	return \@rows;
}

sub db_exec
{
	$_global_sql = shift;

	if ($get_stats)
	{
		$sql_start = Time::HiRes::time();
	}

	my $sth = $dbh->prepare($_global_sql)
		or fail("cannot prepare [$_global_sql]: ", $dbh->errstr);

	dbg("[$_global_sql]");

	my ($total);

	$sth->execute()
		or fail("cannot execute [$_global_sql]: ", $sth->errstr);

	if ($get_stats)
	{
		$sql_end = Time::HiRes::time();
		$sql_time += ($sql_end - $sql_start);
		$sql_count++;
	}

	if (opt('warnslow') && (($sql_end - $sql_start) > getopt('warnslow')))
	{
		wrn("slow query: [$_global_sql] took ", sprintf("%.3f seconds (execute:%.3f)",
			($sql_end - $sql_start), ($sql_send - $sql_start)));
	}

	return $sth->{mysql_insertid};
}

sub set_slv_config
{
	$config = shift;
}

# Get time bounds of the last cycle guaranteed to have all probe results.
sub get_cycle_bounds
{
	my $delay = shift;
	my $now = shift || (time() - $delay - AVAIL_SHIFT_BACK);	# last complete cycle, usually used for service availability calculation

	my $from = cycle_start($now, $delay);
	my $till = cycle_end($now, $delay);

	return ($from, $till, $from);
}

# Get time bounds for rolling week calculation. Last cycle must be complete.
sub get_rollweek_bounds
{
	my $delay = shift;
	my $now = shift || (time() - $delay - ROLLWEEK_SHIFT_BACK);	# last complete cycle, service availability must be calculated

	my $till = cycle_end($now, $delay);
	my $from = $till - __get_macro('{$RSM.ROLLWEEK.SECONDS}') + 1;

	return ($from, $till, cycle_start($till, $delay));
}

# Get bounds for monthly downtime calculation. $till is the last second of latest calculated test cycle.
# $from is the first second of the month.
sub get_downtime_bounds
{
	my $delay = shift;
	my $now = shift || (time() - $delay - ROLLWEEK_SHIFT_BACK);	# last complete cycle, service availability must be calculated

	require DateTime;

	my $till = cycle_end($now, $delay);

	my $dt = DateTime->from_epoch('epoch' => $till);
	$dt->truncate('to' => 'month');
	my $from = $dt->epoch;

	return ($from, $till, cycle_start($till, $delay));
}

# maximum cycles to process by SLV scripts
sub slv_max_cycles($)
{
	my $service = shift;

	my $var;

	if ($service eq 'dns' || $service eq 'dnssec')
	{
		$var = 'max_cycles_dns';
	}
	elsif ($service eq 'rdds')
	{
		$var = 'max_cycles_rdds';
	}
	else
	{
		return DEFAULT_SLV_MAX_CYCLES;
	}

	return (defined($config) && defined($config->{'slv'}->{$var}) ? $config->{'slv'}->{$var} : DEFAULT_SLV_MAX_CYCLES);
}

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
# NB! If a probe was down for the whole specified period or is currently disabled it won't be in a hash.
sub get_probe_times($$$)
{
	my $from = shift;
	my $till = shift;
	my $probes_ref = shift;	# {host => {'hostid' => hostid, 'status' => status}, ...}

	my $result = {};

	return $result if (scalar(keys(%{$probes_ref})) == 0);

	my @probes;
	foreach my $probe (keys(%{$probes_ref}))
	{
		next unless ($probes_ref->{$probe}->{'status'} == HOST_STATUS_MONITORED);

		push(@probes, "'$probe - mon'");
	}

	return $result if (scalar(@probes) == 0);

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

	# offline: if a probe is not in a hash it was offline for the whole period
	return 1 unless (exists($probe_times_ref->{$probe}));

	my $times_ref = $probe_times_ref->{$probe};

	my $clocks_count = scalar(@$times_ref);

	my $clock_index = 0;
	while ($clock_index < $clocks_count)
	{
		my $from = $times_ref->[$clock_index++];
		my $till = $times_ref->[$clock_index++];

		# online
		return 0 if ($from < $clock && $clock <= $till);
	}

	# offline
	return 1;
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
		dbg(__script(), ": no data collected, nothing to send");
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

sub is_internal_error
{
	my $rtt = shift;

	return 0 unless (defined($rtt));

	return 1 if (ZBX_EC_INTERNAL_FIRST >= $rtt && $rtt >= ZBX_EC_INTERNAL_LAST);	# internal error

	return 0;
}

sub get_value_from_desc
{
	my $desc = shift;

	my $index = index($desc, DETAILED_RESULT_DELIM);

	return ($index == -1 ? $desc : substr($desc, 0, $index));
}

sub is_internal_error_desc
{
	my $desc = shift;

	return 0 unless (defined($desc));
	return 0 unless (substr($desc, 0, 1) eq "-");

	return is_internal_error(get_value_from_desc($desc));
}

sub is_service_error
{
	my $service = shift;
	my $rtt = shift;
	my $rtt_low = shift;	# optional

	return 0 unless (defined($rtt));

	# not an error
	if ($rtt >= 0)
	{
		return 1 if ($rtt_low && $rtt > $rtt_low);

		# rtt within limit
		return 0;
	}

	# internal error
	return 0 if (is_internal_error($rtt));

	# dnssec error
	if (lc($service) eq 'dnssec')
	{
		return 1 if (ZBX_EC_DNS_UDP_DNSSEC_FIRST >= $rtt && $rtt >= ZBX_EC_DNS_UDP_DNSSEC_LAST);
		return 1 if (ZBX_EC_DNS_TCP_DNSSEC_FIRST >= $rtt && $rtt >= ZBX_EC_DNS_TCP_DNSSEC_LAST);

		return 0;
	}

	# other service error
	return 1;
}

# Check full error description and tell if it's a service error.
# E. g. if desc is "-401, DNS UDP - The TLD is configured as DNSSEC-enabled, but no DNSKEY was found in the apex"
# this function will return 1 for dnssec service.
sub is_service_error_desc
{
	my $service = shift;
	my $desc = shift;
	my $rtt_low = shift;	# optional

	return 0 unless (defined($desc));
	return 0 if ($desc eq "");

	return is_service_error($service, get_value_from_desc($desc), $rtt_low);
}

sub get_templated_items_like
{
	my $tld = shift;
	my $key_in = shift;

	my $hostid = get_hostid("Template $tld");

	my $items_ref = db_select(
		"select key_".
		" from items".
		" where hostid=$hostid".
			" and key_ like '$key_in%'".
			" and status<>".ITEM_STATUS_DISABLED);

	my @result;
	foreach my $item_ref (@{$items_ref})
	{
		push(@result, $item_ref->[0]);
	}

	return \@result;
}

# Collect cycles that needs to be calculated in form:
# {
#     value_ts1 : [
#         tld1,
#         tld2,
#         tld3,
#         ...
#     ],
#     value_ts2 : [
#         ...
#     ]
# }
#
# where value_ts is value timestamp of the cycle
sub collect_slv_cycles($$$$$$)
{
	my $tlds_ref = shift;
	my $delay = shift;
	my $cfg_key_out = shift;
	my $value_type = shift;	# value type of $cfg_key_out
	my $max_clock = shift;	# latest cycle to process
	my $max_cycles = shift;

	# cache TLD data
	my %cycles;

	my ($lastvalue, $lastclock);

	foreach (@{$tlds_ref})
	{
		$tld = $_;	# set global variable here

		my $itemid = get_itemid_by_host($tld, $cfg_key_out);

		if (get_lastvalue($itemid, $value_type, \$lastvalue, \$lastclock) != SUCCESS)
		{
			# new item
			push(@{$cycles{$max_clock}}, $tld);

			next;
		}

		next if (!opt('dry-run') && history_value_exists($value_type, $max_clock, $itemid));

		my $cycles_added = 0;

		while ($lastclock < $max_clock && (!$max_cycles || $cycles_added < $max_cycles))
		{
			$lastclock += $delay;

			push(@{$cycles{$lastclock}}, $tld);

			$cycles_added++;
		}

		# unset TLD (for the logs)
		$tld = undef;
	}

	return \%cycles;
}

# Process cycles that need to be calculcated.
sub process_slv_avail_cycles($$$$$$$$$)
{
	my $cycles_ref = shift;
	my $probes_ref = shift;
	my $delay = shift;
	my $cfg_keys_in = shift;	# if input key(s) is/are known
	my $cfg_keys_in_cb = shift;	# if input key(s) is/are unknown (DNSSEC, RDDS), call this function go get them
	my $cfg_key_out = shift;
	my $cfg_minonline = shift;
	my $check_probe_values_cb = shift;
	my $cfg_value_type = shift;

	# cache TLD data
	my %keys_in;

	init_values();

	foreach my $value_ts (sort(keys(%{$cycles_ref})))
	{
		my $from = cycle_start($value_ts, $delay);
		my $till = cycle_end($value_ts, $delay);

		dbg("selecting period ", selected_period($from, $till), " (value_ts:", ts_str($value_ts), ")");

		my @online_probe_names = keys(%{get_probe_times($from, $till, $probes_ref)});

		foreach (@{$cycles_ref->{$value_ts}})
		{
			$tld = $_;	# set global variable here

			if (!defined($keys_in{$tld}))
			{
				if (defined($cfg_keys_in))
				{
					$keys_in{$tld} = $cfg_keys_in;
				}
				else
				{
					$keys_in{$tld} = $cfg_keys_in_cb->($tld);
				}

				if (!defined($keys_in{$tld}))
				{
					fail("cannot get input keys for Service availability calculation");
				}
			}

			process_slv_avail($tld, $keys_in{$tld}, $cfg_key_out, $from, $till, $value_ts, $cfg_minonline,
				\@online_probe_names, $check_probe_values_cb, $cfg_value_type);
		}

		# unset TLD (for the logs)
		$tld = undef;
	}

	send_values();
}

sub process_slv_avail($$$$$$$$$$)
{
	my $tld = shift;
	my $cfg_keys_in = shift;	# array reference, e. g. ['rsm.dns.udp.rtt[...]', ...] or ['rsm.dns.udp[...]']
	my $cfg_key_out = shift;
	my $from = shift;
	my $till = shift;
	my $value_ts = shift;
	my $cfg_minonline = shift;
	my $online_probe_names = shift;
	my $check_probe_values_ref = shift;
	my $value_type = shift;

	croak("Internal error: invalid argument to process_slv_avail()") unless (ref($online_probe_names) eq 'ARRAY');

	my $online_probe_count = scalar(@{$online_probe_names});

	if ($online_probe_count < $cfg_minonline)
	{
		push_value($tld, $cfg_key_out, $value_ts, UP_INCONCLUSIVE_NO_PROBES,
				"Up (not enough probes online, $online_probe_count while $cfg_minonline required)");

		if (alerts_enabled() == SUCCESS)
		{
			add_alert(ts_str($value_ts) . "#system#zabbix#$cfg_key_out#PROBLEM#$tld (not enough probes" .
					" online, $online_probe_count while $cfg_minonline required)");
		}

		return;
	}

	my $hostids_ref = probes2tldhostids($tld, $online_probe_names);
	if (scalar(@$hostids_ref) == 0)
	{
		wrn("no probe hosts found");
		return;
	}

	my $host_items_ref = __get_host_items($hostids_ref, $cfg_keys_in);
	if (scalar(keys(%{$host_items_ref})) == 0)
	{
		wrn("no items (".join(',',@{$cfg_keys_in}).") found");
		return;
	}

	my $values_ref = __get_item_values($host_items_ref, $from, $till, $value_type);

	my $probes_with_results = scalar(@{$values_ref});
	if ($probes_with_results < $cfg_minonline)
	{
		push_value($tld, $cfg_key_out, $value_ts, UP_INCONCLUSIVE_NO_DATA,
				"Up (not enough probes with results, $probes_with_results while $cfg_minonline required)");

		if (alerts_enabled() == SUCCESS)
		{
			add_alert(ts_str($value_ts) . "#system#zabbix#$cfg_key_out#PROBLEM#$tld (not enough probes" .
					" with results, $probes_with_results while $cfg_minonline required)");
		}

		return;
	}

	my $probes_with_positive = 0;

	foreach my $probe_values (@{$values_ref})
	{
		my $result = $check_probe_values_ref->($probe_values);

		$probes_with_positive++ if (SUCCESS == $result);

		next unless (opt('debug'));

		dbg("probe result: ", (SUCCESS == $result ? "up" : "down"));
	}

	my $perc = $probes_with_positive * 100 / $probes_with_results;
	my $detailed_info = sprintf("%d/%d positive, %.3f%%", $probes_with_positive, $probes_with_results, $perc);

	if ($perc > SLV_UNAVAILABILITY_LIMIT)
	{
		push_value($tld, $cfg_key_out, $value_ts, UP, "Up ($detailed_info)");
	}
	else
	{
		push_value($tld, $cfg_key_out, $value_ts, DOWN, "Down ($detailed_info)");
	}
}

sub process_slv_rollweek_cycles($$$$$)
{
	my $cycles_ref = shift;
	my $delay = shift;
	my $cfg_key_in = shift;
	my $cfg_key_out = shift;
	my $cfg_sla = shift;

	my %itemids;

	init_values();

	foreach my $value_ts (sort(keys(%{$cycles_ref})))
	{
		my ($from, $till, undef) = get_rollweek_bounds($delay, $value_ts);

		dbg("selecting period ", selected_period($from, $till), " (value_ts:", ts_str($value_ts), ")");

		foreach (@{$cycles_ref->{$value_ts}})
		{
			# NB! This is needed in order to set the value globally.
			$tld = $_;

			$itemids{$tld}{'itemid_in'} = get_itemid_by_host($tld, $cfg_key_in) unless ($itemids{$tld}{'itemid_in'});
			$itemids{$tld}{'itemid_out'} = get_itemid_by_host($tld, $cfg_key_out) unless ($itemids{$tld}{'itemid_out'});

			next if (!opt('dry-run') && float_value_exists($value_ts, $itemids{$tld}{'itemid_out'}));

			# skip calculation if Service Availability value is not yet there
			next if (!opt('dry-run') && !uint_value_exists($value_ts, $itemids{$tld}{'itemid_in'}));

			my $downtime = get_downtime($itemids{$tld}{'itemid_in'}, $from, $till, undef, undef, $delay);	# consider incidents
			my $perc = sprintf("%.3f", $downtime * 100 / $cfg_sla);

			push_value($tld, $cfg_key_out, $value_ts, $perc, "result: $perc% (down: $downtime minutes, sla: $cfg_sla)");
		}

		# unset TLD (for the logs)
		$tld = undef;
	}

	send_values();
}

sub process_slv_downtime_cycles($$$$)
{
	my $cycles_ref = shift;
	my $delay = shift;
	my $cfg_key_in = shift;
	my $cfg_key_out = shift;

	my $sth = get_downtime_prepare();

	my %itemids;

	init_values();

	foreach my $value_ts (sort(keys(%{$cycles_ref})))
	{
		my ($from, $till, undef) = get_downtime_bounds($delay, $value_ts);

		dbg("selecting period ", selected_period($from, $till), " (value_ts:", ts_str($value_ts), ")");

		foreach (@{$cycles_ref->{$value_ts}})
		{
			# NB! This is needed in order to set the value globally.
			$tld = $_;

			$itemids{$tld}{'itemid_in'} = get_itemid_by_host($tld, $cfg_key_in) unless ($itemids{$tld}{'itemid_in'});
			$itemids{$tld}{'itemid_out'} = get_itemid_by_host($tld, $cfg_key_out) unless ($itemids{$tld}{'itemid_out'});

			next if (!opt('dry-run') && uint_value_exists($value_ts, $itemids{$tld}{'itemid_out'}));

			# skip calculation if Service Availability value is not yet there
			next if (!opt('dry-run') && !uint_value_exists($value_ts, $itemids{$tld}{'itemid_in'}));

			my $downtime = get_downtime_execute($sth, $itemids{$tld}{'itemid_in'}, $from, $till, 0, $delay);

			push_value($tld, $cfg_key_out, $value_ts, $downtime, ts_str($from), " - ", ts_str($till));
		}

		# unset TLD (for the logs)
		$tld = undef;
	}

	send_values();
}

# organize values grouped by hosts:
#
# [
#     {
#         'foo[a,b]' => [1],
#         'bar[c,d]' => [-201]
#     },
#     {
#         'foo[a,b]' => [34],
#         'bar[c,d]' => [27, 14]
#     },
#     ...
# ]

sub __get_item_values($$$$)
{
	my $host_items_ref = shift;
	my $from = shift;
	my $till = shift;
	my $value_type = shift;

	return [] if (scalar(keys(%{$host_items_ref})) == 0);

	my %item_host_ids_map = map {
		my $hostid = $_;
		map { $_ => $hostid } (keys(%{$host_items_ref->{$hostid}}))
	} (keys(%{$host_items_ref}));

	my @itemids = map { keys(%{$_}) } (values(%{$host_items_ref}));

	return [] if (scalar(@itemids) == 0);

	my $rows_ref = db_select(
		"select itemid,value".
		" from " . history_table($value_type).
		" where itemid in (" . join(',', @itemids) . ")".
			" and clock between $from and $till".
		" order by clock");

	my %result;

	foreach my $row_ref (@$rows_ref)
	{
		my $itemid = $row_ref->[0];
		my $value = $row_ref->[1];

		my $hostid = $item_host_ids_map{$itemid};
		my $key = $host_items_ref->{$hostid}->{$itemid};

		push(@{$result{$hostid}->{$key}}, $value);

		dbg("  h:$hostid $key=$value");
	}

	return [values(%result)];
}

sub uint_value_exists($$)
{
        my $clock = shift;
        my $itemid = shift;

        my $rows_ref = db_select("select 1 from history_uint where itemid=$itemid and clock=$clock");

        return 1 if (defined($rows_ref->[0]->[0]));

        return 0;
}

sub float_value_exists($$)
{
        my $clock = shift;
        my $itemid = shift;

        my $rows_ref = db_select("select 1 from history where itemid=$itemid and clock=$clock");

        return 1 if (defined($rows_ref->[0]->[0]));

        return 0;
}

sub history_value_exists($$$)
{
	my $value_type = shift;
        my $clock = shift;
        my $itemid = shift;

	my $rows_ref;

	return uint_value_exists($clock, $itemid) if ($value_type == ITEM_VALUE_TYPE_UINT64);
	return float_value_exists($clock, $itemid) if ($value_type == ITEM_VALUE_TYPE_FLOAT);

	fail("internal error: value type $value_type is not supported by function history_value_exists()");
}

sub __make_incident
{
	my %h;

	$h{'eventid'} = shift;
	$h{'false_positive'} = shift;
	$h{'event_clock'} = shift;
	$h{'start'} = shift;
	$h{'end'} = shift;

	return \%h;
}

sub sql_time_condition
{
	my $from = shift;
	my $till = shift;
	my $clock_field = shift;

	$clock_field = "clock" unless (defined($clock_field));

	if (defined($from) and not defined($till))
	{
		return "$clock_field>=$from";
	}

	if (not defined($from) and defined($till))
	{
		return "$clock_field<=$till";
	}

	if (defined($from) and defined($till))
	{
		return "$clock_field=$from" if ($from == $till);
		fail("invalid time conditions: from=$from till=$till") if ($from > $till);
		return "$clock_field between $from and $till";
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

	dbg(selected_period($from, $till));

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
				push(@incidents, __make_incident($eventid, $false_positive, $clock, cycle_start($clock, $delay)));

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
				$incidents[$idx]->{'event_clock'} = $clock;
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
			push(@incidents, __make_incident($eventid, $false_positive, $clock, cycle_start($clock, $delay)));
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
		push(@$incidents, __make_incident(0, 0, 0, $from, $till));
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
				" and " . sql_time_condition($period_from, $period_till).
			" order by clock");

		my $is_down = 0;	# 1 if service is "Down"
					# 0 if it is "Up", "Up-inconclusive-no-data" or "Up-inconclusive-no-probes"
		my $prevclock = 0;

		foreach my $row_ref (@$rows_ref)
		{
			$fetches++;

			my $value = $row_ref->[0];
			my $clock = $row_ref->[1];

			# NB! Do not ignore the first downtime minute
			if ($value == DOWN && $prevclock == 0)
			{
				# first run
				$downtime += 60;
			}
			elsif ($is_down != 0)
			{
				$downtime += $clock - $prevclock;
			}

			$is_down = ($value == DOWN ? 1 : 0);
			$prevclock = $clock;
		}

		# leftover of downtime
		$downtime += $period_till - $prevclock if ($is_down != 0);
	}

	# complete minute
	$downtime += 60 - ($downtime % 60 ? $downtime % 60 : 60);

	# return minutes
	return int($downtime / 60);
}

sub get_downtime_prepare
{
	my $query =
		"select value,clock".
		" from history_uint".
		" where itemid=?".
			" and clock between ? and ?".
		" order by clock";

	if ($get_stats)
	{
		$sql_start = Time::HiRes::time();
	}

	my $sth = $dbh->prepare($query)
		or fail("cannot prepare [$query]: ", $dbh->errstr);

	if ($get_stats)
	{
		$sql_end = Time::HiRes::time();
		$sql_time += ($sql_end - $sql_start);
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

		if ($get_stats)
		{
			$sql_start = Time::HiRes::time();
		}

		$sth->bind_param(1, $itemid, SQL_INTEGER);
		$sth->bind_param(2, $period_from, SQL_INTEGER);
		$sth->bind_param(3, $period_till, SQL_INTEGER);

		$sth->execute()
			or fail("cannot execute query: ", $sth->errstr);

		my ($value, $clock);
		$sth->bind_columns(\$value, \$clock);

		if ($get_stats)
		{
			$sql_end = Time::HiRes::time();
			$sql_time += ($sql_end - $sql_start);
			$sql_count++;
		}

		my $is_down = 0;	# 1 if service is "Down"
					# 0 if it is "Up", "Up-inconclusive-no-data" or "Up-inconclusive-no-probes"
		my $prevclock = 0;

		while ($sth->fetch)
		{
			$fetches++;

			if ($value == DOWN && $prevclock == 0)
			{
				# first run
				$downtime += 60;
			}
			elsif ($is_down != 0)
			{
				$downtime += $clock - $prevclock;
			}

			$is_down = ($value == DOWN ? 1 : 0);
			$prevclock = $clock;
		}

		# leftover of downtime
		$downtime += $period_till - $prevclock if ($is_down != 0);

		$sth->finish();
		$sql_count++;
	}

	# complete minute
	$downtime += 60 - ($downtime % 60 ? $downtime % 60 : 60);

	# return minutes
	return int($downtime / 60);
}

sub history_table($)
{
	my $value_type = shift;

	return "history_uint" if (!defined($value_type) || $value_type == ITEM_VALUE_TYPE_UINT64);	# default
	return "history" if ($value_type == ITEM_VALUE_TYPE_FLOAT);
	return "history_str" if ($value_type == ITEM_VALUE_TYPE_STR);

	fail("THIS_SHOULD_NEVER_HAPPEN");
}

# returns:
# SUCCESS - last clock and value found
# E_FAIL  - nothing found
sub get_lastvalue($$$$)
{
	my $itemid = shift;
	my $value_type = shift;
	my $value_ref = shift;
	my $clock_ref = shift;

	fail("THIS_SHOULD_NEVER_HAPPEN") unless ($clock_ref || $value_ref);

	my $rows_ref;

	if ($value_type == ITEM_VALUE_TYPE_FLOAT || $value_type == ITEM_VALUE_TYPE_UINT64)
	{
		$rows_ref = db_select("select value,clock from lastvalue where itemid=$itemid");
	}
	else
	{
		$rows_ref = db_select("select value,clock from lastvalue_str where itemid=$itemid");
	}

	if (@{$rows_ref})
	{
		$$value_ref = $rows_ref->[0]->[0] if ($value_ref);
		$$clock_ref = $rows_ref->[0]->[1] if ($clock_ref);

		return SUCCESS;
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

#
# returns array of itemids: [itemid1, itemid2, ...]
#
sub get_itemids_by_key_pattern_and_hosts($$;$)
{
	my $key_pattern = shift; # pattern for 'items.key_ like ...' condition
	my $hosts       = shift; # ref to array of hosts, e.g., ['tld1', 'tld2', ...]
	my $item_status = shift; # optional; ITEM_STATUS_ACTIVE or ITEM_STATUS_DISABLED

	my $hosts_placeholder = join(",", ("?") x scalar(@{$hosts}));

	my $item_status_condition = defined($item_status) ? ("items.status=" . $item_status . " and") : "";

	my $bind_values = [$key_pattern, @{$hosts}];
	my $rows = db_select(
		"select items.itemid" .
		" from items left join hosts on hosts.hostid = items.hostid" .
		" where $item_status_condition" .
			" items.key_ like ? and" .
			" hosts.host in ($hosts_placeholder)", $bind_values);

	return [map($_->[0], @{$rows})];
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

# todo: the $vmname's must be fixed accordingly
# todo: also, consider renaming to something like get_rtt_valuemaps()
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
	elsif ($service = 'rdap')
	{
		$vmname = 'RSM RDAP rtt';
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

# todo: the $vmname's must be fixed accordingly
# todo: also, consider renaming to something like get_result_valuemaps()
sub get_statusmaps
{
	my $service = shift;

	my $vmname;
	if ($service eq 'dns' or $service eq 'dnssec')
	{
		# todo: this will be used later (many statuses)
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

	return $value_int . DETAILED_RESULT_DELIM . $maps->{$value_int};
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

# truncate specified unix timestamp to 0 seconds
sub truncate_from
{
	my $ts = shift;

	return $ts - ($ts % 60);
}

# truncate specified unix timestamp to 59 seconds
sub truncate_till
{
	return truncate_from(shift) + 59;
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

# $services is a hash reference of services that need to be checked.
# For each service the delay must be provided. "from" and "till" values
# will be set for services whose tests fall under given time between
# $check_from and $check_till.
#
# Input $services:
#
# [
#   {'dns' => {'delay' => 60}},
#   {'rdds' => {'delay' => 300}}
# ]
#
# Output $services:
#
# [
#   {'dns' => {'delay' => 60, 'from' => 1234234200, 'till' => 1234234259}}	# <- test period found for 'dns' but not for 'rdds'
# ]
#
# The return value is min($from), max($till) from all found periods
#
sub get_real_services_period
{
	my $services = shift;
	my $check_from = shift;
	my $check_till = shift;

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

# Call this function from child, to open separate log file handler and reset stats.
sub init_process
{
	$log_open = 0;
	__reset_stats();
}

sub finalize_process
{
	my $rv = shift // SUCCESS;

	db_disconnect();

	if (SUCCESS == $rv && opt('stats'))
	{
		my $prefix = $tld ? "$tld " : '';

		my $sql_str = format_stats_time($sql_time);

		$sql_str .= " ($sql_count queries)";

		my $total_str = format_stats_time(Time::HiRes::time() - $start_time);

		info($prefix, "PID ($$), total: $total_str, sql: $sql_str");
	}

	closelog();
}

sub slv_exit
{
	my $rv = shift;

	finalize_process($rv);

	exit($rv);
}

sub __is_already_running()
{
	my $filename = __get_pidfile();

	$pidfile = File::Pid->new({ file => $filename });

	fail("cannot lock script") unless (defined($pidfile));

	$pidfile->write() or fail("cannot write to a pid file ", $pidfile->file);

	# the only instance running is us
	return if ($pidfile->pid == $$);

	# pid file exists, see if the pid in it is valid
	my $pid = $pidfile->running();

	if ($pid)
	{
		# yes, we have another instance running
		return $pid;
	}

	# invalid pid in the pid file, update it
	$pidfile->pid($$);
	$pidfile->write() or fail("cannot write to a pid file ", $pidfile->file);

	return;
}

sub fail_if_running()
{
	return if (opt('dry-run'));

	my $pid = __is_already_running();

	if ($pid)
	{
		fail(__script() . " is already running (pid:$pid)");
	}
}

sub exit_if_running()
{
	return if (opt('dry-run'));

	my $pid = __is_already_running();

	if ($pid)
	{
		wrn(__script() . " is already running (pid:$pid)");
		exit 0;
	}
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

my $on_fail_cb;

sub set_on_fail
{
	$on_fail_cb = shift;
}

sub fail
{
	__log('err', join('', @_));

	if ($on_fail_cb)
	{
		dbg("script failed, calling \"on fail\" callback...");
		$on_fail_cb->();
		dbg("\"on fail\" callback finished");
	}

	slv_exit(E_FAIL);
}

sub trim
{
	$_[0] =~ s/^\s+//g;
	$_[0] =~ s/\s+$//g;
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

	$start_time = Time::HiRes::time() if (opt('stats'));

	$get_stats = 1 if (opt('stats') || opt('warnslow'));

	if (opt('debug'))
	{
		dbg("command-line parameters:");
		dbg("$_ => ", getopt($_)) foreach (optkeys());
	}
}

sub parse_slv_opts
{
	$POD2USAGE_FILE = '/opt/zabbix/scripts/slv/rsm.slv.usage';

	parse_opts('tld=s', 'now=n', 'cycles=n');
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
	my $file = shift;
	my $text = shift;

	my $OUTFILE;

	return E_FAIL unless (open($OUTFILE, '>', $file));

	my $rv = print { $OUTFILE } $text;

	close($OUTFILE);

	return E_FAIL unless ($rv);

	return SUCCESS;
}

sub read_file($$$)
{
	my $file = shift;
	my $buf = shift;
	my $error = shift;

	my $contents = do
	{
		local $/ = undef;

		if (!open my $fh, "<", $file)
		{
			$$error = "$!";
			return E_FAIL;
		}

		<$fh>;
	};

	$$buf = $contents;

	return SUCCESS;
}

sub cycle_start($$)
{
	my $now = shift;
	my $delay = shift;

	return $now - ($now % $delay);
}

sub cycle_end($$)
{
	my $now = shift;
	my $delay = shift;

	return cycle_start($now, $delay) + $delay - 1;
}

sub cycles_till_end_of_month($$)
{
	my $now = shift;
	my $delay = shift;

	my $end_of_month = get_end_of_month($now);
	my $this_cycle_start = cycle_start($now, $delay);
	my $last_cycle_end = cycle_end($end_of_month, $delay);
	my $cycle_count = ($last_cycle_end + 1 - $this_cycle_start) / $delay;

	if (opt('debug'))
	{
		require DateTime;

		dbg('now              - ', DateTime->from_epoch('epoch' => $now));
		dbg('this cycle start - ', DateTime->from_epoch('epoch' => $this_cycle_start));
		dbg('end of month     - ', DateTime->from_epoch('epoch' => $end_of_month));
		dbg('last cycle end   - ', DateTime->from_epoch('epoch' => $last_cycle_end));
		dbg('delay            - ', $delay);
		dbg('cycle count      - ', $cycle_count);
	}

	return $cycle_count;
}

sub get_end_of_month($)
{
	my $now = shift;

	require DateTime;

	my $dt = DateTime->from_epoch('epoch' => $now);
	$dt->truncate('to' => 'month');
	$dt->add('months' => 1);
	$dt->subtract('seconds' => 1);
	return $dt->epoch();
}

sub get_end_of_prev_month($)
{
	my $now = shift;

	require DateTime;

	my $dt = DateTime->from_epoch('epoch' => $now);
	$dt->truncate('to' => 'month');
	$dt->subtract('seconds' => 1);
	return $dt->epoch();
}

sub get_month_bounds(;$)
{
	my $now = shift // time();

	require DateTime;

	my $from;
	my $till;

	my $dt = DateTime->from_epoch('epoch' => $now);
	$dt->truncate('to' => 'month');
	$from = $dt->epoch();
	$dt->add('months' => 1);
	$dt->subtract('seconds' => 1);
	$till = $dt->epoch();

	return ($from, $till);
}

sub get_slv_rtt_cycle_stats($$$$)
{
	my $tld         = shift;
	my $rtt_params  = shift;
	my $cycle_start = shift;
	my $cycle_end   = shift;

	my $probes                  = $rtt_params->{'probes'};
	my $rtt_item_key_pattern    = $rtt_params->{'rtt_item_key_pattern'};
	my $timeout_error_value     = $rtt_params->{'timeout_error_value'};
	my $timeout_threshold_value = $rtt_params->{'timeout_threshold_value'};

	if (scalar(keys(%{$probes})) == 0)
	{
		dbg("there are no probes that would be able to collect RTT stats for TLD '$tld', item '$rtt_item_key_pattern'");
		return {
			'expected'   => 0,
			'total'      => 0,
			'performed'  => 0,
			'failed'     => 0,
			'successful' => 0
		};
	}

	my $tld_hosts = [map("$tld $_", keys(%{$probes}))];
	my $tld_itemids = get_itemids_by_key_pattern_and_hosts($rtt_item_key_pattern, $tld_hosts, ITEM_STATUS_ACTIVE);
	my $tld_itemids_str = join(",", @{$tld_itemids});

	fail("Items '$rtt_item_key_pattern' not found") if scalar(@{$tld_itemids}) == 0;

	my $rows = db_select(
			"select count(*)," .
				" count(if(value=$timeout_error_value || value>$timeout_threshold_value,1,null))," .
				" count(if(value between 0 and $timeout_threshold_value,1,null))" .
			" from history" .
			" where itemid in ($tld_itemids_str) and clock between $cycle_start and $cycle_end");

	return {
		'expected'   => scalar(@{$tld_itemids}),        # number of expected tests, based on number of items and number of probes
		'total'      => $rows->[0][0],                  # number of received values, including errors
		'performed'  => $rows->[0][1] + $rows->[0][2],  # number of received values, excluding errors (timeout errors are valid values)
		'failed'     => $rows->[0][1],                  # number of failed tests - timeout errors and successful queries over the time limit
		'successful' => $rows->[0][2]                   # number of successful tests
	};
}

sub get_slv_rtt_cycle_stats_aggregated($$$$)
{
	my $rtt_params_list = shift; # array of hashes
	my $cycle_start     = shift;
	my $cycle_end       = shift;
	my $tld             = shift;

	my %aggregated_stats = (
		'expected'   => 0,
		'total'      => 0,
		'performed'  => 0,
		'failed'     => 0,
		'successful' => 0
	);

	foreach my $rtt_params (@{$rtt_params_list})
	{
		if (!tld_service_enabled($tld, $rtt_params->{'tlds_service'}, $cycle_end))
		{
			next;
		}

		my $service_stats = get_slv_rtt_cycle_stats($tld, $rtt_params, $cycle_start, $cycle_end);

		$aggregated_stats{'expected'}   += $service_stats->{'expected'};
		$aggregated_stats{'total'}      += $service_stats->{'total'};
		$aggregated_stats{'performed'}  += $service_stats->{'performed'};
		$aggregated_stats{'failed'}     += $service_stats->{'failed'};
		$aggregated_stats{'successful'} += $service_stats->{'successful'};
	}

	return \%aggregated_stats;
}

sub get_slv_rtt_monthly_items($$$$)
{
	my $single_tld             = shift; # undef or name of TLD
	my $slv_item_key_performed = shift;
	my $slv_item_key_failed    = shift;
	my $slv_item_key_pfailed   = shift;

	my $host_condition = "";

	my @bind_values = (
		$slv_item_key_performed,
		$slv_item_key_failed,
		$slv_item_key_pfailed
	);

	if (defined($single_tld))
	{
		$host_condition = "hosts.host=? and";
		push(@bind_values, $single_tld);
	}

	my $slv_items = db_select(
			"select hosts.host,items.key_,lastvalue.clock,lastvalue.value" .
			" from items" .
				" left join hosts on hosts.hostid=items.hostid" .
				" left join hosts_groups on hosts_groups.hostid=hosts.hostid" .
				" left join lastvalue on lastvalue.itemid=items.itemid" .
			" where items.status=" . ITEM_STATUS_ACTIVE . " and" .
				" items.key_ in (?,?,?) and" .
				" $host_condition" .
				" hosts.status=" . HOST_STATUS_MONITORED . " and" .
				" hosts_groups.groupid=" . TLDS_GROUPID, \@bind_values);

	# contents: $slv_items_by_tld{$tld}{$item_key} = [$last_clock, $last_value];
	my %slv_items_by_tld = ();

	foreach my $slv_item (@{$slv_items})
	{
		my ($tld, $item_key, $last_clock, $last_value) = @{$slv_item};
		$slv_items_by_tld{$tld}{$item_key} = [$last_clock, $last_value];
	}

	foreach my $tld (keys(%slv_items_by_tld))
	{
		my %tld_items = %{$slv_items_by_tld{$tld}};

		# if any item was found on TLD, then all items must exist
		fail("Item '$slv_item_key_performed' not found for TLD '$tld'")
				unless (exists($tld_items{$slv_item_key_performed}));
		fail("Item '$slv_item_key_failed' not found for TLD '$tld'")
				unless (exists($tld_items{$slv_item_key_failed}));
		fail("Item '$slv_item_key_pfailed' not found for TLD '$tld'")
				unless (exists($tld_items{$slv_item_key_pfailed}));

		if (!defined($tld_items{$slv_item_key_performed}[0]) ||
				!defined($tld_items{$slv_item_key_failed}[0]) ||
				!defined($tld_items{$slv_item_key_pfailed}[0]))
		{
			# if any lastvalue on TLD is undefined, then all lastvalues must be undefined

			fail("Item '$slv_item_key_performed' on TLD '$tld' has lastvalue while other related items don't")
					if (defined($tld_items{$slv_item_key_performed}[0]));
			fail("Item '$slv_item_key_failed' on TLD '$tld' has lastvalue while other related items don't")
					if (defined($tld_items{$slv_item_key_failed}[0]));
			fail("Item '$slv_item_key_pfailed' on TLD '$tld' has lastvalue while other related items don't")
					if (defined($tld_items{$slv_item_key_pfailed}[0]));
		}
		else
		{
			# if all lastvalues on TLD are defined, their clock must be the same

			if ($tld_items{$slv_item_key_performed}[0] != $tld_items{$slv_item_key_failed}[0] ||
					$tld_items{$slv_item_key_performed}[0] != $tld_items{$slv_item_key_pfailed}[0])
			{
				fail("Items '$slv_item_key_performed', '$slv_item_key_failed' and '$slv_item_key_pfailed' have different lastvalue clocks on TLD '$tld'");
			}
		}
	}

	return \%slv_items_by_tld;
}

sub update_slv_rtt_monthly_stats($$$$$$$$)
{
	my $now                    = shift;
	my $max_cycles             = shift;
	my $single_tld             = shift; # undef or name of TLD
	my $slv_item_key_performed = shift;
	my $slv_item_key_failed    = shift;
	my $slv_item_key_pfailed   = shift;
	my $cycle_delay            = shift;
	my $rtt_params_list        = shift;

	# how long to wait for data after $cycle_end if number of performed checks is smaller than expected checks
	# TODO: $max_nodata_time = $cycle_delay * x?
	# TODO: move to rsm.conf?
	my $max_nodata_time = 300;

	# contents: $slv_items->{$tld}{$item_key} = [$last_clock, $last_value];
	my $slv_items = get_slv_rtt_monthly_items($single_tld, $slv_item_key_performed, $slv_item_key_failed, $slv_item_key_pfailed);

	# starting time of the last cycle of the previous month
	my $end_of_prev_month = cycle_start(get_end_of_prev_month($now), $cycle_delay);

	init_values();

	TLD_LOOP:
	foreach my $tld (keys(%{$slv_items}))
	{
		my $last_clock           = $slv_items->{$tld}{$slv_item_key_performed}[0];
		my $last_performed_value = $slv_items->{$tld}{$slv_item_key_performed}[1];
		my $last_failed_value    = $slv_items->{$tld}{$slv_item_key_failed}[1];
		my $last_pfailed_value   = $slv_items->{$tld}{$slv_item_key_pfailed}[1];

		# if there's no lastvalue, start collecting stats from the begining of the current month
		if (!defined($last_clock))
		{
			$last_clock = $end_of_prev_month;
		}

		my $cycles_till_end_of_month = cycles_till_end_of_month($last_clock + $cycle_delay, $cycle_delay);

		for (my $i = 0; $i < $max_cycles; $i++)
		{
			# if new month starts, reset the counters
			if ($last_clock == $end_of_prev_month)
			{
				$cycles_till_end_of_month = cycles_till_end_of_month($last_clock + $cycle_delay, $cycle_delay);
				$last_performed_value = 0;
				$last_failed_value    = 0;
				$last_pfailed_value   = 0;
			}

			my $cycle_start = cycle_start($last_clock + $cycle_delay, $cycle_delay);
			my $cycle_end   = cycle_end($last_clock + $cycle_delay, $cycle_delay);

			if ($cycle_start > $now)
			{
				next TLD_LOOP;
			}

			my $rtt_stats = get_slv_rtt_cycle_stats_aggregated($rtt_params_list, $cycle_start, $cycle_end, $tld);

			if ($rtt_stats->{'total'} < $rtt_stats->{'expected'} && $cycle_end > $now - $max_nodata_time)
			{
				if (opt('debug'))
				{
					dbg("stopping updatig TLD '$tld' because of missing data, cycle from $cycle_start till $cycle_end");
				}
				next TLD_LOOP;
			}

			$cycles_till_end_of_month--;

			if ($cycles_till_end_of_month < 0)
			{
				if (opt('debug'))
				{
					dbg("\$i                        = $i");
					dbg("\$cycles_till_end_of_month = $cycles_till_end_of_month");
					dbg("\$end_of_prev_month        = $end_of_prev_month");
					dbg("\$last_clock               = $last_clock");
					dbg("\$cycle_delay              = $cycle_delay");
					dbg("\$cycle_start              = $cycle_start");
					dbg("\$cycle_end                = $cycle_end");
				}
				fail("\$cycles_till_end_of_month must not be less than 0");
			}

			$last_performed_value += $rtt_stats->{'performed'};
			$last_failed_value    += $rtt_stats->{'failed'};
			$last_pfailed_value    = 100 * $last_failed_value / ($last_performed_value + $cycles_till_end_of_month * $rtt_stats->{'expected'});

			push_value($tld, $slv_item_key_performed, $cycle_start, $last_performed_value);
			push_value($tld, $slv_item_key_failed   , $cycle_start, $last_failed_value);
			push_value($tld, $slv_item_key_pfailed  , $cycle_start, $last_pfailed_value);

			$last_clock = $cycle_start;
		}
	}

	send_values();
}

sub recalculate_downtime($$$$)
{
	my $item_key_avail    = shift;
	my $item_key_downtime = shift;
	my $threshold         = shift;
	my $delay             = shift;

	fail("not supported when running in --dry-run mode") if (opt('dry-run'));

	my $sql;
	my $params;
	my $rows;

	# TODO: get last auditid
	my $last_auditlog_auditid = 0;

	# get unprocessed auditlog entries

	$sql = "
		select
			if(resourcetype = ?, resourceid, 0) as auditlog_eventid,
			count(*),
			max(auditid)
		from
			auditlog
		where
			auditid > ?
		group by
			auditlog_eventid
	";
	$params = [AUDIT_RESOURCE_INCIDENT, $last_auditlog_auditid];
	$rows = db_select($sql, $params);

	return if (scalar(@{$rows}) == 0);

	# get list of events.eventid (incidents) that changed their "false positive" state

	my @eventids = ();

	foreach my $row (@{$rows})
	{
		my ($eventid, $count, $max_auditid) = @{$row};

		$last_auditlog_auditid = $max_auditid if ($last_auditlog_auditid < $max_auditid);

		next if ($eventid == 0); # this is not AUDIT_RESOURCE_INCIDENT
		next if ($count % 2 == 0); # marked + unmarked, no need to recalculate

		push(@eventids, $eventid);
	}

	# NB! Don't save last auditid yet, if history needs to be altered! Altering history may fail!
	if (scalar(@eventids) == 0)
	{
		# TODO: save last auditid
		# ...
		return;
	}

	# get data about affected incidents

	my $eventids_placeholder = join(",", ("?") x scalar(@eventids));
	$sql = "
		select
			events.eventid,
			events.false_positive,
			events.clock,
			(
				select clock
				from events as events_inner
				where
					events_inner.source = events.source and
					events_inner.object = events.object and
					events_inner.objectid = events.objectid and
					events_inner.value = ? and
					events_inner.eventid > events.eventid
				order by events_inner.eventid asc
				limit 1
			) as clock2,
			function_items.hostid,
			function_items.itemid,
			function_items.key_
		from
			events
			left join (
				select distinct
					functions.triggerid,
					items.hostid,
					items.itemid,
					items.key_
				from
					functions
					left join items on items.itemid = functions.itemid
			) as function_items on function_items.triggerid = events.objectid
		where
			events.source = ? and
			events.object = ? and
			events.value = ? and
			events.eventid in ($eventids_placeholder)
		order by
			clock asc
	";
	$params = [TRIGGER_VALUE_FALSE, EVENT_SOURCE_TRIGGERS, EVENT_OBJECT_TRIGGER, TRIGGER_VALUE_TRUE, @eventids];
	$rows = db_select($sql, $params);

	my $requested_rows = scalar(@eventids);
	my $returned_rows  = scalar(@{$rows});
	if ($returned_rows != $requested_rows)
	{
		# some hints for debugging:
		# * $rows aren't filtered by $item_key_avail yet, right?
		# * function_items got more than 1 row for a trigger?
		# * events.source, events.object or events.value in DB has unexpected value?
		fail("mismatch between numbers of requested rows ($requested_rows) and returned rows ($returned_rows)");
	}

	# mapping between "rsm.slv.xxx.avail" and "rsm.slv.xxx.downtime" items
	# $downtime_itemids{$itemid_avail} = $itemid_downtime;
	my %downtime_itemids = ();

	# ranges of "false positive" availability values; these ranges start before incident actually started
	# $false_positives{$itemid_avail} = [[$from, $till], ...]
	my %false_positives = ();

	# periods that have to be recalculated; downtime for each month has to be recalculated till the end of the month
	# $periods{$itemid_avail} = {$month_start_1 => $from_1, $month_start_2 => $from_2, ...}
	my %periods = ();

	foreach my $row (@{$rows})
	{
		my ($eventid, $false_positive, $from, $till, $hostid, $itemid_avail, $key) = @{$row};

		if ($key ne $item_key_avail)
		{
			dbg("skipping incident $eventid (\$item_key_avail = '$item_key_avail', \$key = '$key')");
			next;
		}

		if (!exists($downtime_itemids{$itemid_avail}))
		{
			$sql = "select itemid from items where hostid = ? and key_ = ?";
			$downtime_itemids{$itemid_avail} = db_select_value($sql, [$hostid, $item_key_downtime]);
		}

		if ($false_positive)
		{
			push(@{$false_positives{$itemid_avail}}, [$from - $delay * ($threshold - 1), $till]);
		}

		while ($from <= $till)
		{
			my ($month_start, $month_end) = get_month_bounds($from);

			if (!exists($periods{$itemid_avail}{$month_start}) || $from < $periods{$itemid_avail}{$month_start})
			{
				$periods{$itemid_avail}{$month_start} = $from;
			}

			$from = cycle_end($month_end + $delay, $delay);
		}
	}

	foreach my $itemid_avail (keys(%periods))
	{
		foreach my $from (values(%{$periods{$itemid_avail}}))
		{
			my ($month_from, $month_till) = get_month_bounds($from);
			my $till = cycle_start($month_till, $delay);

			# NB! Even if this is beginning of the month, we have to make sure that we have data from the
			# beginning of the period that has to be recalculated.

			$sql = "select value from history_uint where itemid = ? and clock = ?";
			$rows = db_select($sql, [$downtime_itemids{$itemid_avail}, cycle_start($from - $delay, $delay)]);

			if (scalar @{$rows}) > 1)
			{
				fail("got more than one history entry");
			}

			if (scalar(@{$rows}) == 0)
			{
				#wrn("skipping incident $eventid (cannot alter history, false-positive flag changed for a too old incident)");
				next;
			}

			my $downtime_value = $rows->[0][0];

			if (cycle_start($from, $delay) == cycle_start($month_from, $delay))
			{
				$downtime_value = 0;
			}

			$sql = "select clock, value from history_uint where itemid = ? and clock between ? and ? order by clock asc";
			$rows = db_select($sql, [$itemid_avail, $from - $delay * ($threshold - 1), $till]);

			my %avail = map { $_->[0] => $_->[1] } @{$rows};

			# $false_positives{$itemid_avail} = [[$from, $till], ...]
			foreach my $false_positive (@{$false_positives{$itemid_avail}})
			{
				for (my $clock = $false_positive->[0]; $clock <= $false_positive->[1]; $clock += $delay)
				{
					printf("%d = %d\n", $clock, $avail{$clock});
				}
				print "\n";
			}

			my %downtime = ();
		}








		#my ($from_month, undef) = get_month_bounds($from);
		#my ($till_month, undef) = get_month_bounds($till);






		# $false_positive from $started till $ended
		# group by $triggerid
		# split $started and $ended into months, if needed

		#...;


		# TODO: update lastvalue, if necessary
		#$sql = "
		#	update
		#		lastvalue
		#		inner join history_uint on history_uint.itemid = lastvalue.itemid and history_uint.clock = lastvalue.clock
		#	set lastvalue.value = history_uint.value
		#	where lastvalue.itemid = ?
		#";
		#$params = [$itemid];
	}

	# TODO: save last auditid
	# ...
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
		$stdout = 0 unless (opt('debug') || opt('dry-run'));
		$priority = 'ERR';
	}
	elsif ($syslog_priority eq 'warning')
	{
		$stdout = 0 unless (opt('debug') || opt('dry-run'));
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

sub __reset_stats
{
	$start_time = Time::HiRes::time();
	$sql_time = 0.0;
	$sql_count = 0;
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

	while (1)
	{
		my $rows_ref = db_select(
			"select value".
			" from history_uint".
			" where itemid=$itemid".
				" and " . sql_time_condition($value_time - $diff, $value_time).
			" order by clock desc".
			" limit 1"
		);

		foreach my $row_ref (@$rows_ref)
		{
			return $row_ref->[0];
		}

		# no more attempts
		return if ($diff == $month);

		# try bigger period
		$diff = $month if ($diff == $day);
		$diff = $day if ($diff == $hour);
	}
}

1;
