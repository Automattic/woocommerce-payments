#!/usr/bin/env bash
#
# Auto-generates tests/e2e/config/local.env from local infrastructure.
#
# Usage:
#   bin/setup-e2e-local.sh [--server-path /path/to/transact-platform-server]
#
# The script auto-detects:
#   - Stripe test keys from transact-platform-server/local/secrets.php
#   - Stripe Account ID from the running dev Docker via WP-CLI
#   - Dev tools location from the Docker WordPress install
#   - Transact Platform Server repo path
#
# Values that can't be auto-detected are prompted interactively.

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

info() { echo -e "${BLUE}i${NC} $1"; }
success() { echo -e "${GREEN}+${NC} $1"; }
warn() { echo -e "${YELLOW}!${NC} $1"; }
error() { echo -e "${RED}x${NC} $1"; }

# --- Parse arguments ---
SERVER_PATH=""
WOOPAY_BLOG_ID=""
STRIPE_ACCOUNT_ID=""
SKIP_SUBSCRIPTIONS="1"
SKIP_ACTION_SCHEDULER="1"
SKIP_BLOCKS="1"
MODE="local"  # local or live

while [[ $# -gt 0 ]]; do
    case $1 in
        --server-path) SERVER_PATH="$2"; shift 2 ;;
        --woopay-blog-id) WOOPAY_BLOG_ID="$2"; shift 2 ;;
        --stripe-account-id) STRIPE_ACCOUNT_ID="$2"; shift 2 ;;
        --live) MODE="live"; shift ;;
        --with-subscriptions) SKIP_SUBSCRIPTIONS=""; shift ;;
        --with-action-scheduler) SKIP_ACTION_SCHEDULER=""; shift ;;
        --with-blocks) SKIP_BLOCKS=""; shift ;;
        --help)
            echo "Usage: bin/setup-e2e-local.sh [OPTIONS]"
            echo ""
            echo "Auto-generates tests/e2e/config/local.env from local infrastructure."
            echo ""
            echo "Options:"
            echo "  --server-path PATH       Path to transact-platform-server repo"
            echo "  --woopay-blog-id ID      WooPay Blog ID"
            echo "  --stripe-account-id ID   Stripe Account ID (acct_xxx)"
            echo "  --live                   Use live server mode instead of local"
            echo "  --with-subscriptions     Include WC Subscriptions tests"
            echo "  --with-action-scheduler  Include Action Scheduler tests"
            echo "  --with-blocks            Include WC Blocks tests"
            echo "  --help                   Show this help"
            exit 0
            ;;
        *) error "Unknown option: $1"; exit 1 ;;
    esac
done

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
LOCAL_ENV_PATH="$PROJECT_ROOT/tests/e2e/config/local.env"

resolve_dev_container() {
    local container_id=""
    local container_name=""
    local worktree_id=""
    local candidate=""

    container_id=$(cd "$PROJECT_ROOT" && docker compose ps -q wordpress 2>/dev/null || true)
    if [[ -n "$container_id" ]]; then
        container_name=$(docker inspect --format '{{.Name}}' "$container_id" 2>/dev/null | sed 's#^/##')
        if [[ -n "$container_name" ]]; then
            echo "$container_name"
            return
        fi
    fi

    worktree_id=$(grep '^WORKTREE_ID=' "$PROJECT_ROOT/.env" 2>/dev/null | cut -d= -f2)
    if [[ -n "$worktree_id" ]]; then
        candidate="wcpay_wp_${worktree_id}"
        if docker ps --format '{{.Names}}' 2>/dev/null | grep -q "^${candidate}$"; then
            echo "$candidate"
            return
        fi
    fi

    if docker ps --format '{{.Names}}' 2>/dev/null | grep -q '^wcpay_wp_default$'; then
        echo "wcpay_wp_default"
    fi
}

DEV_CONTAINER="$(resolve_dev_container)"

echo ""
echo "================================================"
echo "  WooPayments E2E Local Environment Setup"
echo "================================================"
echo ""

# Check if local.env already exists
if [[ -f "$LOCAL_ENV_PATH" ]]; then
    warn "local.env already exists at $LOCAL_ENV_PATH"
    read -p "Overwrite? (y/N) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        info "Keeping existing local.env"
        exit 0
    fi
fi

