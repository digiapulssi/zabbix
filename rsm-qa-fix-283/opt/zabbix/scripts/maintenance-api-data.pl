#!/usr/bin/perl -w

use strict;
use warnings;
use JSON::XS qw(decode_json encode_json);
use Path::Tiny qw(path);

use constant JSON_OBJECT_DISABLED_SERVICE => {
	'status'	=> 'Disabled'
};

my $now = time();

foreach my $tld_dir (path('/opt/zabbix/sla')->children)
{
	next unless ($tld_dir->is_dir());

	my $state_file = $tld_dir->child('state');

	next unless ($state_file->exists() && $state_file->is_file());

	my $json = decode_json($state_file->slurp_utf8);

	$json->{'status'} = 'Up-inconclusive';
	$json->{'testedServices'} = {
		'DNS'		=> JSON_OBJECT_DISABLED_SERVICE,
		'DNSSEC'	=> JSON_OBJECT_DISABLED_SERVICE,
		'EPP'		=> JSON_OBJECT_DISABLED_SERVICE,
		'RDDS'		=> JSON_OBJECT_DISABLED_SERVICE
	};
	$json->{'lastUpdateApiDatabase'} = $now;

	$state_file->spew(encode_json($json));
}

