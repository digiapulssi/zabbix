#!/usr/bin/perl -w

use strict;
use warnings;
use Getopt::Long;
use JSON::XS;
use Path::Tiny qw(path);
use Types::Serialiser;

# directory where files are generated

use constant BASE_PATH	=> '/opt/zabbix/sla';

# JSON value types

use constant JSON_VALUE_ARRAY	=> 1;
use constant JSON_VALUE_BOOLEAN	=> 2;
use constant JSON_VALUE_NUMBER	=> 3;
use constant JSON_VALUE_OBJECT	=> 4;
use constant JSON_VALUE_STRING	=> 5;

# JSON keys sorted alphabetically

use constant JSON_KEY_ALARMED			=> 'alarmed';
use constant JSON_KEY_CITY			=> 'city';
use constant JSON_KEY_CYCLE_CALCULATION_TIME	=> 'cycleCalculationDateTime';
use constant JSON_KEY_DOWNTIME			=> 'downtime';
use constant JSON_KEY_EMERGENCY_THRESHOLD	=> 'emergencyThreshold';
use constant JSON_KEY_END_TIME			=> 'endTime';
use constant JSON_KEY_FALSE_POSITIVE		=> 'falsePositive';
use constant JSON_KEY_INCIDENT_ID		=> 'incidentID';
use constant JSON_KEY_INCIDENTS			=> 'incidents';
use constant JSON_KEY_INTERFACE			=> 'interface';
use constant JSON_KEY_LAST_UPDATE		=> 'lastUpdateApiDatabase';
use constant JSON_KEY_METRICS			=> 'metrics';
use constant JSON_KEY_PROBES			=> 'probes';
use constant JSON_KEY_RESULT			=> 'result';
use constant JSON_KEY_RTT			=> 'rtt';
use constant JSON_KEY_SERVICE			=> 'service';
use constant JSON_KEY_START_TIME		=> 'startTime';
use constant JSON_KEY_STATE			=> 'state';
use constant JSON_KEY_STATUS			=> 'status';
use constant JSON_KEY_TARGET			=> 'target';
use constant JSON_KEY_TARGET_IP			=> 'targetIP';
use constant JSON_KEY_TEST_DATA			=> 'testData';
use constant JSON_KEY_TEST_TIME			=> 'testDateTime';
use constant JSON_KEY_TESTED_INTERFACE		=> 'testedInterface';
use constant JSON_KEY_TESTED_SERVICE		=> 'testedService';
use constant JSON_KEY_TLD			=> 'tld';
use constant JSON_KEY_UPDATE_TIME		=> 'updateTime';
use constant JSON_KEY_VERSION			=> 'version';

# global variables

my $fail_immediately = 0;
my $issues_found = 0;
my $debug_logging = 0;
my $help = 0;

my %options = (
	'fail-immediately'	=> \$fail_immediately,
	'debug'			=> \$debug_logging,
	'help'			=> \$help
);

if (!GetOptions(%options))
{
	die();
}

if ($help)
{
	print("Usage: $0 [--fail-immediately|-f] [--debug|-d] [--help|-h]\n");
	exit();
}

# helper function to report issues

sub fail($)
{
	my $message = shift;

	if ($fail_immediately)
	{
		die("FAIL: $message");
	}
	else
	{
		print("FAIL: $message\n");
		$issues_found++;
	}
}

# helper function to report progress

sub info($)
{
	my $message = shift;

	if ($debug_logging)
	{
		print("INFO: $message\n");
	}
}

# JSON content validation functions

sub validate_json_value_string_number($$$)
{
	my $json = shift;
	my $schema = shift;
	my $jsonpath = shift;

	if (exists($schema->{'exact'}))
	{
		if ($json eq $schema->{'exact'})
		{
			info("\"$jsonpath\" has expected value");
		}
		else
		{
			fail("\"$jsonpath\" has unexpected value");
		}
	}
	elsif (exists($schema->{'pattern'}))
	{
		if ($json =~ $schema->{'pattern'})
		{
			info("\"$jsonpath\" matches expected pattern");
		}
		else
		{
			fail("\"$jsonpath\" has unexpected value");
		}
	}
	elsif (exists($schema->{'rule'}))
	{
		&{$schema->{'rule'}}($json, $jsonpath);
	}
	else
	{
		die("missing 'exact' value, 'pattern' or validation 'rule' for JSON string or number in schema");
	}
}

sub validate_json_value($$$);

