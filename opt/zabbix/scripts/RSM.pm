package RSM;

use strict;
use warnings;
use Config::Tiny;
use base 'Exporter';

our @EXPORT = qw(get_rsm_config get_rsm_config_db_keys);

use constant RSM_DEFAULT_CONFIG_FILE => '/opt/zabbix/scripts/rsm.conf';

sub get_rsm_config
{
	my $config_file = shift;

	$config_file = RSM_DEFAULT_CONFIG_FILE unless ($config_file);

	my $config = Config::Tiny->new;

	$config = Config::Tiny->read($config_file);

	unless (defined($config))
	{
		print STDERR (Config::Tiny->errstr(), "\n");
		exit(-1);
	}

	return $config;
}

sub get_rsm_config_db_keys
{
	my $config = shift;

	my @keys;

	foreach my $key (sort(keys(%{$config})))
	{
		push(@keys, $key) if ($key =~ /^db_/);
	}

	return @keys;
}

1;
