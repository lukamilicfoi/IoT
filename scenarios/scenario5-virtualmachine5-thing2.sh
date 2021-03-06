#!/bin/bash
. common
echo "Starting scenario 5 for Thing 2..."
block_ctrlc
rm -f /tmp/msg
ncat -v4l 60000 > /tmp/msg 2>/tmp/2 &
echo -n "Receiving Message unsecurely (^C to quit)."
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
	if [ $(($header % 8)) -ge 4 ]; then
		ID=`echo $msg | cut -d \\  -f 2`
		echo ID=$ID
		fieldtobegin=3
		if [ $(($header % 64)) -ge 32 ]; then
			fieldtobegin=$(($fieldtobegin + 2))
		fi
		if [ $(($header % 32)) -ge 16 ]; then
			fieldtobegin=$(($fieldtobegin + 8))
		fi
		echo fieldtobegin=$fieldtobegin
		DST=
		for a in `echo $msg | cut -d \\  -f $fieldtobegin-$(($fieldtobegin + 7))`; do
			DST=$DST\\x`printf %02X $a`
		done
		echo DST=$DST
		rm -f /tmp/msg
		ncat -4l 60000 > /tmp/msg &
		send_tcp_binary "ACKNOWLEDGMENT" "\\x20${DST}K\\x`printf %02X $ID`" $imm_DST
		echo -n "Receiving Message unsecurely."
		while [ ! -s /tmp/msg ]; do
			sleep 1
			echo -n "."
		done
		kill $! 2>/dev/null
		process_message
	else
		echo "ACKNOWLEDGMENT not requested"
	fi
fi
wait_ctrld
echo "Scenario 5 for Thing 2 finished!"
