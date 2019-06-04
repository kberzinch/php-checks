#!/bin/bash

cd $(dirname "$0")/workspace/$1

find -type f -iname "*.php" -not -path "./vendor/*" -not -iname "*.blade.php" -exec php -l {} \;
