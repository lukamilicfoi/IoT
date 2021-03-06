#!/bin/bash
. common
echo "Starting scenario 4 for Gateway..."
block_ctrlc
determine_hex_bytea 0
determine_hex_bytea 2
determine_hex_bytea 3
echo "DROP TABLE IF EXISTS t${virt2_hex_bytea:5:16};
		TRUNCATE TABLE rules;
		INSERT INTO rules(id, sendreceive, filter, dropmodify, modification, prequery,
		postquery, message_to_send, proto_id_to_send, imm_addr_to_send,
		message_to__receive_, proto_id_to__receive_, imm_addr_to__receive_, activate,
		deactivate, active) VALUES(0, 1,
		'ENCODE(PL, ''ESCAPE'') LIKE ''%FROM t${virt2_hex_bytea:5:16}%''', 1,
		'DST = E'${virt2_hex_bytea:1}'', '', '', '', '', E'\\\\x', '', '', E'\\\\x', NULL,
		NULL, TRUE), (1, 1,
		'DST = E'${virt0_hex_bytea:1}' AND SRC = E'${virt2_hex_bytea:1}'', 2, '', '', '',
		'', '', E'\\\\x', 'E''\\\\x50'' || LEN || SRC || PL', '/my0', $virt2_hex_bytea,
		NULL, NULL, TRUE), (2, 1,
		'DST = E'${virt0_hex_bytea:1}' AND SRC = E'${virt2_hex_bytea:1}'', 2, '', '', '',
		'E''\\\\x50'' || LEN || SRC || PL', '/my0', $virt3_hex_bytea, '', '', E'\\\\x',
		NULL, NULL, TRUE);" | psql -U postgres
run_IoT
echo "Press Enter after IoT has started..."
read var
echo "UPDATE configuration SET (nsecs_id) = (0);
		SELECT config();" | psql -U postgres
insert_DST $virt2_hex_bytea
insert_DST $virt3_hex_bytea
update_IoT
wait_ctrld
kill $!
echo "Scenario 4 for Gateway finished!"
