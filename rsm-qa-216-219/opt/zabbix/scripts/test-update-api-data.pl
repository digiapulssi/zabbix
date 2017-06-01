#!/usr/bin/perl -w

use strict;
use warnings;
use JSON::XS;
use Path::Tiny qw(path);
use Types::Serialiser;

# directory where files are generated

use constant BASE_PATH	=> '/opt/zabbix/sla';

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
# TODO make them command line options

my $fail_immediately = 0;
my $issues_found = 0;
my $debug_logging = 1;

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

# file validation functions

sub read_json_file($)
{
	my $file = shift;
	my $contents = $file->slurp_utf8;
	my ($json, $error);

	eval {$json = decode_json($contents)};
	$error = $@;

	return ($json, $error);
}

sub validate_state_file($)
{
	my $file = shift;

	info("validating \"$file\"");

	my ($json, $error) = read_json_file($file);

	if ($error)
	{
		fail("error reading JSON from \"$file\": $error");
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

	#check that all expected mandatory things are present
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
				fail("validator subroutine for \"$real_thing\" is not yet available");
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

my $expected_things = [
	{
		# <TLD>
		'pattern'	=> qr/.*/,
		'mandatory'	=> 0,
		'contents'	=> [
			{
				# state
				'name'		=> 'state',
				'mandatory'	=> 1,
				'validator'	=> \&validate_state_file
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
						'validator'	=> undef
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
										'validator'	=> undef
									},
									{
										# falsePositive
										'name'		=> 'falsePositive',
										'mandatory'	=> 1,
										'validator'	=> undef
									},
									{
										# <measurementID>
										'pattern'	=> qr/\d+\.\d+\.json/,
										'mandatory'	=> 1,
										'validator'	=> undef
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

validate_all($expected_things, path(BASE_PATH));

if ($issues_found)
{
	die("issues found: $issues_found");
}

info("no issues found");