# --- Auto-detect Transact Platform Server ---
if [[ -z "$SERVER_PATH" ]]; then
    info "Looking for transact-platform-server..."

    SEARCH_PATHS=(
        "$PROJECT_ROOT/../transact-platform-server"
        "$HOME/src/transact-platform-server"
        "$HOME/projects/transact-platform-server"
    )

    for path in "${SEARCH_PATHS[@]}"; do
        if [[ -d "$path" && -f "$path/local/secrets.php" ]]; then
            SERVER_PATH="$(cd "$path" && pwd)"
            success "Found at $SERVER_PATH"
            break
        fi
    done

    if [[ -z "$SERVER_PATH" ]]; then
        warn "Could not auto-detect transact-platform-server"
        read -p "Enter path to transact-platform-server (or press Enter to skip for live mode): " SERVER_PATH
        if [[ -z "$SERVER_PATH" ]]; then
            MODE="live"
            warn "Switching to live server mode"
        fi
    fi
fi

# Validate server path if in local mode
if [[ "$MODE" == "local" && -n "$SERVER_PATH" && ! -f "$SERVER_PATH/local/secrets.php" ]]; then
    error "transact-platform-server not found at $SERVER_PATH (missing local/secrets.php)"
    error "Run the server setup first, or use --live for live server mode."
    exit 1
fi

# --- Extract Stripe credentials from secrets.php ---
STRIPE_PUBLIC_KEY=""
STRIPE_SECRET_KEY=""
STRIPE_WEBHOOK_KEY=""

if [[ "$MODE" == "local" && -f "$SERVER_PATH/local/secrets.php" ]]; then
    info "Extracting Stripe credentials from secrets.php..."

    STRIPE_PUBLIC_KEY=$(grep "WCPAY_STRIPE_TEST_PUBLIC_KEY" "$SERVER_PATH/local/secrets.php" | sed "s/.*'\(pk_test_[^']*\)'.*/\1/")
    STRIPE_SECRET_KEY=$(grep "WCPAY_STRIPE_TEST_SECRET_KEY" "$SERVER_PATH/local/secrets.php" | sed "s/.*'\(sk_test_[^']*\)'.*/\1/")
    STRIPE_WEBHOOK_KEY=$(grep "WCPAY_STRIPE_TEST_WEBHOOK_SIGNATURE_KEY" "$SERVER_PATH/local/secrets.php" | sed "s/.*'\(whsec_[^']*\)'.*/\1/")

    [[ -n "$STRIPE_PUBLIC_KEY" ]] && success "Stripe public key: ${STRIPE_PUBLIC_KEY:0:20}..." || warn "Could not extract Stripe public key"
    [[ -n "$STRIPE_SECRET_KEY" ]] && success "Stripe secret key: ${STRIPE_SECRET_KEY:0:20}..." || warn "Could not extract Stripe secret key"
    [[ -n "$STRIPE_WEBHOOK_KEY" ]] && success "Stripe webhook key: ${STRIPE_WEBHOOK_KEY:0:15}..." || warn "Could not extract Stripe webhook key"
fi

