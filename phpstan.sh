#!/bin/bash

cd /var/tmp/php-checks/$1

if [ ! -f "phpstan.neon" ]; then
$(dirname "$0")/vendor/bin/phpstan analyse --no-progress --configuration=$(dirname "$0")/default-phpstan.neon --level=max --error-format=checkstyle .
else
$(dirname "$0")/vendor/bin/phpstan analyse --no-progress --level=max --error-format=checkstyle .
fi
