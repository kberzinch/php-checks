#!/bin/bash

cd /var/tmp/php-checks/$1
find -iname "*.php" -exec php -l {} \;
