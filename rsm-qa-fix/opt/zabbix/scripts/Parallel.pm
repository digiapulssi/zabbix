package Parallel;

use strict;
use warnings;

use POSIX qw(:sys_wait_h);
use IO::Pipe;
use Exporter qw(import);
use Time::HiRes qw(time);
use Data::Dumper;

use POSIX;
require "syscall.ph";

syscall(&SYS_prctl, 36, 1) >= 0 or die("cannot set subreaper: $!");

my $_CHILD_FAILED = 0;

our @EXPORT = qw(fork_without_pipe fork_with_pipe handle_children print_children children_running set_max_children
		child_failed kill_processes);

my $MAX_CHILDREN = 64;
my %PIDS;

my $signal = 'KILL';

# SIGCHLD handler
$SIG{CHLD} = sub
{
        while ((my $pid = waitpid(-1, WNOHANG)) > 0)
	{
		my $child_exit_status = WEXITSTATUS($?);

		$PIDS{$pid}{'alive'} = 0 if ($PIDS{$pid});

		$_CHILD_FAILED = 1 if ($child_exit_status != 0);
        }
};

sub fork_without_pipe
{
	return undef if (children_running() >= $MAX_CHILDREN);

	my $pid = fork();

	die("fork() failed: $!") unless (defined($pid));

	if ($pid)
	{
		# parent
		$PIDS{$pid}{'alive'} = 1;
	}
	else
	{
		# child
		undef(%PIDS);
	}

	return $pid;
}

sub fork_with_pipe
{
	my $setfh_ref = shift;

	return undef if (children_running() >= $MAX_CHILDREN);

	my $pipe = IO::Pipe->new();

	my $pid = fork();

	die("fork() failed: $!") unless (defined($pid));

	if ($pid)
	{
		# parent
		my $fh = $pipe->reader();
		$fh->blocking(0);	# set non-blocking I/O

		$PIDS{$pid}{'alive'} = 1;
		$PIDS{$pid}{'pipe'} = $pipe;

		return $pid;
	}

	# child
	undef(%PIDS);

	my $fh = $pipe->writer();

	$setfh_ref->($fh) if ($setfh_ref);

	return $pid;
}

sub handle_children
{
	foreach my $pid (keys(%PIDS))
	{
		if (my $pipe = $PIDS{$pid}{'pipe'})
		{
			while (my $line = $pipe->getline())
			{
				print($line);
			}
		}

		delete($PIDS{$pid}) unless ($PIDS{$pid}{'alive'});
	}
}

sub print_children
{
	my $print_sub = shift;

	my $alive = 0;
	my $dead = 0;

	foreach my $pid (keys(%PIDS))
        {
		if ($PIDS{$pid}{'alive'} != 0)
		{
			$alive++;
		}
		else
		{
			$dead++;
		}
	}

	my $msg = "children: alive:$alive dead:$dead";

	if ($print_sub)
	{
		$print_sub->($msg);
	}
	else
	{
		print("$msg\n");
	}
}

sub children_running
{
	return scalar(keys(%PIDS));
}

sub set_max_children
{
	my $value = shift;

	if (!$value)
	{
		open(my $fh, '/proc/cpuinfo') or die("cannot open \"/proc/cpuinfo\": $!\n");
		$value = scalar(map /^processor/, <$fh>);
		close($fh);
	}

	$MAX_CHILDREN = $value;
}

sub child_failed()
{
	return $_CHILD_FAILED;
}

sub kill_processes()
{
	foreach my $running_pid (keys(%PIDS))
	{
		next unless ($PIDS{$running_pid}{'alive'});

		kill($signal, $running_pid);
	}
}

1;
