#!/bin/bash
. common
echo "Starting scenario 4 for mobile phone..."
block_ctrlc
disable_clock
determine_hex_bash 0
determine_ip 1
determine_hex_bytea 2
echo "Press Enter after Gateway, Thing, and Cloud have started..."
read var
while true; do
	rm -f /tmp/msg
	ncat -4l 60000 > /tmp/msg &
	send_tcp_binary "request" "\\x10${virt0_hex_bash}SELECT * FROM t${virt2_hex_bytea:5:16};" \
			$virt1_ip
	echo -n "Receiving Data."
	while [ ! -s /tmp/msg ]; do
		sleep 1
		echo -n "."
	done
	kill $! 2>/dev/null
	process_message
	echo "$PL"
	echo "Waiting for 15 minutes (they will pass in 5 seconds, ^C to quit...)"
	trap "done=" SIGINT
	sleep 5
	trap "" SIGINT
	if [ -v done ]; then
		wait_ctrld
		break
	fi
	echo "date -s \"+14 minutes 54 seconds\"" | ncat -4 $PULSE_SERVER 20000
done
enable_clock
echo "Scenario 4 for mobile phone finished!"
