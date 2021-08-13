#!/bin/bash
sudo pacman --noconfirm -Syy apache bash bluez-libs coreutils firefox gcc git openssl php \
		php-apache php-pgsql postgresql psmisc sed util-linux
sed -Ez 's/;extension=pgsql/extension=pgsql/' /etc/php/php.ini \
		| sed -Ez 's/display_errors = Off/display_errors = On' > temp
sudo mv temp /etc/php/php.ini
git clone https://aur.archlinux.org/libsignal-protocol-c.git
cd libsignal-protocol-c
makepkg --noconfirm -csir
cd ..
sed -Ez 's/LoadModule mpm_event/#LoadModule mpm_event/' /etc/httpd/conf/httpd.conf \
		| sed -Ez '#LoadModule mpm_worker/LoadModule php7_module modules\/libphp7.so\n#LoadModule mpm_worker' \
		| sed -Ez '    #AddHandler/    AddHandler php7-script.php php\n    #AddHandler' \
		| sed -Ez 'Include /Include conf\/extra\/php7_module.conf\nInclude ' > temp
sudo mv temp /etc/https/conf/httpd.conf
rm -rf libsignal-protocol-c
