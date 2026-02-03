#!/usr/bin/env bash

set -e

# Get the directory where this script is located
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Source common.sh for shared QIT setup logic
source "$SCRIPT_DIR/common.sh"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
NC='\033[0m' # No Color

print_error() {
    echo -e "${RED}Error: $1${NC}" >&2
}

print_success() {
    echo -e "${GREEN}$1${NC}"
}


# Check if local.env exists
check_local_env() {
    if [[ ! -f "$QIT_ROOT/config/local.env" ]]; then
        print_error "tests/qit/config/local.env not found"
        echo ""
        echo "To set up local configuration:"
        echo "  1. Copy the template: cp tests/qit/config/default.env tests/qit/config/local.env"
        echo "  2. Edit tests/qit/config/local.env and fill in your credentials"
        echo ""
        echo "See tests/qit/LOCAL_DEVELOPMENT.md for detailed instructions."
        exit 1
    fi
}

# Validate Jetpack tokens for E2E tests
validate_jetpack_tokens() {
    local has_error=0

    # Check Jetpack tokens
    if [[ -z "$E2E_JP_SITE_ID" ]] || [[ -z "$E2E_JP_BLOG_TOKEN" ]] || [[ -z "$E2E_JP_USER_TOKEN" ]]; then
        print_error "Jetpack tokens are required for WooPayments E2E tests"
        echo ""
        echo "The following variables must be set in tests/qit/config/local.env:"
        echo "  E2E_JP_SITE_ID=your_site_id"
        echo "  E2E_JP_BLOG_TOKEN=your_blog_token"
        echo "  E2E_JP_USER_TOKEN=your_user_token"
        echo ""
        echo "See tests/qit/README.md for instructions on obtaining these tokens."
        echo ""
        has_error=1
    fi

    if [[ $has_error -eq 1 ]]; then
        exit 1
    fi
}

# Start the QIT environment
cmd_up() {
    echo "Starting QIT E2E development environment..."
    echo ""

    check_local_env

    # Load local env variables (common.sh already loaded it, but we need to re-export for subprocesses)
    set -a
    source "$QIT_ROOT/config/local.env"
    set +a

    validate_jetpack_tokens

    echo "Launching environment with global setup..."
    echo "(This may take a few minutes on first run)"
    echo ""

    # Run qit env:up with global-setup to run bootstrap/setup.sh
    "$QIT_BINARY" env:up \
        --config "$QIT_ROOT/qit.json" \
        --test-package "$QIT_ROOT/test-package" \
        --global-setup \
        --online \
        --env_file "$QIT_ROOT/config/local.env"

    echo ""
    echo "When finished, run: npm run test:qit-e2e-down"
    echo ""
}

# Stop the QIT environment
cmd_down() {
    echo "Stopping QIT E2E development environment..."

    "$QIT_BINARY" env:down

    print_success "Environment stopped."
}

# Show usage
usage() {
    echo "Usage: $0 <command>"
    echo ""
    echo "Commands:"
    echo "  up      Start the QIT E2E development environment"
    echo "  down    Stop the QIT E2E development environment"
    echo ""
    echo "For detailed documentation, see tests/qit/LOCAL_DEVELOPMENT.md"
}

# Main
case "${1:-}" in
    up)
        cmd_up
        ;;
    down)
        cmd_down
        ;;
    -h|--help|help)
        usage
        ;;
    *)
        usage
        exit 1
        ;;
esac
