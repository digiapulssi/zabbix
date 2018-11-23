package DaWa;

use strict;
use warnings;
use RSMSLV;
use Text::CSV_XS;
use File::Path qw(make_path);
use Fcntl qw(:flock);		# todo phase 1: taken from phase 2 export.pl

use constant CSV_FILES_DIR => '/opt/zabbix/export-tmp';

# catalogs
use constant ID_PROBE => 'probe';
use constant ID_TLD => 'tld';
use constant ID_NS_NAME => 'ns_name';
use constant ID_NS_IP => 'ns_ip';
use constant ID_TRANSPORT_PROTOCOL => 'transport_protocol';
use constant ID_TEST_TYPE => 'test_type';
use constant ID_SERVICE_CATEGORY => 'service_category';
use constant ID_TLD_TYPE => 'tld_type';
use constant ID_STATUS_MAP => 'status_map';
use constant ID_IP_VERSION => 'ip_version';

# data files
use constant DATA_TEST => 'test';
use constant DATA_NSTEST => 'nstest';
use constant DATA_CYCLE => 'cycle';
use constant DATA_INCIDENT => 'incident';
use constant DATA_INCIDENT_END => 'incidentEnd';
use constant DATA_FALSE_POSITIVE => 'falsePositive';
use constant DATA_PROBE_CHANGES => 'probeChanges';

our %CATALOGS = (
	ID_PROBE() => 'probeNames.csv',
	ID_TLD() => 'tlds.csv',
	ID_NS_NAME() => 'nsFQDNs.csv',
	ID_NS_IP() => 'ipAddresses.csv',
	ID_TRANSPORT_PROTOCOL() => 'transportProtocols.csv',
	ID_TEST_TYPE() => 'testTypes.csv',
	ID_SERVICE_CATEGORY() => 'serviceCategory.csv',
	ID_TLD_TYPE() => 'tldTypes.csv',
	ID_STATUS_MAP() => 'statusMaps.csv',
	ID_IP_VERSION() => 'ipVersions.csv');

our %DATAFILES = (
	DATA_TEST() => 'tests.csv',
	DATA_NSTEST() => 'nsTests.csv',
	DATA_CYCLE() => 'cycles.csv',
	DATA_INCIDENT() => 'incidents.csv',
	DATA_INCIDENT_END() => 'incidentsEndTime.csv',
	DATA_FALSE_POSITIVE() => 'falsePositiveChanges.csv',
	DATA_PROBE_CHANGES() => 'probeChanges.csv');

use base 'Exporter';

our @EXPORT = qw(ID_PROBE ID_TLD ID_NS_NAME ID_NS_IP ID_TRANSPORT_PROTOCOL ID_TEST_TYPE ID_SERVICE_CATEGORY
		ID_TLD_TYPE ID_STATUS_MAP ID_IP_VERSION DATA_TEST DATA_NSTEST DATA_CYCLE DATA_INCIDENT DATA_INCIDENT_END
		DATA_FALSE_POSITIVE DATA_PROBE_CHANGES
		%CATALOGS %DATAFILES
		dw_csv_init dw_append_csv dw_load_ids_from_db dw_get_id dw_get_name dw_write_csv_files
		dw_write_csv_catalogs dw_delete_csvs dw_get_cycle_id dw_translate_cycle_id dw_set_date);

my %_MAX_IDS = (
	ID_PROBE() => 32767,
	ID_TLD() => 32767,
	ID_NS_NAME() => 32767,
	ID_NS_IP() => 32767,
	ID_TRANSPORT_PROTOCOL() => 127,
	ID_TEST_TYPE() => 127,
	ID_SERVICE_CATEGORY() => 127,
	ID_TLD_TYPE() => 127,
	ID_STATUS_MAP() => 127,
	ID_IP_VERSION() => 127);

use constant _DIGITS_CLOCK			=> 10;
use constant _DIGITS_SERVICE_CATEGORY_ID	=> 3;
use constant _DIGITS_TLD_ID			=> 5;
use constant _DIGITS_NS_ID			=> 5;
use constant _DIGITS_IP_ID			=> 5;

my (%_csv_files, %_csv_catalogs, $_csv);

