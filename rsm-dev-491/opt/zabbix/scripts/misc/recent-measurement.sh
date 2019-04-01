#!/bin/bash

BASE="/opt/zabbix/sla"

d_tld="tld6"
d_service="dns"
d_date=$(date +%Y/%m/%d)
d_last=1
d_move=0

usage()
{
	[ -n "$1" ] && echo "Error: $*"
	echo "usage   : $0 [-t tld] [-s service] [-d YYYY/MM/DD] [-l LAST] [-m MOVE]"
	echo "options :"
	echo "    -t TLD (default: $d_tld)"
	echo "    -s Service (default: $d_service)"
	echo "    -d DATE (default: $d_date)"
	echo "    -l LAST measurements (default: $d_last)"
	echo "    -m CYCLES to go back from the LAST  measurements (default: $d_move)"
	exit 1
}

die()
{
	echo "Error: $*"
	exit 1
}

tld=
service=
date=
last=
move=
while [ -n "$1" ]; do
	case "$1" in
		-t)
			shift
			[ -z "$1" ] && usage
			tld=$1
			;;
		-s)
			shift
			[ -z "$1" ] && usage
			[[ $1 = "dns" || $1 = "dnssec" || $1 = "rdds" || $1 = "epp" ]] || usage "$1: unknown Service (expected: dns, dnssec, rdds or epp"
			service=$1
			;;
		-d)
			shift
			[ -z "$1" ] && usage
			date=$1
			;;
		-l)
			shift
			[ -z "$1" ] && usage
			[ $1 -gt 0 ] || usage "-l $1: last value must be greater than 0"
			last=$1
			;;
		-m)
			shift
			[ -z "$1" ] && usage
			move=$1
			;;
		--)
			shift
			# stop parsing args
			break
			;;
		*)
			usage
			;;
	esac

	shift
done

[[ -n "$1" && $1 = "-h" ]] && usage

[ -z "$tld" ] && tld=$d_tld
[ -z "$service" ] && service=$d_service
[ -z "$date" ] && date=$d_date
[ -z "$last" ] && last=$d_last
[ -z "$move" ] && move=$d_move

base="$BASE/$tld/monitoring/$service/measurements/$date"

[ -d $base ] || usage "$base - no such directory"

files=
if [ $move -eq 0 ]; then
	files=$(ls $base/*.json | tail -$last)
else
	let last_with_move=$last+move

	files=$(ls $base/*.json | tail -$last_with_move | head -$last)
fi

[ -n "$files" ] || die "directory $base is empty"

for file in $files; do
	ls -l $file
	cat $file | jq -C '.testedInterface[0].probes[] | .city, .testData[].metrics[]'
done
