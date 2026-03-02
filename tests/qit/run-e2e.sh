#!/usr/bin/env bash

set -e

# Get the directory where this script is located
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Source common.sh for shared QIT setup logic (loads credentials, authenticates partner)
source "$SCRIPT_DIR/common.sh"

# Set QIT results directory to a predictable repo-local path.
# QIT_RESULTS_DIR is an env var supported by the QIT CLI that overrides
# the default sys_get_temp_dir() location (e.g. /var/folders/... on macOS).
export QIT_RESULTS_DIR="${QIT_RESULTS_DIR:-$WCP_ROOT/qit-results}"

# Clean previous results — QIT's mkdir() fails if the directory already exists.
rm -rf "$QIT_RESULTS_DIR"

# Run QIT E2E with passthrough arguments.
# All flags (--woo, --wp, --php, --project, etc.) are forwarded as-is.
"$QIT_BINARY" run:e2e "$EXTENSION_NAME" \
    --config "$QIT_ROOT/qit.json" \
    "$@"
