#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
REPO_ROOT="$( cd "$SCRIPT_DIR/../.." && pwd )"

PASS=0
FAIL=0

assert_eq() {
	if [ "$1" = "$2" ]; then
		echo "ok - $3"
		PASS=$((PASS + 1))
	else
		echo "not ok - $3"
		echo "  expected: $2"
		echo "  got:      $1"
		FAIL=$((FAIL + 1))
	fi
}

write_files() {
	local v="$1" changelog_block="$2"
	cat > woocommerce-payments.php <<PHP
<?php
/**
 * Plugin Name: WooPayments
 * Version: $v
 */
PHP
	echo "{\"name\":\"f\",\"version\":\"$v\"}" > package.json
	printf '*** WooPayments Changelog ***\n\n%s= 1.0.0 - 2026-01-01 =\n* Fix - Old\n' "$changelog_block" > changelog.txt
	printf '=== WooPayments ===\nStable tag: %s\n\n== Frequently Asked Questions ==\n\n= 1.1.0 - looks like a version =\n\nFAQ answer.\n\n== Changelog ==\n\n%s= 1.0.0 - 2026-01-01 =\n* Fix - Old\n' "$v" "$changelog_block" > readme.txt
}

# develop and release/1.1.0 diverge after 1.0.0. develop gains an unreleased
# entry; the release branch generates its changelog from two entries.
make_repo_fixture() {
	local dir
	dir="$(mktemp -d "${TMPDIR:-/tmp}/sync-develop-test.XXXXXX")"
	(
		cd "$dir"
		git init -q -b develop
		git config user.email test@test.test
		git config user.name test
		mkdir -p bin changelog
		cp "$REPO_ROOT/bin/bump-version.sh" "$REPO_ROOT/bin/sync-develop-from-release.sh" bin/
		touch changelog/.gitkeep
		echo "Significance: patch" > changelog/fix-a
		echo "Significance: patch" > changelog/fix-b
		write_files 1.0.0 ""
		git add . && git commit -q -m init

		git checkout -q -b release/1.1.0
		git rm -q changelog/fix-a changelog/fix-b
		write_files 1.1.0 $'= 1.1.0 - 2026-02-04 =\n* Fix - A\n* Fix - B\n\n'
		git commit -qam "Release 1.1.0"

		git checkout -q develop
		echo "Significance: patch" > changelog/fix-c
		git add . && git commit -q -m "Unreleased work"
	)
	echo "$dir"
}

block_of() {
	awk '/^= 1\.1\.0 - /{p=1} p&&/^= 1\.0\.0/{exit} p' "$1"
}

DIR=$(make_repo_fixture)
( cd "$DIR" && ./bin/sync-develop-from-release.sh 1.1.0 release/1.1.0 > /dev/null 2>&1 )
assert_eq "$(jq -r .version "$DIR/package.json")" "1.1.0" "bumps package.json"
assert_eq "$(grep '^Stable tag:' "$DIR/readme.txt")" "Stable tag: 1.1.0" "bumps the stable tag"
assert_eq "$(block_of "$DIR/changelog.txt")" $'= 1.1.0 - 2026-02-04 =\n* Fix - A\n* Fix - B' "inserts the changelog.txt block above the previous release"
assert_eq "$(grep -c '^= 1.1.0 - 2026-02-04 =$' "$DIR/readme.txt")" "1" "inserts the readme.txt block once"
assert_eq "$(grep -c '^= 1.1.0 - looks like a version =$' "$DIR/readme.txt")" "1" "leaves readme.txt FAQ headings alone"
assert_eq "$(cd "$DIR" && ls changelog | tr '\n' ' ')" "fix-c " "deletes released entries and keeps unreleased ones"

# The release moves a day and picks up two cherry-picked fixes, D and E. The
# changelog amend for E gets reverted, so E's entry is still unreleased.
(
	cd "$DIR"
	git add -A && git commit -q -m "Sync develop"
	echo "Significance: patch" > changelog/fix-d
	echo "Significance: patch" > changelog/fix-e
	git add . && git commit -q -m "Fixes D and E"
	git checkout -q release/1.1.0
	git checkout -q develop -- changelog/fix-d changelog/fix-e
	git commit -q -m "Cherry-pick D and E"
	git rm -q changelog/fix-d changelog/fix-e
	write_files 1.1.0 $'= 1.1.0 - 2026-02-05 =\n* Fix - A\n* Fix - B\n* Fix - D\n* Fix - E\n\n'
	git commit -qam "Amend changelog"
	git revert --no-edit HEAD > /dev/null
	git rm -q changelog/fix-d
	write_files 1.1.0 $'= 1.1.0 - 2026-02-05 =\n* Fix - A\n* Fix - B\n* Fix - D\n\n'
	git commit -qam "Amend changelog without E"
	git checkout -q develop
	./bin/sync-develop-from-release.sh 1.1.0 release/1.1.0 > /dev/null 2>&1
)
assert_eq "$(block_of "$DIR/changelog.txt")" $'= 1.1.0 - 2026-02-05 =\n* Fix - A\n* Fix - B\n* Fix - D' "replaces the changelog.txt block after an amend"
assert_eq "$(grep -c '^= 1.1.0 - 2026' "$DIR/readme.txt")" "1" "replaces the readme.txt block instead of adding a second one"
assert_eq "$(grep -c '^= 1.0.0 - ' "$DIR/changelog.txt")" "1" "keeps the previous release"
assert_eq "$(cd "$DIR" && ls changelog | tr '\n' ' ')" "fix-c fix-e " "deletes cherry-picked entries and keeps ones the release put back"
rm -rf "$DIR"

echo "# passed $PASS/$((PASS + FAIL))"
[ "$FAIL" -eq 0 ]