my ($_year, $_month, $_day);

my $_catalogs_loaded = 1;

sub dw_csv_init
{
	$_csv = Text::CSV_XS->new({binary => 1, auto_diag => 1});
	$_csv->eol("\n");

	$_catalogs_loaded = 0;
}

# only works with data files
sub dw_append_csv
{
	my $id_type = shift;
	my $array_ref = shift;

	__fix_row($id_type, $array_ref);
	push(@{$_csv_files{$id_type}{'rows'}}, $array_ref);
}

sub __dw_check_id
{
	my $id_type = shift;
	my $id = shift;

	my $max_id = $_MAX_IDS{$id_type};

	fail("unknown catalog: \"$id_type\"") unless ($max_id);

	return E_FAIL if ($id > $max_id);

	return SUCCESS;
}

# only works with catalogs
sub dw_load_ids_from_db
{
	foreach my $id_type (keys(%CATALOGS))
	{
		delete($_csv_catalogs{$id_type});

		my $rows_ref = db_select("select name,id from rsm_$id_type");

		foreach my $row_ref (@$rows_ref)
		{
			fail("ID overflow of catalog \"$id_type\": ", $row_ref->[1])
				unless (__dw_check_id($id_type, $row_ref->[1]) == SUCCESS);

			$_csv_catalogs{$id_type}{$row_ref->[0]} = $row_ref->[1];
		}
	}

	$_catalogs_loaded = 1;
}

# only works with catalogs
sub dw_get_id
{
	my $id_type = shift;
	my $name = shift;

	if (!defined($name))
	{
		wrn("internal error: attempt to get $id_type ID by undefined name!");
		return undef;
	}

	if (opt('dry-run'))
	{
		return $name;
	}

	return $_csv_catalogs{$id_type}{$name} if ($_csv_catalogs{$id_type}{$name});

	# LOCK
	__slv_lock() unless (opt('dry-run'));

	# search for ID in the database, it might have been added by other process
	my $rows_ref = db_select("select id from rsm_$id_type where name='$name'");

	if (scalar(@{$rows_ref}) > 1)
	{
		# UNLOCK
		__slv_unlock() unless (opt('dry-run'));
		fail("THIS_SHOULD_NEVER_HAPPEN: more than one \"$name\" record in table \"rsm_$id_type\"");
	}

	my $id;
	if (scalar(@{$rows_ref}) == 1)
	{
		$id = $rows_ref->[0]->[0];
	}
	else
	{
		$id = db_exec("insert into rsm_$id_type (name) values ('$name')");
	}

	# UNLOCK
	__slv_unlock() unless (opt('dry-run'));

	fail("ID overflow of catalog \"$id_type\": $id") unless (__dw_check_id($id_type, $id) == SUCCESS);

	$_csv_catalogs{$id_type}{$name} = $id;

	return $id;
}

sub dw_get_name
{
	my $id_type = shift;
	my $id = shift;

	return undef unless(defined($id) && ($id ne ''));

	my $found_name;
	foreach my $name (keys(%{$_csv_catalogs{$id_type}}))
	{
		if ($id == $_csv_catalogs{$id_type}{$name})
		{
			$found_name = $name;
			last;
		}
	}

	return $found_name;
}

sub __csv_file_full_path
{
	my $id_type = shift;

	die("File '$id_type' is unknown") unless ($DATAFILES{$id_type});
	die("Internal error: export date is unknown") unless ($_year && $_month && $_day);

	my $path = CSV_FILES_DIR . '/' . $_year . '/' . $_month . '/' . $_day . '/';

	$path .= $tld  . '/' if ($tld);

	__make_path($path);

	return $path . $DATAFILES{$id_type};
}

sub __csv_catalog_full_path
{
	my $id_type = shift;

	die("Catalog '$id_type' is unknown") unless ($CATALOGS{$id_type});

	my $path = CSV_FILES_DIR . '/';

	__make_path($path);

	return $path . $CATALOGS{$id_type};
}

sub dw_write_csv_files
{
	foreach my $id_type (keys(%DATAFILES))
	{
		__write_csv_file($id_type);
		undef($_csv_files{$id_type}{'rows'});
	}
}

