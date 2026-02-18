# AGENTS.md — WooPayments Repository Guide

## Naming & Branding

| Term | Context |
|------|---------|
| **WooPayments** | Official brand name. Use in UI text, docs, user-facing copy. |
| **WooCommerce Payments** | Legacy name. Still appears in code, class names, directory names. |
| **WCPay** | Internal shorthand. Used in code prefixes (`wcpay_`, `WCPay`), conversation. |
| **woocommerce-payments** | Plugin slug, text domain, repo name, directory name. Frozen for backward compatibility — cannot change without breaking updates for existing installs. |

## Repository Overview

WooPayments is a WordPress/WooCommerce plugin for payment processing. PHP backend + React admin interface.

- **License:** GPL-3.0-or-later
- **Repository:** github:Automattic/woocommerce-payments
- **Version & requirements:** See `woocommerce-payments.php` header and `package.json` engines field

## Architecture — Payment Request Flow

**Most important thing to understand.** Every payment flows through these layers in order. Never skip a layer.

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
   - Creates Request objects, configures with setters, calls `send()`

2. **Request Class Layer** (`includes/core/server/request/`)
   - **Always use typed Request classes** for API communication. Never call API client directly.
   - Each operation has its own class: `Create_And_Confirm_Intention`, `Refund_Charge`, `Get_Intention`, etc.
   - Pattern: `$request = Create_And_Confirm_Intention::create()` → setters → `$request->send()`
   - Validates parameters (Stripe ID prefixes, required fields), supports WP hooks for extensibility.
   - See `includes/core/README.md` and `includes/core/CONTRIBUTING.md` for full Request/Response API.

3. **API Client** (`includes/wc-payment-api/class-wc-payments-api-client.php`)
   - Low-level HTTP. **Do not call directly from gateway or feature code.**
   - Handles URL construction, idempotency keys, retry logic (3 retries, exponential backoff), response parsing.

4. **HTTP / Jetpack Layer** (`includes/wc-payment-api/class-wc-payments-http.php`)
   - Delegates to `Jetpack\Connection\Client::remote_request()`. Never modify directly.
   - All auth (blog token signing) handled by Jetpack.

5. **Frontend** (`client/`)
   - React 18.3 + TypeScript. State via `@wordpress/data` stores (one per domain in `client/data/`).
   - Checkout JS creates Stripe PaymentMethod/confirmation token client-side, passes ID to PHP.
   - Check WordPress/WooCommerce Storybooks before building custom components.

### Key Docs

**Architectural (read when working in these areas):**
- `includes/core/README.md` — Core API, Gateway Mode, Services, Request/Response
- `src/README.md` — DI container, PSR-4 structure, Proxy patterns
- `includes/core/CONTRIBUTING.md` — Adding new Request classes

**Deep-dive references (`.claude/docs/`):**
- `payment-flow.md` — Complete call chain with signatures, data transformations, hooks
- `test-patterns.md` — Testing conventions, base classes, mocking patterns
- `mode-system.md` — Mode hierarchy (dev/test/live), frontend data flow
- `pm-promotions.md` — PM Promotions data flow, components, REST API, analytics

