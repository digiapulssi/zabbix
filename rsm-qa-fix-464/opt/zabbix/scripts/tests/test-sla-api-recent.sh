#!/bin/bash

BASE="/opt/zabbix/sla"
SCHEMA_FILE="$(dirname $0)/test-sla-api-recent.schema"

declare -A DELAYS=([dns]=60 [dnssec]=60 [rdds]=300)

WARN_ERRORS=0
[ "$1" = "-w" ] && WARN_ERRORS=1

for t in $BASE/*; do
	tld=${t##*/}

	echo "$tld"

	for s in $t/monitoring/*; do
		service=${s##*/}
		echo "  $service"

		for y in $s/measurements/*; do
#			year=${y##*/}
			for m in $y/*; do
#				month=${m##*/}
				for d in $m/*; do
#					date=${d##*/}

#					echo "    $year/$month/$date"

					delay=${DELAYS[$service]}

#					files=

					prev_ts=
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

						if ! jsonschema -i $i --error-format "{error.path}: {error.message}
" $SCHEMA_FILE; then
						    if [ $WARN_ERRORS -eq 1 ]; then
						    	echo "Warning: $i validation failed, see errors below"
						    else
							echo "Error: $i validation failed, see errors below"
							exit 1

						    fi
						fi

						prev_ts=$ts
					done

#					if ! jsonschema $files $SCHEMA_FILE; then
#						exit 1
#					fi
				done
			done
		done
	done
done
