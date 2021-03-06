#!/bin/bash
. common
echo "Starting scenario 4 for Cloud..."
block_ctrlc
determine_hex_bytea 2
echo "DROP TABLE IF EXISTS t${virt2_hex_bytea:5:16};
		TRUNCATE TABLE rules;" | psql -U postgres
run_IoT
wait_ctrld
kill $!
echo "Scenario 4 for Cloud finished!"
