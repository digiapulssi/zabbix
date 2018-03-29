#!/usr/bin/perl

BEGIN
{
	our $MYDIR = $0; $MYDIR =~ s,(.*)/.*,$1,; $MYDIR = '.' if ($MYDIR eq $0);
}
use lib $MYDIR;

use strict;
use warnings;

use Path::Tiny qw(path);
use ApiHelper;
use RSM;

my $error = rsm_targets_prepare(AH_TMP_DIR, AH_BASE_DIR);

die($error) if ($error);

foreach my $tld_dir (path(AH_BASE_DIR)->children)
{
	next unless ($tld_dir->is_dir());

	my $tld = $tld_dir->basename();

	my $json;

	die("cannot read \"$tld\" state: ", ah_get_error()) unless (ah_state_file_json($tld, \$json) == AH_SUCCESS);

	$json->{'status'} = 'Up-inconclusive';
	$json->{'testedServices'} = {
		'DNS'		=> JSON_OBJECT_DISABLED_SERVICE,
		'DNSSEC'	=> JSON_OBJECT_DISABLED_SERVICE,
		'EPP'		=> JSON_OBJECT_DISABLED_SERVICE,
		'RDDS'		=> JSON_OBJECT_DISABLED_SERVICE
	};

	die("cannot set \"$tld\" state: ", ah_get_error()) unless (ah_save_state($tld, $json) == AH_SUCCESS);
}

$error = rsm_targets_apply();

die($error) if ($error);
