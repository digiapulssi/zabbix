#!/usr/bin/perl

BEGIN
{
	our $MYDIR = $0; $MYDIR =~ s,(.*)/.*,$1,; $MYDIR = '.' if ($MYDIR eq $0);
}
use lib $MYDIR;

use strict;
use warnings;
use Getopt::Long;
use Data::Dumper;
use RSM;
use TLD_constants qw(:general :templates :groups :api :config);
use TLDs;

use constant DEFAULT_PROBE_PORT => 10051;

use constant true => 1;
use constant false => 0;

my %macros = ('{$RSM.EPP.ENABLED}' => 0, '{$RSM.IP4.ENABLED}' => 0, '{$RSM.IP6.ENABLED}' => 0, '{$RSM.RDDS.ENABLED}' => 0);

sub add_probe($$$$$$$$$$);
sub delete_probe($);
sub disable_probe($);
sub rename_probe($$);

sub is_not_empty($$);

sub validate_input;
sub usage;

my %OPTS;
my $rv = GetOptions(\%OPTS, "probe=s", "ip=s", "port=s", "new-name=s", "server-id=s",
			    "epp!", "ipv4!", "ipv6!", "rdds!", "resolver=s",
                	    "delete!", "disable!", "add!", "rename!",
			    "psk-identity=s", "psk=s",
                	    "verbose!", "quiet!", "help|?");

usage() if ($OPTS{'help'} or not $rv);

validate_input();

my $section = get_rsm_config()->{get_rsm_server_key($OPTS{'server-id'})};
pfail("server-id \"", $OPTS{'server-id'}, "\" not found in configuration file") unless (defined($section));

my $attempts = 3;
RELOGIN: zbx_connect($section->{'za_url'}, $section->{'za_user'}, $section->{'za_password'}, $OPTS{'verbose'});

if ($OPTS{'delete'})
{
	delete_probe($OPTS{'probe'});
}
elsif ($OPTS{'disable'})
{
	disable_probe($OPTS{'probe'});
}
elsif ($OPTS{'add'})
{
	my $result = create_macro('{$RSM.PROBE.MAX.OFFLINE}', '1h', undef);
	my $error = get_api_error($result);

	if (defined($error))
	{
		goto RELOGIN if (zbx_need_relogin($result) == true && $attempts-- > 0);
		pfail($error);
	}

	add_probe(
		$OPTS{'probe'},
		$OPTS{'ip'},
		$OPTS{'port'},
		$OPTS{'psk-identity'},
		$OPTS{'psk'},
		$OPTS{'epp'},
		$OPTS{'ipv4'},
		$OPTS{'ipv6'},
		$OPTS{'rdds'},
		$OPTS{'resolver'}
	);
}
elsif ($OPTS{'rename'})
{
	rename_probe($OPTS{'probe'}, $OPTS{'new-name'});
}

exit;

################

