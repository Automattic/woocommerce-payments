#!/usr/bin/env bash

# Get the directory of the current script
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

# Source common.sh using the relative path
source "$DIR/common.sh"

echo "Running security tests..."
set +e
$QIT_BINARY run:security woocommerce-payments --zip=woocommerce-payments.zip --wait
EXIT_CODE=$?
set -e

# QIT exit codes (dev-trunk):
# 0 = success
# 1 = failure (critical security errors)
# 3 = warning (non-critical issues that don't block marketplace listing)
if [ $EXIT_CODE -eq 1 ]; then
    echo "Security test failed with critical errors. Exiting with status 1."
    exit 1
elif [ $EXIT_CODE -eq 3 ]; then
    echo "Security test completed with warnings (non-critical issues). CI will pass."
    exit 0
elif [ $EXIT_CODE -ne 0 ]; then
    echo "Security test failed with unexpected exit code $EXIT_CODE. Exiting with status 1."
    exit 1
fi

echo "Security test passed successfully."
