# QIT E2E Local Development Workflow - Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add a fast, iterative local development workflow for QIT E2E tests that allows developers to start an environment once and run Playwright tests directly.

**Architecture:** Shell script with subcommands (`up`, `down`) that validates prerequisites and manages the QIT environment lifecycle. npm scripts in root package.json invoke the shell script. Comprehensive documentation guides developers through the workflow.

**Tech Stack:** Bash, QIT CLI (`./vendor/bin/qit`), npm scripts

---

## Task 1: Create the e2e-dev.sh Shell Script

**Files:**
- Create: `tests/qit/e2e-dev.sh`

**Step 1: Create the shell script with prerequisite validation and subcommands**

Create `tests/qit/e2e-dev.sh`:

```bash
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
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

print_error() {
    echo -e "${RED}Error: $1${NC}" >&2
}

print_success() {
    echo -e "${GREEN}$1${NC}"
}

print_warning() {
    echo -e "${YELLOW}$1${NC}"
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

    if ! $QIT_BINARY list 2>/dev/null | grep -q 'partner:remove'; then
        echo "Registering QIT partner credentials..."
        if ! $QIT_BINARY partner:add --user="$QIT_USER" --application_password="$QIT_PASSWORD"; then
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
    $QIT_BINARY env:up \
        --config "$QIT_ROOT/qit.json" \
        --global-setup \
        --env_file "$QIT_ROOT/config/local.env"

    echo ""
    print_success "✓ Environment ready!"
    echo ""
    echo "To run tests, first source the environment variables in your shell:"
    echo ""
    echo "  source \"\$($WCP_ROOT/vendor/bin/qit env:source)\""
    echo ""
    echo "Then navigate to the test package and run Playwright:"
    echo ""
    echo "  cd tests/qit/test-package"
    echo "  npx playwright test --ui              # Interactive UI mode"
    echo "  npx playwright test                    # Run all tests headless"
    echo "  npx playwright test path/to/spec.ts   # Run specific test file"
    echo ""
    echo "When finished:"
    echo ""
    echo "  npm run test:qit-e2e-down"
    echo ""
}

# Stop the QIT environment
cmd_down() {
    echo "Stopping QIT E2E development environment..."

    $QIT_BINARY env:down

    print_success "✓ Environment stopped."
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
```

**Step 2: Make the script executable**

Run:
```bash
chmod +x tests/qit/e2e-dev.sh
```

**Step 3: Commit the shell script**

Run:
```bash
git add tests/qit/e2e-dev.sh
git commit -m "feat(qit): add e2e-dev.sh script for local development workflow

Adds a shell script with 'up' and 'down' subcommands to manage the QIT
E2E development environment. Includes prerequisite validation with
helpful error messages for missing credentials."
```

---

## Task 2: Add npm Scripts to package.json

**Files:**
- Modify: `package.json` (lines 51-55, add after existing test:qit-e2e scripts)

**Step 1: Add the npm scripts**

Add the following scripts after line 55 (after `"test:qit-e2e:ci"`):

```json
    "test:qit-e2e-up": "./tests/qit/e2e-dev.sh up",
    "test:qit-e2e-down": "./tests/qit/e2e-dev.sh down",
```

The scripts section should look like (showing context around the change):
```json
    "test:qit-e2e": "./vendor/bin/qit run:e2e woocommerce-payments --config tests/qit/qit.json",
    "test:qit-e2e:shopper": "./vendor/bin/qit run:e2e woocommerce-payments --config tests/qit/qit.json -- --project=shopper",
    "test:qit-e2e:merchant": "./vendor/bin/qit run:e2e woocommerce-payments --config tests/qit/qit.json -- --project=merchant",
    "test:qit-e2e:subscriptions": "./vendor/bin/qit run:e2e woocommerce-payments --config tests/qit/qit.json -p woocommerce-subscriptions -- --project=subscriptions",
    "test:qit-e2e:ci": "CI=true npm run test:qit-e2e",
    "test:qit-e2e-up": "./tests/qit/e2e-dev.sh up",
    "test:qit-e2e-down": "./tests/qit/e2e-dev.sh down",
    "watch": "webpack --watch",
```

**Step 2: Commit the package.json changes**

Run:
```bash
git add package.json
git commit -m "feat(qit): add npm scripts for local E2E development

Adds test:qit-e2e-up and test:qit-e2e-down scripts that invoke the
e2e-dev.sh helper script for managing the QIT development environment."
```