# --- Get Stripe Account ID from dev Docker ---
if [[ -z "$STRIPE_ACCOUNT_ID" && "$MODE" == "local" ]]; then
    info "Looking for Stripe Account ID from dev Docker..."

    if [[ -n "$DEV_CONTAINER" ]]; then
        # Get the serialized account data and extract account_id via PHP
        ACCOUNT_DATA=$(docker exec -u www-data "$DEV_CONTAINER" bash -c \
            "cd /var/www/html && wp option get wcpay_account_data 2>/dev/null" 2>/dev/null || true)

        if [[ -n "$ACCOUNT_DATA" ]]; then
            STRIPE_ACCOUNT_ID=$(echo "$ACCOUNT_DATA" | docker exec -i -u www-data "$DEV_CONTAINER" bash -c \
                "cd /var/www/html && wp eval '\$d = get_option(\"wcpay_account_data\"); echo \$d[\"data\"][\"account_id\"] ?? \"\";'" 2>/dev/null || true)
        fi

        if [[ -n "$STRIPE_ACCOUNT_ID" ]]; then
            success "Stripe Account ID: $STRIPE_ACCOUNT_ID"
        else
            warn "Could not extract Stripe Account ID from dev Docker"
        fi
    else
        warn "Could not find a running dev Docker WordPress container for this checkout"
    fi

    if [[ -z "$STRIPE_ACCOUNT_ID" && -n "$STRIPE_SECRET_KEY" ]]; then
        echo ""
        info "No Stripe Account ID found. Creating a new Stripe Connect test account..."
        STRIPE_RESPONSE=$(curl -s "https://api.stripe.com/v1/accounts" \
            -u "${STRIPE_SECRET_KEY}:" \
            -d "type=custom" \
            -d "country=US" \
            -d "capabilities[card_payments][requested]=true" \
            -d "capabilities[transfers][requested]=true" \
            -d "business_type=individual" 2>/dev/null || true)

        STRIPE_ACCOUNT_ID=$(echo "$STRIPE_RESPONSE" | grep -o '"id": *"acct_[^"]*"' | head -1 | sed 's/.*"acct_/acct_/' | tr -d '"')

        if [[ -n "$STRIPE_ACCOUNT_ID" ]]; then
            success "Created Stripe Connect account: $STRIPE_ACCOUNT_ID"

            # Complete Stripe test account onboarding so E2E tests don't see the setup wizard.
            # Provides business details, individual info (test SSN), bank account, and TOS acceptance.
            info "Completing Stripe test account onboarding..."
            TOS_TS=$(date +%s)
            curl -s "https://api.stripe.com/v1/accounts/$STRIPE_ACCOUNT_ID" \
                -u "${STRIPE_SECRET_KEY}:" -X POST \
                --data-urlencode "business_profile[url]=https://wcpay-e2e.com" \
                -d "business_profile[mcc]=5734" \
                -d "individual[first_name]=Test" \
                -d "individual[last_name]=Account" \
                -d "individual[dob][day]=1" \
                -d "individual[dob][month]=1" \
                -d "individual[dob][year]=1990" \
                -d "individual[address][line1]=123 Test St" \
                -d "individual[address][city]=San Francisco" \
                -d "individual[address][state]=CA" \
                -d "individual[address][postal_code]=94110" \
                -d "individual[ssn_last_4]=0000" \
                -d "individual[id_number]=000000000" \
                -d "individual[email]=test@wcpay-e2e.com" \
                -d "individual[phone]=+14155551234" \
                -d "tos_acceptance[date]=$TOS_TS" \
                -d "tos_acceptance[ip]=127.0.0.1" > /dev/null 2>&1

            # Add test bank account for payouts
            curl -s "https://api.stripe.com/v1/accounts/$STRIPE_ACCOUNT_ID/external_accounts" \
                -u "${STRIPE_SECRET_KEY}:" -X POST \
                -d "external_account[object]=bank_account" \
                -d "external_account[country]=US" \
                -d "external_account[currency]=usd" \
                -d "external_account[routing_number]=110000000" \
                -d "external_account[account_number]=000123456789" > /dev/null 2>&1

            # Verify the account is fully onboarded
            ACCOUNT_STATUS=$(curl -s "https://api.stripe.com/v1/accounts/$STRIPE_ACCOUNT_ID" \
                -u "${STRIPE_SECRET_KEY}:" 2>/dev/null)
            CHARGES_ENABLED=$(echo "$ACCOUNT_STATUS" | grep -o '"charges_enabled": *true' || true)
            if [[ -n "$CHARGES_ENABLED" ]]; then
                success "Stripe account fully onboarded (charges + payouts enabled)"
            else
                warn "Stripe account created but onboarding may be incomplete"
                warn "You may need to complete setup in the Stripe Dashboard"
            fi
        else
            warn "Could not create Stripe account automatically"
            STRIPE_ERROR=$(echo "$STRIPE_RESPONSE" | grep -o '"message": *"[^"]*"' | head -1 | sed 's/"message": *"//' | tr -d '"')
            [[ -n "$STRIPE_ERROR" ]] && warn "Stripe error: $STRIPE_ERROR"
        fi
    fi

    if [[ -z "$STRIPE_ACCOUNT_ID" ]]; then
        echo ""
        info "The Stripe Account ID is a connected merchant account ID (acct_xxx)."
        info "You can find it in WCPay Dev Tools or the Stripe Dashboard."
        read -p "Enter Stripe Account ID (acct_xxx): " STRIPE_ACCOUNT_ID
    fi
fi

# --- Get WooPay Blog ID ---
if [[ -z "$WOOPAY_BLOG_ID" && "$MODE" == "local" ]]; then
    echo ""
    info "WooPay Blog ID is the WPCOM Site ID for https://pay.woo.com."
    info "This is only needed for WooPay-related E2E tests."
    read -p "Enter WooPay Blog ID (or press Enter to use default '111'): " WOOPAY_BLOG_ID
    WOOPAY_BLOG_ID="${WOOPAY_BLOG_ID:-111}"
fi

# --- Detect dev-tools ---
DEV_TOOLS_DOCKER_PATH="$PROJECT_ROOT/docker/wordpress/wp-content/plugins/woocommerce-payments-dev-tools"
WCP_DEV_TOOLS_REPO=""

