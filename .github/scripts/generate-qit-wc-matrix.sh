#!/bin/bash

# Simplified script for QIT WooCommerce version matrix
# QIT handles version resolution for stable, rc, beta, nightly keywords
# We only need to fetch L-1 version for backward compatibility testing

set -e

# Function to get the latest WooCommerce version from WordPress.org API
get_latest_wc_version() {
    curl -s https://api.wordpress.org/plugins/info/1.0/woocommerce.json | jq -r '.version'
}

# Function to get the latest stable version for a specific major version
get_latest_stable_for_major() {
    local major_version=$1
    curl -s https://api.wordpress.org/plugins/info/1.0/woocommerce.json | \
    jq -r --arg major "$major_version" '.versions | with_entries(select(.key | startswith($major + ".") and (contains("-") | not))) | keys | sort_by( . | split(".") | map(tonumber) ) | last'
}

# Function to get the L-1 version (previous major version's latest stable)
get_l1_version() {
    local latest_version=$1
    local major_version=$(echo "$latest_version" | cut -d. -f1)
    local l1_major=$((major_version - 1))
    get_latest_stable_for_major "$l1_major"
}

# Get the latest WooCommerce version
echo "Fetching latest WooCommerce version..." >&2
LATEST_WC_VERSION=$(get_latest_wc_version)
echo "Latest WC version: $LATEST_WC_VERSION" >&2

# Get the L-1 version (we need the actual version number for this)
L1_VERSION=$(get_l1_version "$LATEST_WC_VERSION")
echo "L-1 version: $L1_VERSION" >&2

# Validate L-1 version
if [[ -z "$L1_VERSION" || "$L1_VERSION" == "null" ]]; then
    echo "Error: Could not extract L-1 version" >&2
    exit 1
fi

if [[ ! "$L1_VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    echo "Error: Invalid L-1 version: $L1_VERSION" >&2
    exit 1
fi

# Check if RC and beta are available (for metadata only)
# QIT will handle the actual version resolution
RC_AVAILABLE="false"
BETA_AVAILABLE="false"

LATEST_RC=$(curl -s https://api.wordpress.org/plugins/info/1.0/woocommerce.json | \
    jq -r '.versions | with_entries(select(.key|match("rc";"i"))) | keys | sort_by( . | split("-")[0] | split(".") | map(tonumber) ) | last')

if [[ -n "$LATEST_RC" && "$LATEST_RC" != "null" ]]; then
    RC_BASE="${LATEST_RC%%-*}"
    HIGHEST=$(printf '%s\n%s\n' "$RC_BASE" "$LATEST_WC_VERSION" | sort -V | tail -n1)
    if [[ "$HIGHEST" == "$RC_BASE" && "$RC_BASE" != "$LATEST_WC_VERSION" ]]; then
        RC_AVAILABLE="true"
        echo "RC available: $LATEST_RC" >&2
    else
        echo "RC not applicable (stable $LATEST_WC_VERSION already released)" >&2
    fi
else
    echo "No RC version available" >&2
fi

LATEST_BETA=$(curl -s https://api.wordpress.org/plugins/info/1.0/woocommerce.json | \
    jq -r --arg major "$(echo "$LATEST_WC_VERSION" | cut -d. -f1)" \
    '.versions | with_entries(select(.key | startswith($major + ".") and contains("beta"))) | keys | sort_by( . | split("-")[0] | split(".") | map(tonumber) ) | last')

if [[ -n "$LATEST_BETA" && "$LATEST_BETA" != "null" ]]; then
    BETA_AVAILABLE="true"
    echo "Beta available: $LATEST_BETA" >&2
else
    echo "No beta version available" >&2
fi

# Output JSON with L-1 version and availability flags
# QIT workflows will use keywords (stable, rc, beta) and check availability flags
RESULT=$(jq -n \
    --arg l1_version "$L1_VERSION" \
    --arg rc_available "$RC_AVAILABLE" \
    --arg beta_available "$BETA_AVAILABLE" \
    '{
        l1_version: $l1_version,
        rc_available: ($rc_available == "true"),
        beta_available: ($beta_available == "true")
    }')

echo "$RESULT"
