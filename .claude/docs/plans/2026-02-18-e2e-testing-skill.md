# E2E Testing Skill Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Create an E2E testing skill and setup script so agents can verify their code changes work from a user's perspective.

**Architecture:** Skill at `.claude/skills/e2e-testing/SKILL.md` (canonical), symlinked to `.agents/skills/` and `.claude/commands/`. Setup script at `bin/setup-e2e-local.sh` auto-detects credentials from the local transact-platform-server and dev Docker environment.

**Tech Stack:** Bash (setup script), Markdown (skill), Playwright (tests), Docker (E2E environment)

---

### Task 1: Create the setup script `bin/setup-e2e-local.sh`

**Files:**
- Create: `bin/setup-e2e-local.sh`

**Step 1: Write the setup script**

```bash
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

info() { echo -e "${BLUE}ℹ${NC} $1"; }
success() { echo -e "${GREEN}✔${NC} $1"; }
warn() { echo -e "${YELLOW}⚠${NC} $1"; }
error() { echo -e "${RED}✖${NC} $1"; }

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
DEV_CONTAINER="wcpay_wp_default"

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  WooPayments E2E Local Environment Setup"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
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
if [[ -z "$STRIPE_ACCOUNT_ID" ]]; then
    info "Looking for Stripe Account ID from dev Docker..."

    if docker ps --format '{{.Names}}' 2>/dev/null | grep -q "^${DEV_CONTAINER}$"; then
        STRIPE_ACCOUNT_ID=$(docker exec -u www-data "$DEV_CONTAINER" bash -c \
            "cd /var/www/html && wp option get wcpay_account_data --format=json 2>/dev/null" 2>/dev/null \
            | php -r 'echo (unserialize(stream_get_contents(STDIN))["data"]["account_id"] ?? "");' 2>/dev/null || true)

        if [[ -n "$STRIPE_ACCOUNT_ID" ]]; then
            success "Stripe Account ID: $STRIPE_ACCOUNT_ID"
        else
            warn "Could not extract Stripe Account ID from dev Docker"
        fi
    else
        warn "Dev Docker container ($DEV_CONTAINER) is not running"
    fi

    if [[ -z "$STRIPE_ACCOUNT_ID" ]]; then
        read -p "Enter Stripe Account ID (acct_xxx): " STRIPE_ACCOUNT_ID
    fi
fi

# --- Get WooPay Blog ID ---
if [[ -z "$WOOPAY_BLOG_ID" ]]; then
    info "WooPay Blog ID is needed for WooPay-related E2E tests."
    info "You can find this in the WCPay Dev Tools plugin or in your WP.com dashboard."
    read -p "Enter WooPay Blog ID (or press Enter to use default '111'): " WOOPAY_BLOG_ID
    WOOPAY_BLOG_ID="${WOOPAY_BLOG_ID:-111}"
fi

# --- Detect dev-tools ---
DEV_TOOLS_DOCKER_PATH="$PROJECT_ROOT/docker/wordpress/wp-content/plugins/woocommerce-payments-dev-tools"
WCP_DEV_TOOLS_REPO=""

if [[ -d "$DEV_TOOLS_DOCKER_PATH/.git" ]]; then
    WCP_DEV_TOOLS_REPO="$DEV_TOOLS_DOCKER_PATH"
    success "Dev tools found at: $WCP_DEV_TOOLS_REPO"
elif [[ -d "$DEV_TOOLS_DOCKER_PATH" ]]; then
    # Not a git repo but plugin exists - use it as a local path
    WCP_DEV_TOOLS_REPO="$DEV_TOOLS_DOCKER_PATH"
    success "Dev tools found at: $WCP_DEV_TOOLS_REPO (not a git repo, will be copied)"
else
    warn "Dev tools not found in Docker WordPress install"
    read -p "Enter WCP Dev Tools repo URL or local path: " WCP_DEV_TOOLS_REPO
fi

# --- Write local.env ---
info "Writing $LOCAL_ENV_PATH..."

mkdir -p "$(dirname "$LOCAL_ENV_PATH")"

if [[ "$MODE" == "local" ]]; then
    cat > "$LOCAL_ENV_PATH" << EOF
# WooPayments E2E Local Environment Configuration
# Generated by bin/setup-e2e-local.sh on $(date +%Y-%m-%d)
# See tests/e2e/README.md for documentation.

# --- Server Mode ---
# Using local Transact Platform Server instance.
# Set E2E_USE_LOCAL_SERVER=false to use live server instead.

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
# Uncomment to skip specific test groups:
SKIP_WC_SUBSCRIPTIONS_TESTS=${SKIP_SUBSCRIPTIONS:-}
SKIP_WC_ACTION_SCHEDULER_TESTS=${SKIP_ACTION_SCHEDULER:-}
SKIP_WC_BLOCKS_TESTS=${SKIP_BLOCKS:-}

# --- Debug ---
DEBUG=false
EOF
else
    # Live server mode - need Jetpack tokens
    JP_BLOG_TOKEN=""
    JP_USER_TOKEN=""
    JP_SITE_ID=""

    info "Live server mode requires Jetpack credentials."
    info "Get these from your connected test site using:"
    info "  Jetpack_Options::get_option('blog_token')"
    info "  Jetpack_Options::get_option('user_tokens')"
    info "  Jetpack_Options::get_option('id')"

    if docker ps --format '{{.Names}}' 2>/dev/null | grep -q "^${DEV_CONTAINER}$"; then
        info "Attempting to extract from dev Docker..."
        JP_SITE_ID=$(docker exec -u www-data "$DEV_CONTAINER" bash -c \
            "cd /var/www/html && wp eval 'echo Jetpack_Options::get_option(\"id\");' 2>/dev/null" 2>/dev/null || true)
        JP_BLOG_TOKEN=$(docker exec -u www-data "$DEV_CONTAINER" bash -c \
            "cd /var/www/html && wp eval 'echo Jetpack_Options::get_option(\"blog_token\");' 2>/dev/null" 2>/dev/null || true)
        JP_USER_TOKEN=$(docker exec -u www-data "$DEV_CONTAINER" bash -c \
            "cd /var/www/html && wp eval '\$t = Jetpack_Options::get_option(\"user_tokens\"); echo reset(\$t);' 2>/dev/null" 2>/dev/null || true)
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
SKIP_WC_SUBSCRIPTIONS_TESTS=${SKIP_SUBSCRIPTIONS:-}
SKIP_WC_ACTION_SCHEDULER_TESTS=${SKIP_ACTION_SCHEDULER:-}
SKIP_WC_BLOCKS_TESTS=${SKIP_BLOCKS:-}

# --- Debug ---
DEBUG=false
EOF
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  Setup Complete"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
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
```