sub add_probe($$$$$$$$$$)
{
	my $probe_name = shift;
	my $probe_ip = shift;
	my $probe_port = shift;
	my $psk_identity = shift;
	my $psk = shift;
	my $epp = shift;
	my $ipv4 = shift;
	my $ipv6 = shift;
	my $rdds = shift;
	my $resolver = shift;

	print("Trying to add '$probe_name' probe...\n");
	print "The probe with name '$probe_name' already exists! Trying to enable it\n" if (probe_exists($probe_name));

    ###### Checking and creating required groups and templates

	print("Getting 'Template Proxy Health' template: ");
	my $probe_tmpl_health = create_probe_health_tmpl();
	is_not_empty($probe_tmpl_health, true);

    ########## Creating new Probe

	print("Creating '$probe_name' with interface $probe_ip:$probe_port ");
	my $probe = create_passive_proxy($probe_name, $probe_ip, $probe_port, $psk_identity, $psk);
	is_not_empty($probe, true);

    ########## Creating new Host Group

	print("Creating '$probe_name' host group: ");
	my $probe_groupid = create_group($probe_name);
	is_not_empty($probe_groupid, true);

    ########## Creating Probe template

	print("Creating '$probe_name' template: ");
	my $probe_tmpl = create_probe_template($probe_name, $epp, $ipv4, $ipv6, $rdds, $resolver);
	is_not_empty($probe_tmpl, true);

    ########## Creating Probe status template

	print("Creating '$probe_name' probe status template: ");
	my $root_servers_macros = update_root_servers();
	my $probe_status_templateid = create_probe_status_template($probe_name, $probe_tmpl, $root_servers_macros);
	is_not_empty($probe_status_templateid, true);

    ########## Creating Probe host

	print("Creating '$probe_name' host: ");
	my $probe_host = create_host({
		'groups'	=> [
			{
				'groupid'	=> PROBES_GROUPID
			}
		],
		'templates'	=> [
			{
				'templateid'	=> $probe_status_templateid
			},
			{
				'templateid'	=> APP_ZABBIX_PROXY_TEMPLATEID
			}
		],
		'host'		=> $probe_name,
		'status'	=> HOST_STATUS_MONITORED,
		'proxy_hostid'	=> $probe,
		'interfaces'	=> [
			DEFAULT_MAIN_INTERFACE
		]
	});

	is_not_empty($probe_host, true);

    ########## Creating Probe monitoring host

	print("Creating Probe monitoring host: ");
	my $probe_host_mon = create_host({
		'groups'	=> [
			{
				'groupid'	=> PROBES_MON_GROUPID
			}
		],
		'templates'	=> [
			{
				'templateid'	=> $probe_tmpl_health
			}
		],
		'host'		=> "$probe_name - mon",
		'status'	=> HOST_STATUS_MONITORED,
		'interfaces'	=> [
			{
				'type'	=> INTERFACE_TYPE_AGENT,
				'main'	=> true,
				'useip'	=> true,
				'ip'	=> $probe_ip,
				'dns'	=> '',
				'port'	=> '10050'
			}
		]
	});

	is_not_empty($probe_host_mon, true);

	create_macro('{$RSM.PROXY_NAME}', $probe_name, $probe_host_mon, true);

    ########## Creating TLD hosts for the Probe

	my $tld_list = get_host_group('TLDs', true, true);

	print("Creating TLD hosts for the Probe...\n");

	foreach my $tld (@{$tld_list->{'hosts'}})
	{
		my $tld_name = $tld->{'name'};
		my $tld_groupid = create_group("TLD $tld_name");
		my $tld_type = $tld->{'type'};

		my $main_templateid = create_template("Template $tld_name");

		print("Creating '$tld_name $probe_name' host for '$tld_name' TLD: ");

		my $tld_host = create_host({
			'groups'	=> [
				{
					'groupid'	=> $tld_groupid
				},
				{
					'groupid'	=> $probe_groupid
				},
				{
					'groupid'	=> TLD_PROBE_RESULTS_GROUPID
				},
				{
					'groupid'	=> TLD_TYPE_PROBE_RESULTS_GROUPIDS->{$tld_type}
				}
			],
			'templates'	=> [
				{
					'templateid'	=> $main_templateid
				},
				{
					'templateid'	=> $probe_tmpl
				}
			],
			'host'		=> "$tld_name $probe_name",
			'proxy_hostid'	=> $probe,
			'status'	=> HOST_STATUS_MONITORED,
			'interfaces'	=> [
				DEFAULT_MAIN_INTERFACE
			]
		});

		is_not_empty($tld_host, false);
	}

    ##########

	print("The probe has been added successfully\n");
}

