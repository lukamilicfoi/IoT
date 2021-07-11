#!/bin/bash
pg_ctl initdb -D data -o "-E UTF-8 -U postgres"
sudo mkdir -m 777 /run/postgresql
pg_ctl -D data start
./IoT &
sleep 10
kill -s SIGINT $!
./init_db.sh
pg_ctl -D data stop
cp -r http /srv
