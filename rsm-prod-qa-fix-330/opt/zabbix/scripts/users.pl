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

use constant USER_TYPE_EBERO => 4;		# User type "EBERO"
use constant USER_TYPE_TEHNICAL_SERVICE => 5;	# User type "Technical Services"
use constant USER_TYPE_SUPER_ADMIN => 3;	# User type "Zabbix Super Admin"

# NB! Keep these values in sync with DB schema!
use constant EBERO_GROUPID => 100;		# User group "EBERO users"
use constant TEHNICAL_SERVICE_GROUPID => 110;	# User group "Technical services users"
use constant SUPER_ADMIN_GROUPID => 7;		# User group "Zabbix administrators"

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

parse_opts('add!', 'delete!', 'modify!', 'user=s', 'type=s', 'password=s', 'firstname=s', 'lastname=s', 'server-id=n');

__validate_opts();

my $config = get_rsm_config();

my @server_keys = get_rsm_server_keys($config);

my $modified = 0;
foreach my $server_key (@server_keys)
{
	my $server_id = get_rsm_server_id($server_key);

	if (opt('server-id'))
	{
		next if (getopt('server-id') != $server_id);

		unsetopt('server-id');
	}

	my $section = $config->{$server_key};

	print("Processing $server_key\n");

	my $zabbix = Zabbix->new({'url' => $section->{'za_url'}, 'user' => $section->{'za_user'},
			'password' => $section->{'za_password'}, 'debug' => getopt('debug')});

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
			if ($result->{'error'}->{'data'} =~ /Session terminated/)
			{
				print("Session terminated. Please re-run the same command again");
				print(" with option \"--server-id $server_id\"")  if ($modified == 1);
				print(".\n");
			}
			else
			{
				print("Error: cannot add user \"", getopt('user'), "\". ", $result->{'error'}->{'data'}, "\n");

				if ($modified == 1)
				{
					print("Please fix the issue and re-run the same command with \"--server-id $server_id\"\n");
				}
			}

			exit(-1);
		}

		print("  user with userid ", $result->{'userids'}->[0], " added\n");
	}
	elsif (opt('modify'))
	{
		my $userid = __get_userid($zabbix, $server_id, getopt('user'), $modified);

		my $result = $zabbix->update('user', {'userid' => $userid, 'passwd' => getopt('password')});

		if ($result->{'error'})
		{
			print("Error: cannot change password of user \"", getopt('user'), "\". ", $result->{'error'}->{'data'}, "\n");

			if ($modified == 1)
			{
				print("Please fix the issue and re-run the same command with \"--server-id $server_id\"\n");
			}

			exit(-1);
		}

		print("  user modified\n");
	}
	else
	{
		my $userid = __get_userid($zabbix, $server_id, getopt('user'), $modified);

		my $result = $zabbix->remove('user', [$userid]);

		if ($result->{'error'})
		{
			print("Error: cannot delete user \"", getopt('user'), "\". ", $result->{'error'}->{'data'}, "\n");

			if ($modified == 1)
			{
				print("Please fix the issue and re-run the same command with \"--server-id $server_id\"\n");
			}

			exit(-1);
		}

		print("  user deleted\n");
	}

	$modified = 1;
}

sub __get_userid
{
	my $zabbix = shift;
	my $server_id = shift;
	my $alias = shift;
	my $modified = shift;

	my $options = {'output' => ['userid'], 'filter' => {'alias' => $alias}};

	my $result = $zabbix->get('user', $options);

	if ($result->{'error'})
	{
		if ($result->{'error'}->{'data'} =~ /Session terminated/)
		{
			print("Session terminated. Please re-run the same command again");
			print(" with option \"--server-id $server_id\"") if ($modified == 1);
			print(".\n");
		}
		else
		{
			print("Error: cannot get user \"$alias\". ", $result->{'error'}->{'data'}, "\n");

			if ($modified == 1)
			{
				print("Please fix the issue and re-run the same command with \"--server-id $server_id\"\n");
			}
		}

		exit(-1);
	}

	my $userid = $result->{'userid'};

	if (!$userid)
	{
		print("Error: user \"$alias\" not found on $server_key\n");

		if ($modified == 1)
		{
			print("Please fix the issue and re-run the same command with \"--server-id $server_id\"\n");
		}

		exit(-1);
	}

	return $userid;
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

	my $actions_specified = 0;

	foreach my $opt ('add', 'delete', 'modify')
	{
		$actions_specified++ if (opt($opt));
	}

	if ($actions_specified == 0)
	{
		push(@errors, "\tone of \"--add\", \"--delete\" or \"--modify\" must be specified");
	}
	elsif ($actions_specified != 1)
	{
		push(@errors, "\tonly one of \"--add\", \"--delete\" or \"--modify\" must be specified");
	}

	__opts_fail(@errors) if (0 != scalar(@errors));

	push(@errors, "\tuser name must be specified with \"--user\"") if (!opt('user'));

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
	elsif (opt('modify'))
	{
		foreach my $opt ('type', 'firstname', 'lastname')
		{
			push(@errors, "\toption \"--$opt\" is currently not supported with \"--modify\"") if (opt($opt));
		}

		push(@errors, "\tnew password must be specified with \"--password\"") if (!opt('password'));
	}

	__opts_fail(@errors) if (0 != scalar(@errors));
}

__END__

=head1 NAME

users.pl - manage users in Zabbix

=head1 SYNOPSIS

users.pl --add|--delete|--modify --user <user> [--type <ebero|tech|admin>] [--password <password>] [--firstname <firstname>] [--lastname <lastname>] [--server-id id] [--debug] [--help]

=head1 OPTIONS

=head2 REQUIRED OPTIONS

=over 8

=item B<--add>

Add a new user.

=item B<--delete>

Delete existing user.

=item B<--modify>

Change password of existing user. This option requires --password.

=item B<--user> user

Specify username of the user account.

=head2 REQUIRED OPTIONS FOR ADDING A USER OR CHANGING PASSWORD

=item B<--password> password

Specify user password.

=head2 REQUIRED OPTIONS FOR ADDING A USER

=item B<--type> type

Specify user type, accepted values: ebero, tech or admin.

=item B<--firstname> firstname

Specify first name of a user.

=item B<--lastname> lastname

Specify last name of a user.

=head2 OTHER OPTIONS

=item B<--server-id> id

Specify id of the server to continue the operation from. This option is useful when action was successful on part of the servers.

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
