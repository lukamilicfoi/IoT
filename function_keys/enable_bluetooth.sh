#!/bin/bash
systemctl start bluetooth
bluetoothctl power on
uxterm -e "btmon" &
touch /tmp/bluetooth_enabled
