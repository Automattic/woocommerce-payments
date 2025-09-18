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

# Support running focused specs or Playwright filters via simple CLI flags
SPEC_PATH="./e2e"
SPEC_REQUEST=""
PW_GREP=""
extra_qit_args=()

while [[ $# -gt 0 ]]; do
    case "$1" in
        --spec)
            SPEC_REQUEST="$2"
            shift 2
            ;;
        --grep)
            PW_GREP="$2"
            shift 2
            ;;
        --pw-options)
            extra_qit_args+=( "--pw_options=$2" )
            shift 2
            ;;
        --pw-options=*)
            extra_qit_args+=( "$1" )
            shift
            ;;
        --)
            shift
            extra_qit_args+=( "$@" )
            break
            ;;
        *)
            echo "Unknown option: $1"
            echo "Usage: $0 [--spec <path>] [--grep <pattern>] [--pw-options <options>] [-- <additional QIT args>]"
            exit 1
            ;;
    esac
done

if [[ -n "$SPEC_REQUEST" ]]; then
    # Allow pointing to a single spec file by automatically targeting the default
    # directory and passing a matching Playwright grep instead of a file path.
    spec_candidate="${SPEC_REQUEST#./}"
    spec_file=""

    if [[ -f "$QIT_ROOT/$spec_candidate" ]]; then
        spec_file="$spec_candidate"
    elif [[ -f "$QIT_ROOT/e2e/$spec_candidate" ]]; then
        spec_file="e2e/$spec_candidate"
    fi

    if [[ -n "$spec_file" ]]; then
        SPEC_PATH="./e2e"
        filename="${spec_file##*/}"
        basename="${filename%.spec.js}"
        extra_qit_args+=( "--pw_options=--grep @${basename}" )
    else
        SPEC_PATH="$SPEC_REQUEST"
    fi
fi

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

# Change to QIT directory so qit.yml is automatically found
cd "$QIT_ROOT"

# Convert relative QIT_BINARY path to absolute for directory change compatibility
if [[ "$QIT_BINARY" = ./* ]]; then
    QIT_CMD="$WCP_ROOT/$QIT_BINARY"
else
    QIT_CMD="$QIT_BINARY"
fi

if [[ -n "$PW_GREP" ]]; then
    extra_qit_args+=( "--pw_options=--grep ${PW_GREP}" )
fi

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

# Run QIT E2E tests (qit.yml automatically loaded from current directory)
echo "Running QIT E2E tests for local development..."

if [[ ${#extra_qit_args[@]} -gt 0 ]]; then
    "$QIT_CMD" run:e2e woocommerce-payments "$SPEC_PATH" \
        --source "$WCP_ROOT/woocommerce-payments.zip" \
        "${env_args[@]}" \
        "${extra_qit_args[@]}"
else
    "$QIT_CMD" run:e2e woocommerce-payments "$SPEC_PATH" \
        --source "$WCP_ROOT/woocommerce-payments.zip" \
        "${env_args[@]}"
fi

echo "QIT E2E foundation tests completed!"
