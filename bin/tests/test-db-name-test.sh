#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
TEST_DB_NAME="$SCRIPT_DIR/../test-db-name.sh"

PASS=0
FAIL=0

assert_eq() {
	if [ "$1" = "$2" ]; then
		echo "ok - $3"
		PASS=$((PASS + 1))
	else
		echo "not ok - $3 (got: $1, want: $2)"
		FAIL=$((FAIL + 1))
	fi
}

SHORT_ID=laughing_davinci_da71cd
ID_AT_LIMIT=$(printf 'a%.0s' $(seq 52))
ID_OVER_LIMIT=$(printf 'a%.0s' $(seq 53))
LONG_ID=woocommerce_payments_fix_test_db_name_length_for_long_worktree_branches
LONG_ID_OTHER_SUFFIX=woocommerce_payments_fix_test_db_name_length_for_long_worktree_branchez

assert_eq "$("$TEST_DB_NAME")" "wcpay_tests_default" "defaults to the default worktree"
assert_eq "$("$TEST_DB_NAME" "$SHORT_ID")" "wcpay_tests_laughing_davinci_da71cd" "keeps a short name unchanged"
assert_eq "$("$TEST_DB_NAME" "$ID_AT_LIMIT")" "wcpay_tests_aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa" "keeps a 64-character name unchanged"
assert_eq "$("$TEST_DB_NAME" "$ID_OVER_LIMIT")" "wcpay_tests_aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa_7e196e98" "shortens a 65-character name to 64"
assert_eq "$("$TEST_DB_NAME" "$LONG_ID")" "wcpay_tests_woocommerce_payments_fix_test_db_name_lengt_1acdbc82" "shortens a long name with a hash of the full ID"
assert_eq "$("$TEST_DB_NAME" "$LONG_ID_OTHER_SUFFIX")" "wcpay_tests_woocommerce_payments_fix_test_db_name_lengt_429c3e0c" "keeps long IDs with a shared prefix apart"

echo "$PASS passed, $FAIL failed"
[ "$FAIL" -eq 0 ]
