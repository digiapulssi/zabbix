Name:		zabbix
Version:	3.0.10
Release: 	1%{?alphatag:.%{alphatag}}%{?dist}
Summary:	The Enterprise-class open source monitoring solution
Group:		Applications/Internet
License:	GPLv2+
URL:		http://www.zabbix.com/
Source0:	zabbix-%{version}%{?alphatag:%{alphatag}}.tar.gz
Source1:	selinux
Source3:	zabbix-logrotate.in
Source6:	zabbix-server.init
Source7:	zabbix-proxy.init
Source11:	zabbix-server.service
Source12:	zabbix-proxy.service
Source15:	zabbix-tmpfiles.conf
Source16:	partitioning.sql
Source17:	zbx_vhost.conf
Source18:	zbx_php.conf
Source19:	nginx.conf
Source20:	rsyslog.d-rsm.slv.conf
Source21:	zabbix_server.conf
Source22:	zabbix_proxy_common.conf
Source23:	zabbix_proxy_N.conf
Source24:	zabbix-slv-logrotate
Source25:	cron.d
Patch0:		config.patch
Patch1:		fonts-config.patch
Patch2:		fping3-sourceip-option.patch

Buildroot:	%{_tmppath}/zabbix-%{version}-%{release}-root-%(%{__id_u} -n)

%if 0%{?rhel} >=6
%define build_server 1
%endif

%global selinuxtype	targeted
%global moduletype	services

%global modulenames	zabbix_proxy zabbix_server zabbix_agent zbx_php-fpm zbx_nginx
# Version of distribution SELinux policy package.
%global selinux_policyver	3.13.1-102.el7_3.13

%global _format() export %1=""; for x in %{modulenames}; do %1+=%2; %1+=" "; done;

# Relabel files
%global relabel_files() \ # ADD files in *.fc file

BuildRequires:	mysql-devel
BuildRequires:	ldns-devel >= 1.6.17
BuildRequires:	curl-devel >= 7.13.1
%if 0%{?rhel} >= 6
BuildRequires:	openssl-devel >= 1.0.1
%endif
%if 0%{?rhel} >= 7
BuildRequires:	systemd
%endif
BuildRequires:	selinux-policy selinux-policy-devel

%description
Zabbix is the ultimate enterprise-level software designed for
real-time monitoring of millions of metrics collected from tens of
thousands of servers, virtual machines and network devices.

%package proxy-mysql
Summary:			Zabbix proxy for MySQL or MariaDB database
Group:				Applications/Internet
%if 0%{?rhel} >= 7
Requires(post):		systemd
Requires(preun):	systemd
Requires(postun):	systemd
%else
Requires(post):		/sbin/chkconfig
Requires(preun):	/sbin/chkconfig
Requires(preun):	/sbin/service
Requires(postun):	/sbin/service
%endif
Requires:		ldns >= 1.6.17
Provides:		zabbix-proxy = %{version}-%{release}
Provides:		zabbix-proxy-implementation = %{version}-%{release}
Obsoletes:		zabbix
Obsoletes:		zabbix-proxy

%description proxy-mysql
Zabbix proxy with MySQL or MariaDB database support.

%package proxy-mysql-selinux
Summary:		SELinux Policies for Zabbix proxy
Group:			System Environment/Base
Requires(post):		selinux-policy-base >= %{selinux_policyver}, selinux-policy-targeted >= %{selinux_policyver}, policycoreutils, policycoreutils-python libselinux-utils
Requires:		zabbix-proxy = %{version}-%{release}

%description proxy-mysql-selinux
SELinux policy modules for use with Zabbix proxy

%if 0%{?build_server}
%package server-mysql
Summary:			Zabbix server for MySQL or MariaDB database
Group:				Applications/Internet
Requires:			fping
%if 0%{?rhel} >= 7
Requires(post):		systemd
Requires(preun):	systemd
Requires(postun):	systemd
%else
Requires(post):		/sbin/chkconfig
Requires(preun):	/sbin/chkconfig
Requires(preun):	/sbin/service
Requires(postun):	/sbin/service
%endif
Requires:		ldns >= 1.6.17
Provides:		zabbix-server = %{version}-%{release}
Provides:		zabbix-server-implementation = %{version}-%{release}
Obsoletes:		zabbix
Obsoletes:		zabbix-server

