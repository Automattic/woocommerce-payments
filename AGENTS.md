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
- `capital-flow.md` — Stripe Capital offer acceptance flow, `wcpay-loan-offer` redirect, account cache gating
- `dispute-evidence-system.md` — Dispute challenge UI: evidence matrix, two-tier field resolution, cover letter ordering, field repurposing pattern
- `payment-method-lifecycle.md` — How a payment method reaches checkout: Stripe capability vs. `upe_enabled_payment_method_ids`, status vocabulary, enable/disable paths, the no-unrequest rule

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

## Transact Platform Server Reference

WooPayments' server-side counterpart (webhook handlers, dispute/notification link-building, account backend) lives in `transact-platform-server`, a separate wpcom-side repo — not in this one.

**Locations (priority order, matches `bin/setup-e2e-local.sh` auto-detection):**
1. `../transact-platform-server` — sibling checkout (if available)
2. `~/src/transact-platform-server`
3. `~/projects/transact-platform-server`

**Key paths:** `server/wp-content/rest-api-plugins/endpoints/wcpay/` (dispute/email/webhook logic), `server/wp-content/rest-api-plugins/endpoints/transact/` (core Transact API)

**Caveat:** `server/` and `missioncontrol/` are gitignored rsync mirrors of the wpcom sandbox — no git history locally, content reflects whatever was last pulled. See `tests/e2e/README.md` for the `TRANSACT_PLATFORM_SERVER_REPO` env var used to wire a checkout into E2E tests.

**Check it when** an investigation bottoms out at "this must be server-side" — webhook processing, dispute/reminder notification links, account/onboarding backend logic not found in `includes/wc-payment-api/` or `src/`.

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
pnpm install                        # Install dependencies
pnpm start                          # Watch JS changes (alias: pnpm run watch)
pnpm run watch                      # Rebuild assets while developing locally
pnpm run hmr                        # Hot module replacement server
pnpm run up                         # Start Docker environment at http://localhost:8082
pnpm run dev                        # Start Docker + watch mode
```

### PHP Tests
```bash
pnpm run test:php                    # Run all (first run sets up environment)
pnpm run test:php-watch              # Watch mode
pnpm run test:php-coverage           # With coverage

# Specific test (after initial pnpm run test:php setup):
docker compose exec -u www-data wordpress bash -c \
  "cd /var/www/html/wp-content/plugins/woocommerce-payments && \
  vendor/bin/phpunit --configuration phpunit.xml.dist --filter 'TestClassName::test_method_name'"
```

### JavaScript Tests
```bash
pnpm run test:js                     # Run all JS tests
pnpm run test:watch                  # Watch mode
pnpm run test:debug                  # Debug mode
pnpm run test:update-snapshots       # Update snapshots
```

### E2E Tests

E2E tests use Playwright in Docker containers against a local WordPress site with real Stripe test transactions.

**First-time setup:** Run `bin/setup-e2e-local.sh` to auto-generate `tests/e2e/config/local.env` from your local infrastructure, then `pnpm run build:client && pnpm run test:e2e-setup`. See the E2E skill (`/e2e-testing`) or `tests/e2e/README.md` for full details.

```bash
pnpm run test:e2e                    # Run all E2E tests (headless)
pnpm run test:e2e-ui                 # Interactive UI mode (localhost:8077)
pnpm run test:e2e-setup              # First-time E2E environment setup
pnpm run test:e2e-up                 # Start existing E2E containers
pnpm run test:e2e-down               # Stop E2E containers

