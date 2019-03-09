#!/bin/bash

cd $(dirname "$0")/workspace/$1

$(dirname "$0")/vendor/bin/phpcs --config-set ignore_errors_on_exit 1
$(dirname "$0")/vendor/bin/phpcs --config-set ignore_warnings_on_exit 1
$(dirname "$0")/vendor/bin/phpcs --ignore=vendor,database,*.min.js,*.blade.php,bootstrap/cache --standard=psr2 --report=json --report-file=$2 .
