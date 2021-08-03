#!/bin/bash
sudo cp af_ieee802154_cp.h /usr/include
sudo cp elog.h /usr/include/postgresql/server/utils
g++ -o IoT -l bluetooth -l crypto -l pq -l pthread -l rt -l signal-protocol-c -l ssl IoT.cpp
g++ -o libIoT -shared -fpic -l bluetooth -l crypto -l pq -l pthread -l rt -l signal-protocol-c \
		-l ssl IoT.cpp
