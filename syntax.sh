#!/bin/bash

cd /var/tmp/php-checks/$1
find -type f -iname "*.php" -not -path "./vendor/*" -exec php -l {} \;
