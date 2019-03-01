#!/bin/bash

BASE="/opt/zabbix/sla"
SCHEMA_FILE="$(dirname $0)/test-sla-api-recent.schema"

declare -A DELAYS=([dns]=60 [dnssec]=60 [rdds]=300)

o_year=
o_month=
o_day=

if [ "$1" = "today" ]; then
	s=$(date +%Y/%m/%d)

	o_year=${s%%/*}

	o_day=${s##*/}

	o_month=${s%/*}
	o_monthm=${o_month#*/}
fi

WARN_ERRORS=0
[ "$1" = "-w" ] && WARN_ERRORS=1

for t in $BASE/*; do
	[ -d $t ] || continue

	tld=${t##*/}

	echo "$tld"

	for s in $t/monitoring/*; do
		[ -d $s ] || continue

		service=${s##*/}
		echo "  $service"

		prev_ts=

		for y in $s/measurements/*; do
			[ -n "$o_year" ] && [ $y != $o_year ] && continue
			year=${y##*/}
			for m in $y/*; do
				[ -n "$o_month" ] && [ $m != $o_month ] && continue
				month=${m##*/}
				for d in $m/*; do
					[ -n "$o_day" ] && [ $d != $o_day ] && continue
					date=${d##*/}

					echo -n "    $year/$month/$date"

					delay=${DELAYS[$service]}

#					files=

					for i in $(ls --color=none $d/*.json); do
						ts=${i##*/}
						ts=${ts%%.json}

						if [ -n "$prev_ts" ]; then
							expected_ts=$prev_ts
							let expected_ts=$expected_ts+$delay

							if [ $expected_ts != $ts ]; then
								date=$(date -d @$expected_ts)
								if [ $WARN_ERRORS -eq 1 ]; then
									echo "Warning: missing file for $date: $d/$expected_ts.json"
								else
									echo "Error: missing file for $date: $d/$expected_ts.json"
									exit 1

								fi
							fi
						fi

#						files="$files -i $i"

						echo -n "."

						if ! jsonschema -i $i --error-format "{error.path}: {error.message}
" $SCHEMA_FILE; then
							if [ $WARN_ERRORS -eq 1 ]; then
						    		echo -e "\nWarning: $i validation failed, see errors below"
							else
								echo -e "\nError: $i validation failed, see errors below"
								exit 1
							fi
						fi

						prev_ts=$ts
					done

					[ -z "$prev_ts" ] || echo

#					if ! jsonschema $files $SCHEMA_FILE; then
#						exit 1
#					fi
				done
			done
		done

		[ -z "$prev_ts" ] && echo "    no measurements"
	done
done
