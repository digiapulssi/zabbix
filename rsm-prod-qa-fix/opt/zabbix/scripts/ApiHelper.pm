package ApiHelper;

use strict;
use warnings;
use File::Path qw(make_path);
use DateTime::Format::RFC3339;
use base 'Exporter';
use JSON::XS;
use Types::Serialiser;

use constant AH_DEBUG => 0;

use constant AH_SUCCESS => 0;
use constant AH_FAIL => 1;

use constant AH_INCIDENT_ACTIVE => 'ACTIVE';
use constant AH_STATE_FILE => 'state';
use constant AH_INCIDENT_STATE_FILE => 'state';
use constant AH_FALSE_POSITIVE_FILE => 'falsePositive';
use constant AH_ALARMED_FILE => 'alarmed';
use constant AH_DOWNTIME_FILE => 'downtime';
use constant AH_BASE_DIR => '/opt/zabbix/sla';
use constant AH_TMP_DIR => '/opt/zabbix/sla-tmp';

use constant AH_ROOT_ZONE_DIR => 'zz--root';			# map root zone name (.) to something human readable

use constant AH_CONTINUE_FILE		=> 'last_update.txt';	# file with timestamp of last run with --continue
use constant AH_AUDIT_FILE_PREFIX	=> 'last_audit_';	# file containing timestamp of last auditlog entry that
								# was processed, is saved per db (false_positive change):
								# AH_AUDIT_FILE_PREFIX _ <SERVER_KEY> .txt

use constant JSON_VALUE_INCIDENT_ACTIVE => 'Active';
use constant JSON_VALUE_INCIDENT_RESOLVED => 'Resolved';

use constant JSON_OBJECT_DISABLED_SERVICE => {
	'status'	=> 'Disabled'
};

our @EXPORT = qw(AH_SUCCESS AH_FAIL AH_BASE_DIR AH_TMP_DIR ah_get_error ah_state_file_json ah_save_state
		ah_save_alarmed ah_save_downtime ah_create_incident_json ah_save_incident
		ah_save_false_positive ah_save_measurement ah_get_continue_file ah_get_api_tld ah_get_last_audit
		ah_save_audit ah_save_continue_file ah_encode_pretty_json JSON_OBJECT_DISABLED_SERVICE);

use constant AH_JSON_FILE_VERSION => 1;

my $error_string = "";

sub ah_get_error
{
	return $error_string;
}

sub __ts_str
{
	my $ts = shift;

	my ($sec, $min, $hour, $mday, $mon, $year, $wday, $yday, $isdst) = localtime($ts);

	return sprintf("%.4d%.2d%.2d:%.2d%.2d%.2d", $year + 1900, $mon + 1, $mday, $hour, $min, $sec);
}

sub __ts_full
{
	my $ts = shift;

	$ts = time() unless ($ts);

	return __ts_str($ts) . " ($ts)";
}

sub __gen_base_path($$$)
{
	my $tld = shift;
	my $service = shift;
	my $add_path = shift;

	my $path = "$tld/monitoring";

	$path .= "/$service" if (defined($service));
	$path .= "/$add_path" if (defined($add_path));

	return $path;
}

sub __gen_inc_path($$$$)
{
	my $tld = shift;
	my $service = shift;
	my $eventid = shift;
	my $start = shift;

	return __gen_base_path($tld, $service, "incidents/$start.$eventid");
}

sub __make_base_path($$$)
{
	my $tld = shift;
	my $service = shift;
	my $result_path_ptr = shift;	# pointer

	my $path = AH_TMP_DIR . '/' . __gen_base_path($tld, $service, undef);

	make_path($path, {error => \my $err});

	if (@$err)
	{
		__set_file_error($err);
		return AH_FAIL;
	}

	$$result_path_ptr = $path;

	return AH_SUCCESS;
}

sub __make_inc_path($$$$$)
{
	my $tld = shift;
	my $service = shift;
	my $start = shift;
	my $eventid = shift;
	my $inc_path_ptr = shift;	# pointer

	my $path = AH_TMP_DIR . '/' . __gen_inc_path($tld, $service, $eventid, $start);

	make_path($path, {error => \my $err});

	if (@$err)
	{
		__set_file_error($err);
		return AH_FAIL;
	}

	$$inc_path_ptr = $path;

	return AH_SUCCESS;
}

sub __set_error
{
	$error_string = join('', @_);
}

# todo phase 1: this improved version was taken from the same file of phase 2
sub __set_file_error
{
	my $err = shift;

	$error_string = "";

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

			return;
		}
	}

	$error_string = join('', $err, @_);
}

sub __write_file
{
	my $full_path = shift;
	my $text = shift;
	my $clock = shift;

	my $OUTFILE;

	unless (open($OUTFILE, '>', $full_path))
	{
		__set_error("cannot open file $full_path: $!");
		return AH_FAIL;
	}

	unless (print { $OUTFILE } $text)
	{
		__set_error("cannot write to file $full_path: $!");
		return AH_FAIL;
	}

	close($OUTFILE);

	utime($clock, $clock, $full_path) if (defined($clock));

	return AH_SUCCESS;
}

