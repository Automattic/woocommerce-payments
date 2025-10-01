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
SPEC_TARGET=${WCP_E2E_SPEC:-./e2e}
declare -a FORWARDED_ARGS=()
if [[ $# -gt 0 ]]; then
    if [[ $1 != --* ]]; then
        SPEC_TARGET="$1"
        shift
    fi
    FORWARDED_ARGS=( "$@" )
fi

if [[ ! -e "$SPEC_TARGET" && -e "./e2e/$SPEC_TARGET" ]]; then
    SPEC_TARGET="./e2e/$SPEC_TARGET"
fi

PW_OPTIONS=""
QIT_TEST_ARG="$SPEC_TARGET"

if [[ -f "$SPEC_TARGET" ]]; then
    # Convert file path to Playwright argument while using ./e2e as QIT target.
    # Implemented in POSIX shell to avoid a python dependency.
    abspath() {
        local path="$1"
        if [[ -d "$path" ]]; then
            (cd "$path" 2>/dev/null && pwd -P) || return 1
        else
            local dir base
            dir=$(dirname "$path")
            base=$(basename "$path")
            (cd "$dir" 2>/dev/null && printf '%s/%s' "$(pwd -P)" "$base") || return 1
        fi
    }

    spec_abs=$(abspath "$SPEC_TARGET") || {
        echo "Specified spec file must reside within ./e2e" >&2
        exit 1
    }
    root_abs=$(abspath "./e2e") || {
        echo "Unable to resolve ./e2e directory" >&2
        exit 1
    }

    # Ensure the spec file is inside the e2e root and compute the relative path.
    case "$spec_abs" in
        "$root_abs"/*)
            PW_OPTIONS="${spec_abs#$root_abs/}"
            ;;
        *)
            echo "Specified spec file must reside within ./e2e" >&2
            exit 1
            ;;
    esac
    QIT_TEST_ARG="./e2e"
elif [[ -d "$SPEC_TARGET" ]]; then
    QIT_TEST_ARG="$SPEC_TARGET"
else
    if [[ -n "$SPEC_TARGET" ]]; then
        echo "Unable to locate spec target: $SPEC_TARGET" >&2
    fi
    exit 1
fi

# Build the final command to execute QIT.
echo "Running QIT E2E tests for local development (target: ${QIT_TEST_ARG}${PW_OPTIONS:+ | pw_options: ${PW_OPTIONS}})..."

QIT_CMD_ARGS=(
    "$QIT_CMD" run:e2e woocommerce-payments "$QIT_TEST_ARG"
    --source "$WCP_ROOT/woocommerce-payments.zip"
    "${env_args[@]}"
)

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
