#!/bin/bash

cd $(dirname "$0")/workspace/$1

if [ -f "phpmd.xml" ]; then
$(dirname "$0")/vendor/bin/phpmd . xml cleancode,design,naming,unusedcode,phpmd.xml --ignore-violations-on-exit --exclude vendor --reportfile $2
else
$(dirname "$0")/vendor/bin/phpmd . xml cleancode,design,naming,unusedcode --ignore-violations-on-exit --exclude vendor --reportfile $2
fi