sub __save_inc_false_positive($$$)
{
	my $inc_path = shift;
	my $false_positive = shift;
	my $clock = shift;

	my $false_positive_path = "$inc_path/" . AH_FALSE_POSITIVE_FILE;

	my $json =
	{
		'falsePositive' => ($false_positive ? Types::Serialiser::true : Types::Serialiser::false),
		'updateTime' => $clock
	};

	return __write_file($false_positive_path, __encode_json($json));
}

sub ah_state_file_json($$)
{
	my $ah_tld = shift;
	my $json_ref = shift;

	my $state_path = AH_BASE_DIR . '/' . __gen_base_path($ah_tld, undef, undef) . '/' . AH_STATE_FILE;
	my $buf;

	return AH_FAIL unless (__read_file($state_path, \$buf) == AH_SUCCESS);

	$$json_ref = decode_json($buf);

	return AH_SUCCESS;
}

sub ah_save_state($$)
{
	my $ah_tld = shift;
	my $state_ref = shift;

	my $base_path;

	return AH_FAIL unless (__make_base_path($ah_tld, undef, \$base_path) == AH_SUCCESS);

	my $state_path = "$base_path/" . AH_STATE_FILE;

	return __write_file($state_path, __encode_json($state_ref));
}

sub ah_save_alarmed
{
	my $tld = shift;
	my $service = shift;
	my $status = shift;
	my $clock = shift;

	my $base_path;

	return AH_FAIL unless (__make_base_path($tld, $service, \$base_path) == AH_SUCCESS);

	my $alarmed_path = "$base_path/" . AH_ALARMED_FILE;

	my $json = {'alarmed' => $status};

	return __write_file($alarmed_path, __encode_json($json), $clock);
}

sub ah_save_downtime
{
	my $tld = shift;
	my $service = shift;
	my $downtime = shift;
	my $clock = shift;

	my $base_path;

	return AH_FAIL unless (__make_base_path($tld, $service, \$base_path) == AH_SUCCESS);

	my $alarmed_path = "$base_path/" . AH_DOWNTIME_FILE;

	my $json = {'downtime' => $downtime};

	return __write_file($alarmed_path, __encode_json($json), $clock);
}

sub ah_create_incident_json
{
	my $eventid = shift;	# incident is identified by event ID
	my $start = shift;
	my $end = shift;
	my $false_positive = shift;

	return
	{
		'incidentID' => "$start.$eventid",
		'startTime' => $start,
		'endTime' => $end,
		'falsePositive' => ($false_positive ? Types::Serialiser::true : Types::Serialiser::false),
		'state' => (defined($end) ? JSON_VALUE_INCIDENT_RESOLVED : JSON_VALUE_INCIDENT_ACTIVE)
	};
}

sub __save_inc_state
{
	my $inc_path = shift;
	my $json = shift;
	my $lastclock = shift;

	my $inc_state_path = "$inc_path/" . AH_INCIDENT_STATE_FILE;

	return __write_file($inc_state_path, __encode_json($json), $lastclock);
}

sub __read_inc_file($$$$$$);

sub ah_save_incident
{
	my $tld = shift;
	my $service = shift;
	my $eventid = shift;	# incident is identified by event ID
	my $start = shift;
	my $end = shift;
	my $false_positive = shift;
	my $lastclock = shift;

	my $inc_path;

	return AH_FAIL unless (__make_inc_path($tld, $service, $start, $eventid, \$inc_path) == AH_SUCCESS);

	my $json = {'incidents' => [ah_create_incident_json($eventid, $start, $end, $false_positive)]};

	return AH_FAIL unless (__save_inc_state($inc_path, $json, $lastclock) == AH_SUCCESS);

	# If the there's no falsePositive file yet, just create it with updateTime null.
	# Otherwise do nothing, it should always contain correct false positiveness.
	# The false_positive changes will be updated later, when calling ah_save_false_positive().

	my $buf;
	if (__read_inc_file($tld, $service, $eventid, $start, AH_FALSE_POSITIVE_FILE, \$buf) == AH_FAIL)
	{
		return __save_inc_false_positive($inc_path, $false_positive, undef);
	}
}

sub __read_file($$)
{
	my $file = shift;
	my $buf_ref = shift;

	$$buf_ref = do
	{
		local $/ = undef;
		my $fh;
		if (!open($fh, "<", $file))
		{
			__set_error("cannot open file \"$file\": $!");
			return AH_FAIL;
		}

		<$fh>;
	};

	return AH_SUCCESS;
}

