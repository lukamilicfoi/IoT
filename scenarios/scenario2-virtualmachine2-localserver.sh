#!/bin/bash
. common
echo "Starting scenario 2 for local server..."
block_ctrlc
determine_hex_bash 0
determine_ip 1
echo "Press Enter after Gateway has started and at least one message has been received..."
read var
rm -f /tmp/msg
ncat -4l 60000 > /tmp/msg &
send_tcp_binary "request" "\\0\\xDF\\x85a.temp\\xADt$virt0_hex_bash\\x87a\\xFCa.temp>20\\xE71" \
		$virt1_ip
while true; do
	echo -n "Receiving Data (^C to quit)."
	trap "done=" SIGINT
	while [ ! -s /tmp/msg ] && [ ! -v done ]; do
		sleep 1
		echo -n "."
	done
	trap "" SIGINT
	kill $! 2>/dev/null
	if [ -v done ]; then
		break
	fi
	process_message
	rm -f /tmp/msg
	ncat -4l 60000 > /tmp/msg &
done
send_udp "UNSUBSCRIBE" "\\0\\xF71" $virt1_ip
wait_ctrld
echo "Scenario 2 for local server finished!"
