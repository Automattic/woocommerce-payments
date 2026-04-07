#!/bin/bash

# Script to dynamically generate WooCommerce version matrix for L-1 policy
# This script fetches the latest WC version and calculates the L-1 version

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

# Function to get specific major versions' latest stable
get_major_versions_latest() {
    local latest_version=$1
    local major_version=$(echo "$latest_version" | cut -d. -f1)
    local versions=()

    # Dynamically calculate L-1 major version
    local l1_major=$((major_version - 1))

    # Only get L-1 version (previous major) and current major
    # Skip intermediate major versions as they don't align with L-1 policy
    for ((i=l1_major; i<=major_version; i++)); do
        latest_stable=$(get_latest_stable_for_major "$i")
        if [[ -n "$latest_stable" && "$latest_stable" != "null" ]]; then
            versions+=("$latest_stable")
        fi
    done

    echo "${versions[@]}"
}

# Function to get the latest RC version from WordPress.org API
get_latest_rc_version() {
    curl -s https://api.wordpress.org/plugins/info/1.0/woocommerce.json | \
    jq -r '.versions | with_entries(select(.key|match("rc";"i"))) | keys | sort_by( . | split("-")[0] | split(".") | map(tonumber) ) | last'
}

# Function to get the latest beta version from WordPress.org API
get_latest_beta_version() {
    local latest_version=$1
    local major_version=$(echo "$latest_version" | cut -d. -f1)
    curl -s https://api.wordpress.org/plugins/info/1.0/woocommerce.json | \
    jq -r --arg major "$major_version" '.versions | with_entries(select(.key | startswith($major + ".") and contains("beta"))) | keys | sort_by( . | split("-")[0] | split(".") | map(tonumber) ) | last'
}

# Get the latest WooCommerce version
echo "Fetching latest WooCommerce version..." >&2
LATEST_WC_VERSION=$(get_latest_wc_version)
echo "Latest WC version: $LATEST_WC_VERSION" >&2

# Get the L-1 version
L1_VERSION=$(get_l1_version "$LATEST_WC_VERSION")
echo "L-1 version: $L1_VERSION" >&2

# Get major versions latest stable
MAJOR_VERSIONS=($(get_major_versions_latest "$LATEST_WC_VERSION"))
echo "Major versions latest stable: ${MAJOR_VERSIONS[*]}" >&2

# Get latest RC and beta versions
echo "Fetching latest RC and beta versions..." >&2
LATEST_RC_VERSION=$(get_latest_rc_version)
LATEST_BETA_VERSION=$(get_latest_beta_version "$LATEST_WC_VERSION")
echo "Latest RC version: $LATEST_RC_VERSION" >&2
echo "Latest beta version: $LATEST_BETA_VERSION" >&2

# Build the version array
VERSIONS=("7.7.0")  # Keep for business reasons (significant TPV)

# Add major versions latest stable (excluding current major since we'll use 'latest')
for version in "${MAJOR_VERSIONS[@]}"; do
    # Skip the current major version since we'll use 'latest' instead
    if [[ "$version" != "$LATEST_WC_VERSION" ]]; then
        VERSIONS+=("$version")
    fi
done

# Add latest, beta, rc (with actual versions)
VERSIONS+=("latest")
if [[ -n "$LATEST_BETA_VERSION" && "$LATEST_BETA_VERSION" != "null" ]]; then
    VERSIONS+=("$LATEST_BETA_VERSION")
    echo "Including beta version: $LATEST_BETA_VERSION" >&2
else
    echo "No beta version available, skipping beta tests" >&2
fi

# Decide whether to include RC: only include if RC base version (without suffix) is strictly greater than the latest stable.
INCLUDED_RC_VERSION=""
if [[ -n "$LATEST_RC_VERSION" && "$LATEST_RC_VERSION" != "null" ]]; then
    RC_BASE="${LATEST_RC_VERSION%%-*}"
    # Compare RC_BASE vs LATEST_WC_VERSION using sort -V
    HIGHEST=$(printf '%s\n%s\n' "$RC_BASE" "$LATEST_WC_VERSION" | sort -V | tail -n1)
    if [[ "$HIGHEST" == "$RC_BASE" && "$RC_BASE" != "$LATEST_WC_VERSION" ]]; then
        INCLUDED_RC_VERSION="$LATEST_RC_VERSION"
        VERSIONS+=("$LATEST_RC_VERSION")
        echo "Including RC version: $LATEST_RC_VERSION (base $RC_BASE > latest $LATEST_WC_VERSION)" >&2
    else
        echo "Skipping RC version $LATEST_RC_VERSION because stable $LATEST_WC_VERSION is already released for this line." >&2
    fi
else
    echo "No RC version available, skipping rc tests" >&2
fi

# Validate versions before output
if [[ -z "$L1_VERSION" || "$L1_VERSION" == "null" ]]; then
    echo "Error: Could not extract L-1 version" >&2
    exit 1
fi

if [[ ! "$L1_VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    echo "Error: Invalid L-1 version: $L1_VERSION" >&2
    exit 1
fi

# RC is optional; do not fail if not present or skipped

# Only validate beta if it's available
if [[ -n "$LATEST_BETA_VERSION" && "$LATEST_BETA_VERSION" != "null" ]]; then
    if [[ ! "$LATEST_BETA_VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+ ]]; then
        echo "Error: Invalid beta version: $LATEST_BETA_VERSION" >&2
        exit 1
    fi
fi

# Convert to JSON array and output only the JSON (no extra whitespace or newlines)
# Output a single JSON object with both versions and metadata
RESULT=$(jq -n \
    --argjson versions "$(printf '%s\n' "${VERSIONS[@]}" | jq -R . | jq -s .)" \
    --arg l1_version "$L1_VERSION" \
    --arg rc_version "${INCLUDED_RC_VERSION}" \
    --arg beta_version "${LATEST_BETA_VERSION}" \
    '{
        versions: $versions,
        metadata: {
            l1_version: $l1_version,
            rc_version: (if ($rc_version // "") == "" or ($rc_version == "null") then null else $rc_version end),
            beta_version: (if ($beta_version // "") == "" or ($beta_version == "null") then null else $beta_version end)
        }
    }')

echo "$RESULT"
