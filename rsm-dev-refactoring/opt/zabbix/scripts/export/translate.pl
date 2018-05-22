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

use File::Basename;
use DaWa;
use RSM;
use RSMSLV;

use constant PRINT_RIGHT_SHIFT => 30;

parse_opts();

my $data_file = $ARGV[0];
my $data_file_line = $ARGV[1];

my $data_type;

__usage("number of arguments must be 1 or 2") if (scalar(@ARGV) lt 1 || scalar(@ARGV) gt 2);
__usage("file \"$data_file\" not found") if (! -f $data_file);

$data_type = basename($data_file);

my $found = 0;

foreach my $id_type (keys(%DATAFILES))
{
	if ($DATAFILES{$id_type} eq $data_type)
	{
		$found = 1;
		last;
	}
}

__usage("unsupported file: \"$data_type\"") if ($found == 0);

sub __usage
{
	print("Error: ", join('', @_), "\n\n") if (@_);

	print <<EOF;
usage: $0 <csv file> <line>

example: $0 /opt/zabbix/export/2017/02/22/tld1/cycles.csv
EOF

	exit(-1);
}

set_slv_config(get_rsm_config());
db_connect();

my %valuemaps;

$valuemaps{'dns'} = get_valuemaps('dns');
$valuemaps{'rdds43'} = $valuemaps{'rdds80'} = get_valuemaps('rdds');

dw_csv_init();
dw_load_ids_from_db();

my $fh;
open($fh, '<', $data_file) or die $!;

my $line;

while ($line = <$fh>)
{
	next if (defined($data_file_line) && $. != $data_file_line);

	printf("%6d: %s", $., $line);

	##### TODO: USE MAPPINGS!!!! ######
	if ($data_type eq 'cycles.csv')
	{
		__translate_cycles_line($line);
	}
	elsif ($data_type eq 'tests.csv')
	{
		__translate_tests_line($line);
	}
	elsif ($data_type eq 'nsTests.csv')
	{
		__translate_ns_tests_line($line);
	}
	elsif ($data_type eq 'incidents.csv')
	{
		__translate_incidents_line($line);
	}
	elsif ($data_type eq 'incidentsEndTime.csv')
	{
		__translate_incidents_end_time_line($line);
	}
	elsif ($data_type eq 'probeChanges.csv')
	{
		__translate_probe_changes_line($line);
	}
	else
	{
		__usage("\"$data_type\" is not supported yet, currently supported are cycles.csv, tests.csv, incidents.csv, incidentsEndTime.csv");
	}
}

close($fh);

db_disconnect();

sub __translate_cycles_line
{
	my $line = shift;

	my @columns = split(',', $line);

	my $cycle_id = dw_translate_cycle_id($columns[0]);
	my $cycle_date_minute = $columns[1];
	my $cycle_emergency_threshold = $columns[2];
	my $cycle_status = dw_get_name(ID_STATUS_MAP, $columns[3]);
	my $incident_id = $columns[4];
	my $cycle_tld = dw_get_name(ID_TLD, $columns[5]);
	my $service_category = dw_get_name(ID_SERVICE_CATEGORY, $columns[6]);
	my $cycle_nsfqdn = dw_get_name(ID_NS_NAME, $columns[7]) || '';
	my $cycle_nsip = dw_get_name(ID_NS_IP, $columns[8]) || '';
	my $cycle_nsipversion = dw_get_name(ID_IP_VERSION, $columns[9]) || '';
	my $tld_type = dw_get_name(ID_TLD_TYPE, $columns[10]);
	my $cycle_protocol = dw_get_name(ID_TRANSPORT_PROTOCOL, $columns[11]);

	printf("%-" . PRINT_RIGHT_SHIFT . "s%s\n", 'cycleID', $cycle_id);
	printf("%-" . PRINT_RIGHT_SHIFT . "s%s\n", 'cycleDateMinute', ts_full($cycle_date_minute));
	printf("%-" . PRINT_RIGHT_SHIFT . "s%s\n", 'cycleEmergencyThreshold', $cycle_emergency_threshold);
	printf("%-" . PRINT_RIGHT_SHIFT . "s%s (%s)\n", 'cycleStatus', $cycle_status, $columns[3]);
	printf("%-" . PRINT_RIGHT_SHIFT . "s%s\n", 'incidentID', $incident_id);
	printf("%-" . PRINT_RIGHT_SHIFT . "s%s\n", 'cycleTLD', $cycle_tld);
	printf("%-" . PRINT_RIGHT_SHIFT . "s%s\n", 'serviceCategory', $service_category);
	printf("%-" . PRINT_RIGHT_SHIFT . "s%s\n", 'cycleNSFQDN', $cycle_nsfqdn);
	printf("%-" . PRINT_RIGHT_SHIFT . "s%s\n", 'cycleNSIP', $cycle_nsip);
	printf("%-" . PRINT_RIGHT_SHIFT . "s%s\n", 'cycleNSIPVersion', $cycle_nsipversion);
	printf("%-" . PRINT_RIGHT_SHIFT . "s%s\n", 'tldType', $tld_type);
	printf("%-" . PRINT_RIGHT_SHIFT . "s%s\n", 'cycleProtocol', $cycle_protocol);
}