---

## Task 3: Create LOCAL_DEVELOPMENT.md Documentation

**Files:**
- Create: `tests/qit/LOCAL_DEVELOPMENT.md`

**Step 1: Create the documentation file**

Create `tests/qit/LOCAL_DEVELOPMENT.md`:

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
```

**Step 2: Commit the documentation**

Run:
```bash
git add tests/qit/LOCAL_DEVELOPMENT.md
git commit -m "docs(qit): add LOCAL_DEVELOPMENT.md for iterative test workflow

Comprehensive guide for the local E2E development workflow including:
- Prerequisites and setup instructions
- Quick start guide
- Detailed workflow explanation
- Troubleshooting section
- Tips for efficient development"
```

---

## Task 4: Update README.md with Local Development Section

**Files:**
- Modify: `tests/qit/README.md` (add section after line 1)

**Step 1: Add the local development section**

After line 1 (`## WooPayments QIT Tests`), add:

```markdown

## Local Development

For a faster workflow when writing or debugging E2E tests locally, see [LOCAL_DEVELOPMENT.md](./LOCAL_DEVELOPMENT.md).

This allows you to start the test environment once and run Playwright directly, avoiding the overhead of full orchestration on each run.
```

The beginning of the file should look like:

```markdown
## WooPayments QIT Tests

## Local Development

For a faster workflow when writing or debugging E2E tests locally, see [LOCAL_DEVELOPMENT.md](./LOCAL_DEVELOPMENT.md).

This allows you to start the test environment once and run Playwright directly, avoiding the overhead of full orchestration on each run.

We use the [QIT toolkit](https://qit.woo.com/docs/) for automated testing including security, PHPStan, and E2E tests.
```

**Step 2: Commit the README update**

Run:
```bash
git add tests/qit/README.md
git commit -m "docs(qit): add local development section to README

Links to the new LOCAL_DEVELOPMENT.md guide for iterative test development."
```

---

## Task 5: Test the Workflow

**Files:** None (manual testing)

**Step 1: Verify script runs without local.env**

Run (expect error):
```bash
./tests/qit/e2e-dev.sh up
```

Expected output should include:
```
Error: tests/qit/config/local.env not found
```

**Step 2: Create local.env without credentials and verify error**

Run:
```bash
cp tests/qit/config/default.env tests/qit/config/local.env
./tests/qit/e2e-dev.sh up
```

Expected output should include error messages about missing QIT_USER, QIT_PASSWORD, or Jetpack tokens.

**Step 3: Verify npm scripts work**

Run:
```bash
npm run test:qit-e2e-up
```

Should invoke the shell script and show the same validation errors (if credentials not configured).

**Step 4: (If credentials available) Test full workflow**

If you have valid credentials in local.env:

```bash
# Start environment
npm run test:qit-e2e-up

# Source variables
source "$(./vendor/bin/qit env:source)"

# Verify environment is accessible
echo $QIT_SITE_URL

# Run a single test
cd tests/qit/test-package
npx playwright test tests/woopayments/shopper/shopper-checkout-purchase.spec.ts --headed

# Stop environment
npm run test:qit-e2e-down
```

**Step 5: Clean up test local.env if created for testing**

If you created a temporary local.env for testing, remove it:
```bash
rm tests/qit/config/local.env
```

(local.env is gitignored so it won't be committed)

---

## Task 6: Add Changelog Entry

**Files:**
- Create: `changelog/add-qit-local-dev-workflow` (generated by changelog script)

**Step 1: Add changelog entry**

Run:
```bash
npm run changelog:add -- --type=add --entry="Added local development workflow for QIT E2E tests with npm run test:qit-e2e-up/down commands" --significance=patch
```

**Step 2: Commit the changelog**

Run:
```bash
git add changelog/
git commit -m "changelog: add entry for QIT local dev workflow"
```

---

## Summary

| Task | Files | Description |
|------|-------|-------------|
| 1 | `tests/qit/e2e-dev.sh` | Shell script with up/down commands |
| 2 | `package.json` | npm scripts for test:qit-e2e-up/down |
| 3 | `tests/qit/LOCAL_DEVELOPMENT.md` | Comprehensive documentation |
| 4 | `tests/qit/README.md` | Brief section linking to docs |
| 5 | (manual) | Test the workflow |
| 6 | `changelog/` | Changelog entry |

Total commits: 5 (script, package.json, LOCAL_DEVELOPMENT.md, README.md, changelog)
