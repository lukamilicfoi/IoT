#!/bin/bash
. common
echo "Starting scenario 5 for Thing 1..."
block_ctrlc
determine_hex_bash 0
determine_ip 1
determine_hex_bash 5
echo -n "(Wait for Gateway 1 and both Clouds 1&2 to start, EOF to exit) Message="
if read Message; then
	rm -f /tmp/msg
	ncat -4l 60000 > /tmp/msg &
	send_tcp_binary "Message securely" "\\x27$virt5_hex_bash$Message" $virt1_ip
	echo -n "Receiving ACKNOWLEDGMENT (for 10 seconds, ^C to quit)."
	ctr=10
	trap "done=" SIGINT
	while [ ! -s /tmp/msg ] && [ $ctr -gt 0 ] && [ ! -v done ]; do
		sleep 1
		echo -n "."
		ctr=$(($ctr - 1))
	done
	trap "" SIGINT
	kill $! 2>/dev/null
	if [ ! -v done ]; then
		if [ $ctr -eq 0 ]; then
			echo "No response"
			rm -f /tmp/msg
			ncat -4l 60000 > /tmp/msg &
			ID=$(($RANDOM % 256))
			echo ID=$ID
			send_tcp_binary "ECHO" "\\xB4\\x`printf %02X $ID`$virt5_hex_bash\
$virt0_hex_bash" $virt1_ip
			echo -n "Receiving ACKNOWLEDGMENT."
			while [ ! -s /tmp/msg ]; do
				sleep 1
				echo -n "."
			done
			kill $! 2>/dev/null
		fi
		process_message
		if [ $ctr -eq 0 ]; then
			echo "No secure connection exists"
			send_tcp_binary "Message unsecurely" "\\x20$virt5_hex_bash$Message" \
					$virt1_ip
		fi
	fi
	wait_ctrld
fi
echo "Scenario 5 for Thing 1 finished!"
