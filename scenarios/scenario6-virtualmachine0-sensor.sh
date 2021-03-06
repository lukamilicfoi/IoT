#!/bin/bash
. common
echo "Starting scenario 6 for sensor..."
determine_hex_bytea 1
run_IoT
echo "Press Enter after IoT and display have started..."
read var
echo "COPY (SELECT) TO PROGRAM 'bash -c \"while true; do echo \\\"SELECT send_receive(\
		E''\\\\\\\\\\\\\\\\x00'' || CAST(''sensor=\\\`~luka/read_sensor\\\`'' AS BYTEA), \
		''/my1'', E''\\\\\\\\\\\\\\\\x${virt1_hex_bytea:5:16}'', FALSE, FALSE, TRUE\
		);\\\" | psql -U postgres; sleep 20; done &\"';" | psql -U postgres
wait_ctrld
kill $!
kill `ps ax | grep -E 'bash -c while true' | head -n 1 | sed -E 's/^ *//' | cut -d \\  -f 1`
echo "Scenario 6 for sensor finished!"
