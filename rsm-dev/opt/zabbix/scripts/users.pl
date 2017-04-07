#!/usr/bin/perl

BEGIN
{
	our $MYDIR = $0; $MYDIR =~ s,(.*)/.*,$1,; $MYDIR = '.' if ($MYDIR eq $0);
}
use lib $MYDIR;

use strict;
use warnings;
use Zabbix;
use RSM;
use RSMSLV;

# NB! Keep in sync with front-end!
use constant USER_TYPE_EBERO => 4;
use constant USER_TYPE_TEHNICAL_SERVICE => 5;
use constant USER_TYPE_SUPER_ADMIN => 3;
use constant EBERO_GROUPID => 13;
use constant TEHNICAL_SERVICE_GROUPID => 14;
use constant SUPER_ADMIN_GROUPID => 7;

use constant USER_TYPES =>
{
	'ebero' =>
	{
		'type' => USER_TYPE_EBERO,
		'usrgrpid' => EBERO_GROUPID
	},
	'tech' =>
	{
		'type' => USER_TYPE_TEHNICAL_SERVICE,
		'usrgrpid' => TEHNICAL_SERVICE_GROUPID
	},
	'admin' =>
	{
		'type' => USER_TYPE_SUPER_ADMIN,
		'usrgrpid' => SUPER_ADMIN_GROUPID
	}
};

parse_opts('add!', 'delete!', 'user=s', 'type=s', 'password=s', 'firstname=s', 'lastname=s', 'server-id=n');

__validate_opts();

my $config = get_rsm_config();

my @server_keys = get_rsm_server_keys($config);

my $modified = 0;
foreach my $server_key (@server_keys)
{
	if (opt('server-id'))
	{
		next if (getopt('server-id') != get_rsm_server_id($server_key));

		unsetopt('server-id');
	}

	my $section = $config->{$server_key};

	print($server_key, "\n");

	my $zabbix = Zabbix->new({'url' => $section->{'za_url'}, user => $section->{'za_user'}, password => $section->{'za_password'}});

	if (opt('add'))
	{
		my $options =
		{
			'alias' => getopt('user'),
			'type' => USER_TYPES->{getopt('type')}->{'type'},
			'passwd' => getopt('password'),
			'name' => getopt('firstname'),
			'surname' => getopt('lastname'),
			'usrgrps' => {'usrgrpid' => USER_TYPES->{getopt('type')}->{'usrgrpid'}}};

		my $result = $zabbix->create('user', $options);

		if ($result->{'error'})
		{
			print("Error: cannot add user \"", getopt('user'), "\". ", $result->{'error'}->{'data'}, "\n");

			if ($modified == 1)
			{
				print("Please fix the issue and re-run the same command with \"--server-id ", get_rsm_server_id($server_key), "\"\n");
			}

			exit(-1);
		}
	}
	else
	{
		my $options = {'output' => ['userid'], 'filter' => {'alias' => getopt('user')}};

		my $result = $zabbix->get('user', $options);

		my $userid = $result->{'userid'};

		if (!$userid)
		{
			print("Error: user \"", getopt('user'), "\" not found on $server_key\n");

			if ($modified == 1)
			{
				print("Please fix the issue and re-run the same command with \"--server-id ", get_rsm_server_id($server_key), "\"\n");
			}

			exit(-1);
		}

		$result = $zabbix->remove('user', [$userid]);

		if ($result->{'error'})
		{
			print("Error: cannot delete user \"", getopt('user'), "\". ", $result->{'error'}->{'data'}, "\n");

			if ($modified == 1)
			{
				print("Please fix the issue and re-run the same command with \"--server-id ", get_rsm_server_id($server_key), "\"\n");
			}

			exit(-1);
		}
	}

	$modified = 1;
}

sub __opts_fail
{
	print("Invalid parameters:\n");
	print(join("\n", @_), "\n");
	exit(-1);
}

sub __validate_opts
{
	my @errors;

	push(@errors, "\tboth \"--add\" and \"--delete\" cannot be specified") if (opt('add') && opt('delete'));
	push(@errors, "\tone of \"--add\" or \"--delete\" must be specified") if (!opt('add') && !opt('delete'));
	push(@errors, "\toption \"--user\" must be specified") if (!opt('user'));

	if (opt('add'))
	{
		foreach my $opt ('type', 'password', 'firstname', 'lastname')
		{
			push(@errors, "\toption \"--$opt\" must be specified") if (!opt($opt));
		}


		if (opt('type'))
		{
			my $type = getopt('type');

			push(@errors, "\tunknown user type \"$type\", it must be one of: ebero, tech, admin")
				if ($type ne 'ebero' && $type ne 'tech' && $type ne 'admin');
		}
	}

	__opts_fail(@errors) if (0 != scalar(@errors))
}

__END__

=head1 NAME

users.pl - manage users in Zabbix

=head1 SYNOPSIS

users.pl --add|--delete --user <user> [--type <ebero|tech|admin>] [--password <password>] [--firstname <firstname>] [--lastname <lastname>] [--server-id id] [--dry-run] [--debug] [--help]

=head1 OPTIONS

=over 8

=item B<--add>

Add a new user.

=item B<--delete>

Delete existing user.

=item B<--user> user

Specify username of the user account.

=head2 OPTIONS FOR ADDING A USER

=item B<--type> type

Specify user type, accepted values: ebero, tech or admin.

=item B<--password> password

Specify user password.

=item B<--firstname> firstname

Specify first name of a user.

=item B<--lastname> lastname

Specify last name of a user.

=item B<--server-id> id

Specify id of the server to continue the optration from. This option is useful when action was successful on part of the servers.

=head2 OTHER OPTIONS

=item B<--dry-run>

Print data to the screen, do not change anything in the system.

=item B<--debug>

Run the script in debug mode. This means printing more information.

=item B<--help>

Print a brief help message and exit.

=back

=head1 DESCRIPTION

B<This program> will manage users in Zabbix.

=head1 EXAMPLES

./users.pl --add john --type ebero --password secret --firstname John --lastname Doe

This will add a new EBERO user with specified details.

=cut
