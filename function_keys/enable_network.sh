#!/bin/bash
if ip l s enp0s20u2 up 2>/dev/null; then
	dhcpcd
	/usr/lib/systemd/systemd-networkd-wait-online -i enp0s20u2
elif netctl start wlp3s0-AndroidAP 2>/dev/null; then
	netctl wait-online wlp3s0-AndroidAP
else
	netctl start wlp3s0-dP7Ap3
	netctl wait-online wlp3s0-dP7Ap3
fi
touch /tmp/network_enabled