sub dw_write_csv_catalogs
{
	my $debug = shift;

	foreach my $id_type (keys(%CATALOGS))
	{
		__write_csv_catalog($id_type);
		undef($_csv_catalogs{$id_type}{'rows'});
	}
}

sub dw_delete_csvs
{
	foreach my $id_type (keys(%DATAFILES))
	{
		my $path = __csv_file_full_path($id_type);

		if (-f $path)
		{
			unlink($path) or fail("cannot delete file \"$path\": $!");
		}
	}

	foreach my $id_type (keys(%CATALOGS))
	{
		my $path = __csv_catalog_full_path($id_type);

		if (-f $path)
		{
			unlink($path) or fail("cannot delete file \"$path\": $!");
		}
	}
}

sub dw_get_cycle_id
{
	my $clock = shift;
	my $service_category_id = shift;
	my $tld_id = shift;
	my $ns_id = shift;
	my $ip_id = shift;

	$ns_id = 0 unless (defined($ns_id));
	$ip_id = 0 unless (defined($ip_id));

	if (opt('dry-run'))
	{
		return sprintf(
			"%0"._DIGITS_CLOCK."d%0"._DIGITS_SERVICE_CATEGORY_ID."s%0"._DIGITS_TLD_ID."s%0"._DIGITS_NS_ID."s%0".
			_DIGITS_IP_ID."s", $clock, $service_category_id, $tld_id, $ns_id, $ip_id);
	}

	# todo phase 1: for RDDS the target and the IP can be an empty string:
	if ($service_category_id == 3)
	{
		$ns_id = 0 if ($ns_id eq "");
		$ip_id = 0 if ($ip_id eq "");
	}

	return sprintf(
		"%0"._DIGITS_CLOCK."d%0"._DIGITS_SERVICE_CATEGORY_ID."d%0"._DIGITS_TLD_ID."d%0"._DIGITS_NS_ID."d%0".
		_DIGITS_IP_ID."d", $clock, $service_category_id, $tld_id, $ns_id, $ip_id);
}

sub dw_translate_cycle_id
{
	my $cycle_id = shift;

	my $from = 0;
	my $len = _DIGITS_CLOCK;

	my $clock = int(substr($cycle_id, $from, $len));

	$from += $len;
	$len = _DIGITS_SERVICE_CATEGORY_ID;
	my $service_category = dw_get_name(ID_SERVICE_CATEGORY, int(substr($cycle_id, $from, $len)));

	$from += $len;
	$len = _DIGITS_TLD_ID;
	my $tld = dw_get_name(ID_TLD, int(substr($cycle_id, $from, $len)));

	$from += $len;
	$len = _DIGITS_NS_ID;
	my $ns = dw_get_name(ID_NS_NAME, int(substr($cycle_id, $from, $len))) || '';

	$from += $len;
	$len = _DIGITS_IP_ID;
	my $ip = dw_get_name(ID_NS_IP, int(substr($cycle_id, $from, $len))) || '';

	return "$clock-$service_category-$tld-$ns-$ip";
}

sub dw_set_date
{
	$_year = shift;
	$_month = shift;
	$_day = shift;
}

#################
# Internal subs #
#################

sub __fix_row
{
	my $id_type = shift;
	my $array_ref = shift;

	my $debug = opt('debug');
	my $has_undef = 0;
	my $str = '';

	foreach (@{$array_ref})
	{
		if ($debug)
		{
			unless (defined($_))
			{
				$has_undef = 1;
				$str .= " [UNDEF]";
			}
			else
			{
				$str .= " [$_]";
			}
		}

		$_ //= '';
	}

	if ($debug && $has_undef)
	{
		dbg("$id_type entry with UNDEF value: ", $str);
	}
}

