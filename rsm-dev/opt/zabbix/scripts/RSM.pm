package RSM;

use strict;
use warnings;
use Config::Tiny;
use base 'Exporter';

our @EXPORT = qw(get_rsm_config get_rsm_server_keys get_rsm_server_key);

use constant RSM_SERVER_KEY_PREFIX => 'server_';
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

sub get_rsm_server_keys
{
	my $config = shift;

	my @keys;

	foreach my $key (sort(keys(%{$config})))
	{
		push(@keys, $key) if ($key =~ /^${\(RSM_SERVER_KEY_PREFIX)}([0-9]+)$/)
	}

	return @keys;
}

sub get_rsm_server_key
{
	my $server_id = shift;

	my (undef, $file, $line) = caller();

	die("Internal error: function get_rsm_server_key() needs a parameter ($file:$line)") unless ($server_id);

	return RSM_SERVER_KEY_PREFIX . $server_id;
}

1;