sub __translate_tests_line
{
	my $line = shift;

	my @columns = split(',', $line);

	my $probe_name = dw_get_name(ID_PROBE, $columns[0]);
	my $cycle_date_minute = $columns[1];
	my $test_date_time = $columns[2];
	my $test_rtt = $columns[3];
	my $cycle_id = dw_translate_cycle_id($columns[4]);
	my $test_tld = dw_get_name(ID_TLD, $columns[5]);
	my $test_protocol = dw_get_name(ID_TRANSPORT_PROTOCOL, $columns[6]);
	my $test_ipversion = dw_get_name(ID_IP_VERSION, $columns[7]) || '';
	my $test_ipaddress = dw_get_name(ID_NS_IP, $columns[8]) || '';
	my $test_type = dw_get_name(ID_TEST_TYPE, $columns[9]);
	my $test_nsfqdn = dw_get_name(ID_NS_NAME, $columns[10]) || '';
	my $tld_type = dw_get_name(ID_TLD_TYPE, $columns[11]);

	if ($valuemaps{$test_type}->{$test_rtt})
	{
		$test_rtt .= " (" . $valuemaps{$test_type}->{$test_rtt} . ")";
	}
	elsif ($test_rtt >= 0)
	{
		$test_rtt .= " ms";
	}

	printf("%-" . PRINT_RIGHT_SHIFT . "s%s\n", 'probeName', $probe_name);
	printf("%-" . PRINT_RIGHT_SHIFT . "s%s\n", 'cycleDateMinute', ts_full($cycle_date_minute));
	printf("%-" . PRINT_RIGHT_SHIFT . "s%s\n", 'testDateTime', ts_full($test_date_time));
	printf("%-" . PRINT_RIGHT_SHIFT . "s%s\n", 'testRTT', $test_rtt);
	printf("%-" . PRINT_RIGHT_SHIFT . "s%s\n", 'cycleID', $cycle_id);
	printf("%-" . PRINT_RIGHT_SHIFT . "s%s\n", 'testTLD', $test_tld);
	printf("%-" . PRINT_RIGHT_SHIFT . "s%s\n", 'testProtocol', $test_protocol);
	printf("%-" . PRINT_RIGHT_SHIFT . "s%s\n", 'testIPVersion', $test_ipversion);
	printf("%-" . PRINT_RIGHT_SHIFT . "s%s\n", 'testIPAddress', $test_ipaddress);
	printf("%-" . PRINT_RIGHT_SHIFT . "s%s\n", 'testType', $test_type);
	printf("%-" . PRINT_RIGHT_SHIFT . "s%s\n", 'testNSFQDN', $test_nsfqdn);
	printf("%-" . PRINT_RIGHT_SHIFT . "s%s\n", 'tldType', $tld_type);
}

