#!/bin/bash
. common
echo "Starting scenario 2 for sensor..."
block_ctrlc
disable_clock
determine_ip 1
echo -n "(Wait for Gateway to start, EOF to exit) temperature="
while read temperature; do
	date
	send_udp "Data" "\\x`printf %02X $temperature`" $virt1_ip
	unset done
	echo -n "Minutes are going like seconds, press ^C to stop."
	trap "done=" SIGINT
	while [ ! -v done ]; do
		echo -n "."
		sleep 1
		echo "date -s \"+54 seconds\"" | ncat -4 $PULSE_SERVER 20000
	done
	trap "" SIGINT
	echo -n "(EOF to exit) temperature="
done
enable_clock
echo "Scenario 2 for sensor finished!"
