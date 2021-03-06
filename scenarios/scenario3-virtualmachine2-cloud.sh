#!/bin/bash
. common
echo "Starting scenario 3 for Cloud..."
block_ctrlc
determine_hex_bytea 3
determine_hex_bytea 4
echo "TRUNCATE TABLE rules;" | psql -U postgres
run_IoT
echo "Press Enter after IoT has started..."
read var
insert_imm_DST $virt3_hex_bytea $virt4_hex_bytea
update_IoT
wait_ctrld
kill $!
echo "Scenario 3 for Cloud finished!"
