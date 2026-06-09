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

### Important: Pre-setup steps for local server mode

Before running `npm run test:e2e-setup`, these steps are required:

1. **Sync gitignored server code:** The transact-platform-server has `server/` and `missioncontrol/` gitignored (populated via `npm run pull`). After the E2E setup clones the repo, these dirs are empty. The setup script (`bin/setup-e2e-local.sh`) handles this automatically, or manually:
   ```bash
   rsync -a --delete /path/to/transact-platform-server/server/ tests/e2e/deps/transact-platform-server-e2e/server/
   rsync -a --delete /path/to/transact-platform-server/missioncontrol/ tests/e2e/deps/transact-platform-server-e2e/missioncontrol/
   ```

2. **Install dev tools dependencies:** The dev tools plugin needs `composer install` after cloning:
   ```bash
   cd tests/e2e/deps/wcp-dev-tools-e2e && composer install --no-dev --no-interaction
   ```

3. **Pre-clone dev tools (optional):** To avoid the clone + install race condition, pre-clone before running setup:
   ```bash
   git clone --depth=1 "$WCP_DEV_TOOLS_REPO" tests/e2e/deps/wcp-dev-tools-e2e
   cd tests/e2e/deps/wcp-dev-tools-e2e && composer install --no-dev
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
- **"Critical error" on server startup:** Missing `server/` dir in the E2E clone. Run rsync step above.
- **"vendor/autoload.php not found" in dev tools:** Run `composer install` in `tests/e2e/deps/wcp-dev-tools-e2e/`.
- **Onboarding wizard shown instead of admin pages:** The Stripe test account isn't fully onboarded. Re-run `bin/setup-e2e-local.sh` (auto-creates and onboards), or complete setup in Stripe Dashboard.
- **"Already linked" error:** Run `npm run test:e2e-reset` first for a clean start.

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
