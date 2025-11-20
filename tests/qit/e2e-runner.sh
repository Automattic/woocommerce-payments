#!/usr/bin/env bash

# Enable strict error handling and safe field splitting for reliability
set -euo pipefail
IFS=$'\n\t'

# E2E test runner for WooPayments using QIT
cwd=$(pwd)
WCP_ROOT="$cwd"
QIT_ROOT="$cwd/tests/qit"

# Read local.env and build --env arguments for QIT
if [[ -f "$QIT_ROOT/config/local.env" ]]; then
    while IFS='=' read -r key value; do
        # Skip comments and empty lines
        [[ "$key" =~ ^[[:space:]]*# ]] && continue
        [[ -z "$key" ]] && continue

        # Remove leading/trailing whitespace and quotes from value
        value=$(echo "$value" | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//' -e 's/^["'\'']//' -e 's/["'\'']$//')

        # Export for build scripts that might need it
        export "${key}=${value}"
    done < "$QIT_ROOT/config/local.env"
fi

echo "Running E2E tests..."

# Change to project root directory to build plugin
cd "$WCP_ROOT"

# Compute a signature of sources relevant to the release build and
# skip rebuilding if nothing has changed since the last build.
compute_build_signature() {
    # Hash tracked files that affect the release artifact. This includes
    # sources packaged in the zip and build/config files that affect the output.
    git ls-files -z -- \
        assets \
        i18n \
        includes \
        languages \
        lib \
        src \
        templates \
        client \
        tasks/release.js \
        webpack \
        webpack.config.js \
        babel.config.js \
        package.json \
        package-lock.json \
        composer.json \
        composer.lock \
        woocommerce-payments.php \
        changelog.txt \
        readme.txt \
        SECURITY.md \
        2>/dev/null \
    | xargs -0 shasum -a 256 2>/dev/null \
    | shasum -a 256 \
    | awk '{print $1}'

    # Explicitly return 0 to avoid pipefail issues
    return 0
}

BUILD_HASH_FILE="$WCP_ROOT/woocommerce-payments.zip.hash"

CURRENT_SIG="$(compute_build_signature)"

# If WCP_FORCE_BUILD is set, always rebuild
if [[ -n "${WCP_FORCE_BUILD:-}" ]]; then
    echo "WCP_FORCE_BUILD set; forcing build of WooPayments plugin..."
    npm run build:release
    echo "$CURRENT_SIG" > "$BUILD_HASH_FILE"
elif [[ -f "woocommerce-payments.zip" && -f "$BUILD_HASH_FILE" ]]; then
    LAST_SIG="$(cat "$BUILD_HASH_FILE" 2>/dev/null || true)"
    if [[ "$CURRENT_SIG" == "$LAST_SIG" && -n "$CURRENT_SIG" ]]; then
        echo "No relevant changes detected since last build; skipping build."
    else
        echo "Changes detected; rebuilding WooPayments plugin..."
        npm run build:release
        echo "$CURRENT_SIG" > "$BUILD_HASH_FILE"
    fi
else
    echo "Building WooPayments plugin..."
    npm run build:release
    echo "$CURRENT_SIG" > "$BUILD_HASH_FILE"
fi

# QIT CLI is installed via composer as a dev dependency
QIT_CMD="./vendor/bin/qit"

# Build environment arguments for local development
env_args=()

# Add Jetpack environment variables if available
if [[ -n "${E2E_JP_SITE_ID:-}" ]]; then
    env_args+=( --env "E2E_JP_SITE_ID=${E2E_JP_SITE_ID}" )
fi
if [[ -n "${E2E_JP_BLOG_TOKEN:-}" ]]; then
    env_args+=( --env "E2E_JP_BLOG_TOKEN=${E2E_JP_BLOG_TOKEN}" )
fi
if [[ -n "${E2E_JP_USER_TOKEN:-}" ]]; then
    env_args+=( --env "E2E_JP_USER_TOKEN=${E2E_JP_USER_TOKEN}" )
fi

# Determine the desired spec target. Defaults to the whole suite unless
# overridden via the first positional argument (if it is not an option) or
# the WCP_E2E_SPEC environment variable.
SPEC_TARGET=${WCP_E2E_SPEC:-tests/qit/e2e}
TEST_TAG=""
declare -a FORWARDED_ARGS=()

# Parse arguments to extract spec target and optional --tag
while [[ $# -gt 0 ]]; do
    case "$1" in
        --tag=*)
            TEST_TAG="${1#*=}"
            shift
            ;;
        --tag)
            TEST_TAG="$2"
            shift 2
            ;;
        --*)
            FORWARDED_ARGS+=("$1")
            shift
            ;;
        *)
            # First non-option argument is the spec target
            if [[ -z "${SPEC_TARGET_SET:-}" ]]; then
                SPEC_TARGET="$1"
                SPEC_TARGET_SET=1
            fi
            shift
            ;;
    esac
done

# Normalize paths to work from project root
# Handle various input formats and convert them to paths QIT can use
normalize_path() {
    local input="$1"

    # If path exists as-is from project root, use it
    if [[ -e "$input" ]]; then
        echo "$input"
        return 0
    fi

    # Try prefixing with tests/qit/
    if [[ -e "tests/qit/$input" ]]; then
        echo "tests/qit/$input"
        return 0
    fi

    # Try prefixing with tests/qit/e2e/
    if [[ -e "tests/qit/e2e/$input" ]]; then
        echo "tests/qit/e2e/$input"
        return 0
    fi

    # If it looks like it starts with e2e/, try tests/qit/e2e/
    if [[ "$input" == e2e/* ]] && [[ -e "tests/qit/$input" ]]; then
        echo "tests/qit/$input"
        return 0
    fi

    # If just a filename (no path separators), search for it in e2e directory
    if [[ "$input" != */* ]]; then
        local found
        found=$(find tests/qit/e2e -name "$input" -type f | head -1)
        if [[ -n "$found" ]]; then
            echo "$found"
            return 0
        fi
    fi

    # Path not found
    echo "$input"
    return 1
}

SPEC_TARGET=$(normalize_path "$SPEC_TARGET") || {
    echo "Unable to locate spec target: $SPEC_TARGET" >&2
    exit 1
}

# Determine if we're running a specific file or directory
PW_OPTIONS=""
if [[ -f "$SPEC_TARGET" ]]; then
    # Running a specific spec file - pass it to Playwright via --pw_options
    # QIT needs the e2e directory, Playwright needs the specific file
    E2E_ROOT="tests/qit/e2e"

    # Ensure spec is within e2e directory
    case "$SPEC_TARGET" in
        "$E2E_ROOT"/*)
            # Extract the path relative to e2e directory
            PW_OPTIONS="${SPEC_TARGET#$E2E_ROOT/}"
            SPEC_TARGET="$E2E_ROOT"
            ;;
        *)
            echo "Specified spec file must reside within tests/qit/e2e" >&2
            exit 1
            ;;
    esac
fi

# Build the final command to execute QIT.
echo "Running QIT E2E tests for local development (target: ${SPEC_TARGET}${TEST_TAG:+ | tag: ${TEST_TAG}}${PW_OPTIONS:+ | pw_options: ${PW_OPTIONS}})..."

QIT_CMD_ARGS=(
    "$QIT_CMD" run:e2e woocommerce-payments "$SPEC_TARGET"
    --config "$QIT_ROOT/qit.yml"
    --source "$WCP_ROOT/woocommerce-payments.zip"
    "${env_args[@]}"
)

# Add tag filter if specified
if [[ -n "$TEST_TAG" ]]; then
    QIT_CMD_ARGS+=( --pw_test_tag="${TEST_TAG}" )
fi

if [[ -n "$PW_OPTIONS" ]]; then
    if (( ${#FORWARDED_ARGS[@]} )); then
        for arg in "${FORWARDED_ARGS[@]}"; do
            if [[ "$arg" == --pw_options || "$arg" == --pw_options=* ]]; then
                echo "Do not combine a spec file with manual --pw_options overrides." >&2
                exit 1
            fi
        done
    fi
    QIT_CMD_ARGS+=( --pw_options "$PW_OPTIONS" )
fi

if (( ${#FORWARDED_ARGS[@]} )); then
    QIT_CMD_ARGS+=( "${FORWARDED_ARGS[@]}" )
fi

"${QIT_CMD_ARGS[@]}"

echo "QIT E2E tests completed!"