%description server-mysql
Zabbix server with MySQL or MariaDB database support.

%package server-mysql-selinux
Summary:		SELinux Policies for Zabbix server
Group:			System Environment/Base
Requires(post):		selinux-policy-base >= %{selinux_policyver}, selinux-policy-targeted >= %{selinux_policyver}, policycoreutils, policycoreutils-python libselinux-utils
Requires:		zabbix-server = %{version}-%{release}

%description server-mysql-selinux
SELinux policy modules for use with Zabbix server


%package web
Summary:			Zabbix web frontend common package
Group:				Application/Internet
BuildArch:			noarch
%if 0%{?rhel} >= 7
Requires:			nginx
Requires:			php-fpm >= 5.4
Requires:			php-gd
Requires:			php-bcmath
Requires:			php-mbstring
Requires:			php-xml
Requires:			php-ldap
%endif
Requires:			dejavu-sans-fonts
Requires:			zabbix-web-database = %{version}-%{release}
Requires(post):		%{_sbindir}/update-alternatives
Requires(preun):	%{_sbindir}/update-alternatives

%description web
Zabbix web frontend common package

%package web-mysql
Summary:			Zabbix web frontend for MySQL
Group:				Applications/Internet
BuildArch:			noarch
%if 0%{?rhel} >= 7
Requires:			php-mysqlnd
%endif
Requires:			zabbix-web = %{version}-%{release}
Provides:			zabbix-web-database = %{version}-%{release}

%description web-mysql
Zabbix web frontend for MySQL

%package web-mysql-selinux
Summary:		SELinux Policies for Zabbix web frontend
Group:			System Environment/Base
BuildArch:		noarch
Requires(post):		selinux-policy-base >= %{selinux_policyver}, selinux-policy-targeted >= %{selinux_policyver}, policycoreutils, policycoreutils-python libselinux-utils
Requires:		zabbix-web-mysql = %{version}-%{release}

%description web-mysql-selinux
SELinux policy modules for use with Zabbix web frontend
%endif

%package agent-selinux
Summary:		SELinux Policies for Zabbix agent
Group:			System Environment/Base
BuildArch:		noarch
Requires(post):		selinux-policy-base >= %{selinux_policyver}, selinux-policy-targeted >= %{selinux_policyver}, policycoreutils, policycoreutils-python libselinux-utils
Requires:		zabbix-agent

%description agent-selinux
SELinux policy modules for use with Zabbix agent

%package scripts
Summary:			Zabbix scripts for RSM
Group:				Applications/Internet
BuildArch:			noarch
%if 0%{?rhel} >= 7
Requires:			perl-Data-Dumper, perl-DBD-MySQL, perl-Sys-Syslog
Requires:			perl-DateTime, perl-Config-Tiny, perl-libwww-perl
Requires:			perl-LWP-Protocol-https, perl-JSON-XS, perl-Expect
Requires:			perl-Redis, perl-File-Pid, perl-DateTime-Format-RFC3339
Requires:			perl-Text-CSV_XS, perl-Types-Serialiser
Requires:			perl-Path-Tiny
%endif
AutoReq:			no

%description scripts
Zabbix scripts for RSM

%prep
%setup0 -q -n zabbix-%{version}%{?alphatag:%{alphatag}}
%patch0 -p1
%patch1 -p1
%if 0%{?rhel} >= 7
%patch2 -p1
%endif

cp -r %{SOURCE1}/ ./
cp -r %{SOURCE25}/ ./

# traceroute command path for global script
sed -i -e 's|/usr/bin/traceroute|/bin/traceroute|' database/mysql/data.sql

