#!/bin/bash

cd "${0%/*}"

composer install --no-interaction --no-progress --no-suggest
