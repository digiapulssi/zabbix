#!/usr/bin/perl
#
# EPP availability

use lib '/opt/zabbix/scripts';

use strict;
use warnings;
use RSM;
use RSMSLV;

my $cfg_key_in = 'rsm.epp[{$RSM.TLD}';
my $cfg_key_out = 'rsm.slv.epp.avail';

parse_avail_opts();
exit_if_running();

set_slv_config(get_rsm_config());

db_connect();

my $interval = get_macro_epp_delay();
my $cfg_minonline = get_macro_epp_probe_online();
my $probe_avail_limit = get_macro_probe_avail_limit();

my $clock = (opt('from') ? getopt('from') : time());
my $period = (opt('period') ? getopt('period') : 1);

my $tlds_ref = get_tlds('EPP');

while ($period > 0)
{
	my ($from, $till, $value_ts) = get_interval_bounds($interval, $clock);

	$period -= $interval / 60;
	$clock += $interval;

	my $probes_ref = get_online_probes($from, $till, $probe_avail_limit, undef);

	init_values();

	foreach (@$tlds_ref)
	{
		$tld = $_;

		my $lastclock = get_lastclock($tld, $cfg_key_out);
		fail("configuration error: item \"$cfg_key_out\" not found at host \"$tld\"") if ($lastclock == E_FAIL);
		next if (check_lastclock($lastclock, $value_ts, $interval) != SUCCESS);

		process_slv_avail($tld, $cfg_key_in, $cfg_key_out, $from, $till, $value_ts, $cfg_minonline,
			$probe_avail_limit, $probes_ref, \&check_item_values);
	}

	# unset TLD (for the logs)
	$tld = undef;

	send_values();
}

slv_exit(SUCCESS);

# SUCCESS - no values or at least one successful value
# E_FAIL  - all values unsuccessful
sub check_item_values
{
	my $values_ref = shift;

	return SUCCESS if (scalar(@$values_ref) == 0);

	foreach (@$values_ref)
	{
		return SUCCESS if ($_ == UP);
	}

	return E_FAIL;
}
