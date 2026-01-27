# QIT E2E Local Development Workflow - Design Document

**Issue:** [WOOPMNT-5574](https://linear.app/a8c/issue/WOOPMNT-5574/e2eqit-improve-local-development-flow-for-writing-new-tests)
**Date:** 2026-01-27
**Author:** Miguel Gasca

## Problem

The current QIT E2E setup recreates the test environment on every run (`npm run test:qit-e2e`). While this ensures clean state for CI, it significantly slows down local test development when iterating on new or failing tests.

## Goal

Improve the local developer experience by allowing the QIT test environment to be **reused across runs**, enabling fast iteration with Playwright while keeping CI behavior unchanged.

## Solution Overview

Add a local development workflow that:
1. Starts the QIT environment once with full setup (WordPress, WooCommerce, WooPayments, test data)
2. Keeps it running for repeated Playwright test execution
3. Provides clear documentation and helpful error messages

## Components

### 1. Shell Script: `tests/qit/e2e-dev.sh`

A helper script with subcommands for environment management.

#### Subcommands

**`up`** - Start and setup the QIT environment
- Validates prerequisites:
  - `tests/qit/config/local.env` exists
  - `QIT_USER` and `QIT_PASSWORD` are set
  - Jetpack tokens are set (`E2E_JP_SITE_ID`, `E2E_JP_BLOG_TOKEN`, `E2E_JP_USER_TOKEN`)
- Provides specific error messages for each missing prerequisite
- Runs: `qit env:up --config tests/qit/qit.json --global-setup`
- Prints next steps including the source command and Playwright examples

**`down`** - Stop the QIT environment
- Runs: `qit env:down`
- Prints confirmation

#### Error Messages

When `local.env` is missing:
```
Error: tests/qit/config/local.env not found

To set up local configuration:
  1. Copy the template: cp tests/qit/config/default.env tests/qit/config/local.env
  2. Edit tests/qit/config/local.env and fill in your credentials

See tests/qit/LOCAL_DEVELOPMENT.md for detailed instructions.
```

When QIT credentials are missing:
```
Error: QIT_USER and QIT_PASSWORD are required

These credentials are needed to authenticate with the QIT CLI.
Add them to tests/qit/config/local.env:
  QIT_USER=your_qit_username
  QIT_PASSWORD=your_qit_application_password

To obtain credentials, visit: [QIT dashboard URL]
```

When Jetpack tokens are missing:
```
Error: Jetpack tokens are required for WooPayments E2E tests

The following variables must be set in tests/qit/config/local.env:
  E2E_JP_SITE_ID=your_site_id
  E2E_JP_BLOG_TOKEN=your_blog_token
  E2E_JP_USER_TOKEN=your_user_token

See tests/qit/LOCAL_DEVELOPMENT.md for instructions on obtaining these tokens.
```

#### Success Output (after `up`)

```
✓ Environment ready!

To run tests, first source the environment variables in your shell:

  source "$(./vendor/bin/qit env:source)"

Then navigate to the test package and run Playwright:

  cd tests/qit/test-package
  npx playwright test --ui              # Interactive UI mode
  npx playwright test                    # Run all tests headless
  npx playwright test path/to/spec.ts   # Run specific test file

When finished:

  npm run test:qit-e2e-down
```

### 2. npm Scripts

Add to root `package.json`:

```json
{
  "scripts": {
    "test:qit-e2e-up": "./tests/qit/e2e-dev.sh up",
    "test:qit-e2e-down": "./tests/qit/e2e-dev.sh down"
  }
}
```

### 3. Documentation

#### `tests/qit/LOCAL_DEVELOPMENT.md` (new file)

```markdown
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

[Instructions on obtaining QIT credentials - to be filled based on team process]

### 2. Jetpack Tokens

WooPayments E2E tests require Jetpack tokens to connect to the payments server:
- `E2E_JP_SITE_ID`
- `E2E_JP_BLOG_TOKEN`
- `E2E_JP_USER_TOKEN`

[Instructions on obtaining Jetpack tokens - to be filled based on team process]

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
1. Validates your configuration
2. Starts Docker containers (WordPress, database)
3. Installs and configures WooCommerce and WooPayments
4. Runs the test setup (creates users, products, test data)
5. Keeps the environment running

The first run takes a few minutes. Subsequent runs reuse existing containers if they're still running.

### Sourcing Environment Variables

After starting the environment, source the variables into your shell:

```bash
source "$(./vendor/bin/qit env:source)"
```

This sets variables like `QIT_SITE_URL` that Playwright needs to connect to the test site.

**Note:** You need to run this in each new terminal session.

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

1. Edit test files
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

The local environment persists state between runs. CI starts fresh each time. If tests depend on state from previous tests, they may fail in CI.

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

### Check Test Reports

After running tests, view the HTML report:

```bash
npx playwright show-report
```
```

#### `tests/qit/README.md` update

Add near the top of the existing README:

```markdown
## Local Development

For a faster workflow when writing or debugging E2E tests locally, see [LOCAL_DEVELOPMENT.md](./LOCAL_DEVELOPMENT.md).

This allows you to start the test environment once and run Playwright directly, avoiding the overhead of full orchestration on each run.
```

## Implementation Plan

1. **Create `tests/qit/e2e-dev.sh`**
   - Implement `up` subcommand with validation and environment startup
   - Implement `down` subcommand
   - Add helpful error messages for each failure case

2. **Update `package.json`**
   - Add `test:qit-e2e-up` script
   - Add `test:qit-e2e-down` script

3. **Create `tests/qit/LOCAL_DEVELOPMENT.md`**
   - Write comprehensive documentation
   - Fill in credential acquisition instructions (may need team input)

4. **Update `tests/qit/README.md`**
   - Add brief local development section with link

5. **Test the workflow**
   - Verify environment starts correctly
   - Verify Playwright can connect and run tests
   - Verify environment stops correctly
   - Test error messages for missing prerequisites

## Files to Create/Modify

| File | Action |
|------|--------|
| `tests/qit/e2e-dev.sh` | Create |
| `tests/qit/LOCAL_DEVELOPMENT.md` | Create |
| `tests/qit/README.md` | Modify |
| `package.json` | Modify |

## Out of Scope

- Changes to CI workflow (intentionally unchanged)
- Changes to `npm run test:qit-e2e` behavior
- Main E2E tests in `tests/e2e/` (separate system)
- Fish shell or other non-bash/zsh support
