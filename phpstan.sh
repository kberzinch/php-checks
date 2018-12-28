#!/bin/bash

cd $(dirname "$0")/workspace/$1

if [ -f "phpstan.neon" ]; then
$(dirname "$0")/vendor/bin/phpstan analyse --no-progress --level=max --error-format=checkstyle .
else
$(dirname "$0")/vendor/bin/phpstan analyse --no-progress --configuration=$(dirname "$0")/default-phpstan.neon --level=max --error-format=checkstyle . 2>&1 | grep -v "Note: using configuration file $(dirname "$0")/workspace/${1}/phpstan.neon."
fi
