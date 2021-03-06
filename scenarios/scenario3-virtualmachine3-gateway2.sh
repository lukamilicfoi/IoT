#!/bin/bash
. common
echo "Starting scenario 3 for Gateway 2..."
block_ctrlc
determine_hex_bytea 4
echo "TRUNCATE TABLE rules;" | psql -U postgres
run_IoT
echo "Press Enter after IoT has started..."
read var
insert_DST $virt4_hex_bytea
update_IoT
wait_ctrld
kill $!
echo "Scenario 3 for Gateway 2 finished!"