sub delete_probe($) {
    my $probe_name = shift;

    my ($probe, $probe_hostgroup, $probe_host, $probe_host_mon, $probe_tmpl, $probe_tmpl_status);

    my ($result);

    print "Trying to remove '".$probe_name."' probe...\n";

    $probe = get_probe($probe_name, true);

    check_probe_data($probe, "Probe \"$probe_name\" was not found on server ID ".$OPTS{'server-id'}.". Use script \"probes-enabled.pl\" to list probes available in the system. Terminating...");

    $probe_host = get_host($probe_name, false);

    check_probe_data($probe_host, "The probe host is not found", false);

    $probe_host_mon = get_host($probe_name.' - mon', false);

    check_probe_data($probe_host_mon, "Probe monitoring host with name '$probe_name - mon' is not found", false);

    $probe_tmpl = get_template('Template '.$probe_name, true, false);

    check_probe_data($probe_tmpl, "Probe monitoring template with name 'Template $probe_name' is not found", false);

    $probe_tmpl_status = get_template('Template '.$probe_name.' Status', false, false);

    check_probe_data($probe_tmpl_status, "Probe Status monitoring template with name 'Template $probe_name Status' is not found", false);

    $probe_hostgroup = get_host_group($probe_name, false, false);

    check_probe_data($probe_hostgroup, "Host group with name '$probe_name' is not found", false);

    ########## Deleting probe template
    if (keys %{$probe_tmpl}) {
	my $templateid = $probe_tmpl->{'templateid'};
	my $template_name = $probe_tmpl->{'host'};

	print "Trying to remove '$template_name' probe template: ";

        $result = remove_templates([ $templateid ]);

	is_not_empty($result->{'templateids'}, false);
    }

    ########## Deleting probe template status
    if (keys %{$probe_tmpl_status}) {
	my $templateid = $probe_tmpl_status->{'templateid'};
        my $template_name = $probe_tmpl_status->{'host'};

	print "Trying to remove '$template_name' probe template status: ";

	$result = remove_templates([ $templateid ]);

	is_not_empty($result->{'templateids'}, false);
    }

    ########## Deleting probe host
#    if (keys %{$probe_host}) {
#        my $hostid = $probe_host->{'hostid'};
#        my $host_name = $probe_host->{'host'};
#
#        print "Trying to remove '$host_name' probe host: ";
#
#        $result = remove_hosts( [ $hostid ] );
#
#	is_not_empty($result->{'hostids'}, false);
#    }

    ########## Deleting TLDs and probe host linked to the Probe
    foreach my $host (@{$probe->{'hosts'}}) {
        my $host_name = $host->{'host'};
        my $hostid = $host->{'hostid'};

        print "Trying to remove '$host_name' host: ";

        $result = remove_hosts( [ $hostid ] );

	is_not_empty($result->{'hostids'}, false);
    }

    ########## Deleting probe status monitoring host linked to the Probe
    if (keys %{$probe_host_mon}) {
	my $host_name = $probe_host_mon->{'host'};
	my $hostid = $probe_host_mon->{'hostid'};

	print "Trying to delete '$host_name' host: ";

	$result = remove_hosts( [ $hostid ] );

	is_not_empty($result->{'hostids'}, false);
    }

    ########## Deleting Probe group
    if (keys %{$probe_hostgroup}) {
	my $hostgroupid = $probe_hostgroup->{'groupid'};

	print "Trying to remove '$probe_name' host group: ";

        $result = remove_hostgroups( [ $hostgroupid ] );

	is_not_empty($result->{'groupids'}, false);
    }

    ########## Deleting Probe
    print "Trying to remove '$probe_name' Probe: ";

    $result = remove_probes( [ $probe->{'proxyid'} ] );

    is_not_empty($result->{'proxyids'}, false);

    ##########

    print "The probe has been removed successfully\n";
}

sub disable_probe($) {
    my $probe_name = shift;

    my ($probe, $probe_hostgroup, $probe_host, $probe_host_mon, $probe_tmpl, $probe_tmpl_status);

    my ($result);

    print "Trying to disable '".$probe_name."' probe...\n";

    $probe = get_probe($probe_name, true);

    check_probe_data($probe, "Probe \"$probe_name\" was not found on server ID ".$OPTS{'server-id'}.". Use script \"probes-enabled.pl\" to list probes available in the system. Terminating...");

    $probe_host = get_host($probe_name, false);

    check_probe_data($probe_host, "The probe host is not found", false);

    $probe_host_mon = get_host($probe_name.' - mon', false);

    check_probe_data($probe_host_mon, "Probe monitoring host with name '$probe_name - mon' is not found", false);

    $probe_tmpl = get_template('Template '.$probe_name, true, false);

    check_probe_data($probe_tmpl, "Probe monitoring template with name 'Template $probe_name' is not found", false);

    $probe_tmpl_status = get_template('Template '.$probe_name.' Status', false, false);

    check_probe_data($probe_tmpl_status, "Probe Status monitoring template with name 'Template $probe_name Status' is not found", false);


    ########## Disabling TLDs linked to the probe and Probe monitoring host

    foreach my $host (@{$probe->{'hosts'}}) {
	my $host_name = $host->{'host'};
	my $hostid = $host->{'hostid'};

	print "Trying to disable '$host_name' host: ";

	$result = disable_host($hostid);

	is_not_empty($result->{'hostids'}, false);
    }

    ########## Disabling probe host
    if (defined($probe_host->{'host'})) {
	my $host_name = $probe_host->{'host'};
	my $hostid = $probe_host->{'hostid'};

	print "Trying to disable '$host_name' host: ";

	$result = disable_host($hostid);

	is_not_empty($result->{'hostids'}, false);
    }

    ########## Disabling probe monitoring host
    if (defined($probe_host_mon->{'host'})) {
	my $host_name = $probe_host_mon->{'host'};
	my $hostid = $probe_host_mon->{'hostid'};

	print "Trying to disable '$host_name' host: ";

	$result = disable_host($hostid);

	is_not_empty($result->{'hostids'}, false);
    }

    ########## Disabling all services on the Probe
    foreach my $macro (@{$probe_tmpl->{'macros'}}) {
	my $macro_name = $macro->{'macro'};
	my $hostmacroid = $macro->{'hostmacroid'};

	next unless (defined($macros{$macro_name}));

	print "Disabling macro '$macro_name': ";

	$result = macro_value($hostmacroid, $macros{$macro_name});

	is_not_empty($result->{'hostmacroids'}, false);
    }

    ########## Move the Probe to active mode

    print "Disabling '$probe_name' Probe: ";

    $result = set_proxy_status( $probe->{'proxyid'}, HOST_STATUS_PROXY_ACTIVE);

    is_not_empty($result->{'proxyids'}, false);

    ##########

    print "The probe has been disabled successfully\n";
}

