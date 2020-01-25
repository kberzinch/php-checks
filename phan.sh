#!/bin/bash

cd $(dirname "$0")/workspace/$1

if [ -f ".phan/config.php" ]; then
$(dirname "$0")/vendor/bin/phan --output-mode json --output $2
else
$(dirname "$0")/vendor/bin/phan --exclude-directory-list vendor --output-mode json --output $2 --directory . --signature-compatibility --redundant-condition-detection --dead-code-detection --strict-type-checking --plugin DollarDollarPlugin --plugin AlwaysReturnPlugin --plugin DuplicateArrayKeyPlugin --plugin PregRegexCheckerPlugin --plugin PrintfCheckerPlugin --plugin UnreachableCodePlugin --plugin NonBoolBranchPlugin --plugin NonBoolInLogicalArithPlugin --plugin DuplicateExpressionPlugin --plugin UnusedSuppressionPlugin
fi