sub __translate_ns_tests_line
{
	my $line = shift;

	my @columns = split(',', $line);

	my $probe_name = dw_get_name(ID_PROBE, $columns[0]);
	my $ns_fqdn = dw_get_name(ID_NS_NAME, $columns[1]);
	my $ns_test_tld = dw_get_name(ID_TLD, $columns[2]);
	my $cycle_date_minute = $columns[3];
	my $ns_test_status = dw_get_name(ID_STATUS_MAP, $columns[4]);
	my $tld_type = dw_get_name(ID_TLD_TYPE, $columns[5]);
	my $ns_test_protocol = dw_get_name(ID_TRANSPORT_PROTOCOL, $columns[6]);

	printf("%-" . PRINT_RIGHT_SHIFT . "s%s\n", 'probeName', $probe_name);
	printf("%-" . PRINT_RIGHT_SHIFT . "s%s\n", 'nsFQDN', $ns_fqdn);
	printf("%-" . PRINT_RIGHT_SHIFT . "s%s\n", 'nsTestTLD', $ns_test_tld);
	printf("%-" . PRINT_RIGHT_SHIFT . "s%s\n", 'cycleDateMinute', ts_full($cycle_date_minute));
	printf("%-" . PRINT_RIGHT_SHIFT . "s%s (%s)\n", 'nsTestStatus', $ns_test_status, $columns[2]);
	printf("%-" . PRINT_RIGHT_SHIFT . "s%s\n", 'tldType', $tld_type);
	printf("%-" . PRINT_RIGHT_SHIFT . "s%s\n", 'testProtocol', $ns_test_protocol);
}

sub __translate_incidents_line
{
	my $line = shift;

	my @columns = split(',', $line);

	my $incident_id = $columns[0];
	my $incident_start_time = $columns[1];
	my $incident_tld = dw_get_name(ID_TLD, $columns[2]);
	my $service_category = dw_get_name(ID_SERVICE_CATEGORY, $columns[3]);
	my $tld_type = dw_get_name(ID_TLD_TYPE, $columns[4]);

	printf("%-" . PRINT_RIGHT_SHIFT . "s%s\n", 'incidentID', $incident_id);
	printf("%-" . PRINT_RIGHT_SHIFT . "s%s\n", 'incidentStartTime', ts_full($incident_start_time));
	printf("%-" . PRINT_RIGHT_SHIFT . "s%s\n", 'incidentTLD', $incident_tld);
	printf("%-" . PRINT_RIGHT_SHIFT . "s%s\n", 'serviceCategory', $service_category);
	printf("%-" . PRINT_RIGHT_SHIFT . "s%s\n", 'tldType', $tld_type);
}

sub __translate_incidents_end_time_line
{
	my $line = shift;

	my @columns = split(',', $line);

	my $incident_id = $columns[0];
	my $incident_end_time = $columns[1];
	my $incident_failed_tests = $columns[2];

	printf("%-" . PRINT_RIGHT_SHIFT . "s%s\n", 'incidentID', $incident_id);
	printf("%-" . PRINT_RIGHT_SHIFT . "s%s\n", 'incidentEndTime', ts_full($incident_end_time));
	printf("%-" . PRINT_RIGHT_SHIFT . "s%s\n", 'incidentFailedTests', $incident_failed_tests);
}

sub __translate_probe_changes_line
{
	my $line = shift;

	my @columns = split(',', $line);

	my $probe_name = dw_get_name(ID_PROBE, $columns[0]);
	my $probe_change_date_time = $columns[1];
	my $probe_status = dw_get_name(ID_STATUS_MAP, $columns[2]);

	printf("%-" . PRINT_RIGHT_SHIFT . "s%s\n", 'probeName', $probe_name);
	printf("%-" . PRINT_RIGHT_SHIFT . "s%s\n", 'probeChangeDateTime', ts_full($probe_change_date_time));
	printf("%-" . PRINT_RIGHT_SHIFT . "s%s\n", 'probeStatus', $probe_status);
}