sub rename_probe($$) {
    my $old_name = shift;
    my $new_name = shift;

    my ($result, $probe, $probe_host, $probe_host_mon, $probe_tmpl, $probe_tmpl_status, $probe_hostgroup, $probe_macro);

    print "Trying to rename '".$old_name."' probe...\n";

    $probe = get_probe($old_name, true);

    check_probe_data($probe, "Probe \"$old_name\" was not found on server ID ".$OPTS{'server-id'}.". Use script \"probes-enabled.pl\" to list probes available in the system. Terminating...");

    $probe_host = get_host($old_name, false);

    check_probe_data($probe_host, "The probe host is not found", false);

    $probe_host_mon = get_host($old_name.' - mon', false);

    check_probe_data($probe_host_mon, "Probe monitoring host with name '$old_name - mon' is not found", false);

# check arguments
    $probe_tmpl = get_template('Template '.$old_name, false, false);

    check_probe_data($probe_tmpl, "Probe monitoring template with name 'Template $old_name' is not found", false);

    $probe_tmpl_status = get_template('Template '.$old_name.' Status', false, false);

    check_probe_data($probe_tmpl_status, "Probe Status monitoring template with name 'Template $old_name Status' is not found", false);

    $probe_hostgroup = get_host_group($old_name, false, false);

    check_probe_data($probe_hostgroup, "Host group with name '$old_name' is not found", false);
    
    $probe_macro = get_host_macro($probe_host_mon->{'hostid'}, '{$RSM.PROXY_NAME}');
    
    check_probe_data($probe_macro, "Host group with name '{\$RSM.PROXY_NAME}' is not found", false);

    print "Trying to rename '$old_name' probe: ";
    $result = rename_proxy($probe->{'proxyid'}, $new_name);
    is_not_empty($result->{'proxyids'}, false);

    ########## Renaming TLDs linked to the probe and Probe monitoring host
    foreach my $host (@{$probe->{'hosts'}}) {
	my $host_name = $host->{'host'};
	my $hostid = $host->{'hostid'};
	
	print "Trying to rename '$host_name' host: ";
	
	if ($host_name=~/(.+)\s$old_name$/) {
	    $host_name = $1." ".$new_name;
	}
	else {
	    $host_name = $new_name
	}

	$result = rename_host($hostid, $host_name);
	
	is_not_empty($result->{'hostids'}, false);
    }

    ########## Renaming probe monitoring host
    if (defined($probe_host_mon->{'host'})) {
	my $host_name = $probe_host_mon->{'host'};
	my $hostid = $probe_host_mon->{'hostid'};

	print "Trying to rename '$host_name' host: ";

	$result = rename_host($hostid, $new_name." - mon");

	is_not_empty($result->{'hostids'}, false);
    }

    my $template_name = $probe_tmpl->{'host'};
    print "Trying to rename '$template_name' template: ";
    $result = rename_template($probe_tmpl->{'templateid'}, 'Template '.$new_name);
    is_not_empty($result->{'templateids'}, false);

    $template_name = $probe_tmpl_status->{'host'};
    print "Trying to rename '$template_name' template: ";
    $result = rename_template($probe_tmpl_status->{'templateid'}, 'Template '.$new_name.' Status');
    is_not_empty($result->{'templateids'}, false);

    print "Trying to rename '$old_name' host group: ";
    $result = rename_hostgroup($probe_hostgroup->{'groupid'}, $new_name);
    is_not_empty($result->{'groupids'}, false);
    
    print "Trying to update '{\$RSM.PROXY_NAME}' macro on '$new_name - mon' host: ";
    $result = macro_value($probe_macro->{'hostmacroid'}, $new_name);
    is_not_empty($result->{'hostmacroids'}, false);
    
    # rsm_probes table?
    print "The probe has been renamed successfully\n";
}

