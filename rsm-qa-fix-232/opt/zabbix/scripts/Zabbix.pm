package Zabbix;
# by DotNeft with UTF-8 support

use strict;
use JSON::XS;
use Encode;
use Carp;
use LWP::UserAgent;
use LWP::Protocol::https;

use Data::Dumper;
$Data::Dumper::Sortkeys = 1;

require Exporter;
our @ISA = qw(Exporter);

@Zabbix::EXPORT = qw(new ua get remove update event_ack create exist massadd massremove);

BEGIN {
	$Zabbix::VERSION = '1.0';
	$Zabbix::DEBUG   = 0 unless (defined($Zabbix::DEBUG));
}

use constant true => 1;
use constant false => 0;

sub to_ascii($);
sub to_utf8($);

sub get_authid();
sub set_authid($);
sub delete_authid();

use constant _LOGIN_TIMEOUT => 5;

use constant _DEFAULT_REQUEST_TIMEOUT => 60;
use constant _DEFAULT_REQUEST_ATTEMPTS => 10;

my ($REQUEST_TIMEOUT, $REQUEST_ATTEMPTS, $AUTH_FILE, $DEBUG);

sub new($$)
{
	my ($class, $options) = @_;

	$DEBUG = $options->{'debug'};
	undef($options->{'debug'});

	my $ua = LWP::UserAgent->new();

#	$ua->ssl_opts(verify_hostname => 0);

	$REQUEST_TIMEOUT = (defined($options->{'request_timeout'}) ? $options->{'request_timeout'} :
			_DEFAULT_REQUEST_TIMEOUT);
	$REQUEST_ATTEMPTS = (defined($options->{'request_attempts'}) ? $options->{'request_attempts'} :
			_DEFAULT_REQUEST_ATTEMPTS);

	$ua->agent("Net::Zabbix");

	my $req = HTTP::Request->new(POST => $options->{'url'} . "/api_jsonrpc.php");

	$req->authorization_basic($options->{'user'}, $options->{'password'}) if ($options->{'auth_basic'});

	$req->content_type('application/json-rpc');

	croak("invalid URL: \"" . $options->{'url'} . "\"") unless ($options->{'url'} =~ qr|^https?\://(.+)/?$|);

	my $domain = $1;
	$domain =~ s,/,-,g;
	$AUTH_FILE = "/tmp/" . $domain . ".tmp";

	print("AUTH_FILE: $AUTH_FILE\n") if ($DEBUG);

	if (my $authid = get_authid())
	{
		print("Using previous authid: $authid\n") if ($DEBUG);

		$ua->timeout($REQUEST_TIMEOUT);

		my $self = {
			'UserAgent'	=> $ua,
			'request'	=> $req,
			'count'		=> 0,
			'auth'		=> $authid,
			'error'		=> undef,
		};

		croak("cannot get Zabbix API version information") unless (defined($self->api_version()));

		return bless($self, $class);
	}

	print("no authid in the file, logging in...\n") if ($DEBUG);

	my $request = encode_json({
		'jsonrpc'	=> "2.0",
		'method'	=> "user.login",
		'params'	=> {
			'user'		=> $options->{'user'},
			'password'	=> $options->{'password'},
		},
		'id'	=> 1,
	});

	my $result;

	my $login_attempts = 2;

	while ($login_attempts--)
	{
		print("REQUEST:\n", Dumper($request), "\n") if ($DEBUG);

		$req->content($request);

		$ua->timeout(_LOGIN_TIMEOUT);

		my $res = $ua->request($req);

		$ua->timeout($REQUEST_TIMEOUT);

		croak("cannot connect to Zabbix: " . $res->status_line) unless ($res->is_success);

		eval {$result = decode_json($res->content)};
		croak("Zabbix API returned invalid JSON: " . $@) if ($@);

		print("REPLY:\n", Dumper($result), "\n") if ($DEBUG);

		if (defined($result->{'error'}))
		{
			last unless ($result->{'error'}->{'data'} =~ /Session terminated/);
		}
	}

	if (defined($result->{'error'}))
	{
		croak("cannot connect to Zabbix: " . $result->{'error'}->{'message'} . " " .
				$result->{'error'}->{'data'});
	}

	my $auth = $result->{'result'};

	set_authid($auth);

	return bless({
		'UserAgent'	=> $ua,
		'request'	=> $req,
		'count'		=> 1,
		'auth'		=> $auth,
		'error'		=> undef,
	}, $class);
}

sub get_authid()
{
	my $authid;

	if (-e $AUTH_FILE)
	{
		open(TMP, '<', $AUTH_FILE);

		my @lines = <TMP>;

		close(TMP);

		$authid = shift(@lines);
	}

	return $authid;
}

sub set_authid($)
{
	my $authid = shift;

	open(TMP, '>', $AUTH_FILE) || print("cannot open file \"$AUTH_FILE\": $!\n");

	print TMP $authid;

	close(TMP);
}

sub delete_authid()
{
	unlink($AUTH_FILE);
}

sub ua
{
	return shift->{'UserAgent'};
}

sub req
{
	return shift->{'request'};
}

sub auth
{
	return shift->{'auth'};
}

sub next_id
{
	return ++shift->{'count'};
}

sub last_error
{
	return shift->{'error'};
}

sub set_last_error
{
	my ($self, $error) = @_;

	shift->{'error'} = $error;
}

sub api_version
{
	my ($self) = @_;

	return $self->__execute('apiinfo', 'version', {});
}

sub create
{
	my ($self, $class, $params) = @_;

	return $self->__execute($class, 'create', $params);
}

sub get
{
	my ($self, $class, $params) = @_;

	return $self->__fetch($class, 'get', $params);
}

sub remove
{
	my ($self, $class, $params) = @_;

	return $self->__execute($class, 'delete', $params);
}

sub update
{
	my ($self, $class, $params) = @_;

	return $self->__execute($class, 'update', $params);
}

# TODO consider deleting this unused method
sub objects
{
	my ($self, $class, $params) = @_;

	return $self->__fetch($class, 'getobjects', $params);
}

my $objectids = {
	'trigger'	=> 'triggerid',
	'item'		=> 'itemid',
	'host'		=> 'hostid',
	'template'	=> 'templateid',
	'hostgroup'	=> 'groupid',
	'application'	=> 'applicationid'
};

sub exist
{
	my ($self, $class, $params) = @_;

	$params->{'output'} = [$objectids->{$class}];

	return $self->__fetch_id($class, 'get', $params);
}

# TODO consider deleting this unused method
sub is_readable
{
	my ($self, $class, $params) = @_;

	return $self->__fetch_bool($class, 'isreadable', $params);
}

# TODO consider deleting this unused method
sub is_writeable
{
	my ($self, $class, $params) = @_;

	return $self->__fetch_bool($class, 'iswriteable', $params);
}

# TODO consider deleting this unused method
sub massadd
{
	my ($self, $class, $params) = @_;

	return $self->__execute($class, 'massAdd', $params);
}

# TODO consider deleting this unused method
sub massremove
{
	my ($self, $class, $params) = @_;

	return $self->__execute($class, 'massremove', $params);
}

sub massupdate
{
	my ($self, $class, $params) = @_;

	return $self->__execute($class, 'massupdate', $params);
}

# TODO consider deleting this unused method
sub event_ack
{
	my ($self, $params) = @_;

	return $self->__execute('event', 'acknowledge', $params);
}

#####################################

# TODO consider deleting this unused method
sub conf_export
{
	my ($self, $params) = @_;

	return $self->__fetch('configuration', 'export', $params);
}

# TODO consider deleting this unused method
sub conf_import
{
	my ($self, $params) = @_;

	die "Is not implemented yet!\n";
}

#####################################

# TODO consider deleting this unused method
sub replace_interfaces
{
	my ($self, $params) = @_;

	return $self->__execute('hostinterface', 'replacehostinterfaces', $params);
}

# TODO consider deleting this unused method
sub execute_script
{
	my ($self, $params) = @_;

	die "Is not implemented yet!\n";
}

sub trigger_dep_add
{
	my ($self, $params) = @_;

	return $self->__execute('trigger', 'adddependencies', $params);
}

# TODO consider deleting this unused method
sub trigger_dep_delete
{
	my ($self, $params) = @_;

	return $self->__execute('trigger', 'deleteDependencies', $params);
}

# TODO consider deleting this unused method
sub user_media_add
{
	my ($self, $params) = @_;

	return $self->__execute('user', 'addMedia', $params);
}

# TODO consider deleting this unused method
sub user_media_delete
{
	my ($self, $params) = @_;

	return $self->__execute('user', 'deleteMedia', $params);
}

# TODO consider deleting this unused method
sub user_media_update
{
	my ($self, $params) = @_;

	return $self->__execute('user', 'updateMedia', $params);
}

# TODO consider deleting this unused method
sub user_profile_update
{
	my ($self, $params) = @_;

	return $self->__execute('user', 'updateProfile', $params);
}

sub macro_global_create
{
	my ($self, $params) = @_;

	return $self->__execute('usermacro', 'createGlobal', $params);
}

# TODO consider deleting this unused method
sub macro_global_delete
{
	my ($self, $params) = @_;

	return $self->__execute('usermacro', 'deleteGlobal', $params);
}

sub macro_global_update
{
	my ($self, $params) = @_;

	return $self->__execute('usermacro', 'updateGlobal', $params);
}

#####################################

sub is_array($)
{
	my $ref = shift;

	return ref($ref) eq 'ARRAY';
}

sub is_hash($)
{
	my $ref = shift;

	return ref($ref) eq 'HASH';
}

sub to_ascii($)
{
	my $json = shift;

	if (is_hash($json))
	{
		foreach my $value (values(%{$json}))
		{
			$value = to_ascii($value);
		}
	}
	elsif (is_array($json))
	{
		foreach my $value (@{$json})
		{
			$value = to_ascii($value);
		}
	}
	else
	{
		$json = decode_utf8($json) if (utf8::valid($json));
	}

	return $json;
}

sub to_utf8($)
{
	my $json = shift;

	if (is_hash($json))
	{
		foreach my $value (values(%{$json}))
		{
			$value = to_utf8($value);
		}
	}
	elsif (is_array($json))
	{
		foreach my $value (@{$json})
		{
			$value = to_utf8($value);
		}
	}
	else
	{
		$json = encode_utf8($json);
	}

	return $json;
}

sub __execute($$$)
{
	my ($self, $class, $method, $params) = @_;

	my $result = $self->__send_request($class, $method, $params);

	if (defined($result->{'error'}))
	{
		$self->set_last_error($result->{'error'});

		return $result;
	}

	$self->set_last_error();

	return $result->{'result'};
}

sub __fetch($$$)
{
	my ($self, $class, $method, $params) = @_;

	my $result = to_utf8($self->__send_request($class, $method, $params));

	if (defined($result->{'error'}))
	{
		$self->set_last_error($result->{'error'});

		return $result;
	}

	unless (is_array($result->{'result'}))
	{
		$self->set_last_error("non-array result received when checking " . $class . ":\nREQUEST:\n" .
				Dumper($params) . "\nREPLY:\n" . Dumper($result->{'result'}) . "\n");

		return undef;
	}

	$self->set_last_error();

	# return direct reference to the only element or reference to the whole array
	return scalar(@{$result->{'result'}}) == 1 ? $result->{'result'}->[0] : $result->{'result'};
}

# TODO consider deleting this unused method
sub __fetch_bool($$$)
{
	my ($self, $class, $method, $params) = @_;

	my $result = $self->__send_request($class, $method, $params);

	if (defined($result->{'error'}))
	{
		$self->set_last_error($result->{'error'});

		return undef;
	}

	unless (is_array($result->{'result'}))
	{
		$self->set_last_error("non-array result received when checking " . $class . ":\nREQUEST:\n" .
				Dumper($params) . "\nREPLY:\n" . Dumper($result->{'result'}) . "\n");

		return undef;
	}

	if (scalar(@{$result->{'result'}}) > 1)
	{
		$self->set_last_error("more than one entry found when checking " . $class . ":\nREQUEST:\n" .
				Dumper($params) . "\nREPLY:\n" . Dumper($result->{'result'}) . "\n");

		return false;
	}

	$self->set_last_error();

	return scalar(@{$result->{'result'}}) == 0 ? false : true;
}

sub __fetch_id($$$)
{
	my ($self, $class, $method, $params) = @_;

	my $result = $self->__send_request($class, $method, $params);

	if (defined($result->{'error'}))
	{
		$self->set_last_error($result->{'error'});

		return $result;
	}

	unless (is_array($result->{'result'}))
	{
		$self->set_last_error("non-array result received when checking " . $class . ":\nREQUEST:\n" .
				Dumper($params) . "\nREPLY:\n" . Dumper($result->{'result'}) . "\n");

		return undef;
	}

	if (scalar(@{$result->{'result'}}) > 1)
	{
		$self->set_last_error("more than one entry found when checking " . $class . ":\nREQUEST:\n" .
				Dumper($params) . "\nREPLY:\n" . Dumper($result->{'result'}) . "\n");

		return false;
	}

	$self->set_last_error();

	return scalar(@{$result->{'result'}}) == 0 ? false : $result->{'result'}->[0]->{$objectids->{$class}};
}

sub __send_request
{
	my ($self, $class, $method, $params) = @_;

	my $req = $self->req;

	my $request = {
		'jsonrpc'	=> "2.0",
		'method'	=> "$class.$method",
		'params'	=> $params,
		'id'		=> $self->next_id
	};

	if ($method ne 'version')
	{
		$request->{'auth'} = $self->auth;
	}

	$req->content(to_ascii(encode_json($request)));

	my $res;
	my $sleep = 1;

	for (my $attempts_left = $REQUEST_ATTEMPTS; $attempts_left > 0; $attempts_left--)
	{
		$res = $self->ua->request($req);

		last if ($res->is_success);

		sleep($sleep);

		$sleep *= 1.3;
		$sleep = 3 if ($sleep > 3);
	}

	die("Can't connect to Zabbix: " . $res->status_line) unless ($res->is_success);

	my $result = decode_json($res->content);

	if (defined($result->{'error'}))
	{
		print("REQUEST FAILED:\n", Dumper($req), "\n");
		print("REPLY:\n", Dumper($result), "\n");

		if ($result->{'error'}->{'data'} =~ /Session terminated/)
		{
			delete_authid();
		}
	}
	elsif ($DEBUG)
	{
		print("REQUEST:\n", Dumper($req), "\n");
		print("REPLY:\n", Dumper($result), "\n");
	}

	return $result;
}

1;