sub validate_json_value_object($$$)
{
	my $json = shift;
	my $schema = shift;
	my $jsonpath = shift;

	if (ref($json) eq 'HASH')
	{
		info("\"$jsonpath\" is a JSON object, as expected");

		if (exists($schema->{'members'}))
		{
			my $members = $schema->{'members'};

			# check that members expected by schema can be found in JSON object and validate them
			foreach my $key (keys(%{$members}))
			{
				if (exists($json->{$key}))
				{
					if (exists($members->{$key}->{'member'}))
					{
						validate_json_value($json->{$key}, $members->{$key}->{'member'},
								"$jsonpath.$key");
					}
					else
					{
						die("missing 'member' in JSON object member in schema");
					}
				}
				else
				{
					if (exists($members->{$key}->{'mandatory'}))
					{
						if ($members->{$key}->{'mandatory'})
						{
							fail("mandatory \"$key\" was not found in \"$jsonpath\"");
						}
						else
						{
							info("missing \"$key\" is not mandatory in \"$jsonpath\"")
						}
					}
					else
					{
						die("missing 'mandatory' in JSON object member in schema");
					}
				}
			}

			# check that JSON object does not contain members not expected by schema
			foreach my $key (keys(%{$json}))
			{
				if (!exists($members->{$key}))
				{
					fail("\"$key\" was not expected in \"$jsonpath\"");
				}
			}
		}
		else
		{
			die("missing 'members' of JSON object in schema");
		}
	}
	else
	{
		fail("\"$jsonpath\" is expected to be a JSON object");
	}
}

sub validate_json_value_array($$$)
{
	# TODO

	die("JSON array validator is not available");
}

sub validate_json_value($$$)
{
	my $json = shift;
	my $schema = shift;
	my $jsonpath = shift;

	info("validating \"$jsonpath\"");

	if (defined($json))
	{
		if (exists($schema->{'value'}))
		{
			if ($schema->{'value'} eq JSON_VALUE_STRING || $schema->{'value'} eq JSON_VALUE_NUMBER)
			{
				validate_json_value_string_number($json, $schema, $jsonpath);
			}
			elsif ($schema->{'value'} eq JSON_VALUE_OBJECT)
			{
				validate_json_value_object($json, $schema, $jsonpath);
			}
			elsif ($schema->{'value'} eq JSON_VALUE_ARRAY)
			{
				validate_json_value_array($json, $schema, $jsonpath);
			}
			elsif ($schema->{'value'} eq JSON_VALUE_BOOLEAN)
			{
				if (Types::Serialiser::is_bool($json))
				{
					info("\"$jsonpath\" is a boolean, as expected");
				}
				else
				{
					fail("\"$jsonpath\" is expected to be a boolean");
				}
			}
		}
		else
		{
			die("missing 'value' in JSON schema");
		}
	}
	elsif (exists($schema->{'not null'}))
	{
		if ($schema->{'not null'})
		{
			fail("\"$jsonpath\" cannot be \"null\"");
		}
		else
		{
			info("\"$jsonpath\" is \"null\"");
		}
	}
	else
	{
		die("missing 'not null' in JSON schema");
	}

	info("\"$jsonpath\" validated");
}

# file validation functions

sub read_json_file($)
{
	my $file = shift;
	my $contents = $file->slurp_utf8;
	my $json;

	eval {$json = decode_json($contents)};

	if ($@)
	{
		fail("error reading JSON from \"$file\": $@");
	}

	return $json;
}

sub validate_tld_state_file($)
{
	my $file = shift;

	info("validating \"$file\"");

	my $json = read_json_file($file);

	if (defined($json))
	{
		my $schema = {
			# TODO
		};

		validate_json_value($json, $schema, "");
	}

	info("\"$file\" validated");
}

sub validate_alarmed_file($)
{
	my $file = shift;

	info("validating \"$file\"");

	my $json = read_json_file($file);

	if (defined($json))
	{
		my $schema = {
			'value'		=> JSON_VALUE_OBJECT,
			'members'	=> {
				JSON_KEY_VERSION,
				{
					'mandatory'	=> 1,
					'member'	=> {
						'value'		=> JSON_VALUE_NUMBER,
						'exact'		=> '1'
					}
				},
				JSON_KEY_LAST_UPDATE,
				{
					'mandatory'	=> 1,
					'member'	=> {
						'value'		=> JSON_VALUE_NUMBER,
						'pattern'	=> qr/^(0|[1-9][0-9]*)$/
					}
				},
				JSON_KEY_ALARMED,
				{
					'mandatory'	=> 1,
					'member'	=> {
						'value'		=> JSON_VALUE_STRING,
						'pattern'	=> qr/^(Yes|No|Disabled)$/
					}
				}
			}
		};

		validate_json_value($json, $schema, "");
	}

	info("\"$file\" validated");
}

sub validate_incident_state_file($)
{
	my $file = shift;

	info("validating \"$file\"");

	my $json = read_json_file($file);

	if (defined($json))
	{
		my $schema = {
			# TODO
		};

		validate_json_value($json, $schema, "");
	}

	info("\"$file\" validated");
}

sub validate_false_positive_file($)
{
	my $file = shift;

	info("validating \"$file\"");

	my $json = read_json_file($file);

	if (defined($json))
	{
		my $schema = {
			# TODO
		};

		validate_json_value($json, $schema, "");
	}

	info("\"$file\" validated");
}

sub validate_measurement_file($)
{
	my $file = shift;

	info("validating \"$file\"");

	my $json = read_json_file($file);

	if (defined($json))
	{
		my $schema = {
			# TODO
		};

		validate_json_value($json, $schema, "");
	}

	info("\"$file\" validated");
}

# tree validation functions

sub validate_one($$);