if [[ -d "$DEV_TOOLS_DOCKER_PATH/.git" ]]; then
    WCP_DEV_TOOLS_REPO="$DEV_TOOLS_DOCKER_PATH"
    success "Dev tools found (git repo): $WCP_DEV_TOOLS_REPO"
elif [[ -d "$DEV_TOOLS_DOCKER_PATH" ]]; then
    WCP_DEV_TOOLS_REPO="$DEV_TOOLS_DOCKER_PATH"
    success "Dev tools found: $WCP_DEV_TOOLS_REPO"
else
    warn "Dev tools not found in Docker WordPress install"
    read -p "Enter WCP Dev Tools repo URL or local path: " WCP_DEV_TOOLS_REPO
fi

# --- Write local.env ---
echo ""
info "Writing $LOCAL_ENV_PATH..."

mkdir -p "$(dirname "$LOCAL_ENV_PATH")"

if [[ "$MODE" == "local" ]]; then
    cat > "$LOCAL_ENV_PATH" << EOF
# WooPayments E2E Local Environment Configuration
# Generated by bin/setup-e2e-local.sh on $(date +%Y-%m-%d)
# See tests/e2e/README.md for documentation.

# --- Dev Tools ---
WCP_DEV_TOOLS_REPO='${WCP_DEV_TOOLS_REPO}'

# --- Transact Platform Server ---
TRANSACT_PLATFORM_SERVER_REPO='${SERVER_PATH}'

# --- Stripe Credentials ---
E2E_WCPAY_STRIPE_TEST_PUBLIC_KEY='${STRIPE_PUBLIC_KEY}'
E2E_WCPAY_STRIPE_TEST_SECRET_KEY='${STRIPE_SECRET_KEY}'
E2E_WCPAY_STRIPE_TEST_WEBHOOK_SIGNATURE_KEY='${STRIPE_WEBHOOK_KEY}'
E2E_WCPAY_STRIPE_ACCOUNT_ID='${STRIPE_ACCOUNT_ID}'
E2E_WOOPAY_BLOG_ID='${WOOPAY_BLOG_ID}'

# --- Test Scope ---
# Remove the value to include the test group:
SKIP_WC_SUBSCRIPTIONS_TESTS=${SKIP_SUBSCRIPTIONS}
SKIP_WC_ACTION_SCHEDULER_TESTS=${SKIP_ACTION_SCHEDULER}
SKIP_WC_BLOCKS_TESTS=${SKIP_BLOCKS}

# --- Debug ---
DEBUG=false
EOF
else
    # Live server mode
    JP_BLOG_TOKEN=""
    JP_USER_TOKEN=""
    JP_SITE_ID=""

    echo ""
    info "Live server mode requires Jetpack credentials."
    info "Get these from your connected test site using WP-CLI:"
    info "  wp eval 'echo Jetpack_Options::get_option(\"id\");'"
    info "  wp eval 'echo Jetpack_Options::get_option(\"blog_token\");'"
    info "  wp eval '\\\$t = Jetpack_Options::get_option(\"user_tokens\"); echo reset(\\\$t);'"

    # Try to extract from dev Docker
    if [[ -n "$DEV_CONTAINER" ]]; then
        info "Attempting to extract from dev Docker..."
        JP_SITE_ID=$(docker exec -u www-data "$DEV_CONTAINER" bash -c \
            "cd /var/www/html && wp eval 'echo Jetpack_Options::get_option(\"id\");' 2>/dev/null" 2>/dev/null || true)
        JP_BLOG_TOKEN=$(docker exec -u www-data "$DEV_CONTAINER" bash -c \
            "cd /var/www/html && wp eval 'echo Jetpack_Options::get_option(\"blog_token\");' 2>/dev/null" 2>/dev/null || true)
        JP_USER_TOKEN=$(docker exec -u www-data "$DEV_CONTAINER" bash -c \
            "cd /var/www/html && wp eval '\$t = Jetpack_Options::get_option(\"user_tokens\"); echo reset(\$t);' 2>/dev/null" 2>/dev/null || true)

        [[ -n "$JP_SITE_ID" ]] && success "Jetpack Site ID: $JP_SITE_ID"
        [[ -n "$JP_BLOG_TOKEN" ]] && success "Jetpack Blog Token: ${JP_BLOG_TOKEN:0:20}..."
        [[ -n "$JP_USER_TOKEN" ]] && success "Jetpack User Token: ${JP_USER_TOKEN:0:20}..."
    else
        warn "Could not find a running dev Docker WordPress container for this checkout"
    fi

    [[ -z "$JP_SITE_ID" ]] && read -p "Enter Jetpack Site ID: " JP_SITE_ID
    [[ -z "$JP_BLOG_TOKEN" ]] && read -p "Enter Jetpack Blog Token: " JP_BLOG_TOKEN
    [[ -z "$JP_USER_TOKEN" ]] && read -p "Enter Jetpack User Token: " JP_USER_TOKEN

    cat > "$LOCAL_ENV_PATH" << EOF
