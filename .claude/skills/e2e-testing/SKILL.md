---
name: e2e-testing
description: Run or debug WooPayments Playwright E2E tests and set up its local E2E environment. Use when an E2E check is requested or needed for the affected flow, not for every code change.
metadata:
  version: "1.0.0"
---

# WooPayments E2E Testing

Run Playwright E2E tests to verify changes work from a user's perspective — real browser, real Stripe test transactions, real WordPress site.

## When to Use

- **Verifying a changed flow that needs E2E coverage**: choose the smallest relevant scenario
- **Setting up E2E environment** for the first time
- **Debugging E2E test failures** — reading traces, screenshots, logs
- **Writing new E2E tests** — structure and conventions

Choose a focused spec or scenario, and inspect the current environment before setup or rebuilding. Broaden checks only when a failure, new change or specific unresolved risk warrants it. A request to test does not authorise resetting data or stopping unrelated containers. Use test-mode payment data.

## Quick Reference

| Task | Command |
|------|---------|
| First-time setup | `bin/setup-e2e-local.sh && pnpm run build:client && pnpm run test:e2e-setup` |
| Run all tests | `pnpm run test:e2e` |
| Run specific test | `pnpm run test:e2e tests/e2e/specs/wcpay/merchant/file.spec.ts` |
| Run by name | `pnpm run test:e2e -g "test name"` |
| Run merchant tests | `pnpm run test:e2e tests/e2e/specs/wcpay/merchant` |
| Run shopper tests | `pnpm run test:e2e tests/e2e/specs/wcpay/shopper` |
| UI mode | `pnpm run test:e2e-ui` (open http://localhost:8077) |
| Start containers | `pnpm run test:e2e-up` |
| Stop containers | `pnpm run test:e2e-down` |
| Full reset | `pnpm run test:e2e-reset` |
| View report | `pnpm exec playwright show-report` |

## Workflow 1: First-Time Setup

### Prerequisites

- Docker running
- `pnpm install` and `composer install` completed
- Dev Docker environment running (`pnpm run up`) — needed for credential detection

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
   pnpm run build:client
   ```

3. **Set up the E2E Docker environment:**

   ```bash
   pnpm run test:e2e-setup
   ```

   This takes several minutes. It:
   - Starts Docker containers (WordPress on port 8084, MySQL on port 5698)
   - Starts Transact Platform Server (port 8088)
   - Installs WordPress, WooCommerce, WooPayments, dev tools
   - Configures Stripe account linking
   - Imports sample products and creates test users

4. **Verify it works:**

   ```bash
   pnpm run test:e2e tests/e2e/specs/wcpay/merchant/merchant-admin-deposits.spec.ts
   ```

### Important: Pre-setup steps for local server mode

Before running `pnpm run test:e2e-setup`, these steps are required:

1. **Sync gitignored server code:** The transact-platform-server has `server/` and `missioncontrol/` gitignored (populated via `pnpm run pull`). After the E2E setup clones the repo, these dirs are empty. The setup script (`bin/setup-e2e-local.sh`) handles this automatically, or manually:
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

- **Port 8084 already in use:** Identify the owner with `docker ps` and reuse the intended E2E service if healthy. Preserve unrelated containers. Resolve the port configuration or report the collision; stop a service only within the authorised scope.
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
- **"Already linked" error:** Inspect the configured account and existing site link first. Reuse valid state or correct the specific configuration. `pnpm run test:e2e-reset` deletes environment data; use it only when that reset is explicitly authorised.

## Workflow 2: Running Tests (Agent Verification)

### Before running tests

1. Check Docker with `docker info` and inspect running services with `docker ps`. Start the existing E2E environment with `pnpm run test:e2e-up` when needed; use first-time setup only if it has not been configured.
2. Confirm the checkout and client assets match the revision being tested. Run `pnpm run build:client` only if assets are missing or stale. Reuse a current build.
3. Inspect the relevant specs and their prerequisites. Select the smallest scenario that observes the changed behavior, including relevant failure paths.

### Choosing what to run

Run a focused spec, optionally narrowed to a test name:

```bash
pnpm run test:e2e tests/e2e/specs/wcpay/merchant/merchant-admin-disputes.spec.ts
pnpm run test:e2e tests/e2e/specs/wcpay/merchant/merchant-admin-disputes.spec.ts -g "test name"
```

Use the feature mapping below to locate candidates, then inspect the current filenames and test names. Run a merchant/shopper directory, tag group or full suite only when explicitly requested or when the affected shared behavior needs that breadth. State the gap the broader run will cover.

Stop when the relevant checks pass. If setup or an integration blocks a check, report the blocker and what remains unverified; do not substitute an unrelated passing suite. Clean up only agent-owned processes and temporary test state.

### Reading results

- **Console output:** Pass/fail summary printed after run
- **HTML report:** Run `pnpm exec playwright show-report` to open in browser
- **On failure:** Screenshots saved to `tests/e2e/test-results/`
- **Traces:** Available in `tests/e2e/test-results/` (open with `pnpm exec playwright show-trace <trace.zip>`)

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
pnpm exec playwright show-report

# Open a specific trace
pnpm exec playwright show-trace tests/e2e/test-results/<test-folder>/trace.zip
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
pnpm run test:e2e-ui tests/e2e/specs/wcpay/merchant/failing-test.spec.ts
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

Cleanup and reset remove dependencies or volumes. Inspect ownership and obtain explicit authorisation for data deletion before using them. Stop only services owned by the current task or covered by existing authorisation.

```bash
pnpm run test:e2e-setup    # First-time: build + start + configure everything
pnpm run test:e2e-up       # Start existing containers (no reconfigure)
pnpm run test:e2e-down     # Stop containers
pnpm run test:e2e-cleanup  # Remove deps and docker volumes
pnpm run test:e2e-reset    # Stop + cleanup (full teardown)
```
