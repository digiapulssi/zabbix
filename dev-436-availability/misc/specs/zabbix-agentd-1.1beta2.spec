%define debug_package %{nil}

%define _prefix		/usr/local/zabbix

Name:		zabbix-agentd
Version:	1.1beta2
Release:	1
Group:		System Environment/Daemons
License:	GPL
Summary:	ZABBIX network monitor agent
Vendor:		ZABBIX SIA
URL:		http://www.zabbix.org
Packager:	Eugene Grigorjev <eugene.grigorjev@zabbix.com>
Source:		zabbix-1.1beta2.tar.gz

Autoreq:	no
Buildroot: 	%{_tmppath}/%{name}-%{version}-%{release}-buildroot

#Prefix:		%{_prefix}

%define zabbix_bindir	%{_prefix}/bin
%define zabbix_confdir	%{_prefix}/conf
%define zabbix_initdir	%{_prefix}/init.d
%define zabbix_docdir	%{_prefix}/doc
#%define zabbix_piddir	%{_tmppath}
#%define zabbix_logdir	%{_tmppath}

%define zabbix_piddir	/var/tmp
%define zabbix_logdir	/tmp

%description
The ZABBIX agent is a network monitor

%prep
%setup -n zabbix-1.1beta2

%build
%configure --enable-agent
make

%clean
rm -fr $RPM_BUILD_ROOT

%install
rm -fr $RPM_BUILD_ROOT

# copy documentation
install -d %{buildroot}%{zabbix_docdir}
install -m 644 AUTHORS %{buildroot}%{zabbix_docdir}/AUTHORS
install -m 644 COPYING %{buildroot}%{zabbix_docdir}/COPYING
install -m 644 NEWS %{buildroot}%{zabbix_docdir}/NEWS
install -m 644 README %{buildroot}%{zabbix_docdir}/README

# copy binaries
install -d %{buildroot}%{zabbix_bindir}
install -s -m 755 src/zabbix_agent/zabbix_agentd %{buildroot}%{zabbix_bindir}/zabbix_agentd

# copy config files
install -d %{buildroot}%{zabbix_confdir}
install -m 755 misc/conf/zabbix_agentd.conf %{buildroot}%{zabbix_confdir}/zabbix_agentd.conf

# copy startup script
install -d %{buildroot}%{zabbix_initdir}
install -m 755 misc/init.d/redhat/8.0/zabbix_agentd %{buildroot}%{zabbix_initdir}/zabbix_agentd_ctl

%post
# create ZABBIX group
if [ -z "`grep zabbix /etc/group`" ]; then
  /usr/sbin/groupadd zabbix >/dev/null 2>&1
fi

# create ZABBIX uzer
if [ -z "`grep zabbix /etc/passwd`" ]; then
  /usr/sbin/useradd -g zabbix zabbix >/dev/null 2>&1
fi

# configure ZABBIX agent daemon
TMP_FILE=`mktemp $TMPDIR/zbxtmpXXXXXX`

sed	-e "s#Hostname=localhost#Hostname=`uname -n`#g" \
	-e "s#PidFile=/var/tmp/zabbix_agentd.pid#PidFile=%{zabbix_piddir}/zabbix_agentd.pid#g" \
	-e "s#LogFile=/tmp/zabbix_agentd.log#LogFile=%{zabbix_logdir}/zabbix_agentd.log#g" \
	%{zabbix_confdir}/zabbix_agentd.conf > $TMP_FILE
cat $TMP_FILE > %{zabbix_confdir}/zabbix_agentd.conf

sed	-e "s#progdir=\"/usr/local/zabbix/bin/\"#USER=zabbix; progdir=\"%{zabbix_bindir}/\"; conffile=\"%{zabbix_confdir}/zabbix_agentd.conf\"#g" \
	-e "s#su -c \$progdir\$prog - \$USER#su -c \"\$progdir\$prog -c \$conffile\" - \$USER#g" \
	%{zabbix_initdir}/zabbix_agentd_ctl > $TMP_FILE
cat $TMP_FILE > %{zabbix_initdir}/zabbix_agentd_ctl

rm -f $TMP_FILE

%postun
rm -f %{zabbix_piddir}/zabbix_agentd.pid
rm -f %{zabbix_logdir}/zabbix_agentd.log

%files
%dir %attr(0755,root,root) %{zabbix_docdir}
%attr(0644,root,root) %{zabbix_docdir}/AUTHORS
%attr(0644,root,root) %{zabbix_docdir}/COPYING
%attr(0644,root,root) %{zabbix_docdir}/NEWS
%attr(0644,root,root) %{zabbix_docdir}/README

%dir %attr(0755,root,root) %{zabbix_confdir}
%attr(0644,root,root) %config(noreplace) %{zabbix_confdir}/zabbix_agentd.conf

%dir %attr(0755,root,root) %{zabbix_bindir}
%attr(0755,root,root) %{zabbix_bindir}/zabbix_agentd

%dir %attr(0755,root,root) %{zabbix_initdir}
%attr(0755,root,root) %{zabbix_initdir}/zabbix_agentd_ctl

%changelog
* Thu Dec 01 2005 Eugene Grigorjev <eugene.grigorjev@zabbix.com>
- 1.1beta2
- initial packaging

