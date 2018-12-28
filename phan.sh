#!/bin/bash

cd $(dirname "$0")/workspace/$1

$(dirname "$0")/vendor/bin/phan --exclude-directory-list vendor --output-mode json --output $2 --directory . --dead-code-detection --strict-type-checking --plugin DollarDollarPlugin --plugin AlwaysReturnPlugin --plugin DuplicateArrayKeyPlugin --plugin PregRegexCheckerPlugin --plugin PrintfCheckerPlugin --plugin UnreachableCodePlugin --plugin NonBoolBranchPlugin --plugin NonBoolInLogicalArithPlugin --plugin DuplicateExpressionPlugin --plugin UnusedSuppressionPlugin
