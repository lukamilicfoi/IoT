#!/bin/bash
text=
name=${@: -1}
for a in ${@:1:$#-1}; do
	text="$text`echo -n | downgrade --ala-only $a 2>/dev/null | grep -E $name \\
			| grep -Eo '[0-9]+\\)' | grep -Eo '[0-9]+'`\\n"
done
echo -en $text | downgrade --ala-only ${@:1:$#-1} -- --noconfirm

