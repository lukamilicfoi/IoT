#!/bin/bash
. common
echo "Starting scenario 6 for display..."
run_IoT
echo "Press Enter after IoT has started..."
read var
echo "TRUNCATE TABLE rules;
		INSERT INTO rules(id, sendreceive, filter, dropmodify, modification, prequery, \
		postquery, message_to_send, proto_id_to_send, imm_addr_to_send, \
		message_to__receive_, proto_id_to__receive_, imm_addr_to__receive_, activate, \
		deactivate, active) VALUES(0, 1, 'ENCODE(PL, ''ESCAPE'') LIKE ''sensor=%''', 0, '', \
		'COPY (SELECT SUBSTRING(ENCODE(message.PL, ''ESCAPE'') FROM 8) FROM message) \
		TO PROGRAM ''~luka/display_sensor''', '', '', '', E'\\\\x', '', '', E'\\\\x', NULL, \
		NULL, TRUE);" | psql -U postgres
wait_ctrld
kill $!
echo "Scenario 6 for display finished!"
