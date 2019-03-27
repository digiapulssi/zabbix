#!/usr/bin/perl
#
# Minutes of DNS downtime during running month for particular nameservers

BEGIN { our $MYDIR = $0; $MYDIR =~ s,(.*)/.*/.*,$1,; $MYDIR = '..' if ($MYDIR eq $0); }
use lib $MYDIR;
use strict;
use warnings;
use RSM;
use RSMSLV;
use TLD_constants qw(:groups :api);
use Data::Dumper;
use DateTime;

use constant MAX_CYCLES_TO_PROCESS => 5;

parse_slv_opts();
fail_if_running();
set_slv_config(get_rsm_config());
db_connect();

init_values();
process_values();
send_values();

slv_exit(SUCCESS);

sub process_values
{
}
