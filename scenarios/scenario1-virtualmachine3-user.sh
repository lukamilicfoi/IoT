#!/bin/bash
. common
echo "Starting scenario 1 for User..."
block_ctrlc
determine_hex_bash 0
determine_hex_bash 1
determine_ip 2
echo -n "(Wait for router to start and at least one message to be received from both sensors 1&2, \
EOF to exit) day OFFSET NEXT="
while read day OFFSET NEXT; do
	rm -f /tmp/msg
	ncat -4l 60000 > /tmp/msg &
	send_tcp_binary "request" "\\0\\xDF\\x85a.temp\\x87t,b.pres\\x87p\\xADt$virt0_hex_bash\
\\x87a\\xB4\\xB8t$virt1_hex_bash\\x87b\\xCFa.day=b.day\\x84a.hour=b.hour\\xFCa.day='$day'\
\\xD2\\x8Fa.hour\\x88\\xCE$OFFSET\\xA9\\xC7$NEXT\\xDC\\xD0" $virt2_ip
	echo -n "Receiving Data."
	while [ ! -s /tmp/msg ]; do
		sleep 1
		echo -n "."
	done
	kill $! 2>/dev/null
	process_message
	echo -n "(EOF to exit) day OFFSET NEXT="
done
echo "Scenario 1 for User finished!"
