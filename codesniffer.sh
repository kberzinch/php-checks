#!/bin/bash

../vendor/bin/phpcs --config-set ignore_errors_on_exit 1 > /dev/null
../vendor/bin/phpcs --config-set ignore_warnings_on_exit 1 > /dev/null
../vendor/bin/phpcs --ignore=vendor --standard=psr2 --report=json /var/tmp/php-checks/$1
