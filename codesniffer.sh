#!/bin/bash

../vendor/bin/phpcs --config-set ignore_errors_on_exit 1
../vendor/bin/phpcs --config-set ignore_warnings_on_exit 1
../vendor/bin/phpcs --ignore=vendor --standard=psr2 --report=json --report-file=$2 /var/tmp/php-checks/$1
