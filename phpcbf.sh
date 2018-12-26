#!/bin/bash

cd /var/tmp/php-checks/$1

git checkout $3
git pull

if [[ "$2" -ne "$(git rev-parse HEAD)" ]]
then
    echo "Branch head does not match requested commit, exiting."
    exit;
fi

! $(dirname "$0")/vendor/bin/phpcbf --ignore=vendor --standard=psr2 .

git add .
git commit --author="PHP Checks <php-checks@kberzin.ch>" -m "Fix style issues"
git push origin $3
