# QIT E2E Local Development Workflow

## Overview

This guide describes a fast, iterative workflow for developing QIT E2E tests locally. Instead of running the full test orchestration each time, you start the environment once and run Playwright directly.

**Use this workflow when:**
- Writing new E2E tests
- Debugging failing tests
- Iterating on test fixes

**Use `npm run test:qit-e2e` when:**
- Running the full test suite
- Validating before pushing
- Running in CI

## Prerequisites

### 1. QIT CLI Credentials

You need QIT CLI credentials (`QIT_USER` and `QIT_PASSWORD`) to authenticate with the QIT platform.

Get credentials from the [secret store](https://mc.a8c.com/secret-store/?secret_id=11043).

### 2. Jetpack Tokens

WooPayments E2E tests require Jetpack tokens to connect to the payments server:
- `E2E_JP_SITE_ID`
- `E2E_JP_BLOG_TOKEN`
- `E2E_JP_USER_TOKEN`

See the [Retrieving Jetpack Tokens](./README.md#retrieving-jetpack-tokens) section in README.md for detailed instructions.

### 3. Local Configuration File

Create your local configuration:

```bash
cp tests/qit/config/default.env tests/qit/config/local.env
```

Edit `tests/qit/config/local.env` and add your credentials:

```bash
# QIT CLI Credentials
QIT_USER=your_qit_username
QIT_PASSWORD=your_qit_application_password

# Jetpack Tokens
E2E_JP_SITE_ID=your_site_id
E2E_JP_BLOG_TOKEN=your_blog_token
E2E_JP_USER_TOKEN=your_user_token
```

## Quick Start

```bash
# 1. Start the environment (one-time setup)
npm run test:qit-e2e-up

# 2. Source environment variables (run in each new terminal)
source "$(./vendor/bin/qit env:source)"

# 3. Navigate to test package
cd tests/qit/test-package

# 4. Run tests with Playwright
npx playwright test --ui

# 5. When done, stop the environment
npm run test:qit-e2e-down
```

## Workflow Details

### Starting the Environment

```bash
npm run test:qit-e2e-up
```

This command:
1. Validates your configuration (checks for required credentials)
2. Starts Docker containers (WordPress, database)
3. Installs and configures WooCommerce and WooPayments
4. Runs the test setup (creates users, products, test data)
5. Keeps the environment running for development

The first run takes a few minutes. Subsequent runs are faster if containers are still present.

### Sourcing Environment Variables

After starting the environment, source the variables into your shell:

```bash
source "$(./vendor/bin/qit env:source)"
```

This sets variables like `QIT_SITE_URL` that Playwright needs to connect to the test site.

**Note:** You need to run this command in each new terminal session.

### Running Tests

Navigate to the test package directory:

```bash
cd tests/qit/test-package
```

Common Playwright commands:

```bash
# Interactive UI mode (recommended for development)
npx playwright test --ui

# Run all tests headless
npx playwright test

# Run a specific test file
npx playwright test tests/woopayments/shopper/shopper-checkout-purchase.spec.ts

# Run tests matching a pattern
npx playwright test --grep "checkout"

# Run tests with visible browser
npx playwright test --headed

# Debug mode (step through tests)
npx playwright test --debug

# Run a specific project (shopper, merchant, subscriptions)
npx playwright test --project=shopper
```

### Iterating on Tests

The environment persists between test runs. Your workflow:

1. Edit test files in `tests/qit/test-package/tests/`
2. Run `npx playwright test` (or use UI mode)
3. See results
4. Repeat

No need to restart the environment unless you need to reset the database state.

### Stopping the Environment

When finished developing:

```bash
npm run test:qit-e2e-down
```

## Troubleshooting

### "Cannot connect to site" errors

1. Check the environment is running: `docker ps | grep qit`
2. Ensure you sourced the environment: `echo $QIT_SITE_URL`
3. Try restarting: `npm run test:qit-e2e-down && npm run test:qit-e2e-up`

### Tests pass locally but fail in CI

The local environment persists state between runs. CI starts fresh each time. If tests depend on state from previous tests, they may fail in CI. Ensure tests are independent and don't rely on side effects from other tests.

### Need to reset test data

Stop and restart the environment:

```bash
npm run test:qit-e2e-down
npm run test:qit-e2e-up
```

### Environment variables not set

Run the source command in your current terminal:

```bash
source "$(./vendor/bin/qit env:source)"
```

### "QIT_USER or QIT_PASSWORD not set" error

Ensure your `tests/qit/config/local.env` file exists and contains valid credentials. See [Prerequisites](#prerequisites).

### "Jetpack tokens are required" error

You need to obtain Jetpack tokens from an onboarded WooPayments account. See the [Retrieving Jetpack Tokens](./README.md#retrieving-jetpack-tokens) section in README.md.

## Tips

### Use UI Mode

Playwright's UI mode (`--ui`) is excellent for development:
- Visual test execution
- Time-travel debugging
- Easy test filtering
- Watch mode for re-running on file changes

### Run Specific Tests

During development, run only the test you're working on:

```bash
npx playwright test tests/woopayments/shopper/my-test.spec.ts
```

### Use Debug Mode

For tricky failures, use debug mode to step through:

```bash
npx playwright test --debug tests/woopayments/shopper/my-test.spec.ts
```

### Generate Tests with Codegen

Use Playwright's codegen to record interactions:

```bash
npx playwright codegen $QIT_SITE_URL
```

### Check Test Reports

After running tests, view the HTML report:

```bash
npx playwright show-report
```

## Comparison: Local Dev vs Full Orchestration

| Aspect | `npm run test:qit-e2e-up` | `npm run test:qit-e2e` |
|--------|---------------------------|------------------------|
| Environment | Persistent, reusable | Fresh each run |
| Speed | Fast iteration | Slower (full setup) |
| Test runner | Direct Playwright | QIT orchestrated |
| Use case | Development | CI, validation |
| State | Persists between runs | Clean slate |
