#!/bin/bash
. common
echo "Starting scenario 3 for Thing..."
block_ctrlc
disable_clock
determine_hex_bash 0
determine_ip 1
determine_hex_bash 4
echo "Press Enter after Gateway 1, Cloud, and Gateway 2 have started and min has been entered..."
read var
rm -f /tmp/msg
ncat -4l 60000 > /tmp/msg &
send_tcp_binary "request" "\\x30$virt4_hex_bash${virt0_hex_bash}\
SELECT ALL min FETCH FIRST 1 ROW ONLY;" $virt1_ip
echo -n "Receiving Data."
while [ ! -s /tmp/msg ]; do
	sleep 1
	echo -n "."
done
kill $! 2>/dev/null
process_message
if [ $(($header % 8)) -ge 4 ]; then
	ID=`echo $msg | cut -d \\  -f 2`
	echo ID=$ID
	send_tcp_binary "ACKNOWLEDGMENT" "\\x20${virt4_hex_bash}K\\x`printf %02X $ID`" $virt1_ip
else
	echo "ACKNOWLEDGMENT not requested"
fi
min="${PL:4}"
echo min="$min"
echo -n "(EOF to exit) temperature="
while read temperature; do
	date
	send_tcp_text "Data" "temp=$temperature" $virt1_ip
	echo -n "Minutes are going like seconds until min expires (^C to quit)."
	ctr="$min"
	trap "done=" SIGINT
	while [ "$ctr" -ne 0 ] && [ ! -v done ]; do
		echo -n "."
		sleep 1
		echo "date -s \"+58 seconds\"" | ncat -4 $PULSE_SERVER 20000
		ctr=$(("$ctr" - 1))
	done
	trap "" SIGINT
	if [ -v done ]; then
		wait_ctrld
		break
	fi
	echo -n "(EOF to exit) temperature="
done
enable_clock
echo "Scenario 3 for Thing finished!"