sub check_probe_data($) {
    my $data = shift;
    my $message = shift;
    my $do_exit = shift;

    unless (keys %{$data}) {
	print $message."\n";
	exit(1) if !defined($do_exit) or $do_exit != false;
    }

    return true;
}

##############

sub is_not_empty($$) {
    my $var = shift;
    my $do_exit = shift;

    if (defined($var) and scalar($var)) {
        print "success\n";
    }
    else {
        print "failed\n";
        exit(1) unless ($do_exit == false);
    }
}

##############

sub usage {
    my ($opt_name, $opt_value) = @_;

    print <<EOF;

    Usage: $0 [options]

Required options

        --probe=STRING
                PROBE name
	--server-id=NUM
		ID of Zabbix server specified in /opt/zabbix/rsm.conf
Other options

        --delete
                delete specified Probe
                (default: off)
        --disable
		disable specified Probe
                (default: on)
	--add
		add new probe with specified name and options
		(default: off)
	--rename
		rename existing probe to specified name in --new-name argument
		(default: off)

Options for adding new probe. Argument --add.
	--ip
		IP of new probe node
		(default: empty)
	--port
		Port of new probe node
		(default: 10050)
	--psk-identity
		PSK identity
		(default: name of the Probe)
	--psk
		The preshared key, at least 32 hex digits
		(default: empty)
	--epp
		Enable EPP support for the Probe
		(default: disabled)
	--ipv4
		Enable IPv4 support for the Probe
		(default: disabled)
	--ipv6
		Enable IPv6 support for the Probe
		(default: disabled)
	--rdds
		Enable RDDS support for the Probe
		(default: disabled)
	--resolver
		The name of resolver
		(default: 127.0.0.1)

Options for renaming probe. Argument --rename.
	--new-name
		New probe name
		(default: empty)
EOF
exit(1);
}

sub validate_input
{
	my @possible_actions = ('disable', 'add', 'delete', 'rename');
	my $actions_chosen = 0;
	my $msg = "";

	$msg .= "Probe name must be specified (--probe)\n" unless (defined($OPTS{'probe'}));
	$msg .= "Server id must be specified (--server-id)\n" unless (defined($OPTS{'server-id'}));

	foreach my $action (@possible_actions)
	{
		$actions_chosen++ if (defined($OPTS{$action}));
	}

	if ($actions_chosen == 0)
	{
		$msg .= "At least one option --" . join(", --", @possible_actions) . " must be specified\n";
	}
	elsif ($actions_chosen > 1)
	{
		$msg .= "You need to choose only one option from --" . join(", --", @possible_actions) . "\n";
	}
	elsif (defined($OPTS{'add'}))
	{
		$msg .= "You need to specify Probe IP using --ip argument\n" unless (defined($OPTS{'ip'}));

		$OPTS{'port'} //= DEFAULT_PROBE_PORT;
		$OPTS{'resolver'} //= '127.0.0.1';
		$OPTS{'psk-identity'} //= $OPTS{'probe'} if (defined($OPTS{'psk'}));

		foreach my $option (('epp', 'rdds', 'ipv4', 'ipv6'))
		{
			$OPTS{$option} //= 0;
		}
	}
	elsif (defined($OPTS{'rename'}))
	{
		$msg .= "You need to specify new Probe node name using --new-name argument\n" unless (defined($OPTS{'new-name'}));
	}

	unless ($msg eq "")
	{
		print($msg);
		usage();
	}
}
