#!/bin/bash
inits/file_edit.sh .fluxbox/init 'allowRemoteActions:\ttrue/allowRemoteActions:\tfalse'
killall -s SIGUSR2 fluxbox
rm /tmp/remote_enabled
