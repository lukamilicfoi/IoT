#!/bin/bash
a=`cat /sys/class/backlight/intel_backlight/brightness`
b=$(($a * 110 / 100))
if [ $a -ne $b ]; then
	echo $b
else
	echo $(($b + 1))
fi > /sys/class/backlight/intel_backlight/brightness
