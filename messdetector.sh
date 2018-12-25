#!/bin/bash

../vendor/bin/phpmd /var/tmp/php-checks/$1 xml cleancode,design,naming,unusedcode --ignore-violations-on-exit --exclude /var/tmp/php-checks/$1/vendor/