# only works with data files
sub __write_csv_file
{
	my $id_type = shift;

	if (opt('dry-run'))
	{
		my $header_printed = 0;
		foreach my $row (@{$_csv_files{$id_type}{'rows'}})
		{
			if ($header_printed == 0)
			{
				print("** ", $id_type, " **\n");
				$header_printed = 1;
			}

			print(join(',', @$row), "\n");
		}

		return 1;
	}

	return 1 if (!$_csv_files{$id_type}{'rows'});

	my $path = __csv_file_full_path($id_type);

	my $fh;

	unless (open($fh, ">:encoding(utf8)", $path))
	{
		die($path . ": $!");
		return;
	}

	dbg("dumping to ", $path, "...");

	foreach my $row (@{$_csv_files{$id_type}{'rows'}})
	{
		__fix_row($id_type, $row);

		$_csv->print($fh, $row);
	}

	unless (close($fh))
	{
		die($path . ": $!");
		return;
	}

	return 1;
}

# only works with catalogs
sub __write_csv_catalog
{
	my $id_type = shift;

	die("THIS_SHOULD_NEVER_HAPPEN: no ID type specified with __write_csv_catalog()") unless ($id_type);

	return 1 if (opt('dry-run'));

	return 1 if (scalar(keys(%{$_csv_catalogs{$id_type}})) == 0);

	my $path = __csv_catalog_full_path($id_type);

	my $fh;

	unless (open($fh, ">:encoding(utf8)", $path))
	{
		die($path . ": $!");
		return;
	}

	dbg("dumping to ", $path, "...");
	foreach my $name (sort {$_csv_catalogs{$id_type}{$a} <=> $_csv_catalogs{$id_type}{$b}} (keys(%{$_csv_catalogs{$id_type}})))
	{
		my $id = $_csv_catalogs{$id_type}{$name};

		dbg($id_type, " ", join(',', $id, $name));
		$_csv->print($fh, [$id, $name]);
	}

	unless (close($fh))
	{
		die($path . ": $!");
                return;
	}

	return 1;
}

# todo phase 1: this was taken from ApiHerlper.pm:__set_file_error of phase 2 (improved version)
sub __get_file_error
{
	my $err = shift;

	if (ref($err) eq "ARRAY")
	{
		for my $diag (@$err)
		{
			my ($file, $message) = %$diag;
			if ($file eq '')
			{
				return "$message.";
			}

			return "$file: $message.";
		}
	}

	return join('', $err, @_);
}

sub __make_path
{
	my $path = shift;

	make_path($path, {error => \my $err});

	die(__get_file_error($err)) if (@$err);
}

# todo phase 1: taken from RSMSLV.pm phase 2
my $_lock_fh;
use constant _LOCK_FILE => '/tmp/rsm.slv.data.export.lock';
sub __slv_lock
{
	dbg(sprintf("%7d: %s", $$, 'TRY'));

        open($_lock_fh, ">", _LOCK_FILE) or fail("cannot open lock file " . _LOCK_FILE . ": $!");

	flock($_lock_fh, LOCK_EX) or fail("cannot lock using file " . _LOCK_FILE . ": $!");

	dbg(sprintf("%7d: %s", $$, 'LOCK'));
}

# todo phase 1: taken from RSMSLV.pm phase 2
sub __slv_unlock
{
	close($_lock_fh) or fail("cannot close lock file " . _LOCK_FILE . ": $!");

	dbg(sprintf("%7d: %s", $$, 'UNLOCK'));
}

#sub __read_csv_file
#{
# 	my $id_type = shift;
#
# 	my $file = __csv_file_name($id_type);
#
# 	if (!$_csv_files->{$file})
# 	{
# 		$_csv_files->{$file}->{'name'} =  __csv_file_name($file);
# 	}
#
# 	my $name = $_csv_files->{$file}->{'name'};
#
# 	if (! -r $name)
# 	{
# 		# file do not exist
# 		return 1;
# 	}
#
# 	my $fh;
#
# 	unless (open($fh, "<:encoding(utf8)", $name))
# 	{
# 		die($name . ": $!");
# 		return;
# 	}
#
# 	my @rows;
#
# 	while (my $row = $_csv->getline($fh))
# 	{
# 		#$row->[2] =~ m/pattern/ or next; # 3rd field should match
#
# 		push(@rows, $row);
# 	}
#
# 	close($fh);
#
# 	$_csv_files->{$file}->{'rows'} = \@rows;
#
# 	foreach my $row (@{$_csv_files->{$file}->{'rows'}})
# 	{
# 		dbg('read: ', join(',', @$row));
# 	}
#
# 	return 1;
# }
1;