sub validate_all($$)
{
	my $expected_things = shift;
	my $path = shift;
	my %real_matches = ();

	info("checking \"$path\"");

	# check that all real things are expected
	foreach my $real_thing ($path->children)
	{
		my $expected_match;

		info("checking \"$real_thing\"");

		# find out which one of expected things corresponds to the given real thing
		foreach my $expected_thing (@{$expected_things})
		{
			if (exists($expected_thing->{'name'}))
			{
				if ($real_thing->basename eq $expected_thing->{'name'})
				{
					$expected_match = $expected_thing;
					last;
				}
			}
			elsif (exists($expected_thing->{'pattern'}))
			{
				if ($real_thing->basename =~ $expected_thing->{'pattern'})
				{
					$expected_match = $expected_thing;
					last;
				}
			}
			else
			{
				die("expected thing must have either 'name' or 'pattern'");
			}
		}

		if (defined($expected_match))
		{
			info("\"$real_thing\" was expected, match found");

			if (exists($expected_match->{'mandatory'}))
			{
				if ($expected_match->{'mandatory'})
				{
					$real_matches{$expected_match} = 1;
				}
			}
			else
			{
				die("expected thing must have 'mandatory' flag")
			}

			validate_one($expected_match, $real_thing);
		}
		else
		{
			fail("\"$real_thing\" was not expected");
		}

		info("\"$real_thing\" checked");
	}

	# check that all expected mandatory things are present
	foreach my $expected_thing (@{$expected_things})
	{
		if (exists($expected_thing->{'mandatory'}))
		{
			if ($expected_thing->{'mandatory'})
			{
				if (exists($real_matches{$expected_things}))
				{
					info("mandatory " . (exists($expected_thing->{'name'}) ?
							"\"$expected_thing->{'name'}\"" : "entry") .
							" was found in \"$path\"");
				}
				else
				{
					fail("mandatory " . (exists($expected_thing->{'name'}) ?
							"\"$expected_thing->{'name'}\"" : "entry") .
							" was not found in \"$path\"");
				}
			}
		}
		else
		{
			die("expected thing must have 'mandatory' flag")
		}
	}

	info("\"$path\" checked");
}

sub validate_one($$)
{
	my $expected_thing = shift;
	my $real_thing = shift;

	if (exists($expected_thing->{'validator'}))
	{
		if ($real_thing->is_file)
		{
			info("\"$real_thing\" is a file, as expected");

			if (defined($expected_thing->{'validator'}))
			{
				&{$expected_thing->{'validator'}}($real_thing);
			}
			else
			{
				info("validator subroutine for \"$real_thing\" is not yet available");
			}
		}
		else
		{
			fail("expected \"$real_thing\" to be a file");
		}
	}
	elsif (exists($expected_thing->{'contents'}))
	{
		if ($real_thing->is_dir)
		{
			info("\"$real_thing\" is a directory, as expected");

			validate_all($expected_thing->{'contents'}, $real_thing);
		}
		else
		{
			fail("expected \"$real_thing\" to be a directory");
		}
	}
	else
	{
		die("expected thing must have either 'validator' (for files) or 'contents' (for directories)");
	}
}

# validating generated files

my $expected_in_base_path = [
	{
		# last_update.txt
		'name'		=> 'last_update.txt',
		'mandatory'	=> 0,
		'validator'	=> undef
	},
	{
		# last_audit_<serverID>.txt
		'pattern'	=> qr/^last_audit_.+\.txt$/,
		'mandatory'	=> 0,
		'validator'	=> undef
	},
	{
		# <TLD>
		'pattern'	=> qr/.*/,
		'mandatory'	=> 0,
		'contents'	=> [
			{
				# state
				'name'		=> 'state',
				'mandatory'	=> 1,
				'validator'	=> \&validate_tld_state_file
			},
			{
				# <service>
				'pattern'	=> qr/^(dns|dnssec|epp|rdds)$/,
				'mandatory'	=> 0,
				'contents'	=> [
					{
						# alarmed
						'name'		=> 'alarmed',
						'mandatory'	=> 1,
						'validator'	=> \&validate_alarmed_file
					},
					{
						# downtime
						'name'		=> 'downtime',
						'mandatory'	=> 1,
						'validator'	=> undef
					},
					{
						# incidents
						'name'		=> 'incidents',
						'mandatory'	=> 0,
						'contents'	=> [
							{
								# <incidentID>
								'pattern'	=> qr/\d+\.\d+/,
								'mandatory'	=> 0,
								'contents'	=> [
									{
										# state
										'name'		=> 'state',
										'mandatory'	=> 1,
										'validator'	=> \&validate_incident_state_file
									},
									{
										# falsePositive
										'name'		=> 'falsePositive',
										'mandatory'	=> 1,
										'validator'	=> \&validate_false_positive_file
									},
									{
										# <measurementID>
										'pattern'	=> qr/\d+\.\d+\.json/,
										'mandatory'	=> 1,
										'validator'	=> \&validate_measurement_file
									}
								]
							}
						]
					}
				]
			}
		]
	}
];

validate_all($expected_in_base_path, path(BASE_PATH));

if ($issues_found)
{
	die("issues found: $issues_found");
}

info("no issues found");