**External:**
- [WordPress Components Storybook](https://wordpress.github.io/gutenberg/?path=/docs/) — Check first for UI components
- [WooCommerce Components Storybook](https://woocommerce.github.io/woocommerce/?path=/docs/docs-introduction--docs) — WC-specific UI patterns
- [Stripe API Reference](https://docs.stripe.com/api) — Payment intents, methods, charges, refunds, disputes

## WooCommerce Core Reference

WooPayments integrates with WooCommerce core via hooks, filters, and APIs.

**Locations (priority order):**
1. `../woocommerce/plugins/woocommerce/` — Full monorepo (if available), has git history
2. `docker/wordpress/wp-content/plugins/woocommerce/` — Always available, no git history
3. CI: `./woocommerce/plugins/woocommerce/`

**Key paths:** `includes/` (core classes), `src/` (modern PSR-4), `includes/emails/` (email hooks)

**Proactively check WooCommerce core when you encounter:**
- `WC_*` base classes, `woocommerce_`/`wc_` hooks, `WC()` singleton
- Order/product/customer manipulation code
- `$order->set_status()`/`$order->update_status()` — always trace what hooks and emails fire
- Code hooking into `admin_init` or `init` — trace performance implications

## Directory Structure

| Directory | Purpose | Notes |
|-----------|---------|-------|
| `src/` | Modern PHP (PSR-4, DI container) | **Preferred for new PHP code** |
| `includes/` | Legacy PHP by feature | Active; `admin/`, `payment-methods/`, `subscriptions/`, `multi-currency/` |
| `client/` | React/TypeScript frontend | `components/`, `settings/`, `checkout/`, `onboarding/`, `data/` |
| `tests/unit/` | PHP unit tests (PHPUnit) | Mirrors source structure |
| `tests/e2e/` | E2E tests (Playwright) | |
| `client/**/__tests__/` | JS tests (Jest) | Co-located with source |
| `webpack/` | Webpack config | Shared, production, development, HMR |
| `docker/` | Docker dev environment | |
| `bin/` | Helper scripts | |
| `tasks/` | Build and release automation | |

## Technology Stack

| Layer | Technologies |
|-------|-------------|
| Backend | PHP, WordPress APIs, WooCommerce hooks, Composer |
| Frontend | React 18.3, TypeScript, @wordpress/data (Redux), SCSS |
| Build | Webpack, Babel, PostCSS, @wordpress/scripts |
| Testing | PHPUnit, Jest, Playwright, React Testing Library |
| Quality | ESLint, PHPCS, Psalm, TypeScript, Prettier |

## Common Commands

### Development
```bash
npm install                         # Install dependencies
npm start                           # Watch JS changes (alias: npm run watch)
npm run hmr                         # Hot module replacement server
npm run up                          # Start Docker environment
npm run dev                         # Start Docker + watch mode
```

### PHP Tests
```bash
npm run test:php                    # Run all (first run sets up environment)
npm run test:php-watch              # Watch mode
npm run test:php-coverage           # With coverage

# Specific test (after initial npm run test:php setup):
docker compose exec -u www-data wordpress bash -c \
  "cd /var/www/html/wp-content/plugins/woocommerce-payments && \
  vendor/bin/phpunit --configuration phpunit.xml.dist --filter 'TestClassName::test_method_name'"
```

### JavaScript Tests
```bash
npm run test:js                     # Run all JS tests
npm run test:watch                  # Watch mode
npm run test:debug                  # Debug mode
npm run test:update-snapshots       # Update snapshots
```

### E2E Tests
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

### Changelog
```bash
npm run changelog                   # Interactive
npm run changelog:add -- --type=fix --entry="Fixed a bug"
npm run changelog:add -- --type=add --entry="Added feature" --significance=minor
```
Types: `add`, `fix`, `update`, `dev`. Significances: `patch` (default), `minor`, `major`. Entries go in `changelog/`.

### Other
```bash
npm run i18n:pot                    # Generate translations
```

## Git Workflow

- **PR base:** `develop` | **Release branch:** `trunk`
- Husky manages git hooks

**Before pushing:** Verify branch isn't from a merged PR:
```bash
gh pr list --head "$(git branch --show-current)" --state merged --json number --jq length
```
If non-zero, create a new branch off `develop` instead.

**Before creating a PR:**
- Add and commit a changelog entry: `npm run changelog:add -- --type=<type> --entry="<description>"`
- Use PR template from `.github/PULL_REQUEST_TEMPLATE.md`

**After creating a PR:**
```bash
gh pr edit <number> --add-reviewer Automattic/gamma
gh pr edit <number> --add-label "pr: needs review"
```

## Docker Environment

| Service | URL/Port |
|---------|----------|
| WordPress | `http://localhost:<PORT>` (check `.env`; default 8082, worktrees 8180-8199) |
| phpMyAdmin | `http://localhost:8083` |
| MySQL | `localhost:5678` |

- First-time: `npm run up:recreate`
- Subsequent: `npm run up`
- Worktrees: `npm run worktree:setup` (configure `.env`), `npm run worktree:status` (list all)
- Xdebug ready (requires IDE path mapping)

## Configuration Files

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

## Version Support

- **WordPress:** Strict L-2 (current + 2 previous major versions)
- **WooCommerce:** Loose L-2
- Details: `docs/version-support-policy.md`

## Documentation Index

| Doc | Content |
|-----|---------|
| `README.md` | Main setup and overview |
| `CONTRIBUTING.md` | Contribution guidelines |
| `tests/README.md` | Testing guide |
| `docker/README.md` | Docker setup |
| `includes/core/README.md` | Extensibility docs |
| `docs/` | Additional documentation |

## `.claude/` Documentation Structure

AI-generated docs live in `.claude/`. Permanent developer docs live in `docs/`.

| Directory | Purpose | Naming | Git |
|-----------|---------|--------|-----|
| `.claude/docs/` | Living reference guides | No date prefix; `**Last updated:** YYYY-MM-DD` after title | Tracked |
| `.claude/docs/analysis/` | Research, investigations | `YYYY-MM-DD-description.md` | Tracked |
| `.claude/docs/plans/` | Implementation plans | `YYYY-MM-DD-description.md` | Tracked |
| `.claude/tmp/` | Transitory files | Any | Gitignored |
| `.claude/tmp/reviews/` | Code review outputs | `YYYY-MM-DD-description.md` | Gitignored |
| `.claude/tmp/screenshots/` | UI screenshots | `YYYY-MM-DD-description.png` | Gitignored |
| `.claude/local/` | Developer-local drafts | Any; `-outdated` suffix for archives | Gitignored |

**Living docs** must include `**Last updated:** YYYY-MM-DD` after the title. Update on every modification.

**When to persist:**

| Content | Where |
|---------|-------|
| Reference guides, patterns | `.claude/docs/` |
| Research, analysis | `.claude/docs/analysis/` |
| Implementation plans | `.claude/docs/plans/` |
| Code reviews | `.claude/tmp/reviews/` |
| Screenshots | `.claude/tmp/screenshots/` |

Skip persisting trivial lookups, single-file reads, simple Q&A.

## Agent Rules

- Prefer editing existing files over creating new ones
- Check both `src/` and `includes/` when searching for PHP code
- React components follow WordPress patterns (@wordpress packages)
- PHP tests require Docker — ensure it's running before executing
- Always push only current branch: `git push origin HEAD`
- Always pull with rebase: `git pull origin $(git branch --show-current) --rebase`
