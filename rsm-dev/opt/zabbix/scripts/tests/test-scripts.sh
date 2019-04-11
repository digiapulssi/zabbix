#!/bin/bash

declare -A TYPES=(
	[slv]=1
	[probe]=1
)

declare -A SERVICES=(
	[dns]=1
	[dnssec]=1
	[rdds]=1
	[online]=1
)

declare -A EXCEPTIONS=(
	[rsm.slv.dnssec.downtime.pl]=1
)

die()
{
	echo "Error: $*"
	exit 1
}

print_title()
{
	local title="$1"
	local cmd="$2"

	echo -ne "\033[1;1H"

	echo -ne "\033[2K"
	echo $title

	echo -ne "\033[2K"
	echo

	echo -ne "\033[2K"
	echo "running: $cmd"

	echo -ne "\033[2K"
	echo
}

run()
{
	local title="$1"; shift

	local cmd="$@"

	clear
	print_title "$title" "$cmd"

	$cmd

	local rv=$?

	[ $rv -eq 0 ] || die "$cmd"

	echo -en "\E[6n"
	read -sdR CURPOS

	CURPOS=${CURPOS#*[}

	print_title "$title" "$cmd"

	echo -ne "\033[${CURPOS}HPress Enter to continue..."

	read
}

for script_path in opt/zabbix/scripts/slv/*; do
	script=$(basename $script_path)

	[ "${EXCEPTIONS[$script]}" = 1 ] && continue

	type=$(echo $script | cut -d '.' -f2)
	service=$(echo $script | cut -d '.' -f3)
	action=$(echo $script | cut -d '.' -f4)

	[ "${TYPES[$type]}" = 1 ] || continue
	[ "${SERVICES[$service]}" = 1 ] || continue

	if [ $action = "pl" ]; then
		action=$service
		service=$type
	elif [[ $action = "ns" || $action = "tcp" || $action = "udp" ]]; then
		service="$service $action"
		action=$(echo $script | cut -d '.' -f5)
	fi

	s_uc=$(echo $service | tr [a-z] [A-Z])
	a_uc=$(echo $action | tr [a-z] [A-Z])

	title="$s_uc $a_uc"

	run "$title" $script_path --nolog --dry-run
done

run_custom()
{
	local script_path="$1"
	local title="$2"
	local params="$3"

	run "$title" "$script_path" "$params"
}

run "SLA API" "sudo opt/zabbix/scripts/update-api-data.pl" "--continue"

run "Data Export" "sudo opt/zabbix/scripts/export/export.pl" "--date $(date +%d/%m/%Y -d '1 day ago')"

run "Recent measurements" "sudo opt/zabbix/scripts/sla-api-recent.pl"

echo "Tests successful, congratulations!"
