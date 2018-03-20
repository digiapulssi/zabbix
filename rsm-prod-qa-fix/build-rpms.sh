#!/bin/bash

RSM_VERSION="rsm1.0.5"	# MAJOR.PROD.QA
RPMDIR="rpmbuild"
SRV_VERSION_FILE="include/version.h"
FE_VERSION_FILE="frontends/php/include/defines.inc.php"
AC_VERSION_FILE="configure.ac"
SPEC="$RPMDIR/SPECS/zabbix.spec"
FAILURE=1
SUCCESS=0

usage()
{
	[ -n "$1" ] && echo "$*"

	echo "usage: $0 [-f] [-c] [-r] [-h]"
	echo "       -f|--force      force all compilation steps"
	echo "       -c|--clean      clean all previously generated files"
	echo "       -r|--restore    restore the versions and exit"
	echo "       -h|--help       print this help message"

	exit $FAILURE
}

msg()
{
	echo "BUILD-RPMS $*"
}

restore_versions()
{
	for i in $SRV_VERSION_FILE $FE_VERSION_FILE $AC_VERSION_FILE $SPEC; do
		[ -f $i.rpmbak ] && mv $i.rpmbak $i
	done
}

fail()
{
	[ -n "$1" ] && echo "$*"

	exit $FAILURE
}

OPT_FORCE=0
OPT_CLEAN=0
while [ -n "$1" ]; do
	case "$1" in
		-f|--force)
			OPT_FORCE=1
			;;
		-c|--clean)
			OPT_CLEAN=1
			;;
		-r|--restore)
			restore_versions
			exit $SUCCESS
			;;
		-h|--help)
			usage
			;;
		-*)
			usage "unknown option: \"$1\""
			;;
		*)
			usage
			;;
	esac
	shift
done

[ ! -f $SPEC ] && echo "Error: spec file \"$SPEC\" not found" && fail
[ ! -f $SRV_VERSION_FILE ] && echo "Error: server file \"$SRV_VERSION_FILE\" not found" && fail
[ ! -f $FE_VERSION_FILE ] && echo "Error: frontend file \"$FE_VERSION_FILE\" not found" && fail
[ ! -f $AC_VERSION_FILE ] && echo "Error: autoconf file \"$AC_VERSION_FILE\" not found" && fail

restore_versions

if [[ $OPT_CLEAN -eq 1 ]]; then
	msg "cleaning up"
	make -s clean > /dev/null 2>&1
	make -s distclean > /dev/null 2>&1
	for i in RPMS SRPMS BUILD BUILDROOT; do
		rm -rf $RPMDIR/$i || fail
	done
fi

if ! grep -q "ZBX_STR(ZABBIX_VERSION_PATCH).*ZABBIX_VERSION_RC.*$RSM_VERSION" $SRV_VERSION_FILE; then
	msg "setting server version ($RSM_VERSION)"
	sed -i.rpmbak -r "s/(ZBX_STR\(ZABBIX_VERSION_PATCH\).*ZABBIX_VERSION_RC)/\1 \"$RSM_VERSION\"/" $SRV_VERSION_FILE || fail
fi

if ! grep -q "ZABBIX_VERSION.*$RSM_VERSION" $FE_VERSION_FILE; then
	msg "setting frontend version ($RSM_VERSION)"
	sed -i.rpmbak -r "s/(ZABBIX_VERSION',\s+'[0-9\.]+)'.*/\1$RSM_VERSION');/" $FE_VERSION_FILE || fail
fi

if ! grep -q "^AC_INIT(\[Zabbix\],\[[0-9\.]\+$RSM_VERSION" $AC_VERSION_FILE; then
	msg "setting version for autoconf ($RSM_VERSION)"
	sed -i.rpmbak -r "s/^(AC_INIT\(\[Zabbix\],\[[0-9\.]+)\]\)/\1$RSM_VERSION])/;s/^AM_INIT_AUTOMAKE.*$/AM_INIT_AUTOMAKE([1.9 tar-pax])/" $AC_VERSION_FILE || fail
fi

if ! grep -q "^Version:\s+[0-9\.]+$RSM_VERSION)$" $SPEC; then
	msg "setting version for rpm ($RSM_VERSION)"
	sed -i.rpmbak -r "s/(^Version:\s+[0-9\.]+)$/\1$RSM_VERSION/" $SPEC || fail
fi

if [[ $OPT_FORCE -eq 1 || ! -f configure ]]; then
	msg "running ./bootstrap.sh"
	./bootstrap.sh > /dev/null || fail
fi

if [[ $OPT_FORCE -eq 1 || ! -f Makefile ]]; then
	msg "running ./configure"
	./configure > /dev/null || fail
fi

make -s dbschema > /dev/null || fail

if [[ $OPT_FORCE -eq 1 ]] || ! ls zabbix-*.tar.gz > /dev/null 2>&1; then
	msg "making dist"
	make -s dist > /dev/null || fail
fi

mv zabbix-*.tar.gz $RPMDIR/SOURCES/ || fail

msg "building RPMs, this can take a while"
rpmbuild --quiet --define "_topdir ${PWD}/$RPMDIR" -ba $SPEC >/dev/null || fail

msg "RPM files are available in $RPMDIR/RPMS/x86_64 and $RPMDIR/RPMS/noarch"

restore_versions

exit $SUCCESS
