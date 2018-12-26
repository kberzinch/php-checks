#!/bin/bash

cd /var/tmp/php-checks/$1

git checkout $2

$(dirname "$0")/vendor/bin/phpcbf --ignore=vendor --standard=psr2 .

git add .
git commit -m "Fix style issues"
git push origin $3
