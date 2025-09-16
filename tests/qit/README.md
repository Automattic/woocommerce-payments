# QIT Tests for WooCommerce Payments

This directory contains QIT (Quality Insights Toolkit) tests for WooCommerce Payments, including security, malware, PHPStan, and E2E tests.

## Overview

We use QIT for multiple types of testing:
- **Security tests**: Vulnerability scanning
- **Malware tests**: Malware detection
- **PHPStan tests**: Static code analysis
- **E2E tests**: End-to-end functional testing (NEW)

## Setup and Configuration

### Prerequisites

QIT CLI version 0.10.0 or higher is required. The CLI is included as a Composer dependency, but you can also install it globally:

```bash
composer global require woocommerce/qit-cli:^0.10.0
```

**Note**: The repository already has QIT CLI integration with existing CI secrets (`QIT_CI_USER` and `QIT_CI_SECRET`).

### Basic Setup
- Create `local.env` inside the `tests/qit/config/` directory by copying the variables from `default.env`.
- To get the actual values for local config, refer to this [secret store](https://mc.a8c.com/secret-store/?secret_id=11043) link.
- Once configured, the first time you run the `npm` command, it should create a local auth file which will be used for subsequent runs.

### Available Test Types

#### Security and PHPStan Tests
Currently available through these commands:
```bash
npm run test:qit-security
npm run test:qit-phpstan
npm run test:qit-phpstan-local
npm run test:qit-malware
```

#### E2E Tests (New)
QIT E2E tests provide simplified testing with built-in helpers:
```bash
# Run all E2E tests
npm run test:qit-e2e

# Run with UI mode for debugging
npm run test:qit-e2e-ui

# Generate tests with codegen
npm run test:qit-e2e-codegen
```

## E2E Tests Migration

We are migrating our existing Playwright E2E tests to use QIT infrastructure. Benefits include:
- Simplified setup (no Docker required)
- Built-in helpers for common tasks
- Better compatibility testing across WC/WP/PHP versions
- Cross-plugin testing capabilities
- Production environment testing

### E2E Directory Structure
```
tests/qit/e2e/                   # QIT E2E test files
├── bootstrap/                   # Setup and teardown scripts
├── *.spec.js                    # Test files using QIT helpers
qit.yml                         # QIT configuration
```

### E2E Setup
1. Ensure you have QIT credentials configured in `local.env`
2. Add E2E production credentials (E2E_JP_SITE_ID, E2E_JP_BLOG_TOKEN, E2E_JP_USER_TOKEN)
3. Run tests using the npm scripts above

For detailed E2E testing documentation, see the [E2E README](./e2e/README.md).

## Analyzing Results

### Security/Malware/PHPStan Tests
- Once the test run is done, you'll see a result URL along with the test summary.
- Look at any errors that might have been surfaced and associate with PRs that has introduced the same by using `git blame`.
- Ping the author for fixing the error, or fix it yourself if it is straightforward enough.

### E2E Tests
- Test results include screenshots, traces, and detailed logs
- Failed tests automatically capture screenshots for debugging
- Use UI mode (`npm run test:qit-e2e-ui`) for visual debugging
- Check GitHub Actions artifacts for CI test results

## E2E Testing with Real Stripe Connection (NEW)

### Two Testing Modes

**1. Basic Connectivity (Mock Stripe)**
- Tests plugin activation, admin screens, onboarding flow
- Setup: Set only Jetpack credentials (`E2E_JP_*` variables)
- Use case: Basic integration testing, CI/CD pipelines

**2. Checkout Flow Testing (Real Stripe)**
- Tests complete payment flows, Stripe Elements, payment methods
- Setup: Set real Stripe credentials (`E2E_STRIPE_*` variables)
- Use case: Comprehensive E2E testing before releases

### Configuration for Checkout Testing

Add to `tests/qit/config/local.env`:

```bash
# For full checkout flow testing (RECOMMENDED)
E2E_STRIPE_PUBLISHABLE_KEY=pk_test_your_real_test_key
E2E_STRIPE_ACCOUNT_ID=acct_your_stripe_account_id

# For basic connectivity only (original functionality)
E2E_JP_SITE_ID=248403234
E2E_JP_BLOG_TOKEN=your_blog_token
E2E_JP_USER_TOKEN=your_user_token
```

### Getting Stripe Credentials

1. Log into [Stripe Dashboard](https://dashboard.stripe.com)
2. Ensure you're in "Test mode" (toggle in top-left)
3. Go to "Developers" > "API keys"
4. Copy the "Publishable key" (starts with `pk_test_`)
5. Go to "Settings" > "Account details" and copy Account ID (starts with `acct_`)

### Available Tests

```bash
# Basic connectivity tests (existing)
npm run test:qit-e2e

# New checkout flow tests (requires E2E_STRIPE_* setup)
npm run test:qit-e2e -- tests/qit/e2e/checkout.spec.js
```

### Status Checking

```bash
# After setup, check configuration:
wp woopayments qit_status
```

Shows whether you have real or mock Stripe connection configured.

## Build Process

All QIT tests use the `build:release` command to create `woocommerce-payments.zip` at the root of the directory, which is then uploaded and used for the QIT tests.

For E2E specifically (`npm run test:qit-e2e`), the runner now skips rebuilding when there are no relevant source changes since the last build. It computes a hash of tracked files involved in the release (PHP/JS sources and key config files) and only triggers `build:release` when that hash changes.

- To force a rebuild, set `WCP_FORCE_BUILD=1` or delete `woocommerce-payments.zip` (and optionally `woocommerce-payments.zip.hash`).
- The build artifact and its hash are written to the repository root as `woocommerce-payments.zip` and `woocommerce-payments.zip.hash`.
