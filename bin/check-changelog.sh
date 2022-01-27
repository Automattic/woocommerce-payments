#!/usr/bin/env bash

BASE=${1-origin/develop}
HEAD=${2-HEAD}

# Get only added files from git diff.
CHANGELOG_FILES=$(git diff --name-only --diff-filter=A "$BASE" "$HEAD"  | grep '^changelog\/')

if [[ -n "$CHANGELOG_FILES" ]]; then
	echo "Found changelog file(s):"
	echo "$CHANGELOG_FILES"
else
	echo ":error::No changelog found."
	echo "Add at least one changelog file for your PR by running: ./vendor/bin/changelogger add"
	echo "Choose *patch* to leave it empty if the change is not significant. You can add multiple changelog files in one PR by running this command a few times."
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
