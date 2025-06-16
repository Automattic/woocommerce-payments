#!/usr/bin/env bash

# Get the directory of the current script
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

# Source common.sh using the relative path
source "$DIR/common.sh"

echo "Running security tests..."
$QIT_BINARY run:security woocommerce-payments --zip=woocommerce-payments.zip --wait
if [ $? -ne 0 ]; then
    echo "Failed to run security command. Exiting with status 1."
    exit 1
fi
