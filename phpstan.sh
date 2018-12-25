#!/bin/bash

cd /var/tmp/php-checks/$1


if [ ! -f "phpstan.neon" ]; then
/var/www/php-checks/vendor/bin/phpstan analyse --configuration=/var/www/php-checks/default-phpstan.neon --level=max --error-format=checkstyle . 2>/dev/null | sed '/^[[:space:]]*$/d'
else
/var/www/php-checks/vendor/bin/phpstan analyse --level=max --error-format=checkstyle . 2>/dev/null | sed '/^[[:space:]]*$/d'
fi
