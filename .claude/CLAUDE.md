# CLAUDE.md - WooCommerce Payments Repository Guide

This file provides context about the WooCommerce Payments repository to help Claude Code assist more effectively.

## Repository Overview

WooCommerce Payments (WCPay) is a WordPress plugin that provides payment processing capabilities for WooCommerce stores. It's a complex project combining PHP backend code with a React-based admin interface.

**Key Info:**
- Plugin Name: WooPayments
- License: GPL-3.0-or-later
- Repository: github:Automattic/woocommerce-payments

**Version & Requirements:**
- See `woocommerce-payments.php` header for current version and WordPress/WooCommerce/PHP requirements
- See `package.json` for Node.js version requirements (engines field)

## Architecture — Payment Request Flow

**This is the most important thing to understand.** Every payment flows through these layers in order. Never skip a layer.

```
Checkout Form (JS) → WC_Payment_Gateway_WCPay::process_payment()
  → Request classes (includes/core/server/request/) → Request::send()
    → WC_Payments_API_Client::send_request() → request()
      → WC_Payments_Http::remote_request()
        → Jetpack Connection Client
          → https://public-api.wordpress.com/wpcom/v2/sites/{blog_id}/wcpay/{api}
            → Transact-API backend → Stripe
```

### Layer Rules

1. **Gateway Layer** (`includes/class-wc-payment-gateway-wcpay.php`)
   - Orchestrates payment flows. Does NOT contain business logic.
   - Entry points: `process_payment()`, `process_refund()`, `capture_charge()`
   - Creates Request objects, configures them with setters, calls `send()`

2. **Request Class Layer** (`includes/core/server/request/`)
   - **Always use typed Request classes** for API communication. Never call the API client directly.
   - Each operation has its own class: `Create_And_Confirm_Intention`, `Refund_Charge`, `Get_Intention`, etc.
   - Pattern: `$request = Create_And_Confirm_Intention::create()` → configure with setters → `$request->send()`
   - Request classes validate parameters (Stripe ID prefixes, required fields) and support WordPress hooks for extensibility.
   - See `includes/core/README.md` and `includes/core/CONTRIBUTING.md` for the full Request/Response API.

3. **API Client** (`includes/wc-payment-api/class-wc-payments-api-client.php`)
   - Low-level HTTP communication. **Do not call directly from gateway or feature code.**
   - Handles URL construction, idempotency keys, retry logic (3 retries, exponential backoff), and response parsing.
   - Base endpoint: `https://public-api.wordpress.com/wpcom/v2/sites/{blog_id}/wcpay/`

4. **HTTP / Jetpack Layer** (`includes/wc-payment-api/class-wc-payments-http.php`)
   - Delegates to `Jetpack\Connection\Client::remote_request()`. Never modify directly.
   - All auth (blog token signing) is handled by Jetpack.

5. **Frontend** (`client/`)
   - React 18.3 + TypeScript. State via `@wordpress/data` stores (one store per domain in `client/data/`).
   - Checkout JS creates a Stripe PaymentMethod or confirmation token on the client, passes the ID to PHP.
   - Uses WordPress and WooCommerce component libraries — check Storybooks before building custom components.

### Key Architectural Docs (read when working in these areas)

- `includes/core/README.md` — Core API architecture, Gateway Mode, Services, Request/Response system
- `src/README.md` — Dependency injection container, PSR-4 structure, Proxy patterns
- `includes/core/CONTRIBUTING.md` — How to add new Request classes and extend the Core API

### Detailed Reference Docs

- `.claude/docs/payment-flow.md` — Complete call chain with method signatures, data transformations, and hooks
- `.claude/docs/test-patterns.md` — Testing conventions, base classes, mocking patterns, example tests
- `.claude/docs/mode-system.md` — Mode hierarchy (dev/test/live), frontend data flow, debugging test vs dev mode UI

### External Documentation

When building features, consult these references:
- **WordPress Components Storybook:** https://wordpress.github.io/gutenberg/?path=/docs/ — Check here first for UI components before creating custom ones
- **WooCommerce Components Storybook:** https://woocommerce.github.io/woocommerce/?path=/docs/docs-introduction--docs — WooCommerce-specific UI patterns
- **Stripe API Reference:** https://docs.stripe.com/api — Payment intents, payment methods, charges, refunds, disputes

## WooCommerce Core Reference

WooPayments is a separate plugin that integrates with WooCommerce core, leveraging its hooks, filters, and APIs. Having the WooCommerce codebase available locally provides useful context when working on WooPayments.

**Locations (in priority order):**
1. `../woocommerce/plugins/woocommerce/` — Full monorepo checkout (if available). Has git history.
2. `docker/wordpress/wp-content/plugins/woocommerce/` — Always available. Built plugin with `includes/` and `src/`. No git history but has all PHP code needed for hook tracing.
3. In the CI pipeline: checked out via `actions/checkout` to `./woocommerce/plugins/woocommerce/`.

