#!/usr/bin/perl

BEGIN
{
	our $MYDIR = $0; $MYDIR =~ s,(.*)/.*,$1,; $MYDIR = '.' if ($MYDIR eq $0);
}
use lib $MYDIR;

use strict;
use warnings;

use JSON::XS qw(decode_json encode_json);
use Path::Tiny qw(path);
use ApiHelper;
use RSM;

use constant JSON_OBJECT_DISABLED_SERVICE => {
	'status'	=> 'Disabled'
};

sub fail
{
	print('Error: ', join('', @_));
	exit(-1);
}

my $now = time();

if (my $error = rsm_targets_prepare(AH_TMP_DIR, AH_BASE_DIR))
{
	fail($error);
}

foreach my $tld_dir (path(AH_BASE_DIR)->children)
{
	next unless ($tld_dir->is_dir());

	my $tld = $tld_dir->basename();

	my $json;

	next unless (ah_state_file_json($tld, \$json) == AH_SUCCESS);

	$json->{'status'} = 'Up (inconclusive)';
	$json->{'testedServices'} = {
		'DNS'		=> JSON_OBJECT_DISABLED_SERVICE,
		'DNSSEC'	=> JSON_OBJECT_DISABLED_SERVICE,
		'EPP'		=> JSON_OBJECT_DISABLED_SERVICE,
		'RDDS'		=> JSON_OBJECT_DISABLED_SERVICE
	};

	if (ah_save_state($tld, $json) != AH_SUCCESS)
	{
		fail("cannot set \"$tld\" state: ", ah_get_error());
	}
}

if (my $error = rsm_targets_apply())
{
	fail($error);
}
