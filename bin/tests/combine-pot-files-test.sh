#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
REPO_ROOT="$( cd "$SCRIPT_DIR/../.." && pwd )"
COMBINE="$REPO_ROOT/bin/combine-pot-files.php"

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

refs_for() {
	awk -v id="msgid \"$2\"" '/^#: /{ refs = refs $0 "\n"; next } $0 == id { printf "%s", refs; exit } /^$/{ refs = "" }' "$1"
}

make_fixture() {
	local dir
	dir="$(mktemp -d "${TMPDIR:-/tmp}/combine-pot-files-test.XXXXXX")"
	mkdir -p "$dir/dist/chunks" "$dir/languages"
	cat > "$dir/dist/index.js.map" <<'JSON'
{"file":"index.js","sources":["webpack://woocommerce-payments/./client/index.js"]}
JSON
	cat > "$dir/dist/settings.js.map" <<'JSON'
{"file":"settings.js","sources":["webpack://woocommerce-payments/./client/settings/index.js"]}
JSON
	cat > "$dir/dist/multi-currency.js.map" <<'JSON'
{"file":"multi-currency.js","sources":["webpack://woocommerce-payments/./includes/multi-currency/client/settings/index.js"]}
JSON
	cat > "$dir/dist/chunks/123.js.map" <<'JSON'
{"file":"chunks/123.js?ver=abc","sources":["webpack://woocommerce-payments/./client/shared/lazy.tsx"]}
JSON
	cat > "$dir/dist/chunks/orphan.js.map" <<'JSON'
{"file":"chunks/orphan.js?ver=def","sources":["webpack://woocommerce-payments/./client/orphan.tsx"]}
JSON
	cat > "$dir/dist/i18n-chunk-entries.json" <<'JSON'
{"chunks/123.js":["index.js","settings.js"]}
JSON
	cat > "$dir/languages/woocommerce-payments-client.pot" <<'POT'
msgid ""
msgstr ""

#: client/index.js:1
msgid "Entry string"
msgstr ""

#: client/shared/lazy.tsx:5
msgid "Lazy string"
msgstr ""

#: client/orphan.tsx:5
msgid "Orphan string"
msgstr ""

#: includes/multi-currency/client/settings/index.js:7
msgid "Multi-currency string"
msgstr ""
POT
	cat > "$dir/languages/woocommerce-payments.pot" <<'POT'
msgid ""
msgstr ""

#: includes/class-foo.php:1
msgid "PHP string"
msgstr ""
POT
	echo "$dir"
}

run_combine() {
	( cd "$1" && php "$COMBINE" languages/woocommerce-payments-client.pot languages/woocommerce-payments.pot )
}

# Test: strings in lazy chunks are credited to the entries that load them
DIR=$(make_fixture)
OUT=$(run_combine "$DIR")
POT="$DIR/languages/woocommerce-payments.pot"
assert_eq "$(refs_for "$POT" "Entry string")" "#: dist/index.js:1
#: client/index.js:1" "entry string references its entry file"
assert_eq "$(refs_for "$POT" "Lazy string")" "#: dist/index.js:1
#: dist/settings.js:1
#: client/shared/lazy.tsx:5" "chunk string references every entry that loads the chunk"
assert_eq "$(refs_for "$POT" "Orphan string")" "#: client/orphan.tsx:5" "string in a chunk no entry loads gets no dist reference"
assert_eq "$(echo "$OUT" | grep -c "No entry loads the chunk 'chunks/orphan.js'")" "1" "warns about a chunk no entry loads"
assert_eq "$(refs_for "$POT" "Multi-currency string")" "#: dist/multi-currency.js:1
#: includes/multi-currency/client/settings/index.js:7" "multi-currency string references its entry file"
assert_eq "$(refs_for "$POT" "PHP string")" "#: includes/class-foo.php:1" "PHP string keeps its reference"
rm -rf "$DIR"

# Test: a missing chunk-to-entry map fails the build
DIR=$(make_fixture)
rm "$DIR/dist/i18n-chunk-entries.json"
set +e
OUT=$(run_combine "$DIR")
EXIT=$?
set -e
assert_eq "$EXIT" "1" "missing chunk-to-entry map exits with 1"
assert_eq "$(echo "$OUT" | grep -c "Unable to load 'dist/i18n-chunk-entries.json'")" "1" "missing chunk-to-entry map explains the failure"
rm -rf "$DIR"

# Test: a missing client POT fails the build
DIR=$(make_fixture)
rm "$DIR/languages/woocommerce-payments-client.pot"
set +e
OUT=$(run_combine "$DIR")
EXIT=$?
set -e
assert_eq "$EXIT" "1" "missing client POT exits with 1"
assert_eq "$(echo "$OUT" | grep -c "File not found: languages/woocommerce-payments-client.pot")" "1" "missing client POT explains the failure"
rm -rf "$DIR"

# Test: a missing argument fails with usage
set +e
OUT=$(php "$COMBINE" languages/woocommerce-payments-client.pot)
EXIT=$?
set -e
assert_eq "$EXIT" "1" "missing argument exits with 1"
assert_eq "$(echo "$OUT" | grep -c "^Usage:")" "1" "missing argument prints usage"

echo "# passed $PASS/$((PASS + FAIL))"
[ "$FAIL" -eq 0 ]
