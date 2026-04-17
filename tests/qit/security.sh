#!/usr/bin/env bash

# Get the directory of the current script
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

# Source common.sh using the relative path
source "$DIR/common.sh"

JSON=0
for arg in "$@"; do
	case "$arg" in --json) JSON=1 ;; esac
done

echo "Running security tests..." >&2
set +e
RAW="$($QIT_BINARY run:security woocommerce-payments --zip=woocommerce-payments.zip --wait 2>&1)"
EXIT_CODE=$?
set -e

# Preserve the raw QIT output on stdout when `--json` is not requested so
# existing CI log consumers keep working. In `--json` mode, route the raw
# output to stderr so stdout is a single parseable JSON document.
if [ "$JSON" -eq 1 ]; then
	echo "$RAW" >&2
else
	echo "$RAW"
fi

# QIT exit codes (dev-trunk):
# 0 = success
# 1 = failure (critical security errors)
# 3 = warning (non-critical issues that don't block marketplace listing)
case "$EXIT_CODE" in
	0) STATUS="pass" ;;
	3) STATUS="warning" ;;
	1) STATUS="fail" ;;
	*) STATUS="error" ;;
esac

REPORT_URL=$(echo "$RAW" | grep -oE 'https://qit\.woo\.com/[^ ]+' | head -n1 || true)
LINES=$(printf '%s\n' "$RAW" | wc -l | tr -d ' ')

if [ "$JSON" -eq 1 ]; then
	jq -n \
		--arg tool "qit-security" \
		--arg status "$STATUS" \
		--argjson exit_code "$EXIT_CODE" \
		--arg report_url "${REPORT_URL:-}" \
		--argjson raw_output_lines "$LINES" \
		'{tool:$tool,
		  exit_code:$exit_code,
		  status:$status,
		  violations:{critical:null, warning:null, info:null},
		  report_url:(if $report_url == "" then null else $report_url end),
		  raw_output_lines:$raw_output_lines}'
fi

# Preserve pre-existing CI semantics: a warning (exit 3) is non-blocking.
if [ $EXIT_CODE -eq 1 ]; then
	echo "Security test failed with critical errors. Exiting with status 1." >&2
	exit 1
elif [ $EXIT_CODE -eq 3 ]; then
	echo "Security test completed with warnings (non-critical issues). CI will pass." >&2
	exit 0
elif [ $EXIT_CODE -ne 0 ]; then
	echo "Security test failed with unexpected exit code $EXIT_CODE. Exiting with status 1." >&2
	exit 1
fi

echo "Security test passed successfully." >&2
