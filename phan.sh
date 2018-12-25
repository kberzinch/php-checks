#!/bin/bash

../vendor/bin/phan --exclude-directory-list /var/tmp/php-checks/$1/vendor --output-mode json --output $2 --directory /var/tmp/php-checks/$1 --dead-code-detection --strict-type-checking
