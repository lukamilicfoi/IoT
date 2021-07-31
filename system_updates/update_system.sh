#!/bin/bash
cd ~
rm -rf asp/* ~luka/asp/* ~luka/aur/* ~luka/data
if [ `cat /etc/hostname` = luka ]; then
	touch ~luka/0
else
	touch ~luka/1
fi
sed -Ez 's/.*########(.*)########.*/\1/' inits/initialize_host_part_2.sh > temp.sh
. temp.sh
rm temp.sh
cd -