# Run specific tests
pnpm run test:e2e tests/e2e/specs/wcpay/merchant/  # All merchant tests
pnpm run test:e2e tests/e2e/specs/wcpay/shopper/   # All shopper tests
pnpm run test:e2e -- -g "dispute"                   # By test name
```

**E2E environment ports:** WordPress `:8084` | phpMyAdmin `:8085` | Transact Server `:8088` | Playwright UI `:8077`

### Build & Quality
```bash
pnpm run build:client                # Build production JS
pnpm run build                       # Build release package
pnpm run lint                        # Run all linters
pnpm run lint:js                     # ESLint + TypeScript
pnpm run lint:php                    # PHPCS
pnpm run lint:php-fix                # Auto-fix PHP issues
pnpm run format                      # Format with Prettier
pnpm run psalm                       # PHP static analysis
```

### Changelog
```bash
pnpm run changelog                   # Interactive
pnpm run changelog:add -- --type=fix --entry="Fixed a bug"
pnpm run changelog:add -- --type=add --entry="Added feature" --significance=minor
```
Types: `add`, `fix`, `update`, `dev`. Significances: `patch` (default), `minor`, `major`. Entries go in `changelog/`.

### Dependencies & supply-chain cooldown

All pnpm settings live in `pnpm-workspace.yaml` — since pnpm 11 the `package.json` `pnpm` field and non-auth `.npmrc` settings are no longer read. Three supply-chain guards are on:

- **`minimumReleaseAge: 1440`** — pnpm won't resolve an npm version until it is 1 day old, closing the window between a malicious publish and its detection. It gates *resolution* (`pnpm add`/`update`, lockfile regeneration); on pnpm ≥ 11.1.3 `pnpm install --frozen-lockfile` also re-validates existing lockfile entries and aborts on a too-young pin (`ERR_PNPM_MINIMUM_RELEASE_AGE_VIOLATION`). Dependabot's 7-day cooldown keeps its PRs clear of this.
- **`blockExoticSubdeps: true`** — transitive dependencies must resolve from the registry; a transitive git/tarball URL fails the install.
- **`allowBuilds`** — the build-script allowlist (`strictDepBuilds` is on by default, so a dependency whose install script is not listed here fails the install). List a package as `true` to let it build, `false` to silence it.

**Pushing an urgent security bump through the cooldown:** add the package to `minimumReleaseAgeExclude` in `pnpm-workspace.yaml` (bare name or exact `pkg@version`), or run `pnpm audit --fix`, which auto-exempts advisory-patched versions. Remove the exclude once the version ages past the window.

### Other
```bash
pnpm run i18n:pot                    # Generate translations
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
- Add and commit a changelog entry: `pnpm run changelog:add -- --type=<type> --entry="<description>"`
- Use PR template from `.github/PULL_REQUEST_TEMPLATE.md`
- Open PRs in **draft mode** (`gh pr create --draft`).

**After creating a PR:**
- Ask the author to review the PR description and testing instructions, then manually test the changes.
- Add the `pr: needs review` label and reviewers only after the PR has been manually tested, and only when explicitly asked.

## Git Worktrees

Worktrees provide isolated working directories for parallel feature work. Each worktree gets its own Docker port range (8180-8199).

