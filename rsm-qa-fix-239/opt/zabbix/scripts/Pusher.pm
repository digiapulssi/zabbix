package Pusher;

use strict;
use warnings;

use IO::Socket;
use JSON::XS qw(decode_json encode_json);
use RSMSLV;

use base 'Exporter';

our @EXPORT = qw(push_to_trapper);

use constant ZBX_HEADER	=> "ZBXD\1";

sub validate_data($)
{
	my $data = shift();

	dbg("validating data to send");

	croak("'data' must be an array reference") unless(ref($data) eq 'ARRAY');

	foreach my $element (@{$data})
	{
		croak("each element of 'data' array must be a hash reference") unless(ref($element) eq 'HASH');

		my %expected = (
			'host'	=> 0,
			'key'	=> 0,
			'value'	=> 0,
			'clock'	=> 0
		);

		while (my ($field, $value) = each(%{$element}))
		{
			croak("unexpected field '$field' in one of elements'") unless(exists($expected{$field}));
			croak("value of field '$field' cannot be null'") unless(defined($value));
			$expected{$field} = 1;
		}

		while (my ($field, $is_present) = each(%expected))
		{
			croak("each element of 'data' array must have '$field' field") unless($is_present == 1);
		}
	}

	dbg("data successfully validated");
}

sub send_request($$)
{
	my $socket = shift();
	my $request = shift();

	my $length = length($request);

	dbg("sending request:'$request' length:$length");

	my $header = ZBX_HEADER;

	for (1..8)
	{
		$header .= chr($length % 256);
		$length /= 256;
	}

	print{$socket}($header . $request);

	dbg("request successfully written to TCP layer buffer");
}

sub connect_to_server($$$$)
{
	my $server = shift();
	my $port = shift();
	my $timeout = shift();
	my $attempts = shift();

	dbg("connecting to '$server:$port' (timeout:$timeout, attempts:$attempts)");

	while ($attempts-- > 0)
	{
		my $socket = IO::Socket::INET->new(
			PeerAddr	=> $server,
			PeerPort	=> $port,
			Timeout		=> $timeout,
			Proto		=> 'tcp'
		);

		if ($socket)
		{
			dbg("connected successfully");
			return $socket;
		}

		dbg("cannot connect to '$server:$port', $attempts attempts left");
	}

	fail("failed to connect to '$server:$port'");
}

sub receive_response($)
{
	my $socket = shift();

	dbg("reading from TCP layer buffer");

	my $response = "";

	while (my $block = <$socket>)
	{
		$response .= $block;
	}

	dbg("finished reading: '$response'");
	return $response;
}

sub disconnect_from_server($)
{
	my $socket = shift();

	close($socket);
}

sub decode_response($)
{
	my $response = shift();

	dbg("decoding '$response'");

	fail("response is shorter than expected Zabbix protocol header") if (length($response) < length(ZBX_HEADER) + 8);
	fail("unexpected response header") unless (substr($response, 0, length(ZBX_HEADER)) eq ZBX_HEADER);

	# TODO validate response length properly

	my $json;

	eval {$json = decode_json(substr($response, length(ZBX_HEADER) + 8))};
	fail("error reading response: '$@'") if ($@);

	fail("missing 'response' field in response, most likely it's not Zabbix") unless (exists($json->{'response'}));
	fail("missing 'info' field in response, most likely it's not Zabbix") unless (exists($json->{'info'}));

	unless ($json->{'response'} eq "success")
	{
		fail("got 'response':'" . $json->{'response'} . "' instead of 'success'," .
				" this can only happen if we've sent invalid request");
	}

	dbg("decoded successfully, 'info':'" . ($json->{'info'} // "null") . "'");
}

sub push_to_trapper($$$$$)
{
	my $server = shift();
	my $port = shift();
	my $timeout = shift();
	my $attempts = shift();
	my $data = shift();

	validate_data($data);

	# We are using old zabbix_sender protocol without 'clock' and 'ns' fields for the whole JSON.
	# This will force Zabbix to save values with timestamps we provide without messing around with them.

	my $request = encode_json({
		'request'	=> "sender data",
		'data'		=> $data
	});

	my $socket = connect_to_server($server, $port, $timeout, $attempts);

	send_request($socket, $request);

	# Once we've sent a request we must be committed to waiting for trapper to read and fully process it.
	# There is no guaranteed way to cancel processing and by sending same value again we will definitely
	# end up with a duplicate in the database. Hence no timeouts and retries below this point.

	my $response = receive_response($socket);

	disconnect_from_server($socket);

	decode_response($response);
}

1;
