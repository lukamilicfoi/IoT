#!/bin/bash
echo $((`cat /sys/class/backlight/intel_backlight/brightness` * 90 / 100)) > \
		/sys/class/backlight/intel_backlight/brightness
