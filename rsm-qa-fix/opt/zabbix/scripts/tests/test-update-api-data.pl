#!/usr/bin/perl -w

use strict;
use warnings;
use Getopt::Long;
use JSON::XS;
use Path::Tiny qw(path);
use Types::Serialiser;
use Data::Validate::IP qw(is_ipv4 is_ipv6);

# directory where files are generated

use constant BASE_PATH	=> '/opt/zabbix/sla';

# JSON value types

use constant JSON_VALUE_ARRAY	=> 1;
use constant JSON_VALUE_BOOLEAN	=> 2;
use constant JSON_VALUE_NUMBER	=> 3;
use constant JSON_VALUE_OBJECT	=> 4;
use constant JSON_VALUE_STRING	=> 5;

# JSON keys sorted alphabetically

use constant JSON_KEY_DNS			=> 'DNS';
use constant JSON_KEY_DNSSEC			=> 'DNSSEC';
use constant JSON_KEY_EPP			=> 'EPP';
use constant JSON_KEY_RDDS			=> 'RDDS';
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
use constant JSON_KEY_TESTED_SERVICES		=> 'testedServices';
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

sub fail($$$)
{
	my $treepath = shift;
	my $jsonpath = shift;
	my $message = shift;

	if ($fail_immediately)
	{
		if (defined($jsonpath))
		{
			die("FAIL: \"$treepath\": \"$jsonpath\": $message");
		}
		else
		{
			die("FAIL: \"$treepath\": $message");
		}
	}
	else
	{
		if (defined($jsonpath))
		{
			print("FAIL: \"$treepath\": \"$jsonpath\": $message\n");
		}
		else
		{
			print("FAIL: \"$treepath\": $message\n");
		}

		$issues_found++;
	}
}

# helper function to report progress

sub info($$$)
{
	my $treepath = shift;
	my $jsonpath = shift;
	my $message = shift;

	if ($debug_logging)
	{
		if (defined($jsonpath))
		{
			print("INFO: \"$treepath\": \"$jsonpath\": $message\n");
		}
		else
		{
			print("INFO: \"$treepath\": $message\n");
		}
	}
}

# JSON content validation functions

sub validate_json_value_string_or_number($$$$)
{
	my $json = shift;
	my $schema = shift;
	my $jsonpath = shift;
	my $file = shift;

	if (exists($schema->{'exact'}))
	{
		if ($json eq $schema->{'exact'})
		{
			info($file, $jsonpath, "has expected value");
		}
		else
		{
			fail($file, $jsonpath, "has unexpected value");
		}
	}
	elsif (exists($schema->{'pattern'}))
	{
		if ($json =~ $schema->{'pattern'})
		{
			info($file, $jsonpath, "matches expected pattern");
		}
		else
		{
			fail($file, $jsonpath, "has unexpected value");
		}
	}
	elsif (exists($schema->{'rule'}))
	{
		&{$schema->{'rule'}}($json, $jsonpath, $file);
	}
	else
	{
		die("missing 'exact' value, 'pattern' or validation 'rule' for JSON string or number in schema");
	}
}

sub validate_json_value($$$$);

sub validate_json_value_object($$$$)
{
	my $json = shift;
	my $schema = shift;
	my $jsonpath = shift;
	my $file = shift;

	if (ref($json) eq 'HASH')
	{
		info($file, $jsonpath, "is a JSON object, as expected");

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
								"$jsonpath.$key", $file);
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
							fail($file, "$jsonpath.$key", "mandatory field not found");
						}
						else
						{
							info($file, "$jsonpath.$key", "is missing but not mandatory")
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
					fail($file, "$jsonpath.$key", "unexpected field");
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
		fail($file, $jsonpath, "is expected to be a JSON object");
	}
}