# read an old file from incident directory
sub __read_inc_file($$$$$$)
{
	my $tld = shift;
	my $service = shift;
	my $eventid = shift;
	my $start = shift;
	my $file = shift;
	my $buf_ref = shift;

	$file = AH_BASE_DIR . '/' . __gen_inc_path($tld, $service, $eventid, $start) . '/' . $file;

	dbg("file: $file");

	return __read_file($file, $buf_ref);
}

# When saving false positiveness, read from AH_BASE_DIR, write to AH_TMP_DIR.
#
# We need to get the incident state file from AH_BASE_DIR in order to get current
# "falsePositive" value and if it has changed, update it in the state file. We
# don't want to change any other parameter (e. g. incident start time) of the
# incident in the state file.
#
# If we received a false positiveness update request but the incident is not yet
# processed (no incident state file) we ignore this change and notify the caller
# about the need to try updating false positiveness later by setting $later_ref
# flag to 1.
sub ah_save_false_positive
{
	my $tld = shift;
	my $service = shift;
	my $eventid = shift;	# incident is identified by event ID
	my $start = shift;
	my $false_positive = shift;
	my $clock = shift;
	my $later_ref = shift;	# should we update false positiveness later? (incident state file does not exist yet)

	if (!defined($later_ref))
	{
		die("internal error: ah_save_false_positive() called without last parameter");
	}

	my $buf;
	if (__read_inc_file($tld, $service, $eventid, $start, AH_INCIDENT_STATE_FILE, \$buf) == AH_FAIL)
	{
		# no incident state file yet, do not update false positiveness at this point
		$$later_ref = 1;

		my $curr_err = ah_get_error();
		__set_error("incident state file not found, try to update false positiveness (changed at ", __ts_full($clock), ") later (error was: $curr_err)");

		return AH_FAIL;
	}

	my $json = decode_json($buf);

	my $inc_path;

	return AH_FAIL unless (__make_inc_path($tld, $service, $start, $eventid, \$inc_path) == AH_SUCCESS);

	my $curr_false_positive = (($json->{'incidents'}->[0]->{'falsePositive'} eq Types::Serialiser::true) ? 1 : 0);

	if ($curr_false_positive != $false_positive)
	{
		dbg("false positiveness of $eventid changed: $false_positive");

		$json->{'incidents'}->[0]->{'falsePositive'} = ($false_positive ? Types::Serialiser::true : Types::Serialiser::false);

		return AH_FAIL unless (__save_inc_state($inc_path, $json, $clock) == AH_SUCCESS);
	}

	return __save_inc_false_positive($inc_path, $false_positive, $clock);
}

sub ah_save_measurement
{
	my $tld = shift;
	my $service = shift;
	my $eventid = shift;	# incident is identified by event ID
	my $start = shift;
	my $json = shift;
	my $clock = shift;

	my $inc_path;

	return AH_FAIL unless (__make_inc_path($tld, $service, $start, $eventid, \$inc_path) == AH_SUCCESS);

	my $json_path = "$inc_path/$clock.$eventid.json";

	return __write_file($json_path, __encode_json($json), $clock);
}

sub dbg
{
	return if (AH_DEBUG == 0);

	my @args = @_;

	my $depth = 1;

	my $func = (caller($depth))[3];

	print("${func}() ", join('', @args), "\n");
}

sub ah_get_continue_file
{
	return AH_BASE_DIR . '/' . AH_CONTINUE_FILE;
}

sub ah_save_continue_file
{
	my $ts = shift;

	return __write_file(AH_TMP_DIR . '/' . AH_CONTINUE_FILE, $ts);
}

sub ah_encode_pretty_json
{
	return encode_json(shift);
}

sub ah_get_api_tld
{
	my $tld = shift;

	return AH_ROOT_ZONE_DIR if ($tld eq ".");

	return $tld;
}

sub __get_audit_file_path
{
	my $server_key = shift;

	return AH_BASE_DIR . '/' . AH_AUDIT_FILE_PREFIX . $server_key . '.txt';
}

sub __encode_json
{
	my $json_ref = shift;

	$json_ref->{'version'} = AH_JSON_FILE_VERSION;
	$json_ref->{'lastUpdateApiDatabase'} = time();

	return encode_json($json_ref);
}

# get the time of last audit log entry that was checked
sub ah_get_last_audit
{
	my $server_key = shift;

	die("Internal error: ah_get_last_audit() server_key not specified") unless ($server_key);

	my $audit_file = __get_audit_file_path($server_key);

	my $handle;

	if (-e $audit_file)
	{
		fail("cannot open last audit check file $audit_file\": $!") unless (open($handle, '<', $audit_file));

		chomp(my @lines = <$handle>);

		close($handle);

		return $lines[0];
	}

	return 0;
}

sub ah_save_audit
{
	my $server_key = shift;
	my $clock = shift;

	die("Internal error: ah_save_audit() server_key not specified") unless ($server_key && $clock);

	return __write_file(AH_TMP_DIR . '/' . AH_AUDIT_FILE_PREFIX . $server_key . '.txt', $clock);
}

1;