**Step 2: Make the script executable**

Run: `chmod +x bin/setup-e2e-local.sh`

**Step 3: Test the script runs without errors**

Run: `bin/setup-e2e-local.sh --help`
Expected: Help text with options

**Step 4: Commit**

```bash
git add bin/setup-e2e-local.sh
git commit -m "feat: add E2E local environment setup script

Automates tests/e2e/config/local.env generation by detecting
credentials from local transact-platform-server and dev Docker."
```

---

### Task 2: Create the E2E testing skill

**Files:**
- Create: `.claude/skills/e2e-testing/SKILL.md`

**Step 1: Write the skill**

````markdown
---
name: e2e-testing
description: Use when running E2E tests, setting up the E2E environment, debugging E2E test failures, or verifying code changes work from a user's perspective. Triggers include "run E2E tests", "verify changes", "Playwright", "test setup", "E2E failures".
version: 1.0.0
---

# WooPayments E2E Testing

Run Playwright E2E tests to verify changes work from a user's perspective — real browser, real Stripe test transactions, real WordPress site.

## When to Use

- **After implementing a feature or fix** — verify it works end-to-end
- **Setting up E2E environment** for the first time
- **Debugging E2E test failures** — reading traces, screenshots, logs
- **Writing new E2E tests** — structure and conventions

## Quick Reference

