#!/bin/bash

if [ ! -d "$(dirname "$0")/workspace/$1" ]; then
mkdir -p $(dirname "$0")/workspace/
cd $(dirname "$0")/workspace/
git clone ${2} ${1}
cd $1
git checkout ${3}
composer install
exit
fi
cd $(dirname "$0")/workspace/$1/
git remote set-url origin ${2}
git fetch
git checkout ${3}
composer install
