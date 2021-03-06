#!/bin/bash
. common
echo "Starting scenario 4 for Thing..."
block_ctrlc
while true; do
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
	if [ -v done ]; then
		wait_ctrld
		break
	fi
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
	if [ ! -z "`echo "$PL" | grep -E 'SELECT.*\\*.*'`" ]; then
		echo -n "(EOF to exit) r1 r2 r3="
		if ! read r1 r2 r3; then
			break
		fi
		send_tcp_binary "Data" "\\x20${DST}r1,r2,r3=$r1,$r2,$r3" $imm_DST
	else
		echo "Bad request"
	fi
done
echo "Scenario 4 for Thing finished!"