sub validate_json_value_array($$$$)
{
	my $json = shift;
	my $schema = shift;
	my $jsonpath = shift;
	my $file = shift;

	if (ref($json) eq 'ARRAY')
	{
		info($file, $jsonpath, "is a JSON array, as expected");

		if (exists($schema->{'element'}))
		{
			for (my $i = 0; $i < scalar(@{$json}); $i++)
			{
				validate_json_value($json->[$i], $schema->{'element'}, "$jsonpath\[$i]", $file);
			}
		}
		else
		{
			die("missing 'element' of JSON array in schema");
		}
	}
	else
	{
		fail($file, $jsonpath, "is expected to be a JSON array");
	}
}

sub validate_json_value($$$$)
{
	my $json = shift;
	my $schema = shift;
	my $jsonpath = shift;
	my $file = shift;

	info($file, $jsonpath, "validation begins");

	if (defined($json))
	{
		if (exists($schema->{'value'}))
		{
			if ($schema->{'value'} eq JSON_VALUE_STRING || $schema->{'value'} eq JSON_VALUE_NUMBER)
			{
				validate_json_value_string_or_number($json, $schema, $jsonpath, $file);
			}
			elsif ($schema->{'value'} eq JSON_VALUE_OBJECT)
			{
				validate_json_value_object($json, $schema, $jsonpath, $file);
			}
			elsif ($schema->{'value'} eq JSON_VALUE_ARRAY)
			{
				validate_json_value_array($json, $schema, $jsonpath, $file);
			}
			elsif ($schema->{'value'} eq JSON_VALUE_BOOLEAN)
			{
				if (Types::Serialiser::is_bool($json))
				{
					info($file, $jsonpath, "is a boolean, as expected");
				}
				else
				{
					fail($file, $jsonpath, "is expected to be a boolean");
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
			fail($file, $jsonpath, "cannot be \"null\"");
		}
		else
		{
			info($file, $jsonpath, "is \"null\"");
		}
	}
	else
	{
		die("missing 'not null' in JSON schema");
	}

	info($file, $jsonpath, "validation ends");
}

# helper function to read file containing JSON

sub read_json_file($)
{
	my $file = shift;
	my $contents = $file->slurp_utf8;
	my $json;

	eval {$json = decode_json($contents)};

	if ($@)
	{
		fail($file, undef, "error reading JSON: $@");
	}

	return $json;
}

# common JSON pieces

my $tested_service_object_schema = {
	'value'		=> JSON_VALUE_OBJECT,
	'not null'	=> 1,
	'members'	=> {
		JSON_KEY_STATUS,
		{
			'mandatory'	=> 1,
			'member'	=> {
				'value'		=> JSON_VALUE_STRING,
				'not null'	=> 1,
				'pattern'	=> qr/^(Up|Down|Disabled)$/
			}
		},
		JSON_KEY_EMERGENCY_THRESHOLD,
		{
			'mandatory'	=> 0,
			'member'	=> {
				'value'		=> JSON_VALUE_NUMBER,
				'not null'	=> 1,
				'pattern'	=> qr/^(0|[1-9][0-9]*)(\.[0-9]*)?$/
			}
		},
		JSON_KEY_INCIDENTS,
		{
			'mandatory'	=> 0,
			'member'	=> {
				'value'		=> JSON_VALUE_ARRAY,
				'not null'	=> 1,
				'element'	=> {
					'value'		=> JSON_VALUE_OBJECT,
					'not null'	=> 1,
					'members'	=> {
						JSON_KEY_INCIDENT_ID,
						{
							'mandatory'	=> 1,
							'member'	=> {
								'value'		=> JSON_VALUE_STRING,
								'not null'	=> 1,
								'pattern'	=> qr/^(0|[1-9][0-9]*)\.[0-9]+$/
							}
						},
						JSON_KEY_START_TIME,
						{
							'mandatory'	=> 1,
							'member'	=> {
								'value'		=> JSON_VALUE_NUMBER,
								'not null'	=> 1,
								'pattern'	=> qr/^(0|[1-9][0-9]*)$/
							}
						},
						JSON_KEY_FALSE_POSITIVE,
						{
							'mandatory'	=> 1,
							'member'	=> {
								'value'		=> JSON_VALUE_BOOLEAN,
								'not null'	=> 1
							}
						},
						JSON_KEY_STATE,
						{
							'mandatory'	=> 1,
							'member'	=> {
								'value'		=> JSON_VALUE_STRING,
								'not null'	=> 1,
								'pattern'	=> qr/^(Active|Resolved)$/
							}
						},
						JSON_KEY_END_TIME,
						{
							'mandatory'	=> 1,
							'member'	=> {
								'value'		=> JSON_VALUE_NUMBER,
								'not null'	=> 0,
								'pattern'	=> qr/^(0|[1-9][0-9]*)$/
							}
						}
					}
				}
			}
		}
	}
};

# file validation functions

sub validate_tld_state_file($)
{
	my $file = shift;

	info($file, undef, "validation begins");

	my $json = read_json_file($file);

	if (defined($json))
	{
		my $schema = {
			'value'		=> JSON_VALUE_OBJECT,
			'not null'	=> 1,
			'members'	=> {
				JSON_KEY_VERSION,
				{
					'mandatory'	=> 1,
					'member'	=> {
						'value'		=> JSON_VALUE_NUMBER,
						'not null'	=> 1,
						'exact'		=> '1'
					}
				},
				JSON_KEY_TLD,
				{
					'mandatory'	=> 1,
					'member'	=> {
						'value'		=> JSON_VALUE_STRING,
						'not null'	=> 1,
						'pattern'	=> qr/.*/
					}
				},
				JSON_KEY_STATUS,
				{
					'mandatory'	=> 1,
					'member'	=> {
						'value'		=> JSON_VALUE_STRING,
						'not null'	=> 1,
						'pattern'	=> qr/^(Up|Down|Up-inconclusive)$/
					}
				},
				JSON_KEY_LAST_UPDATE,
				{
					'mandatory'	=> 1,
					'member'	=> {
						'value'		=> JSON_VALUE_NUMBER,
						'not null'	=> 1,
						'pattern'	=> qr/^(0|[1-9][0-9]*)$/
					}
				},
				JSON_KEY_TESTED_SERVICES,
				{
					'mandatory'	=> 1,
					'member'	=> {
						'value'		=> JSON_VALUE_OBJECT,
						'not null'	=> 1,
						'members'	=> {
							JSON_KEY_DNS,
							{
								'mandatory'	=> 1,
								'member'	=> $tested_service_object_schema
							},
							JSON_KEY_DNSSEC,
							{
								'mandatory'	=> 1,
								'member'	=> $tested_service_object_schema
							},
							JSON_KEY_EPP,
							{
								'mandatory'	=> 1,
								'member'	=> $tested_service_object_schema
							},
							JSON_KEY_RDDS,
							{
								'mandatory'	=> 1,
								'member'	=> $tested_service_object_schema
							},
						}
					}
				}
			}
		};

		validate_json_value($json, $schema, "", $file);
	}

	info($file, undef, "validation ends");
}

sub validate_alarmed_file($)
{
	my $file = shift;

	info($file, undef, "validation begins");

	my $json = read_json_file($file);

	if (defined($json))
	{
		my $schema = {
			'value'		=> JSON_VALUE_OBJECT,
			'not null'	=> 1,
			'members'	=> {
				JSON_KEY_VERSION,
				{
					'mandatory'	=> 1,
					'member'	=> {
						'value'		=> JSON_VALUE_NUMBER,
						'not null'	=> 1,
						'exact'		=> '1'
					}
				},
				JSON_KEY_LAST_UPDATE,
				{
					'mandatory'	=> 1,
					'member'	=> {
						'value'		=> JSON_VALUE_NUMBER,
						'not null'	=> 1,
						'pattern'	=> qr/^(0|[1-9][0-9]*)$/
					}
				},
				JSON_KEY_ALARMED,
				{
					'mandatory'	=> 1,
					'member'	=> {
						'value'		=> JSON_VALUE_STRING,
						'not null'	=> 1,
						'pattern'	=> qr/^(Yes|No|Disabled)$/
					}
				}
			}
		};

		validate_json_value($json, $schema, "", $file);
	}

	info($file, undef, "validation ends");
}

sub validate_downtime_file($)
{
	my $file = shift;

	info($file, undef, "validation begins");

	my $json = read_json_file($file);

	if (defined($json))
	{
		my $schema = {
			'value'		=> JSON_VALUE_OBJECT,
			'not null'	=> 1,
			'members'	=> {
				JSON_KEY_VERSION,
				{
					'mandatory'	=> 1,
					'member'	=> {
						'value'		=> JSON_VALUE_NUMBER,
						'not null'	=> 1,
						'exact'		=> '1'
					}
				},
				JSON_KEY_LAST_UPDATE,
				{
					'mandatory'	=> 1,
					'member'	=> {
						'value'		=> JSON_VALUE_NUMBER,
						'not null'	=> 1,
						'pattern'	=> qr/^(0|[1-9][0-9]*)$/
					}
				},
				JSON_KEY_DOWNTIME,
				{
					'mandatory'	=> 1,
					'member'	=> {
						'value'		=> JSON_VALUE_NUMBER,
						'not null'	=> 1,
						'pattern'	=> qr/^(0|[1-9][0-9]*)$/
					}
				}
			}
		};

		validate_json_value($json, $schema, "", $file);
	}

	info($file, undef, "validation ends");
}

sub validate_incident_state_file($)
{
	my $file = shift;

	info($file, undef, "validation begins");

	my $json = read_json_file($file);

	if (defined($json))
	{
		my $schema = {
			'value'		=> JSON_VALUE_OBJECT,
			'not null'	=> 1,
			'members'	=> {
				JSON_KEY_VERSION,
				{
					'mandatory'	=> 1,
					'member'	=> {
						'value'		=> JSON_VALUE_NUMBER,
						'not null'	=> 1,
						'exact'		=> '1'
					}
				},
				JSON_KEY_LAST_UPDATE,
				{
					'mandatory'	=> 1,
					'member'	=> {
						'value'		=> JSON_VALUE_NUMBER,
						'not null'	=> 1,
						'pattern'	=> qr/^(0|[1-9][0-9]*)$/
					}
				},
				JSON_KEY_INCIDENTS,
				{
					'mandatory'	=> 0,
					'member'	=> {
						'value'		=> JSON_VALUE_ARRAY,
						'not null'	=> 1,
						'element'	=> {
							'value'		=> JSON_VALUE_OBJECT,
							'not null'	=> 1,
							'members'	=> {
								JSON_KEY_INCIDENT_ID,
								{
									'mandatory'	=> 1,
									'member'	=> {
										'value'		=> JSON_VALUE_STRING,
										'not null'	=> 1,
										'pattern'	=> qr/^(0|[1-9][0-9]*)\.[0-9]+$/
									}
								},
								JSON_KEY_START_TIME,
								{
									'mandatory'	=> 1,
									'member'	=> {
										'value'		=> JSON_VALUE_NUMBER,
										'not null'	=> 1,
										'pattern'	=> qr/^(0|[1-9][0-9]*)$/
									}
								},
								JSON_KEY_FALSE_POSITIVE,
								{
									'mandatory'	=> 1,
									'member'	=> {
										'value'		=> JSON_VALUE_BOOLEAN,
										'not null'	=> 1
									}
								},
								JSON_KEY_STATE,
								{
									'mandatory'	=> 1,
									'member'	=> {
										'value'		=> JSON_VALUE_STRING,
										'not null'	=> 1,
										'pattern'	=> qr/^(Active|Resolved)$/
									}
								},
								JSON_KEY_END_TIME,
								{
									'mandatory'	=> 1,
									'member'	=> {
										'value'		=> JSON_VALUE_NUMBER,
										'not null'	=> 0,
										'pattern'	=> qr/^(0|[1-9][0-9]*)$/
									}
								}
							}
						}
					}
				}
			}
		};

		validate_json_value($json, $schema, "", $file);
	}

	info($file, undef, "validation ends");
}

sub validate_false_positive_file($)
{
	my $file = shift;

	info($file, undef, "validation begins");

	my $json = read_json_file($file);

	if (defined($json))
	{
		my $schema = {
			'value'		=> JSON_VALUE_OBJECT,
			'not null'	=> 1,
			'members'	=> {
				JSON_KEY_VERSION,
				{
					'mandatory'	=> 1,
					'member'	=> {
						'value'		=> JSON_VALUE_NUMBER,
						'not null'	=> 1,
						'exact'		=> '1'
					}
				},
				JSON_KEY_LAST_UPDATE,
				{
					'mandatory'	=> 1,
					'member'	=> {
						'value'		=> JSON_VALUE_NUMBER,
						'not null'	=> 1,
						'pattern'	=> qr/^(0|[1-9][0-9]*)$/
					}
				},
				JSON_KEY_FALSE_POSITIVE,
				{
					'mandatory'	=> 1,
					'member'	=> {
						'value'		=> JSON_VALUE_BOOLEAN,
						'not null'	=> 1
					}
				},
				JSON_KEY_UPDATE_TIME,
				{
					'mandatory'	=> 1,
					'member'	=> {
						'value'		=> JSON_VALUE_NUMBER,
						'not null'	=> 0,
						'pattern'	=> qr/^(0|[1-9][0-9]*)$/
					}
				}
			}
		};

		validate_json_value($json, $schema, "", $file);
	}

	info($file, undef, "validation ends");
}

sub validate_ip($$$)
{
	my $json = shift;
	my $jsonpath = shift;
	my $file = shift;

	if (is_ipv4($json))
	{
		info($file, $jsonpath, "is a valid IPv4 address");
	}
	elsif (is_ipv6($json))
	{
		info($file, $jsonpath, "is a valid IPv6 address");
	}
	else
	{
		fail($file, $jsonpath, "invalid IP address");
	}
}

sub validate_test_result($$$)
{
	my $json = shift;
	my $jsonpath = shift;
	my $file = shift;

	my %results = (							# meaningless hint
		'-200, No reply from name server'			=> 'DNS',
		'-201, Invalid reply from name server'			=> 'DNS',
		'-204, DNSSEC error'					=> 'DNS',
		'-206, No AD bit in the answer from resolver'		=> 'DNS',
		'-200, No reply from RDDS43 server'			=> 'RDDS',
		'-201, Syntax error on RDDS43 output'			=> 'RDDS',
		'-204, No reply from RDDS80 server'			=> 'RDDS',
		'-205, Cannot resolve a Whois host name'		=> 'RDDS',
		'-207, Invalid HTTP status code'			=> 'RDDS',
		'ok'							=> 'both'
	);

	if (exists($results{$json}))
	{
		info($file, $jsonpath, "has expected value");
	}
	else
	{
		fail($file, $jsonpath, "has unexpected value");
	}
}

sub validate_measurement_file($)
{
	my $file = shift;

	info($file, undef, "validation begins");

	my $json = read_json_file($file);

	if (defined($json))
	{
		my $schema = {
			'value'		=> JSON_VALUE_OBJECT,
			'not null'	=> 1,
			'members'	=> {
				JSON_KEY_VERSION,
				{
					'mandatory'	=> 1,
					'member'	=> {
						'value'		=> JSON_VALUE_NUMBER,
						'not null'	=> 1,
						'exact'		=> '1'
					}
				},
				JSON_KEY_LAST_UPDATE,
				{
					'mandatory'	=> 1,
					'member'	=> {
						'value'		=> JSON_VALUE_NUMBER,
						'not null'	=> 1,
						'pattern'	=> qr/^(0|[1-9][0-9]*)$/
					}
				},
				JSON_KEY_TLD,
				{
					'mandatory'	=> 1,
					'member'	=> {
						'value'		=> JSON_VALUE_STRING,
						'not null'	=> 1,
						'pattern'	=> qr/.*/
					}
				},
				JSON_KEY_SERVICE,
				{
					'mandatory'	=> 1,
					'member'	=> {
						'value'		=> JSON_VALUE_STRING,
						'not nutt'	=> 1,
						'pattern'	=> qr/^(DNS|DNSSEC|EPP|RDDS)$/
					}
				},
				JSON_KEY_CYCLE_CALCULATION_TIME,
				{
					'mandatory'	=> 1,
					'member'	=> {
						'value'		=> JSON_VALUE_NUMBER,
						'not null'	=> 1,
						'pattern'	=> qr/^(0|[1-9][0-9]*)$/
					}
				},
				JSON_KEY_STATUS,
				{
					'mandatory'	=> 1,
					'member'	=> {
						'value'		=> JSON_VALUE_STRING,
						'not null'	=> 1,
						'pattern'	=> qr/^(Up|Down)$/
					}
				},
				JSON_KEY_TESTED_INTERFACE,
				{
					'mandatory'	=> 1,
					'member'	=> {
						'value'		=> JSON_VALUE_ARRAY,
						'not null'	=> 1,
						'element'	=> {
							'value'		=> JSON_VALUE_OBJECT,
							'not null'	=> 1,
							'members'	=> {
								JSON_KEY_INTERFACE,
								{
									'mandatory'	=> 1,
									'member'	=> {
										'value'		=> JSON_VALUE_STRING,
										'not null'	=> 1,
										'pattern'	=> qr/^(DNS|DNSSEC|EPP|RDDS43|RDDS80)$/
									}
								},
								JSON_KEY_PROBES,
								{
									'mandatory'	=> 1,
									'member'	=> {
										'value'		=> JSON_VALUE_ARRAY,
										'not null'	=> 1,
										'element'	=> {
											'value'		=> JSON_VALUE_OBJECT,
											'not null'	=> 1,
											'members'	=> {
												JSON_KEY_CITY,
												{
													'mandatory'	=> 1,
													'member'	=> {
														'value'		=> JSON_VALUE_STRING,
														'not null'	=> 1,
														'pattern'	=> qr/.*/
													}
												},
												JSON_KEY_STATUS,
												{
													'mandatory'	=> 1,
													'member'	=> {
														'value'		=> JSON_VALUE_STRING,
														'not null'	=> 1,
														'pattern'	=> qr/^(Up|Down|No result|Offline)$/
													}
												},
												JSON_KEY_TEST_DATA,
												{
													'mandatory'	=> 1,
													'member'	=> {
														'value'		=> JSON_VALUE_ARRAY,
														'not null'	=> 1,
														'element'	=> {
															'value'		=> JSON_VALUE_OBJECT,
															'not null'	=> 1,
															'members'	=> {
																JSON_KEY_TARGET,
																{
																	'mandatory'	=> 1,
																	'member'	=> {
																		'value'		=> JSON_VALUE_STRING,
																		'not null'	=> 0,
																		'pattern'	=> qr/.*/
																	}
																},
																JSON_KEY_STATUS,
																{
																	'mandatory'	=> 1,
																	'member'	=> {
																		'value'		=> JSON_VALUE_STRING,
																		'not null'	=> 1,
																		'pattern'	=> qr/^(Up|Down)$/
																	}
																},
																JSON_KEY_METRICS,
																{
																	'mandatory'	=> 1,
																	'member'	=> {
																		'value'		=> JSON_VALUE_ARRAY,
																		'not null'	=> 1,
																		'element'	=> {
																			'value'		=> JSON_VALUE_OBJECT,
																			'not null'	=> 1,
																			'members'	=> {
																				JSON_KEY_TEST_TIME,
																				{
																					'mandatory'	=> 1,
																					'member'	=> {
																						'value'		=> JSON_VALUE_NUMBER,
																						'not null'	=> 1,
																						'pattern'	=> qr/^(0|[1-9][0-9]*)$/
																					}
																				},
																				JSON_KEY_TARGET_IP,
																				{
																					'mandatory'	=> 1,
																					'member'	=> {
																						'value'		=> JSON_VALUE_STRING,
																						'not null'	=> 0,
																						'rule'		=> \&validate_ip
																					}
																				},
																				JSON_KEY_RTT,
																				{
																					'mandatory'	=> 1,
																					'member'	=> {
																						'value'		=> JSON_VALUE_NUMBER,
																						'not null'	=> 0,
																						'pattern'	=> qr/^(0|[1-9][0-9]*)$/
																					}
																				},
																				JSON_KEY_RESULT,
																				{
																					'mandatory'	=> 1,
																					'member'	=> {
																						'value'		=> JSON_VALUE_STRING,
																						'not null'	=> 1,
																						'rule'		=> \&validate_test_result
																					}
																				}
																			}
																		}
																	}
																}
															}
														}
													}
												}
											}
										}
									}
								}
							}
						}
					}
				}
			}
		};

		validate_json_value($json, $schema, "", $file);
	}

	info($file, undef, "validation ends");
}

# tree validation functions

sub validate_one($$);

sub validate_all($$)
{
	my $expected_things = shift;
	my $path = shift;
	my %real_matches = ();

	info($path, undef, "check begins");

	# check that all real things are expected
	foreach my $real_thing ($path->children)
	{
		my $expected_match;

		info($real_thing, undef, "check begins");

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
			info($real_thing, undef, "was expected, match found");

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
			fail($real_thing, undef, "was not expected");
		}

		info($real_thing, undef, "check ends");
	}

	# check that all expected mandatory things are present
	foreach my $expected_thing (@{$expected_things})
	{
		if (exists($expected_thing->{'mandatory'}))
		{
			if ($expected_thing->{'mandatory'})
			{
				if (exists($real_matches{$expected_thing}))
				{
					info($path, undef, "mandatory " . (exists($expected_thing->{'name'}) ?
							"\"$expected_thing->{'name'}\"" : "entry") . " was found");
				}
				else
				{
					fail($path, undef, "mandatory " . (exists($expected_thing->{'name'}) ?
							"\"$expected_thing->{'name'}\"" : "entry") . " was not found");
				}
			}
		}
		else
		{
			die("expected thing must have 'mandatory' flag")
		}
	}

	info($path, undef, "check ends");
}

sub validate_one($$)
{
	my $expected_thing = shift;
	my $real_thing = shift;

	if (exists($expected_thing->{'validator'}))
	{
		if ($real_thing->is_file)
		{
			info($real_thing, undef, "is a file, as expected");

			if (defined($expected_thing->{'validator'}))
			{
				&{$expected_thing->{'validator'}}($real_thing);
			}
			else
			{
				info($real_thing, undef, "validator subroutine is not available");
			}
		}
		else
		{
			fail($real_thing, undef, "expected to be a file");
		}
	}
	elsif (exists($expected_thing->{'contents'}))
	{
		if ($real_thing->is_dir)
		{
			info($real_thing, undef, "is a directory, as expected");

			validate_all($expected_thing->{'contents'}, $real_thing);
		}
		else
		{
			fail($real_thing, undef, "expected to be a directory");
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
				# monitoring
				'name'		=> 'monitoring',
				'mandatory'	=> 1,
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
								'mandatory'	=> 0,
								'validator'	=> \&validate_downtime_file
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
		]
	}
];

validate_all($expected_in_base_path, path(BASE_PATH));

if ($issues_found)
{
	die("issues found: $issues_found");
}

info(BASE_PATH, undef, "no issues found");
