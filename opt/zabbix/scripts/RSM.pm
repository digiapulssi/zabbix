package RSM;

use strict;
use warnings;
use Config::Tiny;
use File::Path qw(make_path remove_tree);
use base 'Exporter';

our @EXPORT = qw(get_rsm_config get_rsm_server_keys get_rsm_server_key get_rsm_local_key get_rsm_local_id
		rsm_targets_prepare rsm_targets_copy);

use constant RSM_SERVER_KEY_PREFIX => 'server_';
use constant RSM_DEFAULT_CONFIG_FILE => '/opt/zabbix/scripts/rsm.conf';

my ($_TARGET_DIR, $_TMP_DIR);

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

# todo phase 1: taken from ApiHelper:__system
sub __system
{
	my $cmd = join('', @_);

	system($cmd);

	if ($? == -1)
	{
		return "failed to execute: $!";
	}

	if ($? & 127)
	{
		return sprintf("child died with signal %d, %s coredump", ($? & 127),  ($? & 128) ? 'with' : 'without');
	}

	return undef;
}

# todo phase 1: this was made based on ApiHelper:ah_begin, which must be removed in phase 2
sub rsm_targets_copy($$)
{
	my $tmp_dir = shift;
	my $target_dir = shift;

	my $strip_components = () = $tmp_dir =~ /\//g;

	return __system('tar -cf - ', $tmp_dir, ' 2>/dev/null | tar --ignore-command-error -C ', $target_dir, ' --strip-components=', $strip_components, ' -xf -');
}

# todo phase 1: this was made based on ApiHelper:ah_end, which must be removed in phase 2
sub rsm_targets_prepare($$)
{
	my $tmp_dir = shift;
	my $target_dir = shift;

	my $err;

	if (-d $tmp_dir)
	{
		remove_tree($tmp_dir, {keep_root => 1, error => \$err});

		if (@$err)
		{
			return "cannot empty temporary directory " . __get_file_error($err);
		}
	}
	else
	{
		remove_tree($tmp_dir, {error => \$err});

		if (@$err)
		{
			return "cannot delete temporary directory " . __get_file_error($err);
		}

		make_path($tmp_dir, {error => \$err});

		if (@$err)
		{
			return "cannot create temporary directory " . __get_file_error($err);
		}
	}

	if (-f $target_dir)
	{
		if (!unlink($target_dir))
		{
			return __get_file_error($!);
		}
	}

	make_path($target_dir, {error => \$err});

	if (@$err)
	{
		return "cannot create target directory " . __get_file_error($err);
	}

	return undef;
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

			return $error_string;
		}
	}

	return join('', $err, @_);
}

1;
