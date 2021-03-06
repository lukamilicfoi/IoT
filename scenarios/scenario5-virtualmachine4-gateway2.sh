#!/bin/bash
. common
echo "Starting scenario 5 for Gateway 2..."
block_ctrlc
determine_hex_bytea 5
echo "TRUNCATE TABLE rules;" | psql -U postgres
run_IoT
echo "Press Enter after IoT has started..."
read var
insert_DST $virt5_hex_bytea
update_IoT
wait_ctrld
kill $!
echo "Scenario 5 for Gateway 2 finished!"
