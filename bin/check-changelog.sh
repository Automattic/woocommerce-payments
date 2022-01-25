#!/usr/bin/env bash

BASE=${1-origin/develop}
HEAD=${2-HEAD}

# Get only added files from git diff.
CHANGELOG_FILES=$(git diff --name-only --diff-filter=A "$BASE" "$HEAD"  | grep '^changelog\/')

if [[ "$CHANGELOG_FILES" != "" ]]; then
	echo "Found changelog file(s):"
	echo "$CHANGELOG_FILES"
else
	echo -e "\033[31m ERROR: no changelog found. \033[00m"
	echo "Add add at least one changelog file for your PR by running: ./vendor/bin/changelogger add"
	exit 1
fi

echo "Validating changelog files..."
CHECK=$(./vendor/bin/changelogger validate --gh-action)
if [[ -z "$CHECK" ]]; then
	echo "All changelog files are valid."
else
	echo $CHECK
	exit 1
fi