# WooPayments E2E Local Environment Configuration
# Generated by bin/setup-e2e-local.sh on $(date +%Y-%m-%d)
# See tests/e2e/README.md for documentation.

# --- Server Mode ---
E2E_USE_LOCAL_SERVER=false

# --- Dev Tools ---
WCP_DEV_TOOLS_REPO='${WCP_DEV_TOOLS_REPO}'

# --- Jetpack Credentials (Live Server) ---
E2E_JP_BLOG_TOKEN='${JP_BLOG_TOKEN}'
E2E_JP_USER_TOKEN='${JP_USER_TOKEN}'
E2E_JP_SITE_ID='${JP_SITE_ID}'

# --- Test Scope ---
SKIP_WC_SUBSCRIPTIONS_TESTS=${SKIP_SUBSCRIPTIONS}
SKIP_WC_ACTION_SCHEDULER_TESTS=${SKIP_ACTION_SCHEDULER}
SKIP_WC_BLOCKS_TESTS=${SKIP_BLOCKS}

# --- Debug ---
DEBUG=false
EOF
fi

# --- Post-setup: handle gitignored server code ---
# The transact-platform-server has server/ and missioncontrol/ gitignored — these are
# populated via 'npm run pull' from the WPCOM sandbox. When the E2E setup clones
# the server via git, only the local tooling (bin/, docker/, etc.) is included.
# We rsync the full directories from the original local repo.
E2E_SERVER_DEPS_PATH="$PROJECT_ROOT/tests/e2e/deps/transact-platform-server-e2e"
SERVER_GITIGNORED_DIRS=("server" "missioncontrol")

if [[ "$MODE" == "local" && -n "$SERVER_PATH" ]]; then
    HAS_SERVER_CODE=false
    for dir in "${SERVER_GITIGNORED_DIRS[@]}"; do
        [[ -d "$SERVER_PATH/$dir" ]] && HAS_SERVER_CODE=true
    done

    if [[ "$HAS_SERVER_CODE" == true ]]; then
        if [[ -d "$E2E_SERVER_DEPS_PATH" ]]; then
            info "Copying gitignored server code to E2E clone..."
            for dir in "${SERVER_GITIGNORED_DIRS[@]}"; do
                if [[ -d "$SERVER_PATH/$dir" ]]; then
                    rsync -a --delete "$SERVER_PATH/$dir/" "$E2E_SERVER_DEPS_PATH/$dir/"
                    success "Synced $dir/"
                fi
            done
        else
            info "After 'npm run test:e2e-setup' clones the server, run:"
            echo ""
            for dir in "${SERVER_GITIGNORED_DIRS[@]}"; do
                echo "  rsync -a --delete '$SERVER_PATH/$dir/' '$E2E_SERVER_DEPS_PATH/$dir/'"
            done
            echo ""
        fi
    else
        warn "Server code (server/, missioncontrol/) not found in $SERVER_PATH"
        warn "E2E setup will try a one-shot 'npm run pull -- -s' in its clone if sandbox access is configured."
    fi
fi

echo ""
echo "================================================"
echo "  Setup Complete"
echo "================================================"
echo ""
success "local.env written to: $LOCAL_ENV_PATH"
echo ""
info "Next steps:"
echo "  1. npm install && composer install  (if not done)"
echo "  2. npm run build:client             (build JS assets)"
echo "  3. npm run test:e2e-setup           (spin up E2E Docker environment)"
echo "  4. npm run test:e2e                 (run all E2E tests)"
echo ""
info "Useful commands:"
echo "  npm run test:e2e -- -g 'test name'  (run by grep)"
echo "  npm run test:e2e tests/e2e/specs/wcpay/merchant/file.spec.ts  (run specific)"
echo "  npm run test:e2e-ui                 (interactive UI mode)"
echo "  npm run test:e2e-down               (stop E2E containers)"
echo ""
info "Notes:"
echo "  - If 'npm run test:e2e-setup' fails with 'already linked', run 'npm run test:e2e-reset' first."
echo "  - Dev tools need 'composer install' in their clone. The setup script handles this automatically."
echo ""
