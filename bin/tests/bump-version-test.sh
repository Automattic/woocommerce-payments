#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
REPO_ROOT="$( cd "$SCRIPT_DIR/../.." && pwd )"
BUMP="$REPO_ROOT/bin/bump-version.sh"

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

assert_contains() {
	if echo "$1" | grep -Fq "$2"; then
		echo "ok - $3"
		PASS=$((PASS + 1))
	else
		echo "not ok - $3"
		echo "  haystack: $1"
		echo "  needle:   $2"
		FAIL=$((FAIL + 1))
	fi
}

make_fixture() {
	local dir
	dir="$(mktemp -d "${TMPDIR:-/tmp}/bump-version-test.XXXXXX")"
	cat > "$dir/woocommerce-payments.php" <<'PHP'
<?php
/**
 * Plugin Name: WooPayments
 * Version: 10.6.0
 */
PHP
	cat > "$dir/package.json" <<'JSON'
{
  "name": "test-fixture",
  "version": "10.6.0"
}
JSON
	cat > "$dir/package-lock.json" <<'JSON'
{
  "name": "test-fixture",
  "version": "10.6.0",
  "lockfileVersion": 3,
  "packages": {
    "": { "name": "test-fixture", "version": "10.6.0" }
  }
}
JSON
	cat > "$dir/readme.txt" <<'TXT'
=== WooPayments ===
Stable tag: 10.6.0
TXT
	echo "$dir"
}

# Test: write mode rewrites all three files
DIR=$(make_fixture)
( cd "$DIR" && "$BUMP" 10.7.0 > /dev/null )
assert_contains "$(cat "$DIR/woocommerce-payments.php")" "* Version: 10.7.0" "php header bumped"
assert_eq "$(jq -r .version "$DIR/package.json")" "10.7.0" "package.json bumped"
assert_eq "$(jq -r .version "$DIR/package-lock.json")" "10.7.0" "package-lock.json bumped"
assert_contains "$(cat "$DIR/readme.txt")" "Stable tag: 10.7.0" "readme stable tag bumped"
rm -rf "$DIR"

# Test: --check consistent
DIR=$(make_fixture)
set +e
( cd "$DIR" && "$BUMP" --check > /dev/null )
CHECK_EXIT=$?
set -e
assert_eq "$CHECK_EXIT" "0" "check passes on consistent fixture"
rm -rf "$DIR"

# Test: --check --json consistent
DIR=$(make_fixture)
JSON_OUT=$( cd "$DIR" && "$BUMP" --check --json )
assert_eq "$(echo "$JSON_OUT" | jq -r .consistent)" "true" "check --json reports consistent"
assert_eq "$(echo "$JSON_OUT" | jq -r .php_header)" "10.6.0" "check --json emits php version"
rm -rf "$DIR"

# Test: --check detects inconsistency (exit 2)
DIR=$(make_fixture)
sed -i.bak 's/10.6.0/10.5.0/' "$DIR/readme.txt" && rm -f "$DIR/readme.txt.bak"
set +e
( cd "$DIR" && "$BUMP" --check --json > "$DIR/out.json" 2>/dev/null )
CHECK_EXIT=$?
set -e
assert_eq "$CHECK_EXIT" "2" "check returns 2 on inconsistency"
assert_eq "$(jq -r .consistent "$DIR/out.json")" "false" "check --json reports inconsistent"
rm -rf "$DIR"

# Test: --check detects package-lock.json version mismatch
DIR=$(make_fixture)
tmp="$(mktemp)"
jq '.version = "10.5.0" | .packages[""].version = "10.5.0"' \
	"$DIR/package-lock.json" > "$tmp" && mv "$tmp" "$DIR/package-lock.json"
set +e
( cd "$DIR" && "$BUMP" --check --json > "$DIR/out.json" 2>/dev/null )
CHECK_EXIT=$?
set -e
assert_eq "$CHECK_EXIT" "2" "check returns 2 on lockfile mismatch"
assert_eq "$(jq -r .consistent "$DIR/out.json")" "false" "check --json reports inconsistent on lockfile mismatch"
assert_eq "$(jq -r .package_lock "$DIR/out.json")" "10.5.0" "check --json reports package-lock version"
rm -rf "$DIR"

# Test: invalid version format
DIR=$(make_fixture)
set +e
( cd "$DIR" && "$BUMP" not-a-version 2>/dev/null )
BAD_EXIT=$?
set -e
assert_eq "$BAD_EXIT" "3" "invalid version returns 3"
rm -rf "$DIR"

# Test: --dry-run leaves files untouched
DIR=$(make_fixture)
BEFORE_PHP=$(shasum "$DIR/woocommerce-payments.php" | awk '{print $1}')
BEFORE_PKG=$(shasum "$DIR/package.json" | awk '{print $1}')
BEFORE_LOCK=$(shasum "$DIR/package-lock.json" | awk '{print $1}')
BEFORE_README=$(shasum "$DIR/readme.txt" | awk '{print $1}')
DRY_OUT=$( cd "$DIR" && "$BUMP" 10.7.0 --dry-run 2>&1 )
assert_eq "$(shasum "$DIR/woocommerce-payments.php" | awk '{print $1}')" "$BEFORE_PHP" "dry-run leaves php header untouched"
assert_eq "$(shasum "$DIR/package.json" | awk '{print $1}')" "$BEFORE_PKG" "dry-run leaves package.json untouched"
assert_eq "$(shasum "$DIR/package-lock.json" | awk '{print $1}')" "$BEFORE_LOCK" "dry-run leaves package-lock.json untouched"
assert_eq "$(shasum "$DIR/readme.txt" | awk '{print $1}')" "$BEFORE_README" "dry-run leaves readme.txt untouched"
assert_contains "$DRY_OUT" "10.6.0 -> 10.7.0" "dry-run reports current vs target"
rm -rf "$DIR"

echo "# passed $PASS/$((PASS + FAIL))"
[ "$FAIL" -eq 0 ]
