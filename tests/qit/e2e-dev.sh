#!/usr/bin/env bash

set -e

# Get the directory where this script is located
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WCP_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
QIT_ROOT="$SCRIPT_DIR"
QIT_BINARY="${QIT_BINARY:-$WCP_ROOT/vendor/bin/qit}"

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

# Load and validate environment variables
load_and_validate_env() {
    # Load local env variables
    set -a
    source "$QIT_ROOT/config/local.env"
    set +a

    local has_error=0

    # Check QIT credentials
    if [[ -z "$QIT_USER" ]] || [[ -z "$QIT_PASSWORD" ]]; then
        print_error "QIT_USER and QIT_PASSWORD are required"
        echo ""
        echo "These credentials are needed to authenticate with the QIT CLI."
        echo "Add them to tests/qit/config/local.env:"
        echo "  QIT_USER=your_qit_username"
        echo "  QIT_PASSWORD=your_qit_application_password"
        echo ""
        echo "To obtain credentials, see: https://mc.a8c.com/secret-store/?secret_id=11043"
        echo ""
        has_error=1
    fi

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

# Register QIT partner if needed
register_qit_partner() {
    export QIT_DISABLE_ONBOARDING=yes

    if ! "$QIT_BINARY" list 2>/dev/null | grep -q 'partner:remove'; then
        echo "Registering QIT partner credentials..."
        if ! "$QIT_BINARY" partner:add --user="$QIT_USER" --application_password="$QIT_PASSWORD"; then
            print_error "Failed to register QIT partner. Check your credentials."
            exit 1
        fi
    fi
}

# Start the QIT environment
cmd_up() {
    echo "Starting QIT E2E development environment..."
    echo ""

    check_local_env
    load_and_validate_env
    register_qit_partner

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
