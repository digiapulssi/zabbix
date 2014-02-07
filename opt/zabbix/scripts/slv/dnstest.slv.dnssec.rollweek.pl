#!/usr/bin/perl -w
#
# DNSSEC rolling week

use lib '/opt/zabbix/scripts';

use DNSTest;
use DNSTestSLV;

my $cfg_key_in = 'dnstest.slv.dnssec.avail';
my $cfg_key_out = 'dnstest.slv.dnssec.rollweek';

parse_opts();
exit_if_running();

set_slv_config(get_dnstest_config());

db_connect();

my $interval = get_macro_dns_udp_delay();
my $cfg_sla = get_macro_dns_rollweek_sla();

my ($from, $till, $value_ts) = get_rollweek_bounds();

my $tlds_ref = get_tlds();

init_values();

foreach (@$tlds_ref)
{
    $tld = $_;

    my ($itemid_in, $itemid_out, $lastclock) = get_rollweek_data($tld, $cfg_key_in, $cfg_key_out);
    next if (check_lastclock($lastclock, $value_ts, $interval) != SUCCESS);

    my $fails = get_down_count($itemid_in, $itemid_out, $from, $till);
    my $perc = sprintf("%.3f", $fails * 100 / $cfg_sla);

    info("fails:$fails perc:$perc");
    push_value($tld, $cfg_key_out, $value_ts, $perc);
}

send_values();

slv_exit(SUCCESS);
