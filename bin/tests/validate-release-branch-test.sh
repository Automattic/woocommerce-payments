#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
REPO_ROOT="$( cd "$SCRIPT_DIR/../.." && pwd )"
VALIDATE="$REPO_ROOT/bin/validate-release-branch.sh"

PASS=0
FAIL=0

assert_eq() {
	if [ "$1" = "$2" ]; then
		echo "ok - $3"
		PASS=$((PASS + 1))
	else
		echo "not ok - $3 ($1 != $2)"
		FAIL=$((FAIL + 1))
	fi
}

make_repo_fixture() {
	local v="$1"
	local dir
	dir="$(mktemp -d "${TMPDIR:-/tmp}/validate-test.XXXXXX")"
	(
		cd "$dir"
		git init -q -b main
		git config user.email test@test.test
		git config user.name test
		mkdir -p changelog
		# Leave changelog empty (no pending entries). Seed a .gitkeep so dir is preserved.
		touch changelog/.gitkeep
		cat > woocommerce-payments.php <<PHP
<?php
/**
 * Plugin Name: WooPayments
 * Version: $v
 */
PHP
		echo "{\"name\":\"f\",\"version\":\"$v\"}" > package.json
		echo "{\"name\":\"f\",\"version\":\"$v\",\"lockfileVersion\":3,\"packages\":{\"\":{\"name\":\"f\",\"version\":\"$v\"}}}" > package-lock.json
		cat > readme.txt <<TXT
=== WooPayments ===
Stable tag: $v
TXT
		cat > changelog.txt <<CL
= $v - 2026-04-15 =
* Fix - Something

CL
		# Copy bump-version.sh so validate can find it via its own bin/
		mkdir -p bin
		cp "$REPO_ROOT/bin/bump-version.sh" bin/bump-version.sh
		chmod +x bin/bump-version.sh
		cp "$REPO_ROOT/bin/validate-release-branch.sh" bin/validate-release-branch.sh
		chmod +x bin/validate-release-branch.sh
		git add .
		git commit -q -m init
		git checkout -q -b "release/$v"
	)
	echo "$dir"
}

# Happy path
V=10.7.0
DIR=$(make_repo_fixture "$V")
( cd "$DIR" && ./bin/validate-release-branch.sh --json > "$DIR.out.json" )
assert_eq "$(jq -r .pass "$DIR.out.json")" "true" "happy path passes"
assert_eq "$(jq -r '.checks.branch_name.pass' "$DIR.out.json")" "true" "branch_name ok"
assert_eq "$(jq -r '.checks.version_consistent.pass' "$DIR.out.json")" "true" "version consistent"
assert_eq "$(jq -r '.checks.stable_tag_matches.pass' "$DIR.out.json")" "true" "stable tag matches"
assert_eq "$(jq -r '.checks.changelog_has_release_entry.pass' "$DIR.out.json")" "true" "changelog has entry"
assert_eq "$(jq -r '.checks.no_pending_changelog_entries.pass' "$DIR.out.json")" "true" "no pending entries"
rm -rf "$DIR"

# Pending changelog file makes it fail
DIR=$(make_repo_fixture "$V")
(
	cd "$DIR"
	echo "pending" > changelog/pending.txt
	git add changelog/pending.txt
	git commit -q -m wip
)
set +e
( cd "$DIR" && ./bin/validate-release-branch.sh --json > "$DIR.out.json" )
EXIT=$?
set -e
assert_eq "$EXIT" "1" "pending entries causes non-zero exit"
assert_eq "$(jq -r .pass "$DIR.out.json")" "false" "pass=false on failure"
assert_eq "$(jq -r '.checks.no_pending_changelog_entries.pass' "$DIR.out.json")" "false" "pending entries check fails"
rm -rf "$DIR"

# Wrong branch name
DIR=$(make_repo_fixture "$V")
( cd "$DIR" && git checkout -q -b feature/whatever )
set +e
( cd "$DIR" && ./bin/validate-release-branch.sh --json > "$DIR.out.json" )
EXIT=$?
set -e
assert_eq "$EXIT" "1" "wrong branch name fails"
assert_eq "$(jq -r '.checks.branch_name.pass' "$DIR.out.json")" "false" "branch_name check fails"
rm -rf "$DIR"

echo "# passed $PASS/$((PASS + FAIL))"
[ "$FAIL" -eq 0 ]
