#!/bin/bash
pg_ctl initdb -D data -o "-E UTF-8 -U postgres"
sudo mkdir -m 777 /run/postgresql
pg_ctl -D data start
sudo ./IoT &
sleep 10
sudo killall -s SIGINT IoT
./init_db.sh
pg_ctl -D data stop
sudo cp -r http /srv
