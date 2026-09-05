# Architecture and code placement

**Last updated:** 2026-09-05

Read the sections relevant to the current task. Paths and commands in this reference are relative to the repository root unless stated otherwise.

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


## Documentation Index

| Doc | Content |
|-----|---------|
| `README.md` | Main setup and overview |
| `CONTRIBUTING.md` | Contribution guidelines |
| `tests/README.md` | Testing overview & index of suites (unit, JS, E2E, QIT) |
| `docker/README.md` | Docker setup |
| `includes/core/README.md` | Extensibility docs |
| `docs/` | Additional documentation |

