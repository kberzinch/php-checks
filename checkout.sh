#!/bin/bash

if [ ! -d "$(dirname "$0")/workspace/$1" ]; then
mkdir -p $(dirname "$0")/workspace/
cd $(dirname "$0")/workspace/
{ set +x; } 2>/dev/null
echo "+ git clone $(echo ${2} | sed -e "s/x-access-token:.*@/x-access-token:redacted@/g") ${1}"
git clone ${2} ${1}
set -x
cd $1
git checkout ${3}
composer install --no-interaction --no-progress --no-suggest
exit
fi
cd $(dirname "$0")/workspace/$1/
{ set +x; } 2>/dev/null
echo "+ git remote set-url origin $(echo ${2} | sed -e "s/x-access-token:.*@/x-access-token:redacted@/g")"
git remote set-url origin ${2}
set -x
git fetch
git checkout ${3}
composer install --no-interaction --no-progress --no-suggest