%if 0%{?build_server}
# copy sql files for servers
cat database/mysql/schema.sql > database/mysql/create.sql
cat database/mysql/images.sql >> database/mysql/create.sql
cat database/mysql/data.sql >> database/mysql/create.sql
cat %{SOURCE16} >> database/mysql/create.sql
gzip database/mysql/create.sql

cp %{SOURCE19} frontends/nginx.conf
%endif

# sql files for proxyes
gzip database/mysql/schema.sql

%build
build_flags="
	-q
	--enable-dependency-tracking
	--sysconfdir=/etc/zabbix
	--libdir=%{_libdir}/zabbix
	--with-openssl
	--with-libcurl
	--enable-proxy
	--enable-ipv6
"

%if 0%{?rhel} >=6
build_flags="$build_flags --with-openssl"
%endif

%if 0%{?build_server}
build_flags="$build_flags --enable-server"
%endif

CFLAGS="$RPM_OPT_FLAGS -fPIC -pie -Wl,-z,relro -Wl,-z,now"
CXXFLAGS="$RPM_OPT_FLAGS -fPIC -pie -Wl,-z,relro -Wl,-z,now"

export CFLAGS
export CXXFLAGS
%configure $build_flags --with-mysql --enable-dbtls
make -s %{?_smp_mflags}
%if 0%{?build_server}
mv src/zabbix_server/zabbix_server src/zabbix_server/zabbix_server_mysql
%endif
mv src/zabbix_proxy/zabbix_proxy src/zabbix_proxy/zabbix_proxy_mysql

%if 0%{?build_server}
touch src/zabbix_server/zabbix_server
%endif
touch src/zabbix_proxy/zabbix_proxy

cd selinux && make SHARE="%{_datadir}" TARGETS="%{modulenames}"

%install

rm -rf $RPM_BUILD_ROOT

# install
make DESTDIR=$RPM_BUILD_ROOT install

# install necessary directories
mkdir -p $RPM_BUILD_ROOT%{_localstatedir}/log/zabbix
%if 0%{?build_server}
mkdir -p $RPM_BUILD_ROOT%{_localstatedir}/log/zabbix/slv
%endif
mkdir -p $RPM_BUILD_ROOT%{_localstatedir}/run/zabbix

# install server and proxy binaries
%if 0%{?build_server}
install -m 0755 -p src/zabbix_server/zabbix_server_* $RPM_BUILD_ROOT%{_sbindir}/
rm $RPM_BUILD_ROOT%{_sbindir}/zabbix_server
%endif
install -m 0755 -p src/zabbix_proxy/zabbix_proxy_* $RPM_BUILD_ROOT%{_sbindir}/
rm $RPM_BUILD_ROOT%{_sbindir}/zabbix_proxy
rm $RPM_BUILD_ROOT%{_sysconfdir}/zabbix/zabbix_proxy.conf

# install scripts and modules directories
mkdir -p $RPM_BUILD_ROOT/usr/lib/zabbix
%if 0%{?build_server}
mv $RPM_BUILD_ROOT%{_datadir}/zabbix/alertscripts $RPM_BUILD_ROOT/usr/lib/zabbix
%endif
mv $RPM_BUILD_ROOT%{_datadir}/zabbix/externalscripts $RPM_BUILD_ROOT/usr/lib/zabbix
mkdir $RPM_BUILD_ROOT%{_libdir}/zabbix/modules

