#!/bin/bash
. common
echo "Starting scenario 3 for server..."
block_ctrlc
echo -n "(EOF to exit) min="
if read min; then
	rm -f /tmp/msg
	ncat -v4l 60000 > /tmp/msg 2>/tmp/2 &
	echo -n "Waiting for request (^C to quit)."
	trap "done=" SIGINT
	while [ ! -s /tmp/msg ] && [ ! -v done ]; do
		sleep 1
		echo -n "."
	done
	trap "" SIGINT
	kill $! 2>/dev/null
	if [ ! -v done ]; then
		imm_DST=`cat /tmp/2 | head -n 3 | tail -n 1 \\
				| grep -Eo '[0-9]{1,3}\\.[0-9]{1,3}\\.[0-9]{1,3}\\.[0-9]{1,3}'`
		echo imm_DST=$imm_DST
		process_message
		fieldtobegin=2
		if [ $header -ge 128 ]; then
			fieldtobegin=$(($fieldtobegin + 1))
		fi
		if [ $(($header % 128)) -ge 64 ]; then
			fieldtobegin=$(($fieldtobegin + 2))
		fi
		if [ $(($header % 64)) -ge 32 ]; then
			fieldtobegin=$(($fieldtobegin + 8))
		fi
		echo fieldtobegin=$fieldtobegin
		DST=
		for a in `echo $msg | cut -d \\  -f $fieldtobegin-$(($fieldtobegin + 7))`; do
			DST=$DST\\x`printf %02X $a`
		done
		echo DST=$DST
		if [ ! -z "`echo "$PL" | grep -E 'SELECT.*min.*'`" ]; then
			rm -f /tmp/msg
			ncat -4l 60000 > /tmp/msg &
			ID=$(($RANDOM % 256))
			echo ID=$ID
			send_tcp_binary "Data" "\\xA4\\x`printf %02X $ID`${DST}min=$min" $imm_DST
			echo -n "Receiving ACKNOWLEDGMENT."
			while [ ! -s /tmp/msg ]; do
				sleep 1
				echo -n "."
			done
			kill $! 2>/dev/null
			process_message
		else
			echo "Bad request"
		fi
	fi
	wait_ctrld
fi
echo "Scenario 3 for server finished!"
