#!/bin/bash
. common
echo "Starting scenario 1 for router..."
block_ctrlc
determine_hex_bytea 0
determine_hex_bytea 1
echo "DROP TABLE IF EXISTS t${virt0_hex_bytea:5:16}, t${virt1_hex_bytea:5:16};
		TRUNCATE TABLE rules;" | psql -U postgres
run_IoT
wait_ctrld
kill $!
echo "Scenario 1 for router finished!"
