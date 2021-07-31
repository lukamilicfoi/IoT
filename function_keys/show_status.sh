uxterm -e "if [ -e /tmp/audio_enabled ]; then
	echo \"audio is enabled\"
else
	echo \"audio is disabled\"
fi
if [ -e /tmp/bluetooth_enabled ]; then
	echo \"bluetooth is enabled\"
else
	echo \"bluetooth is disabled\"
fi
if [ -e /tmp/clock_disabled ]; then
	echo \"clock is disabled\"
else
	echo \"clock is enabled\"
fi
if [ -e /tmp/network_enabled ]; then
	echo \"network is enabled\"
else
	echo \"network is disabled\"
fi
if [ -e /tmp/ntp_disabled ]; then
	echo \"ntp is disabled\"
else
	echo \"ntp is enabled\"
fi
if [ -e /tmp/remote_enabled ]; then
	echo \"remote is enabled\"
else
	echo \"remote is disabled\"
fi
if [ -e /tmp/keyboard_hr ]; then
	echo \"keyboard is hr\"
else
	echo \"keyboard is us\"
fi
if [ -e /tmp/ntfs_enabled ]; then
	echo \"ntfs is enabled\"
else
	echo \"ntfs is disabled\"
fi
read" &
