#!/bin/bash
cd ~
if grep -Eq 'systemtray' .fluxbox/init; then
	inits/file_edit.sh .fluxbox/init 'systemtray, clock/systemtray'
else
	inits/file_edit.sh .fluxbox/init "!.* menu\\.\\n/session.screen0.toolbar.tools: prevworkspace, \
workspacename, nextworkspace, iconbar, systemtray"
fi
killall -s SIGUSR2 fluxbox
cd -
touch /tmp/clock_disabled
