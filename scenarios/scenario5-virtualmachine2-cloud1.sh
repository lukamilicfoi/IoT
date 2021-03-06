#!/bin/bash
. common
echo "Starting scenario 5 for Cloud 1..."
block_ctrlc
determine_hex_bytea 3
determine_hex_bytea 5
echo "TRUNCATE TABLE rules;" | psql -U postgres
run_IoT
echo "Press Enter after IoT has started..."
read var
insert_imm_DST $virt3_hex_bytea $virt5_hex_bytea
update_IoT
wait_ctrld
kill $!
echo "Scenario 5 for Cloud 1 finished!"
