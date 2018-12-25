#!/bin/bash

if [ ! -d "/var/tmp/php-checks/$1" ]; then
mkdir /var/tmp/php-checks
cd /var/tmp/php-checks
git clone ${2} ${1}
cd $1
git checkout ${3}
composer install
exit
fi
cd /var/tmp/php-checks/$1/
git remote set-url origin ${2}
git fetch
git checkout ${3}
composer install