%if 0%{?build_server}
# install frontend files
find frontends/php -name '*.orig' | xargs rm -f
cp -a frontends/php/* $RPM_BUILD_ROOT%{_datadir}/zabbix
mkdir -p $RPM_BUILD_ROOT%{_sharedstatedir}/php/session

# install frontend configuration files
mkdir -p $RPM_BUILD_ROOT%{_sysconfdir}/zabbix/web
touch $RPM_BUILD_ROOT%{_sysconfdir}/zabbix/web/zabbix.conf.php
mv $RPM_BUILD_ROOT%{_datadir}/zabbix/conf/maintenance.inc.php $RPM_BUILD_ROOT%{_sysconfdir}/zabbix/web/
%endif

# drop config files in place
%if 0%{?rhel} >= 7
install -Dm 0644 -p %{SOURCE17} $RPM_BUILD_ROOT%{_sysconfdir}/nginx/conf.d/zbx_vhost.conf
install -Dm 0644 -p %{SOURCE18} $RPM_BUILD_ROOT%{_sysconfdir}/php-fpm.d/zabbix.conf
%endif


# install configuration files
mv $RPM_BUILD_ROOT%{_sysconfdir}/zabbix/zabbix_proxy.conf.d $RPM_BUILD_ROOT%{_sysconfdir}/zabbix/zabbix_proxy.d
%if 0%{?build_server}
mv $RPM_BUILD_ROOT%{_sysconfdir}/zabbix/zabbix_server.conf.d $RPM_BUILD_ROOT%{_sysconfdir}/zabbix/zabbix_server.d
%endif

%if 0%{?build_server}
mkdir -p $RPM_BUILD_ROOT%{_sysconfdir}/rsyslog.d
cp %{SOURCE20} $RPM_BUILD_ROOT%{_sysconfdir}/rsyslog.d/rsm.slv.conf
cp %{SOURCE21} $RPM_BUILD_ROOT%{_sysconfdir}/zabbix/zabbix_server.conf
%endif

cp %{SOURCE22} $RPM_BUILD_ROOT%{_sysconfdir}/zabbix/zabbix_proxy_common.conf
cp %{SOURCE23} $RPM_BUILD_ROOT%{_sysconfdir}/zabbix/zabbix_proxy_N.conf

# install logrotate configuration files
mkdir -p $RPM_BUILD_ROOT%{_sysconfdir}/logrotate.d
%if 0%{?build_server}
cat %{SOURCE3} | sed \
	-e 's|COMPONENT|server|g' \
	> $RPM_BUILD_ROOT%{_sysconfdir}/logrotate.d/zabbix-server
%endif
cat %{SOURCE3} | sed \
	-e 's|COMPONENT|proxy*|g' \
	> $RPM_BUILD_ROOT%{_sysconfdir}/logrotate.d/zabbix-proxy

# install startup scripts
%if 0%{?rhel} >= 7
%if 0%{?build_server}
install -Dm 0644 -p %{SOURCE11} $RPM_BUILD_ROOT%{_unitdir}/zabbix-server.service
%endif
install -Dm 0644 -p %{SOURCE12} $RPM_BUILD_ROOT%{_unitdir}/zabbix-proxy.service
%else
%if 0%{?build_server}
install -Dm 0755 -p %{SOURCE6} $RPM_BUILD_ROOT%{_sysconfdir}/init.d/zabbix-server
%endif
install -Dm 0755 -p %{SOURCE7} $RPM_BUILD_ROOT%{_sysconfdir}/init.d/zabbix-proxy
%endif

# install systemd-tmpfiles conf
%if 0%{?rhel} >= 7
%if 0%{?build_server}
install -Dm 0644 -p %{SOURCE15} $RPM_BUILD_ROOT%{_prefix}/lib/tmpfiles.d/zabbix-server.conf
%endif
install -Dm 0644 -p %{SOURCE15} $RPM_BUILD_ROOT%{_prefix}/lib/tmpfiles.d/zabbix-proxy.conf
%endif

# Install policy modules
%_format MODULES selinux/$x.pp.bz2
echo $MODULES
install -d %{buildroot}%{_datadir}/selinux/packages
install -m 0644 $MODULES \
    %{buildroot}%{_datadir}/selinux/packages

install -d %{buildroot}/opt/zabbix/
cp -r opt/zabbix/* %{buildroot}/opt/zabbix/

install -d $RPM_BUILD_ROOT%{_sysconfdir}/cron.d/
cp -r cron.d/* $RPM_BUILD_ROOT%{_sysconfdir}/cron.d/

install -Dm 0644 -p %{SOURCE24} $RPM_BUILD_ROOT%{_sysconfdir}/logrotate.d/zabbix-slv

%clean
rm -rf $RPM_BUILD_ROOT

%pre proxy-mysql
getent group zabbix > /dev/null || groupadd -r zabbix
getent passwd zabbix > /dev/null || \
	useradd -r -g zabbix -d %{_localstatedir}/lib/zabbix -s /sbin/nologin \
	-c "Zabbix Monitoring System" zabbix
:

%if 0%{?build_server}
%pre server-mysql
getent group zabbix > /dev/null || groupadd -r zabbix
getent passwd zabbix > /dev/null || \
	useradd -r -g zabbix -d %{_localstatedir}/lib/zabbix -s /sbin/nologin \
	-c "Zabbix Monitoring System" zabbix
mkdir -p %{_localstatedir}/lib/zabbix
chown -R zabbix:zabbix %{_localstatedir}/lib/zabbix
:
%endif

%pre scripts
getent group zabbix > /dev/null || groupadd -r zabbix
getent passwd zabbix > /dev/null || \
	useradd -r -g zabbix -d %{_localstatedir}/lib/zabbix -s /sbin/nologin \
	-c "Zabbix Monitoring System" zabbix
:

%post proxy-mysql
%if 0%{?rhel} >= 7
%systemd_post zabbix-proxy.service
%else
/sbin/chkconfig --add zabbix-proxy
%endif
/usr/sbin/update-alternatives --install %{_sbindir}/zabbix_proxy \
	zabbix-proxy %{_sbindir}/zabbix_proxy_mysql 10
:

%post proxy-mysql-selinux
%{_sbindir}/semodule -n -s %{selinuxtype} -i %{_datadir}/selinux/packages/zabbix_agent.pp.bz2
%{_sbindir}/semodule -n -s %{selinuxtype} -i %{_datadir}/selinux/packages/zabbix_proxy.pp.bz2
if %{_sbindir}/selinuxenabled ; then
    %{_sbindir}/load_policy
    %relabel_files
fi

%if 0%{?build_server}
%post server-mysql
%if 0%{?rhel} >= 7
%systemd_post zabbix-server.service
%else
/sbin/chkconfig --add zabbix-server
%endif
/usr/sbin/update-alternatives --install %{_sbindir}/zabbix_server \
	zabbix-server %{_sbindir}/zabbix_server_mysql 10
:

%post server-mysql-selinux
%{_sbindir}/semodule -n -s %{selinuxtype} -i %{_datadir}/selinux/packages/zabbix_agent.pp.bz2
%{_sbindir}/semodule -n -s %{selinuxtype} -i %{_datadir}/selinux/packages/zabbix_server.pp.bz2
if %{_sbindir}/selinuxenabled ; then
    %{_sbindir}/load_policy
    %relabel_files
fi


%post web
/usr/sbin/update-alternatives --install %{_datadir}/zabbix/fonts/graphfont.ttf \
	zabbix-web-font %{_datadir}/fonts/dejavu/DejaVuSans.ttf 10
:

%post web-mysql-selinux
%{_sbindir}/semodule -n -s %{selinuxtype} -i %{_datadir}/selinux/packages/zabbix_agent.pp.bz2
%{_sbindir}/semodule -n -s %{selinuxtype} -i %{_datadir}/selinux/packages/zbx_nginx.pp.bz2
%{_sbindir}/semodule -n -s %{selinuxtype} -i %{_datadir}/selinux/packages/zbx_php-fpm.pp.bz2
if %{_sbindir}/selinuxenabled ; then
    %{_sbindir}/load_policy
    %relabel_files
fi

%endif

%post agent-selinux
%{_sbindir}/semodule -n -s %{selinuxtype} -i %{_datadir}/selinux/packages/zabbix_agent.pp.bz2
if %{_sbindir}/selinuxenabled ; then
    %{_sbindir}/load_policy
    %relabel_files
fi

%post scripts
systemctl restart rsyslog

%preun proxy-mysql
if [ "$1" = 0 ]; then
%if 0%{?rhel} >= 7
%systemd_preun zabbix-proxy.service
%else
/sbin/service zabbix-proxy stop >/dev/null 2>&1
/sbin/chkconfig --del zabbix-proxy
%endif
/usr/sbin/update-alternatives --remove zabbix-proxy \
%{_sbindir}/zabbix_proxy_mysql
fi
:

%if 0%{?build_server}
%preun server-mysql
if [ "$1" = 0 ]; then
%if 0%{?rhel} >= 7
%systemd_preun zabbix-server.service
%else
/sbin/service zabbix-server stop >/dev/null 2>&1
/sbin/chkconfig --del zabbix-server
%endif
/usr/sbin/update-alternatives --remove zabbix-server \
	%{_sbindir}/zabbix_server_mysql
fi
:

%preun web
if [ "$1" = 0 ]; then
/usr/sbin/update-alternatives --remove zabbix-web-font \
	%{_datadir}/fonts/dejavu/DejaVuSans.ttf
fi
:
%endif

%postun proxy-mysql
%if 0%{?rhel} >= 7
%systemd_postun_with_restart zabbix-proxy.service
%else
if [ $1 -ge 1 ]; then
/sbin/service zabbix-proxy try-restart >/dev/null 2>&1 || :
fi
%endif

%postun proxy-mysql-selinux
if [ $1 -eq 0 ]; then
    %{_sbindir}/semodule -n -r zabbix-proxy &> /dev/null || :
    %{_sbindir}/semodule -n -r zabbix-agent &> /dev/null || :
    if %{_sbindir}/selinuxenabled ; then
	%{_sbindir}/load_policy
	%relabel_files
    fi
fi

%if 0%{?build_server}
%postun server-mysql
%if 0%{?rhel} >= 7
%systemd_postun_with_restart zabbix-server.service
%else
if [ $1 -ge 1 ]; then
/sbin/service zabbix-server try-restart >/dev/null 2>&1 || :
fi
%endif

%postun server-mysql-selinux
if [ $1 -eq 0 ]; then
    %{_sbindir}/semodule -n -r zabbix-server &> /dev/null || :
    %{_sbindir}/semodule -n -r zabbix-agent &> /dev/null || :
    if %{_sbindir}/selinuxenabled ; then
	%{_sbindir}/load_policy
	%relabel_files
    fi
fi

%postun web-mysql-selinux
if [ $1 -eq 0 ]; then
    %{_sbindir}/semodule -n -r zabbix-agent &> /dev/null || :
    %{_sbindir}/semodule -n -r zbx_nginx &> /dev/null || :
    %{_sbindir}/semodule -n -r zbx_php-fpm &> /dev/null || :
    if %{_sbindir}/selinuxenabled ; then
	%{_sbindir}/load_policy
	%relabel_files
    fi
fi
%endif

%postun agent-selinux
if [ $1 -eq 0 ]; then
    %{_sbindir}/semodule -n -r zabbix-agent &> /dev/null || :
    if %{_sbindir}/selinuxenabled ; then
	%{_sbindir}/load_policy
	%relabel_files
    fi
fi

%postun scripts
systemctl restart rsyslog

%files proxy-mysql
%defattr(-,root,root,-)
%doc AUTHORS ChangeLog COPYING NEWS README
%doc database/mysql/schema.sql.gz
%attr(0640,root,zabbix) %config(noreplace) %{_sysconfdir}/zabbix/zabbix_proxy_common.conf
%attr(0640,root,zabbix) %config(noreplace) %{_sysconfdir}/zabbix/zabbix_proxy_N.conf
%dir /usr/lib/zabbix/externalscripts
%config(noreplace) %{_sysconfdir}/logrotate.d/zabbix-proxy
%attr(0755,zabbix,zabbix) %dir %{_localstatedir}/log/zabbix
%attr(0755,zabbix,zabbix) %dir %{_localstatedir}/run/zabbix
%{_mandir}/man8/zabbix_proxy.8*
%if 0%{?rhel} >= 7
%{_unitdir}/zabbix-proxy.service
%{_prefix}/lib/tmpfiles.d/zabbix-proxy.conf
%else
%{_sysconfdir}/init.d/zabbix-proxy
%endif
%{_sbindir}/zabbix_proxy_mysql

%files proxy-mysql-selinux
%defattr(-,root,root,0755)
%attr(0644,root,root) %{_datadir}/selinux/packages/zabbix_proxy.pp.bz2
%attr(0644,root,root) %{_datadir}/selinux/packages/zabbix_agent.pp.bz2

%if 0%{?build_server}
%files server-mysql
%defattr(-,root,root,-)
%doc AUTHORS ChangeLog COPYING NEWS README
%doc database/mysql/create.sql.gz
%attr(0640,root,zabbix) %config(noreplace) %{_sysconfdir}/zabbix/zabbix_server.conf
%dir /usr/lib/zabbix/alertscripts
%dir /usr/lib/zabbix/externalscripts
%config %{_sysconfdir}/logrotate.d/zabbix-server
%attr(0755,zabbix,zabbix) %dir %{_localstatedir}/log/zabbix
%attr(0755,zabbix,zabbix) %dir %{_localstatedir}/log/zabbix/slv
%attr(0755,zabbix,zabbix) %dir %{_localstatedir}/run/zabbix
%{_mandir}/man8/zabbix_server.8*
%if 0%{?rhel} >= 7
%{_unitdir}/zabbix-server.service
%{_prefix}/lib/tmpfiles.d/zabbix-server.conf
%else
%{_sysconfdir}/init.d/zabbix-server
%endif
%{_sbindir}/zabbix_server_mysql
%{_bindir}/rsm_epp_dec
%{_bindir}/rsm_epp_enc
%{_bindir}/rsm_epp_gen

%files server-mysql-selinux
%defattr(-,root,root,0755)
%attr(0644,root,root) %{_datadir}/selinux/packages/zabbix_server.pp.bz2
%attr(0644,root,root) %{_datadir}/selinux/packages/zabbix_agent.pp.bz2


%files web
%defattr(-,root,root,-)
%doc AUTHORS ChangeLog COPYING NEWS README
%doc frontends/nginx.conf
%dir %attr(0750,nginx,nginx) %{_sysconfdir}/zabbix/web
%dir %attr(0750,nginx,nginx) %{_sharedstatedir}/php/session
%ghost %attr(0644,nginx,nginx) %config(noreplace) %{_sysconfdir}/zabbix/web/zabbix.conf.php
%config(noreplace) %{_sysconfdir}/zabbix/web/maintenance.inc.php
%if 0%{?rhel} >= 7
%config(noreplace) %{_sysconfdir}/nginx/conf.d/zbx_vhost.conf
%config(noreplace) %{_sysconfdir}/php-fpm.d/zabbix.conf
%endif
%{_datadir}/zabbix

%files web-mysql
%defattr(-,root,root,-)

%files web-mysql-selinux
%defattr(-,root,root,0755)
%attr(0644,root,root) %{_datadir}/selinux/packages/zabbix_agent.pp.bz2
%attr(0644,root,root) %{_datadir}/selinux/packages/zbx_nginx.pp.bz2
%attr(0644,root,root) %{_datadir}/selinux/packages/zbx_php-fpm.pp.bz2

%files agent-selinux
%defattr(-,root,root,0755)
%attr(0644,root,root) %{_datadir}/selinux/packages/zabbix_agent.pp.bz2


%files scripts
%defattr(-,zabbix,zabbix,0755)
/opt/zabbix/*
%defattr(-,root,root,0755)
/etc/cron.d/*
%config %{_sysconfdir}/logrotate.d/zabbix-slv
%config %{_sysconfdir}/rsyslog.d/rsm.slv.conf

%endif


%changelog
* Wed Dec 21 2016 Alexey Pustovalov <alexey.pustovalov@zabbix.com> - 3.0.7-1-rsm
- update to RSM version

* Wed Dec 21 2016 Kodai Terashima <kodai.terashima@zabbix.com> - 3.0.7-1
- update to 3.0.7

* Thu Dec 08 2016 Kodai Terashima <kodai.terashima@zabbix.com> - 3.0.6-1
- update to 3.0.6

* Sun Oct 02 2016 Kodai Terashima <kodai.terashima@zabbix.com> - 3.0.5-1
- update to 3.0.5
- use zabbix user and group for Java Gateway
- add SuccessExitStatus=143 for Java Gateway servie file

* Sun Jul 24 2016 Kodai Terashima <kodai.terashima@zabbix.com> - 3.0.4-1
- update to 3.0.4

* Sun May 22 2016 Kodai Terashima <kodai.terashima@zabbix.com> - 3.0.3-1
- update to 3.0.3
- fix java gateway systemd script to use java options

* Wed Apr 20 2016 Kodai Terashima <kodai.terashima@zabbix.com> - 3.0.2-1
- update to 3.0.2
- remove ZBX-10459.patch

* Sat Apr 02 2016 Kodai Terashima <kodai.terashima@zabbix.com> - 3.0.1-2
- fix proxy packges doesn't have schema.sql.gz
- add server and web packages for RHEL6
- add ZBX-10459.patch

* Sun Feb 28 2016 Kodai Terashima <kodai.terashima@zabbix.com> - 3.0.1-1
- update to 3.0.1
- remove DBSocker parameter

* Sat Feb 20 2016 Kodai Terashima <kodai.terashima@zabbix.com> - 3.0.0-2
- agent, proxy and java-gateway for RHEL 5 and 6

* Mon Feb 15 2016 Kodai Terashima <kodai.terashima@zabbix.com> - 3.0.0-1
- update to 3.0.0

* Thu Feb 11 2016 Kodai Terashima <kodai.terashima@zabbix.com> - 3.0.0rc2
- update to 3.0.0rc2
- add TIMEOUT parameter for java gateway conf

* Thu Feb 04 2016 Kodai Terashima <kodai.terashima@zabbix.com> - 3.0.0rc1
- update to 3.0.0rc1

* Sat Jan 30 2016 Kodai Terashima <kodai.terashima@zabbix.com> - 3.0.0beta2
- update to 3.0.0beta2

* Thu Jan 21 2016 Kodai Terashima <kodai.terashima@zabbix.com> - 3.0.0beta1
- update to 3.0.0beta1

* Thu Jan 14 2016 Kodai Terashima <kodai.terashima@zabbix.com> - 3.0.0alpha6
- update to 3.0.0alpla6
- remove zabbix_agent conf and binary

* Wed Jan 13 2016 Kodai Terashima <kodai.terashima@zabbix.com> - 3.0.0alpha5
- update to 3.0.0alpha5

* Fri Nov 13 2015 Kodai Terashima <kodai.terashima@zabbix.com> - 3.0.0alpha4-1
- update to 3.0.0alpha4

* Thu Oct 29 2015 Kodai Terashima <kodai.terashima@zabbix.com> - 3.0.0alpha3-2
- fix web-pgsql package dependency
- add --with-openssl option

* Mon Oct 19 2015 Kodai Terashima <kodai.terashima@zabbix.com> - 3.0.0alpha3-1
- update to 3.0.0alpha3

* Tue Sep 29 2015 Kodai Terashima <kodai.terashima@zabbix.com> - 3.0.0alpha2-3
- add IfModule for mod_php5 in apache configuration file
- fix missing proxy_mysql alternatives symlink
- chagne snmptrap log filename
- remove include dir from server and proxy conf

* Fri Sep 18 2015 Kodai Terashima <kodai.terashima@zabbix.com> - 3.0.0alpha2-2
- fix create.sql doesn't contain schema.sql & images.sql

* Tue Sep 15 2015 Kodai Terashima <kodai.terashima@zabbix.com> - 3.0.0alpha2-1
- update to 3.0.0alpha2

* Sat Aug 22 2015 Kodai Terashima <kodai.terashima@zabbix.com> - 2.5.0-1
- create spec file from scratch
- update to 2.5.0
