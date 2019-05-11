#!/bin/bash

cd $(dirname "$0")/workspace/$1

$(dirname "$0")/vendor/bin/phpcs --config-set ignore_errors_on_exit 1
$(dirname "$0")/vendor/bin/phpcs --config-set ignore_warnings_on_exit 1

if [ -f "phpcs.xml" ]; then
    $(dirname "$0")/vendor/bin/phpcs --report=json --report-file=$2
else
    $(dirname "$0")/vendor/bin/phpcs --standard=$(dirname "$0")/phpcs-standard.xml --report=json --report-file=$2 .
fi
