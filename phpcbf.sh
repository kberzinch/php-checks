#!/bin/bash

if [ ! -d "$(dirname "$0")/workspace/$1" ]; then
mkdir -p $(dirname "$0")/workspace
cd $(dirname "$0")/workspace
git clone ${4} ${1}
fi
cd $(dirname "$0")/workspace/$1/
git remote set-url origin ${4}

git checkout $3
git pull

if [[ "$2" != "$(git rev-parse HEAD)" ]]
then
    echo "Branch head does not match requested commit, exiting."
    exit;
fi

! $(dirname "$0")/vendor/bin/phpcbf --ignore=vendor --standard=psr2 .

git add .
GIT_COMMITTER_NAME='PHP Checks' GIT_COMMITTER_EMAIL='php-checks@kberzin.ch' git commit --author="PHP Checks <php-checks@kberzin.ch>" -m "Fix style issues"
git push origin $3
