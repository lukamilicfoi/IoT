#!/bin/bash
if ip l s enp0s20u2 down 2>/dev/null; then
	netctl stop-all
fi
rm /tmp/network_enabled
