#!/bin/bash
sudo cp af_ieee802154_cp.h /usr/include
sudo cp elog.h /usr/include/postgresql/server/utils
g++ -oIoT -lbluetooth -lcrypto -lpq -lpthread -lrt -lsignal-protocol-c -lssl IoT.cpp
g++ -olibIoT -shared -fPIC -lbluetooth -lcrypto -lpq -lpthread -lrt -lsignal-protocol-c -lssl IoT.cpp

