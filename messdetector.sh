#!/bin/bash

cd $(dirname "$0")/workspace/$1

$(dirname "$0")/vendor/bin/phpmd . xml cleancode,design,naming,unusedcode --ignore-violations-on-exit --exclude vendor --reportfile $2