**Key paths within WooCommerce:**
- `includes/` — Core PHP classes (`WC_Emails`, `WC_Order`, hooks, gateways)
- `src/` — Modern PSR-4 code (newer features, DI container)
- `includes/emails/` — Email hook handlers (important for understanding side effects of status changes)

**When to reference WooCommerce core:**
- When working with WC hooks/filters — check the core implementation to understand parameters, timing, and context
- When using WC base classes (e.g., `WC_Payment_Gateway`) — understand the parent class behavior
- When debugging issues that may involve core behavior
- When implementing features that interact with WC APIs (orders, products, customers, etc.)
- **When changing order statuses** — trace what hooks fire and what side effects occur (emails, API calls). Check `includes/class-wc-emails.php` and `includes/abstracts/abstract-wc-order.php`
- **When reviewing code that hooks into `admin_init` or `init`** — trace the full call chain to understand performance implications

**Auto-reference triggers:** Proactively check WooCommerce core when you encounter:
- Classes using `WC_*` base classes
- Hooks starting with `woocommerce_` or `wc_`
- Usage of `WC()` singleton or WC helper functions
- Order, product, or customer manipulation code
- `$order->set_status()` or `$order->update_status()` calls — always check what hooks and emails fire

## Directory Structure

### PHP Code
- **`src/`** - Modern PHP code using PSR-4 autoloading and dependency injection
  - Uses a service `Container` for dependency injection
  - Preferred location for new PHP code
- **`includes/`** - Legacy PHP codebase organized by feature
  - `admin/`, `payment-methods/`, `subscriptions/`, `multi-currency/`, etc.
  - Still actively used but prefer `src/` for new code

### Frontend Code
- **`client/`** - React/TypeScript frontend application
  - `components/` - Reusable UI components
  - `settings/`, `checkout/`, `onboarding/` - Feature areas
  - `data/` - Redux state management (@wordpress/data)
  - Uses React 18.3 and TypeScript

### Tests
- **`tests/unit/`** - PHP unit tests (PHPUnit)
- **`tests/js/`** - JavaScript test configuration
- **`tests/e2e/`** - End-to-end tests (Playwright)
- JS tests are co-located with source files in `client/**/__tests__/`

### Build & Config
- **`webpack/`** - Modular webpack configuration (shared, production, development, HMR)
- **`tasks/`** - Build and release automation
- **`bin/`** - Helper scripts
- **`docker/`** - Docker development environment

## Technology Stack

**Backend:** PHP, WordPress APIs, WooCommerce hooks, Composer
**Frontend:** React, TypeScript, @wordpress/data (Redux), SCSS
**Build:** Webpack, Babel, PostCSS, @wordpress/scripts
**Testing:** PHPUnit, Jest, Playwright, React Testing Library
**Quality:** ESLint, PHPCS, Psalm, TypeScript, Prettier

*See `composer.json`, `package.json`, and `woocommerce-payments.php` for specific version requirements*

## Common Commands

### Development
```bash
npm install             # Install dependencies
npm start               # Watch JS changes (alias: npm run watch)
npm run hmr             # Hot module replacement server
npm run up              # Start Docker environment
npm run dev             # Start Docker + watch mode
```

### Testing

**PHP Tests:**
```bash
# First run sets up the environment (installs WP, activates plugins).
# Requires composer install --dev and Docker running (npm run up).
npm run test:php                    # Run all PHP tests in Docker
npm run test:php-watch              # Watch mode
npm run test:php-coverage           # With coverage

# To run a specific test class or method directly:
docker compose exec -u www-data wordpress bash -c \
  "cd /var/www/html/wp-content/plugins/woocommerce-payments && \
  vendor/bin/phpunit --configuration phpunit.xml.dist --filter 'TestClassName::test_method_name'"

# Run npm run test:php once first to set up the test environment,
# then use the docker compose exec command for faster subsequent runs.
```

**JavaScript Tests:**
```bash
npm run test:js                     # Run all JS tests
npm run test:watch                  # Watch mode
npm run test:debug                  # Debug mode
npm run test:update-snapshots       # Update snapshots
```

**E2E Tests:**
```bash
npm run test:e2e                    # Run E2E tests
npm run test:e2e-ui                 # UI mode
npm run test:e2e-up                 # Setup test environment
npm run test:e2e-down               # Teardown
```

### Build & Quality
```bash
npm run build:client                # Build production JS
npm run build                       # Build release package
npm run lint                        # Run all linters
npm run lint:js                     # ESLint + TypeScript
npm run lint:php                    # PHPCS
npm run lint:php-fix                # Auto-fix PHP issues
npm run format                      # Format with Prettier
npm run psalm                       # PHP static analysis
```

