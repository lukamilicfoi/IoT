#!/bin/bash
sudo pacman --noconfirm -Syy apache bash bluez-libs coreutils firefox gcc git openssl php \
		php-apache php-pgsql postgresql psmisc sed util-linux
git clone https://aur.archlinux.org/libsignal-protocol-c.git
cd libsignal-protocol-c
makepkg --noconfirm -csir
cd ..
rm -rf libsignal-protocol-c
