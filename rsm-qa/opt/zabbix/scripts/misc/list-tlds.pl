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
use RSM;
use RSMSLV;

parse_opts('verbose!', 'service=s', 'server-id=i');

setopt('nolog');
setopt('dry-run');

usage() if (opt('help'));

my $config = get_rsm_config();

set_slv_config($config);

my @server_keys;

if (opt('server-id'))
{
	push(@server_keys, get_rsm_server_key(getopt('server-id')));
}
else
{
	@server_keys = get_rsm_server_keys($config);
}

my $total_tlds = 0;

foreach (@server_keys)
{
	$server_key = $_;

	db_connect($server_key);

	my $tlds_ref = get_tlds(getopt('service'));

	my $tlds = scalar(@{$tlds_ref});

	$total_tlds += $tlds;

	if (opt('verbose'))
	{
		foreach my $tld (@{$tlds_ref})
		{
			print("    $tld\n");
		}
	}

	db_disconnect();

	print("  ") unless (opt('server-id'));
	print("$tlds TLDs");
	print(" with ", uc(getopt('service')), " enabled") if (opt('service'));
	print(" on $server_key\n");
}

unless (opt('server-id'))
{
	print("total $total_tlds TLDs");
	print(" with ", uc(getopt('service')), " enabled") if (opt('service'));
	print(" on " . scalar(@server_keys) . " servers\n");
}

__END__

=head1 NAME

list-tlds.pl - print information about number of TLDs in the system

=head1 SYNOPSIS

list-tlds.pl [--verbose] [--service <name>] [--server-id <id>] [--debug] [--help]

=head1 OPTIONS

=over 8

=item B<--verbose>

Additionally print TLD names when counting.

=item B<--service> name

Optionally specify service that must be enabled on TLD, one of: dns, dnssec, rdds, epp

=item B<--server-id>

Optionally specify the server ID to query the data only from it.

=item B<--debug>

Run the script in debug mode. This means printing more information.

=item B<--help>

Print a brief help message and exit.

=back

=head1 DESCRIPTION

B<This program> will count number of TLDs in the system.

=head1 EXAMPLES

./$0 --server-id 2

This will print number of TLDs configured on server 2.

=cut
