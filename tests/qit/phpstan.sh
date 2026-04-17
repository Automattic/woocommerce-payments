#!/usr/bin/env bash

# Get the directory of the current script
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

# Source common.sh using the relative path
source "$DIR/common.sh"

JSON=0
ARGS=()
for arg in "$@"; do
	case "$arg" in
		--json) JSON=1 ;;
		*) ARGS+=("$arg") ;;
	esac
done

# Check if the --local flag is provided which means the tests should run against the development build
ZIP_FILE=""
if echo "${ARGS[@]}" | grep -q -- "--local"; then
	ZIP_FILE="$WCP_ROOT/woocommerce-payments.zip"

	# Check if the zip file exists
	if [[ ! -f "$ZIP_FILE" ]]; then
		echo "Zip file $ZIP_FILE does not exist. Please ensure the zip file is present in the main folder." >&2
		exit 1
	fi

	echo "Running PHPStan tests with development build $ZIP_FILE..." >&2
	set +e
	RAW="$($QIT_BINARY run:phpstan "$EXTENSION_NAME" --zip "$ZIP_FILE" --wait 2>&1)"
	EXIT_CODE=$?
	set -e
else
	echo "Running PHPStan tests..." >&2
	set +e
	RAW="$($QIT_BINARY run:phpstan "$EXTENSION_NAME" --wait 2>&1)"
	EXIT_CODE=$?
	set -e
fi

# Preserve the raw QIT output on stdout when `--json` is not requested so
# existing CI log consumers keep working. In `--json` mode, route the raw
# output to stderr so stdout is a single parseable JSON document.
if [ "$JSON" -eq 1 ]; then
	echo "$RAW" >&2
else
	echo "$RAW"
fi

if [ "$EXIT_CODE" -eq 0 ]; then
	STATUS="pass"
else
	STATUS="fail"
fi

REPORT_URL=$(echo "$RAW" | grep -oE 'https://qit\.woo\.com/[^ ]+' | head -n1 || true)
LINES=$(printf '%s\n' "$RAW" | wc -l | tr -d ' ')

if [ "$JSON" -eq 1 ]; then
	jq -n \
		--arg tool "qit-phpstan" \
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

if [ "$EXIT_CODE" -ne 0 ]; then
	echo "Failed to run PHPStan command. Exiting with status 1." >&2
	exit 1
fi
