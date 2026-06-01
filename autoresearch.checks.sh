#!/bin/bash
set -euo pipefail

LINT_LOG="$(mktemp -t wcpay-autoresearch-lint.XXXXXX.log)"
if ! npm run lint:js >"${LINT_LOG}" 2>&1; then
	cat "${LINT_LOG}" | tail -120
	rm -f "${LINT_LOG}"
	exit 1
fi
rm -f "${LINT_LOG}"
