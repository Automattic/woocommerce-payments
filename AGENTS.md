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

**Namespace caveat for `includes/`:** ~12 files in `includes/` use the `WCPay` namespace (e.g., `class-wc-payments-checkout.php`, `class-database-cache.php`). When referencing a global-namespace class from these files, you must add a `use` import or prefix with `\`. PHPStan catches this but local PHPCS won't — run `composer run phpstan` before pushing changes that introduce new cross-namespace references.

**No `declare(strict_types=1)` in `includes/`:** The project's PHPCS rules require the file docblock immediately after `<?php`. Adding `declare(strict_types=1)` between them causes lint failures. Files in `src/` (PSR-4) may use it, but `includes/` files must not.

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

E2E tests use Playwright in Docker containers against a local WordPress site with real Stripe test transactions.

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

## Git Worktrees

Worktrees provide isolated working directories for parallel feature work. Each worktree gets its own Docker port range (8180-8199).

**Setup:** `npm run worktree:setup` (configures `.env`), `npm run worktree:status` (list all), `npm run tube:start` (tunnel — see [Jurassic Tube](#jurassic-tube-ssh-tunnels))

**CRITICAL: Never remove a worktree that is your current working directory.** Removing the CWD makes ALL subsequent commands fail irrecoverably — no `cd`, no subshell can fix it.

**Safe cleanup sequence (always from the main repo):**
```bash
# 1. Switch to main repo FIRST
cd /path/to/main/repo

# 2. Now safe to remove
git worktree remove /path/to/worktree

# 3. Clean up
git worktree prune
git branch -d worktree-feat/branch-name
```

**Merging worktree work:** `git checkout main` fails inside a worktree when main is checked out elsewhere. Use `git -C` from the main repo:
```bash
cd /path/to/main/repo
git -C /path/to/main/repo merge worktree-feat/branch-name
```

## Docker Environment

| Service | URL/Port |
|---------|----------|
| WordPress | `http://localhost:<PORT>` (check `.env`; default 8082, worktrees 8180-8199) |
| phpMyAdmin | `http://localhost:8083` |
| MySQL | `localhost:5678` |

- First-time: `npm run up:recreate`
- Subsequent: `npm run up`
- Xdebug ready (requires IDE path mapping)

## Jurassic Tube (SSH Tunnels)

Jurassic Tube creates public HTTPS tunnels (`<subdomain>.jurassic.tube`) to your local WordPress instance. Useful for testing webhooks, mobile devices, or sharing a dev site.

### Commands

| Command | Purpose |
|---------|---------|
| `npm run tube:setup` | First-time setup: registers subdomain, generates SSH keys, creates `bin/jurassictube/config.env` |
| `npm run tube:start` | Starts tunnel (WordPress URLs resolve automatically via `wp-config.php`) |
| `npm run tube:stop` | Stops tunnel |
| `npm run tube:status` | Shows subdomain, port, tunnel state, and worktree info |

### Worktree Support

`tube:start` is worktree-aware. It auto-detects worktrees and handles configuration automatically:

**Default (one tunnel at a time):**
- In a worktree, `tube:start` copies config/keys from the main repo if no local config exists
- Reads `WORDPRESS_PORT` from the worktree's `.env` to forward the tunnel to the correct port
- Only one tunnel can use a subdomain at a time — starting in a worktree redirects the subdomain to the worktree's port

**Per-worktree subdomains (parallel tunnels):**
- Run `npm run tube:setup` in the worktree to register a dedicated subdomain
- Each worktree then has its own `bin/jurassictube/config.env` with a unique subdomain
- Multiple tunnels can run simultaneously on different subdomains

**Agent workflow for tunnels in worktrees:**
```bash
# 1. Ensure worktree has a port assigned
npm run worktree:setup

# 2. Ensure Docker is running
npm run up

# 3. Start tunnel (auto-copies config from main repo if needed)
npm run tube:start

# 4. When done
npm run tube:stop
```

**Key details:**
- `bin/jurassictube/` is gitignored — config and keys are never committed
- Port is resolved at runtime from `WORDPRESS_PORT` in `.env` (never hardcoded in config)
- WordPress URLs resolve automatically via `wp-config.php` (`DOCKER_HOST` from `HTTP_HOST`) — no DB updates needed

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
- **PHPCS class structure ordering:** `SlevomatCodingStandard.Classes.ClassStructure.IncorrectGroupOrder` requires methods in order: public → protected → private. When adding new private methods, place them after all public and protected methods. Run `vendor/bin/phpcbf --standard=phpcs.xml.dist <file>` to auto-fix ordering violations.
- **Migration version_compare:** When adding a migration class in `includes/migrations/`, the `version_compare()` threshold must match the version in the `@since` tag (e.g., `version_compare('10.6.0', $previous_version, '>')` for `@since 10.6.0`). The version represents when the migration ships, not when the old behavior was introduced.
- **Styles cache invalidation on plugin update:** `WC_Payments_Utils::compute_styles_cache_version()` uses `WCPAY_VERSION_NUMBER` in its hash, but the cached WP option persists across updates. Hook `invalidate_styles_cache_version` to `woocommerce_woocommerce_payments_updated` to clear stale caches.