### Other
```bash
npm run changelog                   # Add changelog entry (interactive)
npm run changelog:add               # Add changelog entry (non-interactive, for automation)
npm run i18n:pot                    # Generate translations
```

## Development Workflows

### Code Organization
- **New PHP code:** Use `src/` with dependency injection
- **Legacy PHP:** Lives in `includes/`, prefer refactoring to `src/`
- **Frontend:** React components in `client/` with TypeScript
- **Tests:** Mirror source structure in `tests/unit/` for PHP

### Testing Conventions
- PHP tests use PHPUnit and follow WordPress testing practices
- JS tests use Jest with @wordpress/scripts preset
- Co-locate JS tests with source files or in `__tests__/` directories
- PHP tests run in Docker via `npm run test:php` (see `bin/run-tests.sh`)

### Git Workflow
- Main branch for PRs: `develop`
- Release branch: `trunk`
- Husky manages git hooks
- **Before pushing to a branch**, verify it doesn't belong to a merged PR:
  ```bash
  gh pr list --head "$(git branch --show-current)" --state merged --json number --jq length
  ```
  If the result is non-zero, the branch's PR was already merged. Do NOT push — create a new branch off `develop` instead.
- **Before creating a PR:**
  - Must add and commit a changelog entry (use 'patch' significance if change is not significant)
  - For Claude/automation: `npm run changelog:add -- --type=<type> --entry="<description>"`
  - For interactive use: `npm run changelog`
  - Changelog must be committed and pushed before creating the PR
- Use PR template from `.github/PULL_REQUEST_TEMPLATE.md` when creating pull requests
  - Include testing instructions
  - Check mobile testing requirement
  - Link to release testing docs post-merge
- **After creating a PR:**
  - Assign `Automattic/gamma` as reviewer: `gh pr edit <number> --add-reviewer Automattic/gamma`
  - Add the `pr: needs review` label: `gh pr edit <number> --add-label "pr: needs review"`

### Docker Environment
- WordPress: http://localhost:<PORT> (check `.env` for your port; default 8082 for main checkout, 8180-8199 for worktrees)
- phpMyAdmin: http://localhost:8083
- MySQL: localhost:5678
- Xdebug ready (requires IDE path mapping)
- First-time setup: `npm run up:recreate` (auto-starts infrastructure if needed)
- Subsequent runs: `npm run up`
- For git worktrees: `npm run worktree:setup` to configure `.env` with unique port
- To list all worktrees and their ports: `npm run worktree:status`

### Dependency Management
- WordPress dependencies extracted automatically via webpack plugin
- External packages added via `requestToExternal` and `requestToHandle` in webpack.config.js
- Use Composer for PHP dependencies
- Use npm for JavaScript dependencies

### Changelog
- Use `npm run changelog` for interactive changelog entry creation
- Use `npm run changelog:add` for non-interactive (automation/Claude) usage:
  ```bash
  npm run changelog:add -- --type=fix --entry="Fixed a bug"
  npm run changelog:add -- --type=add --entry="Added feature" --significance=minor
  # Or with positional args: npm run changelog:add -- patch fix "Fixed a bug"
  ```
- Types: add, fix, update, dev
- Significances: patch (default), minor, major
- Entries go in `changelog/` directory

## Important Configuration Files

| File | Purpose |
|------|---------|
| `package.json` | npm scripts and dependencies |
| `composer.json` | PHP dependencies and autoloading |
| `webpack.config.js` | Main webpack entry |
| `phpunit.xml.dist` | PHPUnit configuration |
| `phpcs.xml.dist` | PHP coding standards |
| `tests/js/jest.config.js` | Jest configuration |
| `tests/e2e/playwright.config.ts` | E2E test config |
| `tsconfig.json` | TypeScript configuration |
| `.eslintrc` | ESLint rules |

## Version Support Policy
- WordPress: Strict L-2 (supports current and 2 previous major versions)
- WooCommerce: Loose L-2
- See `docs/version-support-policy.md` for details

## Documentation
- `README.md` - Main setup and overview
- `CONTRIBUTING.md` - Contribution guidelines
- `tests/README.md` - Testing guide
- `docker/README.md` - Docker setup
- `includes/core/README.md` - Extensibility docs
- `docs/` - Additional documentation

## Tips for Claude
- Prefer editing existing files over creating new ones
- Check both `src/` and `includes/` when searching for PHP code
- React components follow WordPress coding patterns (@wordpress packages)
- Test files mirror source structure
- PHP tests require Docker - ensure it's running before executing tests
- Use `npm run test:php` to run all tests or edit the command to pass PHPUnit filters
- When pushing, always push only the current branch: `git push origin HEAD` (not `git push` which tries to push all configured branches)
- When pulling, always pull only the current branch: `git pull origin $(git branch --show-current)` or `git pull --rebase origin HEAD`
