#!/bin/bash
killall btmon
bluetoothctl power off
systemctl stop bluetooth
rm /tmp/bluetooth_enabled
