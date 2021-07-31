#!/bin/bash
inits/file_edit.sh .fluxbox/init 'allowRemoteActions:\tfalse/allowRemoteActions:\ttrue'
killall -s SIGUSR2 fluxbox
touch /tmp/remote_enabled
