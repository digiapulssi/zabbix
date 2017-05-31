package ApiHelper;

use strict;
use warnings;
use File::Path qw(make_path);
use DateTime::Format::RFC3339;
use base 'Exporter';
use JSON::XS;

use constant AH_SUCCESS => 0;
use constant AH_FAIL => 1;

use constant AH_PATH_RELATIVE	=> 0;
use constant AH_PATH_FULL	=> 1;

use constant AH_INCIDENT_ACTIVE => 'ACTIVE';
use constant AH_STATE_FILE => 'state';
use constant AH_END_FILE => 'end';
use constant AH_FALSE_POSITIVE_FILE => 'falsePositive';
use constant AH_ALARMED_FILE => 'alarmed';
use constant AH_ALARMED_YES => 'YES';
use constant AH_ALARMED_NO => 'NO';
use constant AH_ALARMED_DISABLED => 'DISABLED';
use constant AH_BASE_DIR => '/opt/zabbix/sla';
use constant AH_TMP_DIR => '/opt/zabbix/sla-tmp';

use constant AH_ROOT_ZONE_DIR => 'zz--root';			# map root zone name (.) to something human readable

use constant AH_CONTINUE_FILE		=> 'last_update.txt';	# file with timestamp of last run with --continue
use constant AH_AUDIT_FILE_PREFIX	=> 'last_audit_';	# file containing timestamp of last auditlog entry that
								# was processed, is saved per db (false_positive change):
								# AH_AUDIT_FILE_PREFIX _ <SERVER_KEY> .txt

our @EXPORT = qw(AH_SUCCESS AH_FAIL AH_ALARMED_YES AH_ALARMED_NO AH_ALARMED_DISABLED ah_get_error ah_save_state
		ah_save_alarmed ah_save_incident ah_inc_fp_relative_path
		ah_save_false_positive ah_save_incident_json ah_get_continue_file ah_get_api_tld ah_get_last_audit
		ah_save_audit ah_save_continue_file ah_encode_pretty_json);

use constant AH_JSON_FILE_VERSION => 1;

my $error_string = "";

sub ah_get_error
{
	return $error_string;
}

sub __make_base_path
{
	my $tld = shift;
	my $service = shift;
	my $result_path_ptr = shift;	# pointer
	my $add_path = shift;
	my $path_type = shift;	# relative/full

	$tld = lc($tld);
	$service = lc($service) if ($service);

	my $path = "";
	$path .= AH_TMP_DIR . "/" if ($path_type == AH_PATH_FULL);
	$path .= "$tld/";
	$path .= "$service/" if ($service);
	$path .= $add_path if ($add_path);

	make_path($path, {error => \my $err});

	if (@$err)
	{
		__set_file_error($err);
		return AH_FAIL;
	}

	$$result_path_ptr = $path;

	return AH_SUCCESS;
}

sub __make_inc_path
{
	my $tld = shift;
	my $service = shift;
	my $start = shift;
	my $eventid = shift;
	my $inc_path_ptr = shift;	# pointer
	my $path_type = shift;

	return __make_base_path($tld, $service, $inc_path_ptr, "incidents/$start.$eventid", $path_type);
}

sub __set_error
{
	$error_string = shift;
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

sub __apply_inc_end
{
	my $inc_path = shift;
	my $end = shift;
	my $lastclock = shift;

	my $end_path = "$inc_path/" . AH_END_FILE;

	return __write_file($end_path, AH_INCIDENT_ACTIVE, $lastclock) unless (defined($end));

	my $dt = DateTime->from_epoch('epoch' => $end);
	my $f = DateTime::Format::RFC3339->new();

	return __write_file($end_path, $f->format_datetime($dt), $end);
}

sub __apply_inc_false_positive
{
	my $inc_path = shift;
	my $false_positive = shift;
	my $clock = shift;

	my $false_positive_path = "$inc_path/" . AH_FALSE_POSITIVE_FILE;

	if ($false_positive != 0)
	{
		return __write_file($false_positive_path, '', $clock);
	}

	if ((-e $false_positive_path) and not unlink($false_positive_path))
	{
		__set_file_error($!);
		return AH_FAIL;
	}

	return AH_SUCCESS;
}

sub ah_save_state
{
	my $ah_tld = shift;
	my $state_ref = shift;

	my $base_path;

	return AH_FAIL unless (__make_base_path($ah_tld, undef, \$base_path, undef, AH_PATH_FULL) == AH_SUCCESS);

	my $state_path = "$base_path/" . AH_STATE_FILE;

	my $json = $state_ref;

	return __write_file($state_path, __encode_json($json));
}

sub ah_save_alarmed
{
	my $tld = shift;
	my $service = shift;
	my $status = shift;
	my $clock = shift;

	my $base_path;

	return AH_FAIL unless (__make_base_path($tld, $service, \$base_path, undef, AH_PATH_FULL) == AH_SUCCESS);

	my $alarmed_path = "$base_path/" . AH_ALARMED_FILE;

	return __write_file($alarmed_path, $status, $clock);
}

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

	return AH_FAIL unless (__make_inc_path($tld, $service, $start, $eventid, \$inc_path, AH_PATH_FULL) == AH_SUCCESS);

	return AH_FAIL unless (__apply_inc_end($inc_path, $end, $lastclock) == AH_SUCCESS);

	return __apply_inc_false_positive($inc_path, $false_positive, $start);
}

# todo phase 1: new function needed for deleting falsePositive file
sub ah_inc_fp_relative_path
{
	my $tld = shift;
	my $service = shift;
	my $eventid = shift;	# incident is identified by event ID
	my $start = shift;
	my $inc_fp_relative_path_ref = shift;

	my $inc_path;

	return AH_FAIL unless (__make_inc_path($tld, $service, $start, $eventid, \$inc_path, AH_PATH_RELATIVE) == AH_SUCCESS);

	$$inc_fp_relative_path_ref = "$inc_path/" . AH_FALSE_POSITIVE_FILE;

	return AH_SUCCESS;
}

sub ah_save_false_positive
{
	my $tld = shift;
	my $service = shift;
	my $eventid = shift;	# incident is identified by event ID
	my $start = shift;
	my $false_positive = shift;
	my $clock = shift;

	my $inc_path;

	return AH_FAIL unless (__make_inc_path($tld, $service, $start, $eventid, \$inc_path, AH_PATH_FULL) == AH_SUCCESS);

	return __apply_inc_false_positive($inc_path, $false_positive, $clock);
}

sub ah_save_incident_json
{
	my $tld = shift;
	my $service = shift;
	my $eventid = shift;	# incident is identified by event ID
	my $start = shift;
	my $json = shift;
	my $clock = shift;

	my $inc_path;

	return AH_FAIL unless (__make_inc_path($tld, $service, $start, $eventid, \$inc_path, AH_PATH_FULL) == AH_SUCCESS);

	my $json_path = "$inc_path/$clock.$eventid.json";

	return __write_file($json_path, __encode_json($json), $clock);
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
	return JSON->new->utf8(1)->pretty(1)->encode(shift);
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