**Setup:** `pnpm run worktree:setup` (configures `.env`), `pnpm run worktree:status` (list all), `pnpm run tube:start` (tunnel — see [Jurassic Tube](#jurassic-tube-ssh-tunnels))

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

- First-time: `pnpm run up:recreate`
- Subsequent: `pnpm run up` brings the local WordPress server up at `http://localhost:8082` by default.
- When testing local frontend/admin UI changes, run `pnpm run watch` so built assets are regenerated.
- Xdebug ready (requires IDE path mapping)
- Local WP admin credentials are `admin` / `admin`. Do **not** change the local admin password with `wp user update admin --user_pass=...` unless explicitly requested. If browser/MCP login fails, ask before resetting credentials.

## Jurassic Tube (SSH Tunnels)

Jurassic Tube creates public HTTPS tunnels (`<subdomain>.jurassic.tube`) to your local WordPress instance. Useful for testing webhooks, mobile devices, or sharing a dev site.

### Commands

| Command | Purpose |
|---------|---------|
| `pnpm run tube:setup` | First-time setup: registers subdomain, generates SSH keys, creates `bin/jurassictube/config.env` |
| `pnpm run tube:start` | Starts tunnel (WordPress URLs resolve automatically via `wp-config.php`) |
| `pnpm run tube:stop` | Stops tunnel |
| `pnpm run tube:status` | Shows subdomain, port, tunnel state, and worktree info |

### Worktree Support

`tube:start` is worktree-aware. It auto-detects worktrees and handles configuration automatically:

**Default (one tunnel at a time):**
- In a worktree, `tube:start` copies config/keys from the main repo if no local config exists
- Reads `WORDPRESS_PORT` from the worktree's `.env` to forward the tunnel to the correct port
- Only one tunnel can use a subdomain at a time — starting in a worktree redirects the subdomain to the worktree's port

**Per-worktree subdomains (parallel tunnels):**
- Run `pnpm run tube:setup` in the worktree to register a dedicated subdomain
- Each worktree then has its own `bin/jurassictube/config.env` with a unique subdomain
- Multiple tunnels can run simultaneously on different subdomains

**Agent workflow for tunnels in worktrees:**
```bash
# 1. Ensure worktree has a port assigned
pnpm run worktree:setup

# 2. Ensure Docker is running
pnpm run up

# 3. Start tunnel (auto-copies config from main repo if needed)
pnpm run tube:start

# 4. When done
pnpm run tube:stop
```

**Key details:**
- `bin/jurassictube/` is gitignored — config and keys are never committed
- Port is resolved at runtime from `WORDPRESS_PORT` in `.env` (never hardcoded in config)
- WordPress URLs resolve automatically via `wp-config.php` (`DOCKER_HOST` from `HTTP_HOST`) — no DB updates needed

## Configuration Files

| File | Purpose |
|------|---------|
| `package.json` | pnpm scripts and dependencies |
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

## Backward Compatibility

WooPayments has backward-compatibility obligations in **both directions**. Any change to a **public or externally exposed** class, interface, function, or method signature is **high-risk** and **must state its backward-compatibility impact in the PR description** — regardless of whether the symbol lives under the `WCPay\Internal` namespace.

Treat a symbol as **externally exposed** when it is implemented or consumed outside this plugin — by extensions, themes, WooPay, mobile apps, or other plugins — even if it lives under `Internal`. `Internal` is **not** a stability guarantee. When in doubt, assume it is exposed and state the BC impact.

**As a producer of public API.** WooPayments exposes a large surface that third parties consume:
- `do_action`/`apply_filters` hooks — `wcpay_*` and the `woocommerce_*` hooks this plugin fires (renaming a hook, changing its args, or dropping a filter's passthrough value is breaking).
- The Request/Response class layer (`includes/core/server/request/`) — deliberately hook-extensible (`wcpay_*_request` filters), so its constructors, setters, and validation are consumed externally.
- The public gateway class `WC_Payment_Gateway_WCPay` and payment-method classes.
- REST controllers (`includes/admin/`, `includes/reports/`, `includes/multi-currency/`) — route paths, params, response shapes — and registered Abilities.
- `wcpay_*` option keys and stored meta.

Adding a **required** method to an interface that external code can implement is backward-incompatible — existing implementers fatal on load. Prefer a non-breaking alternative: add the method to a concrete class, introduce a separate new interface, or provide a default via an abstract base class.

**Deprecate, don't rename.** Never rename or remove an existing public symbol (class, interface, method, constant, hook, option key) in place. Mark the old one `@deprecated`, add the replacement alongside it, and keep both working through a deprecation window so consumers can migrate.

**As a consumer of upstream WooCommerce contracts.** WooPayments extends and implements upstream WooCommerce classes and interfaces — e.g. `WC_Payment_Gateway_CC`, `Blocks\Payments\Integrations\AbstractPaymentMethodType`, and `Blocks\Integrations\IntegrationInterface`. The `Internal` namespace is not a stability guarantee upstream either: WooCommerce can change these contracts, and doing so is exactly the class of break this guardrail exists to prevent (a WC 10.9.0 change to an `Internal` `FeedInterface` fataled older WooCommerce Stripe Gateway versions on load). When implementing an upstream contract, keep the implementation compatible across the supported WC range (L, L-1, L-2) and guard against contract changes rather than assuming the interface is frozen.

### The compatibility surface is wider than PHP signatures

Class and function signatures are not the only contracts. The following are equally binding: a change to any of them is **high-risk** and requires the same backward-compatibility impact statement in the PR description.

**Hooks and filters are public contracts.** Every `do_action` and `apply_filters` call — the `wcpay_*` hooks and the `woocommerce_*` hooks this plugin fires — is an interface third-party callbacks depend on. Removing a hook, renaming it, or removing/reordering its arguments breaks every attached callback. Changing *when* or *whether* a hook fires can break consumers that depend on its timing. Additive is the safe path: append new arguments at the end, never remove or reorder existing ones. To retire a hook, fire it through `do_action_deprecated()` / `apply_filters_deprecated()` for a deprecation window instead of deleting it.

**Never trust data that flows through hooks.** Keep hook callback parameters untyped and validate or coerce the value before passing it to strictly typed code, since any callback can receive a value another one produced. And when firing a filter, validate the final return value before using it, since any callback in the chain can return the wrong thing.

**Overridable classes are contracts too, including which internal methods get called.** Third-party code subclasses `WC_Payment_Gateway_WCPay` and the payment-method classes and overrides individual public and protected methods, so those methods are contracts: changing their signatures or removing them breaks subclasses even when no caller inside this plugin remains. Adding a fast path or skip that avoids calling an overridable method silently disables those overrides even though no signature changed: the subclass's code simply stops running. When optimizing such a class, ensure overridable methods are still invoked on every code path, or treat the change as breaking.

**Registered script and style handles are public contracts.** Third-party code enqueues this plugin's handles and lists them as dependencies — the handles this plugin registers for admin, checkout, and express-checkout assets (both the `WCPAY_*` and `wcpay-*` forms) — including handles that were only ever registered incidentally. Renaming or removing a handle breaks those consumers. To rename with a compatibility window, register the legacy handle as an alias that depends on the new handle (the same pattern WordPress core uses for `jquery` → `jquery-core`); do not register the same file under both handles, or pages with mixed consumers will load it twice.

**Do not assume global state.** WooPayments code runs in admin, REST, CLI, cron, webhook, and front-end contexts, and not all of them set the globals a front-end request does (`$post`, `$wp_query`, an initialized session or cart). Webhook and cron handlers in particular run with no cart and no logged-in customer. A newly introduced read of a global, or of `WC()->…` state, in a path reachable outside a standard request is a fatal or a silent misbehavior in the contexts that do not set it. Guard the exact dependency explicitly: use `function_exists`/`class_exists` for symbols, `isset` for variables, `did_action` for lifecycle state, and verify that `WC()` and the required component are initialized before dereferencing `WC()->…`.

**Do not assume single-site.** Multisite changes where data lives: site-scoped vs network-scoped options (`get_option` vs `get_site_option`), per-site tables, user roles and capabilities, and upload paths all differ. A change that reads or writes site state must state in its PR whether it behaves correctly under multisite — and if it was not tested there, say so explicitly.

**Do not assume install layout.** WordPress could be configured to run in a subdirectory, with relocated `wp-content`, and behind reverse proxies. Never build paths or URLs by concatenation from the domain root; derive them (`plugins_url()`, `plugin_dir_path()`, `wp_upload_dir()`, and mind the `home_url()` vs `site_url()` distinction). A path that works on a root install and breaks elsewhere is a compatibility bug, not an edge case.

### Database migrations

Migration classes live in `includes/migrations/` and run on `woocommerce_woocommerce_payments_updated`, gated by `version_compare` against the version the store last had installed.

- **The threshold is the release that runs it; only convergent migrations may bump it.** A new migration's threshold names the release that ships it; `@since` records when the class landed and never changes afterward. Bumping the threshold re-runs the migration on every store below the new value - the repo does this deliberately when a second run lands in the same end state (see the registration comment above the `Payment_Method_Deprecation_Settings_Update` hooks in `class-wc-payments.php`, which carries the live thresholds). Convergence is the test, not delete-only: that sweep rewrites `upe_enabled_payment_method_ids` and disables the deprecated gateway, and is still safe to bump because a second run filters an already-filtered list. If a second run would land somewhere different, write a new class.
- **A missing version option runs everything.** `get_option( 'woocommerce_woocommerce_payments_version' )` returns `false` on a fresh install, which makes the `'>'` threshold gate true and the `'<='` early-return guard false - both styles in use, so every migration also fires on brand-new stores, and again if the option is ever lost. Guard `empty( $previous_version )` when running on a fresh store would be wrong, as `Multi_Currency_Cache_Autodetect_Existing_Install` does.
- **A downgrade must not fatal or corrupt data.** Deleting a dead option is fine - old code reads the default. Dropping a key old code still reads costs the merchant that setting: `Migrate_Express_Checkout_Locations` and `Migrate_Payment_Request_To_Express_Checkout_Enabled` do exactly that, a deliberate trade, not a pattern to copy. Reshaping a value in place fatals or silently misbehaves, since old code still parses that key: put the new shape under a new key, prefer leaving the old key readable for a release, and if you cut over, state what a downgrade costs in the PR description.

### Before changing any public or externally exposed surface (agent checklist)

1. Identify the contract you are touching: signature, hook, global/scope expectation, site topology, or install layout.
2. Assume unseen consumers. You cannot enumerate third-party code; if the surface is reachable from outside this plugin, someone consumes it.
3. Prefer the additive path (new optional method, appended hook argument, new symbol + deprecation) over changing what exists.
4. State the impact in the PR description: what changed, who could consume it, and why it is safe or what the deprecation path is.
5. If you cannot establish the impact, stop and flag it to the user as needing review.

> Core's [AGENTS.md Backward Compatibility](https://github.com/woocommerce/woocommerce/blob/trunk/AGENTS.md#backward-compatibility) section carries the same guardrail.

## Documentation Index

| Doc | Content |
|-----|---------|
| `README.md` | Main setup and overview |
| `CONTRIBUTING.md` | Contribution guidelines |
| `tests/README.md` | Testing overview & index of suites (unit, JS, E2E, QIT) |
| `docker/README.md` | Docker setup |
| `includes/core/README.md` | Extensibility docs |
| `docs/` | Additional documentation |

## `.claude/` Documentation Structure

AI-generated docs live in `.claude/`. Permanent developer docs live in `docs/`.

| Directory | Purpose | Naming | Git |
|-----------|---------|--------|-----|
| `.claude/docs/` | Living reference guides | No date prefix; `**Last updated:** YYYY-MM-DD` after title | Tracked |
| `.claude/docs/analysis/` | Research, investigations | `YYYY-MM-DD-description.md` | Gitignored |
| `.claude/docs/plans/` | Implementation plans | `YYYY-MM-DD-description.md` | Gitignored |
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
- New PHP code in `src/` must follow PSR-4 class/file naming and existing folder conventions. Prefer `WCPay\Internal\Service\PascalCaseService` in `src/Internal/Service/`, register services in the appropriate `src/Internal/DependencyManagement/ServiceProvider/*ServiceProvider.php`, resolve them through `wcpay_get_container()` from legacy `includes/` code, and place matching tests under `tests/unit/src/...` with namespaced PascalCase test classes.
- React components follow WordPress patterns (@wordpress packages)
- Prefer TypeScript for new client code where possible (`.ts`/`.tsx` over `.js`/`.jsx`), especially for new React components and shared data/types.
- For client UI changes, reuse existing WooPayments/WooCommerce components, typography, spacing, colors, and interaction patterns where appropriate; check nearby screens/components before introducing custom styles so new UI remains visually consistent with the rest of the client.
- PHP tests require Docker — ensure it's running before executing
- Always push only current branch: `git push origin HEAD`
- Always pull with rebase: `git pull origin $(git branch --show-current) --rebase`
- **PHPCS class structure ordering:** `SlevomatCodingStandard.Classes.ClassStructure.IncorrectGroupOrder` requires methods in order: public → protected → private. When adding new private methods, place them after all public and protected methods. Run `vendor/bin/phpcbf --standard=phpcs.xml.dist <file>` to auto-fix ordering violations.
- **Migration version_compare:** When adding a migration class in `includes/migrations/`, the `version_compare()` threshold names the release that ships it (e.g., `version_compare( '10.6.0', $previous_version, '>' )` for a migration shipping in 10.6.0) - not the release that introduced the old behavior. `@since` starts out matching it but stays put if the threshold is later bumped; see Database migrations for when bumping is allowed.
- **Styles cache invalidation on plugin update:** `WC_Payments_Styles_Cache::compute_styles_cache_version()` in `includes/class-wc-payments-styles-cache.php` uses `WCPAY_VERSION_NUMBER`, while its cached options persist across updates. Keep `WC_Payments_Styles_Cache::handle_theme_change()` hooked to `woocommerce_woocommerce_payments_updated` so the styles version and stored WooPay appearance are invalidated.
- **Abilities API registrations** (`src/Internal/Abilities/AbilitiesRegistrar.php` + `src/Internal/Abilities/Domain/*.php`): each ability lives in its own `Domain/<AbilityName>.php` class implementing `Automattic\WooCommerce\Abilities\AbilityDefinition`. When you change the code path behind a registered ability (REST controller callback, backing Request class, capability gate), audit the relevant Domain class for required updates (annotations, `input_schema`, `output_schema`, description). List abilities use the WC 10.9 paginated output envelope (`{ <collection>: [...], total_pages, page, per_page }`) via the `AbstractWCPayAbility` base. The feature gates on `class_exists('\Automattic\WooCommerce\Internal\Abilities\AbilitiesLoader')` and silently no-ops on WC < 10.9. Each Domain class points at the controller method that backs it with `@see`; the controller method points back at the Domain class with the same `@see` so the connection is visible from both sides — keep that pairing when adding a new ability. Run `vendor/bin/phpunit --filter 'Abilities'` after such changes — covers both the registrar coordinator and per-ability Domain tests.
- **ExPlat experiments — assign on the Tracks anon-ID from `WC_Tracks_Client::get_identity()`:** ExPlat joins an experiment's assignments to its Tracks events on identity, so any other assignment key reports zero conversions with no error. Resolve the anon-ID through the same helper that stamps the events rather than reading `$_COOKIE['tk_ai']` or minting one via `Jetpack_Tracks_Client`; those diverge when the cookie is absent, and the wrong ID then sticks in user meta. A `wpcom:user_id` identity (stores running the standalone Jetpack plugin) has no joinable key, so sit the experiment out. Resolve identity only after the consent check, since it persists user meta. Consent means `WC_Site_Tracking::is_tracking_enabled()`, the predicate that gates the events; the raw `woocommerce_allow_tracking` option misses the kill-switch filters. See `ReviewPromptExperiment::assignment_key()`.
- **`rawurlencode()` query values before `add_query_arg()`:** it appends values as-is, so a `+` in the base64 anon-ID arrives as a space and keys the assignment on a different identity than the Tracks events. Same pattern as WooCommerce core's copy of this class and PR #11815. See `Experimental_Abtest::request_variation()`.
- **Constants in tests — literals on the assert side:** When a value has a named constant (currency codes like `WCPay\Constants\Currency_Code`, status/enum constants, etc.), use the constant for *incidental* values in the **arrange/act** phases — fixtures, mock return values, setup, and values passed *into* the system under test in their own statements. Use **plain literals** for anything that is the point of an assertion: the expected value, mock `->with()` payloads, **and even an act-input nested inside an `assert*()` wrapper**. Rationale (Meszaros *xUnit Test Patterns* / Fowler): an assertion should pin its expected value *independently* of the code under test — reusing the system-under-test's own constant on both sides couples them and can mask a wrong/drifted constant, and a bare literal (`'EUR'`, `'complete'`) reads better as an expected value than the constant. Don't convert literals where the literal *is* the point: array **keys**, values whose **case** or invalidity is load-bearing (e.g. lowercase Stripe-response codes, rejection-path sentinels), or tests of the constant/formatting logic itself (literals there are the independent oracle). Quick guard: a constant shouldn't appear inside an `assert*()` call — e.g. `grep -n 'assert.*Currency_Code::'` returns nothing.
