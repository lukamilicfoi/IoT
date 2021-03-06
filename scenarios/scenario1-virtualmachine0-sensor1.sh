#!/bin/bash
. common
echo "Starting scenario 1 for sensor 1..."
block_ctrlc
disable_clock
determine_ip 2
echo -n "(Wait for router to start, EOF to exit) temperature="
while read temperature; do
	date
	send_tcp_text "Data" "temp,day,hour=$temperature,'`date +%d%m%y`','`date +%H`'" $virt2_ip
	echo "Waiting for half an hour (it passes automatically in 4 seconds)..."
	sleep 4
	echo "date -s \"+29 minutes 54 seconds\"" | ncat -4 $PULSE_SERVER 20000
	echo -n "(EOF to exit) temperature="
done
enable_clock
echo "Scenario 1 for sensor 1 finished!"
