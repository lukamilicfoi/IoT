#!/bin/bash
cd ~
inits/file_edit.sh .fluxbox/init 'systemtray/systemtray, clock'
killall -s SIGUSR2 fluxbox
cd -
rm /tmp/clock_disabled
