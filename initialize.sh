#!/bin/bash
./IoT &
sleep 10
kill -s SIGINT $!
./init_db.sh
cp -r http /srv
