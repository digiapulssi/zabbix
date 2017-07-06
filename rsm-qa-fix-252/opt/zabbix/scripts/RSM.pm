package RSM;

use strict;
use warnings;
use Config::Tiny;
use File::Path qw(make_path remove_tree);
use base 'Exporter';

our @EXPORT = qw(get_rsm_config get_rsm_server_keys get_rsm_server_key get_rsm_server_id get_rsm_local_key
		get_rsm_local_id rsm_targets_prepare rsm_targets_apply rsm_targets_delete get_db_tls_settings);

use constant RSM_SERVER_KEY_PREFIX => 'server_';
use constant RSM_DEFAULT_CONFIG_FILE => '/opt/zabbix/scripts/rsm.conf';

my ($_TARGET_DIR, $_TMP_DIR, %_TO_DELETE);

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

sub get_rsm_server_id
{
	my $server_id = shift;

	$server_id =~ s/${\RSM_SERVER_KEY_PREFIX}//;

	return $server_id;
}

sub get_rsm_local_key
{
	my $config = shift;

	die("Internal error: no configuration passed to function get_rsm_local_key()") unless ($config);
	die("Configuration error: no \"local\" server defined") unless ($config->{'_'}->{'local'});

	return $config->{'_'}->{'local'};
}

sub get_rsm_local_id
{
	my $config = shift;

	die("Internal error: no configuration passed to function get_rsm_local_key()") unless ($config);
	die("Configuration error: no \"local\" server defined") unless ($config->{'_'}->{'local'});

	my $id = $config->{'_'}->{'local'};

	$id =~ s/^${\(RSM_SERVER_KEY_PREFIX)}//;

	return $id;
}

# todo phase 1: taken from ApiHelper:__system, should be removed from there in phase 2
sub __system
{
	my $cmd = join('', @_);

	my $rv = system($cmd);

	if ($rv == -1)
	{
		return "cannot execute command [$cmd]: $!";
	}

	if ($rv & 127)
	{
		return sprintf("cannot execute command [$cmd], child died with signal %d, %s coredump", ($rv & 127),  ($rv & 128) ? 'with' : 'without');
	}

	return undef;
}

# todo phase 1: this was made based on ApiHelper:ah_begin, which must be removed in phase 2
sub rsm_targets_apply()
{
	my $strip_components = () = $_TMP_DIR =~ /\//g;

	my $error = __system('tar -cf - ', $_TMP_DIR, ' 2>/dev/null | tar --ignore-command-error -C ', $_TARGET_DIR, ' --strip-components=', $strip_components, ' -xf -');

	return $error if ($error);

	foreach my $file (keys(%_TO_DELETE))
	{
		my $target_file = $_TARGET_DIR . "/" . $file;

		if (-f $target_file)
		{
			if (!unlink($target_file))
			{
				return __get_file_error($!);
			}
		}
	}
}

# todo phase 1: this was made based on ApiHelper:ah_end, which must be removed in phase 2
sub rsm_targets_prepare($$)
{
	$_TMP_DIR = shift;
	$_TARGET_DIR = shift;

	my $err;

	if (-d $_TMP_DIR)
	{
		remove_tree($_TMP_DIR, {keep_root => 1, error => \$err});

		if (@$err)
		{
			return "cannot empty temporary directory " . __get_file_error($err);
		}
	}
	else
	{
		remove_tree($_TMP_DIR, {error => \$err});

		if (@$err)
		{
			return "cannot delete temporary directory " . __get_file_error($err);
		}

		make_path($_TMP_DIR, {error => \$err});

		if (@$err)
		{
			return "cannot create temporary directory " . __get_file_error($err);
		}
	}

	if (-f $_TARGET_DIR)
	{
		if (!unlink($_TARGET_DIR))
		{
			return __get_file_error($!);
		}
	}

	make_path($_TARGET_DIR, {error => \$err});

	if (@$err)
	{
		return "cannot create target directory " . __get_file_error($err);
	}

	return undef;
}

# todo phase 1: new function
sub rsm_targets_delete($)
{
	my $file = shift;	# file to delete from target

	$_TO_DELETE{$file} = undef;	# use hash instead of array to avoid duplicates
}

# todo phase 1: this was taken from ApiHelper::__set_file_error, it must be decided what to do with 2 identical functions like that in phase 2
sub __get_file_error
{
	my $err = shift;

	my $error_string = "";

	if (ref($err) eq "ARRAY")
	{
		for my $diag (@$err)
		{
			my ($file, $message) = %$diag;
			if ($file eq '')
			{
				$error_string .= "$message. ";
			}
			else
			{
				$error_string .= "$file: $message. ";
			}
		}

		return $error_string;
	}

	return join('', $err, @_);
}

# mapping between configuration file parameters and MySQL driver options
my %mapping = (
	'db_key_file'	=> 'mysql_ssl_client_key',
	'db_cert_file'	=> 'mysql_ssl_client_cert',
	'db_ca_file'	=> 'mysql_ssl_ca_file',
	'db_ca_path'	=> 'mysql_ssl_ca_path',
	'db_cipher'	=> 'mysql_ssl_cipher'
);

# reads database TLS settings from configuration file section
sub get_db_tls_settings($)
{
	my $section = shift;

	my $db_tls_settings = "";

	while (my ($config_param, $mysql_param) = each(%mapping))
	{
		$db_tls_settings .= ";$mysql_param=$section->{$config_param}" if (exists($section->{$config_param}));
	}

	return $db_tls_settings eq "" ? "mysql_ssl=0" : "mysql_ssl=1$db_tls_settings";
}

1;
