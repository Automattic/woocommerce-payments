#!/usr/bin/env bash

# Enable strict error handling and safe field splitting for reliability
set -euo pipefail
IFS=$'\n\t'

# E2E test runner for WooPayments using QIT
cwd=$(pwd)
WCP_ROOT="$cwd"
QIT_ROOT="$cwd/tests/qit"

# Load local env variables if present
if [[ -f "$QIT_ROOT/config/local.env" ]]; then
    . "$QIT_ROOT/config/local.env"
fi

# If QIT_BINARY is not set, default to ./vendor/bin/qit
QIT_BINARY=${QIT_BINARY:-./vendor/bin/qit}

echo "Running E2E tests..."

# Change to project root directory to build plugin
cd "$WCP_ROOT"

# Foundation version: Simplified build process for easier testing
# For this foundation PR, we'll always build to avoid complex signature computation issues

BUILD_HASH_FILE="$WCP_ROOT/woocommerce-payments.zip.hash"

# For this foundation PR, always build if zip doesn't exist or if forced
if [[ -n "${WCP_FORCE_BUILD:-}" ]] || [[ ! -f "woocommerce-payments.zip" ]]; then
    echo "Building WooPayments plugin..."
    npm run build:release
    echo "foundation-build-$(date +%s)" > "$BUILD_HASH_FILE"
else
    echo "Using existing woocommerce-payments.zip"
fi

# Change to QIT directory so qit.yml is automatically found
cd "$QIT_ROOT"

# Convert relative QIT_BINARY path to absolute for directory change compatibility
if [[ "$QIT_BINARY" = ./* ]]; then
    QIT_CMD="$WCP_ROOT/$QIT_BINARY"
else
    QIT_CMD="$QIT_BINARY"
fi

echo "Running QIT E2E foundation tests (no Jetpack credentials)..."

# Run our QIT E2E tests (qit.yml automatically loaded from current directory)
"$QIT_CMD" run:e2e woocommerce-payments ./e2e \
    --source "$WCP_ROOT/woocommerce-payments.zip"

echo "QIT E2E foundation tests completed!"
