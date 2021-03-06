#!/bin/bash
g++ -o IoT -l bluetooth -l crypto -l pq -l pthread -l rt -l signal-protocol-c -l ssl IoT.cpp
g++ -o libIoT -shared -fPIC -l bluetooth -l crypto -l pq -l pthread -l rt -l signal-protocol-c \
		-l ssl IoT.cpp

