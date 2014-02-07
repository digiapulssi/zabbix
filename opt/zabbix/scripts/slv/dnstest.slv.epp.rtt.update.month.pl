#!/usr/bin/perl -w
#
# EPP update-command monthly resolution RTT

use lib '/opt/zabbix/scripts';

use DNSTest;
use DNSTestSLV;

my $cfg_key_in = 'dnstest.epp.rtt[{$DNSTEST.TLD},update]';
my $cfg_key_out = 'dnstest.slv.epp.rtt.update.month';

parse_opts();
exit_if_running();

set_slv_config(get_dnstest_config());

my ($from, $till, $value_ts) = get_month_bounds();

my $interval = $till + 1 - $from;

db_connect();

my $cfg_max_value = get_macro_epp_rtt('update');
my $cfg_delay = get_macro_epp_delay();

my $tlds_ref = get_tlds();

init_values();

foreach (@$tlds_ref)
{
    $tld = $_;

    next if (check_lastclock($tld, $cfg_key_out, $value_ts, $interval) != SUCCESS);

    process_slv_monthly($tld, $cfg_key_in, $cfg_key_out, $from, $till, $value_ts, $cfg_delay, \&check_item_value, MIN_UPDATE_ERROR, MAX_UPDATE_ERROR);
}

send_values();

slv_exit(SUCCESS);

sub check_item_value
{
    my $value = shift;

    return (is_service_error($value) == SUCCESS or $value > RTT_LIMIT_MULTIPLIER * $cfg_max_value) ? FAIL : SUCCESS;
}
