# WooPayments Test Matrix

Structured inventory of every test suite used in the WooPayments plugin — automated, semi-automated, and manual. Covers category, status, trigger, coverage areas, and reliability notes.

**Last updated:** 2026-03-20

---

## Ownership

All test suites, CI workflows, and release processes are owned and maintained by the **WooPayments team**. The **QIT platform** used by some suites is maintained by a separate **QualityOps team**, but the WooPayments-specific QIT test specs and CI integration are the WooPayments team's responsibility. Release process activities (#21-26) are led by the **release lead** (rotating within the team).

---

## Overview

| # | Suite | Category | Owner | Trigger | Required for PR | Gate Release |
|---|-------|----------|-------|---------|:---:|:---:|
| 1 | [PHPUnit](#1-phpunit) | Unit | WooPayments | PR, merge_group | Yes | No |
| 2 | [PHPUnit Coverage](#2-phpunit-coverage) | Unit / Coverage | WooPayments | PR | Yes | No |
| 3 | [Jest](#3-jest) | Unit | WooPayments | PR, merge_group | Yes | No |
| 4 | [Playwright E2E (PR)](#4-playwright-e2e--pr) | E2E | WooPayments | PR (develop/trunk) | Yes | No |
| 5 | [Playwright E2E (Scheduled)](#5-playwright-e2e--scheduled) | E2E | WooPayments | Push + every 6 h | No | No |
| 6 | [Playwright E2E (Atomic)](#6-playwright-e2e--atomic) | E2E | WooPayments | Manual | No | No |
| 7 | [Smoke Tests](#7-smoke-tests) | E2E / Release | WooPayments | Release workflow | No | Yes |
| 8 | [QIT E2E (PR)](#8-qit-e2e--pr) | E2E | WooPayments (platform: QualityOps) | PR (develop/trunk) | Yes | No |
| 9 | [QIT E2E (Scheduled)](#9-qit-e2e--scheduled) | E2E | WooPayments (platform: QualityOps) | Push (develop/trunk) | No | No |
| 10 | [QIT E2E (Pre-release)](#10-qit-e2e--pre-release) | E2E | WooPayments (platform: QualityOps) | Manual | No | Yes |
| 11 | [QIT Security & Malware](#11-qit-security--malware) | Security | WooPayments (platform: QualityOps) | PR | Yes | No |
| 12 | [Performance Tests](#12-performance-tests) | Performance | WooPayments | Manual | No | No |
| 13 | [PHPCS](#13-phpcs) | Linting | WooPayments | PR, merge_group | Yes | No |
| 14 | [PHPStan](#14-phpstan) | Static Analysis | WooPayments | PR, merge_group | Yes | No |
| 15 | [ESLint + CSS Lint](#15-eslint--css-lint) | Linting | WooPayments | PR, merge_group | Yes | No |
| 16 | [TypeScript](#16-typescript) | Type Checking | WooPayments | PR, merge_group | Yes | No |
| 17 | [PHP Compatibility](#17-php-compatibility) | Compatibility | WooPayments | PR | Yes | No |
| 18 | [WC/WP Compatibility](#18-wcwp-compatibility) | Compatibility | WooPayments | PR | Partial | No |
| 19 | [Changelog Check](#19-changelog-check) | Process | WooPayments | PR (develop, release/*) | Yes | No |
| 20 | [Bundle Size](#20-bundle-size) | Performance | WooPayments | PR | Yes | No |
| | **Release Testing** | | | | | |
| 21 | [Internal Testing](#21-internal-testing) | Manual QA | Release lead | Week 4 Fri – Week 5 Mon | N/A | Yes |
| 22 | [Apple Pay Testing](#22-apple-pay-testing) | Manual QA | Release lead | Week 4 Thu | N/A | Yes |
| 23 | [Release Branch E2E Re-run](#23-release-branch-e2e-re-run) | Semi-Auto | Release lead | Week 4 Thu | N/A | Yes |
| 24 | [Release Branch QIT Tests](#24-release-branch-qit-tests) | Semi-Auto | Release lead | Week 4 Thu | N/A | Yes |
| | **Release Review & Communication** | | | | | |
| 25 | [Call for Testing](#25-call-for-testing) | Process | Release lead | Week 4 Thu | N/A | Yes |
| 26 | [AI-Assisted Code Review](#26-ai-assisted-code-review) | Review | Release lead | Week 4 Thu | N/A | Informational |

---

## Automated Test Suites

### 1. PHPUnit

| Field | Value |
|-------|-------|
| **Category** | Unit |
| **Files** | ~187 test files |
| **CI Workflow** | `php-lint-test.yml` |
| **Trigger** | `pull_request`, `merge_group` |
| **Required** | Yes |
| **Status** | Active |

**Configuration:**

| Config File | Scope |
|-------------|-------|
| `phpunit.xml.dist` | Main — all tests in `tests/unit/` (two suites: `WCPay` excluding multi-currency, and `WCPayMultiCurrency`) |
| `phpunit-includes.xml.dist` | Coverage for `includes/` directory |
| `phpunit-src.xml.dist` | Coverage for `src/` directory (PSR-4) |

- **PHPUnit version:** 9.6.34
- **Bootstrap:** `tests/unit/bootstrap.php` (loads WP test library, WooCommerce, WooPayments; handles PHP 8+ compat)
- **CI matrix:** PHP 7.4 + latest Gutenberg; includes PHP 7.4 + Gutenberg 22.3.0
- **Max parallel jobs:** 10

**Coverage areas:**

| Area | Test Files | Covers |
|------|-----------|--------|
| Core / root | ~52 | Gateway layer, services, utilities, webhooks, customer management |
| Core / request | ~30 | Request classes, API server, response handling |
| Multi-currency | ~28 | Currency handling, rates, conversions |
| Admin | ~18 | Settings pages, dashboard, order management |
| Subscriptions | ~13 | Subscription creation, processing, lifecycle |
| Migrations | ~8 | Database migrations |
| Notes | ~7 | Order/payment notes |
| WooPay | ~6 | WooPay express checkout |
| Payment methods | ~6 | Card, APM implementations |
| Fraud prevention | ~6 | Fraud detection/prevention rules |
| Express checkout | ~4 | Apple Pay, Google Pay integration |
| API client | ~2 | HTTP layer, retry logic |
| Reports | ~2 | Reporting endpoints |
| Other | ~5 | Emails, in-person payments, duplicate detection, constants |

---

### 2. PHPUnit Coverage

| Field | Value |
|-------|-------|
| **Category** | Unit / Coverage |
| **Files** | Same ~187 test files |
| **CI Workflow** | `coverage.yml` |
| **Trigger** | `pull_request` |
| **Required** | Yes |
| **Status** | Active |

- **CI matrix:** PHP 7.4 + 8.1 x directories `includes` + `src` = 4 combinations
- **Coverage tool:** xdebug (full instrumentation)
- **Script:** `bin/run-ci-tests-check-coverage.bash`

---

### 3. Jest

| Field | Value |
|-------|-------|
| **Category** | Unit |
| **Files** | ~285 test files |
| **CI Workflow** | `js-lint-test.yml` |
| **Trigger** | `pull_request`, `merge_group` |
| **Required** | Yes |
| **Status** | Active |

**Configuration:** `tests/js/jest.config.js`

- **Jest version:** 29.7.0
- **Preset:** `@wordpress/jest-preset-default`
- **Test patterns:** `**/__tests__/**/*.(js|ts|tsx)`, `**/?(*.)(spec|test).(js|ts|tsx)`, `**/test/*.(js|ts|tsx)`
- **Module roots:** `client/`, `includes/multi-currency/client/`
- **Key dependencies:** @testing-library/react 14.3.1, msw 1.3.2, ts-jest 29.2.2

**Coverage areas:**

| Area | Test Files | Covers |
|------|-----------|--------|
| Components | ~57 | Accordion, buttons, chips, selects, modals, forms, notices, tooltips |
| Settings | ~49 | Payment methods config, fraud protection, deposits, notifications |
| Data stores | ~33 | Redux (@wordpress/data) actions, reducers, selectors, resolvers |
| Express checkout | ~27 | Apple Pay, Google Pay, Amazon Pay, tokenized checkout |
| Checkout | ~21 | Blocks checkout, classic checkout, WooPay, UPE styling, 3DS |
| Payment details | ~20 | Payment/dispute details, timeline, refund modal |
| Disputes | ~19 | Evidence submission, validation, UI |
| Utils | ~12 | Sanitize, date-time, card brands, fees, viewport |
| Transactions | ~9 | Transaction list/table |
| Overview | ~7 | Dashboard pages |
| Onboarding | ~7 | KYC, business details, form flow |
| Deposits | ~5 | Deposit list, instant payouts, filters |
| Multi-currency | ~11 | Currency frontend (separate module root) |
| Other | ~8 | VAT, subscriptions edit, documents, hooks, capital, card readers |

---

### 4. Playwright E2E — PR

| Field | Value |
|-------|-------|
| **Category** | E2E |
| **Specs** | 46 spec files |
| **CI Workflow** | `e2e-pull-request.yml` |
| **Trigger** | `pull_request` (develop/trunk), `workflow_dispatch`, `workflow_call` |
| **Required** | Yes |
| **Status** | Active |

**Configuration:** `tests/e2e/playwright.config.ts`

- **Playwright version:** 1.51.1
- **Browser:** Desktop Chrome (chromium) only
- **Viewport:** 1280 x 720
- **Workers:** 1 (sequential)
- **Retries:** 0 (two-pass approach: full suite, then failed specs only)
- **Timeouts:** 120 s per test, 20 s assertions
- **Artifacts:** Screenshots on failure, video on first retry, trace on failure

**CI matrix:** WC L-1 + latest x test groups (wcpay, subscriptions, blocks) x branches (merchant, shopper) = ~10 combinations

**Spec files by directory:**

| Directory | Count | Covers |
|-----------|-------|--------|
| `wcpay/merchant/` | 19 | Account balance, deposits, disputes, transactions, refunds, manual capture, multi-currency, WooPay setup |
| `wcpay/shopper/` | 18 | Checkouts (standard, site-editor, blocks), payment methods (Alipay, Klarna, BNPLS), saved cards, coupons, UPE |
| `subscriptions/merchant/` | 3 | Renewal (manual + Action Scheduler), settings |
| `subscriptions/shopper/` | 5 | Purchases (free trial, sign-up fee, no fee, multiple), manage payments |
| `performance/` | 1 | Payment method rendering performance |
| Root (setup) | 2 | Auth setup (3 roles), basic sanity (home, admin, my-account) |

---

### 5. Playwright E2E — Scheduled

| Field | Value |
|-------|-------|
| **Category** | E2E |
| **Specs** | Same 46 spec files |
| **CI Workflow** | `e2e-test.yml` |
| **Trigger** | Cron every 6 h (`0 */6 * * *`), `push` (develop/trunk), `workflow_dispatch` |
| **Required** | No |
| **Status** | Active |

**CI matrix (extended):**

| WooCommerce Version | PHP | WordPress | Notes |
|---------------------|-----|-----------|-------|
| 7.7.0 (legacy) | 7.4 | latest | Blocks tests excluded |
| L-1 | 8.3 | latest | |
| latest | 8.3 | latest | |
| beta (if available) | 8.3 | latest | |
| RC (if available) | 8.4 | latest | |
| latest | 8.3 | nightly | WP nightly |

Broader version matrix than the PR workflow. Catches compatibility issues before they reach PRs.

---

### 6. Playwright E2E — Atomic

| Field | Value |
|-------|-------|
| **Category** | E2E |
| **Specs** | Subset (wcpay shopper only) |
| **CI Workflow** | `e2e-tests-atomic.yml` |
| **Trigger** | `workflow_dispatch` (manual) |
| **Required** | No |
| **Status** | Active |

Tests against WordPress.com Atomic hosting environment. Sequential execution (max parallel: 1). Uses Atomic-specific secrets and setup action.

---

### 7. Smoke Tests

| Field | Value |
|-------|-------|
| **Category** | E2E / Release |
| **Specs** | E2E subset on built zip artifact |
| **CI Workflow** | `build-zip-and-run-smoke-tests.yml` |
| **Trigger** | Release workflow (`workflow_call`), `workflow_dispatch` |
| **Required** | No (support workflow) |
| **Status** | Active |
| **Gates release** | Yes |

Builds the plugin distribution zip, then runs E2E tests against the packaged artifact (not source). Uses the same E2E infrastructure via `e2e-pull-request.yml` as a reusable workflow with `wcpay-use-build-artifact: true`.

**CI matrix:** WC 7.7.0 (PHP 7.4), L-1, latest, optional RC/beta.

---

### 8. QIT E2E — PR

| Field | Value |
|-------|-------|
| **Category** | E2E |
| **Specs** | 46 specs (mirrored to QIT format) |
| **CI Workflow** | `qit-e2e-pr.yml` → `qit-e2e-run.yml` |
| **Trigger** | `pull_request` (develop/trunk), `workflow_dispatch` |
| **Required** | Yes |
| **Status** | Active |

**Configuration:** `tests/qit/test-package/playwright.config.js`

- **Projects:** default, shopper, merchant, subscriptions
- **Reporters:** CTRF, JSON, Blob, HTML, Allure
- **Retries:** 1 on CI only
- **Base URL:** `QIT_SITE_URL` or `http://localhost:8080`
- **CI matrix:** WC L-1 + latest x 3 projects (shopper, merchant, subscriptions) = 6 combinations

QIT (Quality Insights Testing) runs tests on a WordPress.com cloud environment, validating the plugin in a more production-like setup.

---

### 9. QIT E2E — Scheduled

| Field | Value |
|-------|-------|
| **Category** | E2E |
| **Specs** | Same QIT specs |
| **CI Workflow** | `qit-e2e.yml` |
| **Trigger** | `push` (develop/trunk), `workflow_dispatch` |
| **Required** | No |
| **Status** | Active |

Extended matrix including WC L-1, latest, nightly, beta, RC, plus WP nightly and PHP 8.4. ~20+ combinations.

---

### 10. QIT E2E — Pre-release

| Field | Value |
|-------|-------|
| **Category** | E2E |
| **Specs** | Same QIT specs |
| **CI Workflow** | `qit-e2e-prerelease.yml` |
| **Trigger** | `workflow_dispatch` (manual, requires `woocommerce-version` input) |
| **Required** | No |
| **Status** | Active |
| **Gates release** | Yes (run during WooCommerce pre-release cycle) |

Used to test against pre-release/beta WC versions (e.g., `10.5.0-dev`, `9.7.0-beta.1`). 3 combinations (shopper, merchant, subscriptions).

---

### 11. QIT Security & Malware

| Field | Value |
|-------|-------|
| **Category** | Security |
| **CI Workflow** | `qit.yml` |
| **Trigger** | `pull_request` |
| **Required** | Yes |
| **Status** | Active |

Runs two scans:

- `qit run:security` — security vulnerability scanning
- `qit run:malware` — malware detection

Exit codes: 0 = pass, 1 = fail, 2 = warning only (warnings do NOT fail CI).

---

### 12. Performance Tests

| Field | Value |
|-------|-------|
| **Category** | Performance |
| **Specs** | 1 spec file (`tests/e2e/specs/performance/payment-methods.spec.ts`) |
| **Config** | `tests/e2e/playwright.performance.config.ts` |
| **Trigger** | Manual (`npm run test:e2e-performance`) |
| **Required** | No |
| **Status** | Active |

Visual diff-based performance testing for payment method rendering. Retries: 2 on CI. Tolerance: 3.5% for WC 7.7.0, 2.5% for WC 7.8+.

---

## Static Analysis & Linting

### 13. PHPCS

| Field | Value |
|-------|-------|
| **Category** | Linting |
| **CI Workflow** | `php-lint-test.yml` (lint job) |
| **Trigger** | `pull_request`, `merge_group` |
| **Required** | Yes |
| **Status** | Active |

- **Config:** `phpcs.xml.dist`
- **Standards:** WordPress coding standards, WooCommerce sniffs, Slevomat coding standard
- **Includes:** PHP compatibility sniffs (`phpcs-compat.xml.dist` for compat-only checks)

---

### 14. PHPStan

| Field | Value |
|-------|-------|
| **Category** | Static Analysis |
| **CI Workflow** | `php-lint-test.yml` (lint job) |
| **Trigger** | `pull_request`, `merge_group` |
| **Required** | Yes |
| **Status** | Active |

---

### 15. ESLint + CSS Lint

| Field | Value |
|-------|-------|
| **Category** | Linting |
| **CI Workflow** | `js-lint-test.yml` (lint job) |
| **Trigger** | `pull_request`, `merge_group` |
| **Required** | Yes |
| **Status** | Active |

- **ESLint version:** 7.30.0 with TypeScript, React, a11y, and Calypso plugins
- **Stylelint version:** 13.11.0 with WordPress config

---

### 16. TypeScript

| Field | Value |
|-------|-------|
| **Category** | Type Checking |
| **CI Workflow** | `js-lint-test.yml` (lint job, via `lint:js`) |
| **Trigger** | `pull_request`, `merge_group` |
| **Required** | Yes |
| **Status** | Active |

- **TypeScript version:** 4.5.2
- **Config:** `tsconfig.json`

---

### 17. PHP Compatibility

| Field | Value |
|-------|-------|
| **Category** | Compatibility |
| **CI Workflow** | `php-compatibility.yml` |
| **Trigger** | `pull_request` |
| **Required** | Yes |
| **Status** | Active |

Validates PHP version compatibility using `bin/phpcs-compat.sh`.

---

### 18. WC/WP Compatibility

| Field | Value |
|-------|-------|
| **Category** | Compatibility |
| **CI Workflow** | `compatibility.yml` |
| **Trigger** | `pull_request` |
| **Required** | Partial (main matrix required, beta allowed to fail) |
| **Status** | Active |

**Main matrix (required):**

| WooCommerce | WordPress | Gutenberg | PHP |
|-------------|-----------|-----------|-----|
| 7.6.0 (min) | latest | latest | 7.4 |
| latest | latest | latest | 7.4 |
| 7.6.0 | 6.0 | 13.6.0 | 7.4 |

**Beta matrix (allowed to fail):** WC beta x PHP 7.4, 8.0, 8.1.

---

### 19. Changelog Check

| Field | Value |
|-------|-------|
| **Category** | Process |
| **CI Workflow** | `check-changelog.yml` |
| **Trigger** | `pull_request` (develop, release/*) — excludes `.github/**` |
| **Required** | Yes |
| **Status** | Active |

Validates a changelog entry exists for every PR. Uses `bin/check-changelog.sh`.

---

### 20. Bundle Size

| Field | Value |
|-------|-------|
| **Category** | Performance |
| **CI Workflow** | `bundle-size.yml` |
| **Trigger** | `pull_request`, `workflow_dispatch` |
| **Required** | Yes |
| **Status** | Active |

Monitors JS/CSS bundle sizes using `preactjs/compressed-size-action`. Tracks `release/**/*.js` and `release/**/*.css` (excludes vendor).

---

## Release Process Activities

These activities occur during the release cycle (5-week cadence). Code freeze is Week 4 Wednesday.

### Release Testing

### 21. Internal Testing

| Field | Value |
|-------|-------|
| **Category** | Manual QA |
| **Trigger** | Week 4 Friday – Week 5 Monday (2-day window) |
| **DRI** | Release lead + team |
| **Gates release** | Yes |

Team members manually test the release candidate against a checklist covering key merchant and shopper flows. Bugs found are triaged and either fixed or deferred.

---

### 22. Apple Pay Testing

| Field | Value |
|-------|-------|
| **Category** | Manual QA |
| **Trigger** | Week 4 Thursday |
| **DRI** | Release lead |
| **Gates release** | Yes |

Manual verification of Apple Pay flows on real devices. Cannot be fully automated due to Apple's device/biometric requirements.

---

### 23. Release Branch E2E Re-run

| Field | Value |
|-------|-------|
| **Category** | Semi-Automated |
| **Trigger** | Week 4 Thursday |
| **DRI** | Release lead |
| **Gates release** | Yes |

Full E2E suite re-run against the release branch to catch any regressions introduced by bug fixes during the testing window.

---

### 24. Release Branch QIT Tests

| Field | Value |
|-------|-------|
| **Category** | Semi-Automated |
| **Trigger** | Week 4 Thursday |
| **DRI** | Release lead |
| **Gates release** | Yes |

QIT E2E and security tests run against the release branch. Uses `qit-e2e-prerelease.yml` workflow.

---

### Release Review & Communication

### 25. Call for Testing

| Field | Value |
|-------|-------|
| **Category** | Process |
| **Trigger** | Week 4 Thursday (P2 post) |
| **DRI** | Release lead |
| **Gates release** | Yes |

P2 post inviting broader team to test the release candidate. Includes a summary of changes and areas to focus on.

---

### 26. AI-Assisted Code Review

| Field | Value |
|-------|-------|
| **Category** | Review |
| **Trigger** | Week 4 Thursday |
| **DRI** | Release lead |
| **Gates release** | Informational only |

AI reviews the diff between the previous release and the release candidate, flagging potential issues. Results are reviewed but do not block the release.

---

## Coverage Matrix

Which product areas are covered by which test suites.

| Product Area | PHPUnit | Jest | E2E (Playwright) | QIT E2E | QIT Security | Manual QA |
|-------------|:---:|:---:|:---:|:---:|:---:|:---:|
| **Checkout (classic)** | ✅ | ✅ | ✅ | ✅ | | ✅ |
| **Checkout (blocks)** | ✅ | ✅ | ✅ | ✅ | | ✅ |
| **Payment methods (card)** | ✅ | ✅ | ✅ | ✅ | | ✅ |
| **Payment methods (APMs)** | ✅ | ✅ | ✅ | ✅ | | ✅ |
| **Express checkout (Apple/Google Pay)** | ✅ | ✅ | ✅ | | | ✅ |
| **Saved cards / tokenization** | ✅ | ✅ | ✅ | ✅ | | ✅ |
| **Subscriptions** | ✅ | | ✅ | ✅ | | ✅ |
| **Multi-currency** | ✅ | ✅ | ✅ | ✅ | | ✅ |
| **Disputes** | ✅ | ✅ | ✅ | ✅ | | ✅ |
| **Refunds** | ✅ | ✅ | ✅ | ✅ | | ✅ |
| **Manual capture** | ✅ | | ✅ | ✅ | | ✅ |
| **Deposits / payouts** | ✅ | ✅ | ✅ | ✅ | | ✅ |
| **Transactions list** | ✅ | ✅ | ✅ | ✅ | | |
| **Fraud prevention** | ✅ | ✅ | | | | |
| **Settings / configuration** | ✅ | ✅ | ✅ | ✅ | | ✅ |
| **Onboarding / KYC** | ✅ | ✅ | | ✅ (MC only) | | ✅ |
| **Admin dashboard / overview** | ✅ | ✅ | ✅ | ✅ | | |
| **WooPay** | ✅ | ✅ | ✅ | ✅ (setup) | | ✅ |
| **In-person payments** | ✅ | | | | | ✅ |
| **Emails / notifications** | ✅ | | | | | |
| **Migrations** | ✅ | | | | | |
| **API client / HTTP layer** | ✅ | | | | | |
| **DI container / services** | ✅ | | | | | |
| **Security vulnerabilities** | | | | | ✅ | |
| **Malware** | | | | | ✅ | |
| **Bundle size / performance** | | | ✅ (1 spec) | | | |
| **Apple Pay (real device)** | | | | | | ✅ |

---

## Reliability Notes

### E2E CI Snapshot (as of 2026-03-20)

Based on **scheduled** workflow runs only. PR-triggered workflows are excluded because frequent cancellations from new commits skew the pass/fail ratios.

#### Playwright E2E — Scheduled (`e2e-test.yml`)

Last 20 runs: **9 passed**, 11 failed. Three distinct failure patterns:

| Failing Test | Frequency | Error | WC/WP Versions Affected |
|-------------|-----------|-------|------------------------|
| `shopper-myaccount-payment-methods-add-fail.spec.ts` — "it should not add the card" | Recurring | Assertion failure | WC 9.9.7 |
| `shopper-myaccount-saved-cards.spec.ts` — "prevents adding another card" | Recurring | Assertion failure | WC 9.9.7 |
| `merchant-disputes-respond.spec.ts` — "saved values are restored" | Recurring | Assertion failure at line 684 | WP nightly + WC latest |
| WC Blocks shopper specs (checkout-failures, checkout-purchase, saved-card-checkout) | 2 runs | Multiple specs failing together | **WP nightly only** |

**WP nightly blocks regression:** Two consecutive scheduled runs show all WC Blocks shopper specs failing on WP nightly — `checkout-failures`, `checkout-purchase`, and `saved-card-checkout` all fail. This suggests a breaking change in WordPress nightly affecting the blocks checkout integration. Stable WP versions are unaffected.

#### QIT E2E — Scheduled (`qit-e2e.yml`)

Baseline (March 1–9): **14 passed**, 6 failed. Two issues have since degraded the suite:

1. **March 10+:** Merchant tests fail on WC nightly only — a WC nightly-specific issue unrelated to WooPayments code changes.
2. **March 18+:** Shopper tests fail across all WC versions after async renderer E2E tests were added (#11447). These tests are incompatible with the QIT environment and have been skipped (#11467) while the fix is investigated.

The March 1–9 baseline reflects the suite's actual reliability excluding these two known issues.

---

### E2E Test Status (Code-Level)

#### Skipped / Incomplete Tests

| Test | Location | Issue |
|------|----------|-------|
| Alipay Checkout (QIT) | `tests/qit/.../alipay-checkout-purchase.spec.ts` | Entire suite skipped — Alipay sandbox unavailable in QIT environment |
| Account Balance | `tests/e2e/.../merchant-admin-account-balance.spec.ts` | Tagged `@todo` — step implementations are empty placeholders ([#9188](https://github.com/Automattic/woocommerce-payments/issues/9188)) |
| WC 7.7.0 + Subscriptions | CI matrix (`e2e-test.yml`) | Disabled — fatal error with `WC_Site_Tracking` class |
| Multi-currency Storefront tests (QIT) | `tests/qit/.../multi-currency-on-boarding.spec.ts` | Dynamically skipped when Storefront theme unavailable |

#### Disabled Visual Regression Assertions

Multiple specs have screenshot assertions commented out due to flakiness. These tests still run their functional assertions but skip visual comparisons.

| Spec | Disabled Assertions |
|------|-------------------|
| `multi-currency-on-boarding.spec.ts` | 3 screenshots (E2E + QIT) |
| `multi-currency.spec.ts` | 1 screenshot (E2E + QIT) |
| `merchant-admin-transactions.spec.ts` | 1 screenshot (E2E + QIT) |
| `merchant-admin-deposits.spec.ts` | 1 screenshot (E2E + QIT) |
| `merchant-orders-full-refund.spec.ts` | 3 screenshots (E2E) |
| `merchant-disputes-view-details-via-order-notice.spec.ts` | 1 screenshot (E2E + QIT) |
| `merchant-admin-analytics.spec.ts` | 1 screenshot (QIT only) |

#### CI-Level Test Exclusions

- **Performance specs** are excluded from all CI runs (`testIgnore: /specs\/performance/`)
- **Blocks tests** only run when `E2E_GROUP=blocks` (filtered by `@blocks` tag)
- **Subscriptions/Action Scheduler tests** are skipped when running blocks group
- **Blocks merchant tests** excluded from matrix — no merchant blocks tests exist

### General Considerations

- **E2E sequential execution:** All Playwright tests run with `workers: 1` because some tests have dependencies (e.g., save card → pay → delete card). This makes the suite slower but more deterministic.

- **E2E two-pass retry strategy:** Instead of Playwright's built-in retries, the CI uses a two-pass approach: run all specs, then re-run only failed specs. This avoids masking flaky tests while still recovering from transient failures.

- **Compatibility matrix beta tolerance:** The WC/WP compatibility workflow allows beta matrix combinations to fail without blocking PRs. This prevents unreleased WooCommerce changes from blocking WooPayments development.

- **Performance tests are manual-only:** The single performance spec (`payment-methods.spec.ts`) is not part of any automated CI workflow. It must be triggered manually via `npm run test:e2e-performance`.

- **Apple Pay cannot be automated:** Apple Pay requires real device biometric authentication, making it impossible to include in automated E2E suites. This remains a manual testing requirement for every release.