| Task | Command |
|------|---------|
| First-time setup | `bin/setup-e2e-local.sh && npm run build:client && npm run test:e2e-setup` |
| Run all tests | `npm run test:e2e` |
| Run specific test | `npm run test:e2e tests/e2e/specs/wcpay/merchant/file.spec.ts` |
| Run by name | `npm run test:e2e -- -g "test name"` |
| Run merchant tests | `npm run test:e2e tests/e2e/specs/wcpay/merchant` |
| Run shopper tests | `npm run test:e2e tests/e2e/specs/wcpay/shopper` |
| UI mode | `npm run test:e2e-ui` (open http://localhost:8077) |
| Start containers | `npm run test:e2e-up` |
| Stop containers | `npm run test:e2e-down` |
| Full reset | `npm run test:e2e-reset` |
| View report | `npx playwright show-report` |

## Workflow 1: First-Time Setup

### Prerequisites

- Docker running
- `npm install` and `composer install` completed
- Dev Docker environment running (`npm run up`) — needed for credential detection

### Steps

1. **Run the setup script:**

   ```bash
   bin/setup-e2e-local.sh
   ```

   This auto-detects credentials from:
   - Transact Platform Server (`local/secrets.php`) — Stripe test keys
   - Dev Docker (`wp option get wcpay_account_data`) — Stripe Account ID
   - Dev Docker plugins dir — Dev tools

   It asks interactively for anything it can't find.

   Options:
   - `--server-path /path/to/transact-platform-server` — override auto-detection
   - `--live` — use live server mode (Jetpack tokens) instead of local
   - `--with-subscriptions` — include subscription tests
   - `--help` — see all options

2. **Build the client:**

   ```bash
   npm run build:client
   ```

3. **Set up the E2E Docker environment:**

   ```bash
   npm run test:e2e-setup
   ```

   This takes several minutes. It:
   - Starts Docker containers (WordPress on port 8084, MySQL on port 5698)
   - Starts Transact Platform Server (port 8088)
   - Installs WordPress, WooCommerce, WooPayments, dev tools
   - Configures Stripe account linking
   - Imports sample products and creates test users

4. **Verify it works:**

   ```bash
   npm run test:e2e tests/e2e/specs/wcpay/merchant/merchant-admin-deposits.spec.ts
   ```

### Troubleshooting Setup

- **Port 8084 already in use:** Stop conflicting containers with `docker ps` then `docker stop <container>`
- **`host.docker.internal` not found (Linux):** Create `tests/e2e/docker-compose.override.yml`:
  ```yaml
  services:
    playwright:
      environment:
        - BASE_URL=http://localhost:8084
  ```
- **Dev tools clone fails:** Ensure `WCP_DEV_TOOLS_REPO` in `local.env` points to a valid git repo or local path

## Workflow 2: Running Tests (Agent Verification)

### Before running tests — prerequisites check

```bash
# 1. Docker running?
docker info > /dev/null 2>&1 || echo "Start Docker first"

# 2. E2E containers up?
docker ps --format '{{.Names}}' | grep -q wcp_e2e_wordpress || npm run test:e2e-up

# 3. Client built with latest changes?
npm run build:client
```

### Choosing what to run

**After a change to merchant admin UI:**
```bash
npm run test:e2e tests/e2e/specs/wcpay/merchant/
```

**After a change to checkout/shopper flow:**
```bash
npm run test:e2e tests/e2e/specs/wcpay/shopper/
```

**After a change to a specific feature (e.g., disputes):**
```bash
npm run test:e2e -- -g "dispute"
```

**Run a single spec file:**
```bash
npm run test:e2e tests/e2e/specs/wcpay/merchant/merchant-admin-disputes.spec.ts
```

**Run block-based checkout tests only:**
```bash
npm run test:e2e -- --grep @blocks
```

### Reading results

- **Console output:** Pass/fail summary printed after run
- **HTML report:** Run `npx playwright show-report` to open in browser
- **On failure:** Screenshots saved to `tests/e2e/test-results/`
- **Traces:** Available in `tests/e2e/test-results/` (open with `npx playwright show-trace <trace.zip>`)

### Test mapping — which specs cover which features

| Feature area | Spec directory / files |
|-------------|----------------------|
| Deposits/payouts | `merchant/merchant-admin-deposits.spec.ts` |
| Transactions | `merchant/merchant-admin-transactions.spec.ts` |
| Disputes | `merchant/merchant-admin-disputes.spec.ts`, `merchant-disputes-*.spec.ts` |
| Orders & refunds | `merchant/merchant-orders-*.spec.ts` |
| Multi-currency | `merchant/multi-currency*.spec.ts`, `merchant/merchant-orders-multi-currency.spec.ts` |
| Payment settings | `merchant/merchant-payment-settings-*.spec.ts` |
| Checkout (shortcode) | `shopper/shopper-checkout-*.spec.ts` |
| Checkout (blocks) | `shopper/` specs tagged `@blocks` |
| Saved cards | `shopper/shopper-saved-card*.spec.ts` |
| WooPay | `merchant/woopay-setup.spec.ts`, `shopper/shopper-woopay*.spec.ts` |
| Subscriptions | `specs/subscriptions/` |

## Workflow 3: Debugging Failures

### Step 1: Read the failure output

The console shows which test failed and the error message. Look for:
- Assertion failures (expected vs actual)
- Timeout errors (element not found — usually a selector issue or slow page)
- Network errors (server not responding)

### Step 2: Check artifacts

```bash
# Screenshots (taken on failure)
ls tests/e2e/test-results/

# Open the HTML report
npx playwright show-report

# Open a specific trace
npx playwright show-trace tests/e2e/test-results/<test-folder>/trace.zip
```

### Step 3: Access the E2E site directly

The E2E WordPress site stays running after tests:

- **WordPress admin:** http://localhost:8084/wp-admin/
  - Username: `admin`, Password: `password`
- **Shop front:** http://localhost:8084/shop/
- **Transact Platform Server:** http://localhost:8088 (when using local server)

### Step 4: Check container logs

```bash
# WordPress container logs
docker logs wcp_e2e_wordpress --tail 50

# Server container logs (local server mode)
docker logs transact_platform_server_wordpress_e2e --tail 50

# MySQL logs
docker logs wcp_e2e_mysql --tail 50
```

### Step 5: Run in UI mode for interactive debugging

```bash
npm run test:e2e-ui tests/e2e/specs/wcpay/merchant/failing-test.spec.ts
```

Open http://localhost:8077 in your browser. UI mode lets you:
- Step through tests
- See the browser in real-time
- Use the locator picker to verify selectors
- View console.log output

## Writing New E2E Tests

### Directory structure

| Test type | Directory |
|-----------|----------|
| Merchant tests | `tests/e2e/specs/wcpay/merchant/` |
| Shopper tests | `tests/e2e/specs/wcpay/shopper/` |
| Subscription merchant | `tests/e2e/specs/subscriptions/merchant/` |
| Subscription shopper | `tests/e2e/specs/subscriptions/shopper/` |

### Test patterns

```typescript
import { test, expect } from '@playwright/test';
import { getMerchant, getShopper } from '../../utils/helpers';

test.describe( 'Feature description', () => {
    test( 'should do something specific', async ( { browser } ) => {
        const { merchantPage } = await getMerchant( browser );
        // ... test steps
    } );
} );
```

### Key conventions

- Use `getMerchant(browser)` / `getShopper(browser)` for role-based browsing
- Prefer user-facing locators: `page.getByRole()`, `page.getByLabel()`, `page.getByText()`
- Use `page.getByTestId()` as fallback, CSS selectors as last resort
- Tests run sequentially (workers: 1) — some tests depend on prior state
- Timeout is 120s per test, 20s per expect assertion

### Test cards

Defined in `tests/e2e/config/default.ts`:
- `4242424242424242` — basic successful card
- `4000002760003184` — 3DS authentication required
- `4000000000000002` — declined
- `4000000000000259` — triggers fraudulent dispute

## Environment Reference

| Service | URL | Container |
|---------|-----|-----------|
| E2E WordPress | http://localhost:8084 | `wcp_e2e_wordpress` |
| E2E MySQL | localhost:5698 | `wcp_e2e_mysql` |
| E2E phpMyAdmin | http://localhost:8085 | `wcp_e2e_phpmyadmin` |
| Transact Server | http://localhost:8088 | `transact_platform_server_wordpress_e2e` |
| Playwright UI | http://localhost:8077 | (via docker-compose) |

## Lifecycle Commands

```bash
npm run test:e2e-setup    # First-time: build + start + configure everything
npm run test:e2e-up       # Start existing containers (no reconfigure)
npm run test:e2e-down     # Stop containers
npm run test:e2e-cleanup  # Remove deps and docker volumes
npm run test:e2e-reset    # Stop + cleanup (full teardown)
```
````

**Step 2: Commit**

```bash
git add .claude/skills/e2e-testing/SKILL.md
git commit -m "feat: add E2E testing skill for agent verification

Comprehensive skill covering setup, test execution, and debugging
of Playwright E2E tests. Enables agents to verify code changes
work from a user's perspective."
```

---

### Task 3: Create symlinks for cross-agent compatibility

**Files:**
- Create: `.agents/skills/e2e-testing/SKILL.md` (symlink)
- Create: `.claude/commands/e2e-testing.md` (symlink)

**Step 1: Create `.agents/skills/` directory and symlink**

```bash
mkdir -p .agents/skills/e2e-testing
ln -s ../../../.claude/skills/e2e-testing/SKILL.md .agents/skills/e2e-testing/SKILL.md
```

**Step 2: Create `.claude/commands/` directory and symlink**

```bash
mkdir -p .claude/commands
ln -s ../skills/e2e-testing/SKILL.md .claude/commands/e2e-testing.md
```

**Step 3: Verify symlinks resolve**

```bash
ls -la .agents/skills/e2e-testing/SKILL.md
ls -la .claude/commands/e2e-testing.md
cat .agents/skills/e2e-testing/SKILL.md | head -5  # Should show YAML frontmatter
```

**Step 4: Commit**

```bash
git add .agents/skills/e2e-testing/SKILL.md .claude/commands/e2e-testing.md
git commit -m "feat: add cross-agent symlinks for E2E testing skill

Symlinks from .agents/skills/ (fieldguide standard) and
.claude/commands/ (slash command) to canonical .claude/skills/."
```

---

### Task 4: Update CLAUDE.md with E2E section

**Files:**
- Modify: `CLAUDE.md:152-158` (existing E2E Tests section)

**Step 1: Expand the E2E Tests section**

Replace the existing 4-line E2E section (lines 152-158) with:

```markdown
### E2E Tests

E2E tests use Playwright and run in Docker containers against a local WordPress site with real Stripe test transactions.

**First-time setup:** Run `bin/setup-e2e-local.sh` to auto-generate `tests/e2e/config/local.env` from your local infrastructure, then `npm run build:client && npm run test:e2e-setup`. See the E2E skill (`/e2e-testing`) or `tests/e2e/README.md` for full details.

```bash
npm run test:e2e                    # Run all E2E tests (headless)
npm run test:e2e-ui                 # Interactive UI mode (localhost:8077)
npm run test:e2e-setup              # First-time E2E environment setup
npm run test:e2e-up                 # Start existing E2E containers
npm run test:e2e-down               # Stop E2E containers

# Run specific tests
npm run test:e2e tests/e2e/specs/wcpay/merchant/  # All merchant tests
npm run test:e2e tests/e2e/specs/wcpay/shopper/   # All shopper tests
npm run test:e2e -- -g "dispute"                   # By test name
```

**E2E environment ports:** WordPress `:8084` | phpMyAdmin `:8085` | Transact Server `:8088` | Playwright UI `:8077`
```

**Step 2: Commit**

```bash
git add CLAUDE.md
git commit -m "docs: expand E2E test section in CLAUDE.md

Add setup instructions, common commands, and port reference
for the E2E testing environment."
```

---

### Task 5: Update .gitignore for new directories

**Files:**
- Modify: `.gitignore`

**Step 1: Verify `.agents/` is not gitignored**

The `.agents/skills/` directory should be tracked in git (it contains the cross-agent symlink). Check that `.gitignore` does NOT exclude `.agents/`. Currently it doesn't — no changes needed unless it does.

**Step 2: Verify `.claude/commands/` is not gitignored**

The `.claude/commands/` directory should be tracked. The gitignore has `.claude/local/` and `**/.claude/**/*.local.*` but not `.claude/commands/` — no changes needed.

**Step 3: Verify `.claude/skills/` is not gitignored**

Same check — `.claude/skills/` is not currently gitignored. No changes needed.

**Step 4: Commit (only if changes were needed)**

No commit expected for this task.

---

### Task 6: Run the setup script and verify E2E tests work

**Step 1: Run the setup script**

```bash
bin/setup-e2e-local.sh
```

Expected: Script auto-detects credentials and generates `tests/e2e/config/local.env`.

**Step 2: Build client**

```bash
npm run build:client
```

Expected: Build succeeds.

**Step 3: Set up E2E environment**

```bash
npm run test:e2e-setup
```

Expected: Docker containers start, WordPress configured, WooPayments activated.

**Step 4: Run a smoke test**

```bash
npm run test:e2e tests/e2e/specs/wcpay/merchant/merchant-admin-deposits.spec.ts
```

Expected: Test passes (or fails for known reasons, not setup issues).

**Step 5: Verify the HTML report**

```bash
npx playwright show-report
```

Expected: Report opens in browser showing test results.
