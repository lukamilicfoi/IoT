#!/bin/bash
. common
echo "Starting scenario 2 for Gateway..."
block_ctrlc
determine_hex_bytea 0
echo "DROP TABLE IF EXISTS t${virt0_hex_bytea:5:16};
		CREATE TABLE t${virt0_hex_bytea:5:16}(d NUMERIC(10, 0),
		t TIMESTAMP(4) WITHOUT TIME ZONE, temp NUMERIC(10, 0));
		TRUNCATE TABLE rules;
		INSERT INTO rules(id, sendreceive, filter, dropmodify, modification, prequery,
		postquery, message_to_send, proto_id_to_send, imm_addr_to_send,
		message_to__receive_, proto_id_to__receive_, imm_addr_to__receive_,
		activate, deactivate, active) VALUES(0, 1, 'LEN = E''\\\\x0001''', 1,
		'PL = DECODE(''temp='' || GET_BYTE(PL, 0), ''ESCAPE'');'
		'LEN = SET_BYTE(LEN, 1, OCTET_LENGTH(PL))', '', '', '', '', E'\\\\x', '', '',
		E'\\\\x', NULL, NULL, TRUE);" | psql -U postgres
run_IoT
wait_ctrld
kill $!
echo "Scenario 2 for Gateway finished!"
