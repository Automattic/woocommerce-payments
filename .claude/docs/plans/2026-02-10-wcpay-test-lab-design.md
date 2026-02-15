# WCPay Test Lab — Design Document

**Date:** 2026-02-10
**Status:** Draft — pending approval
**Scope:** New sub-page within WCPay Dev Tools plugin for controlling test account state

---

## 1. Problem

WooPayments developers need to quickly populate test Stripe accounts with realistic data (charges, refunds, disputes, payouts) to test UI scenarios. Today this requires manual checkout flows, direct Stripe API calls, or fragmented scripts. There is no unified, efficient way to set up specific account states.

Key constraint: WooPayments charges exist within the context of WooCommerce orders. Creating Stripe charges without matching WC orders leads to data inconsistencies and unreliable UI. Data must flow through the full WC payment processing pipeline.

---

## 2. Solution

**"Test Lab"** — a new React-based sub-page within the WCPay Dev Tools plugin that provides:

- One-click **experiments** (presets) that set up common test scenarios
- Individual **operations** for fine-grained control
- **WP-CLI commands** that mirror every UI operation (for terminal and AI agent use)
- Full **checkout simulation** that creates real WC orders processed through WooPayments
- Direct **Stripe API** operations for post-checkout actions (payouts, disputes)
- **Payment method management** using WooPayments' own settings logic
- **Environment-aware guardrails** that prevent accidents on non-dev environments

---

## 3. Architecture

### 3.1 Single Engine, Multiple Entry Points

```
+-- WCPay Dev Tools Plugin -----------------------------------------+
|                                                                    |
|  +-- Test Lab React App ----------------------------------------+ |
|  |  Gutenberg components, registered as WP admin sub-page        | |
|  |  Calls PHP REST endpoints via wp.apiFetch                     | |
|  +------------------------------+-------------------------------+  |
|                                 |                                  |
|  +-- REST API ------------------+-------------------------------+  |
|  |  /wp-json/wcpay-dev/v1/test-lab/*                             | |
|  |  Thin controllers, delegates to operations                    | |
|  +------------------------------+-------------------------------+  |
|                                 |                                  |
|  +-- Operations Layer -----------+------------------------------+  |
|  |  Stripe PHP SDK    <-> Stripe API                             | |
|  |  WC Checkout       <-> WooPayments gateway                    | |
|  |  WC Smooth Gen     <-> Products/Customers                     | |
|  |  WCPay Internals   <-> Settings, capabilities, account cache  | |
|  +--------------------------------------------------------------+  |
|                                                                    |
|  +-- WP-CLI Commands -------------------------------------------+  |
|  |  wp wcpay-dev test-lab <operation> [--args]                   | |
|  |  Same operations layer, different entry point                 | |
|  +--------------------------------------------------------------+  |
+--------------------------------------------------------------------+
```

### 3.2 Four Integration Points

| Layer | What It Talks To | Used For |
|-------|-----------------|----------|
| Stripe PHP SDK | Stripe API directly | Payouts, account state, balance, external accounts |
| WC Checkout Simulator | WooPayments `process_payment()` | Orders with real Stripe charges |
| WC Smooth Generator | WC product/customer APIs | Catalog setup (products, customers) |
| WooPayments Internals | Settings, capabilities, account cache | Payment method toggles, mode detection |

### 3.3 Key Principles

**For anything WooPayments already has logic for, call that logic — don't reimplement it.** Direct Stripe calls are only for operations WooPayments doesn't handle (manual payouts, dispute creation via test cards, etc.).

**Tag all generated artifacts.** Every entity created by Test Lab (products, orders, customers, refunds) must carry metadata identifying it as Test Lab-generated. This enables reliable programmatic cleanup and prevents confusion with manually created data. See section 5.6 for details.

---

## 4. Dependencies

| Dependency | Type | Purpose |
|-----------|------|---------|
| `stripe/stripe-php` | Composer (new) | Direct Stripe API for payouts, disputes, account state |
| `woocommerce/wc-smooth-generator` | Auto-installed on demand | Product and customer generation |
| `@wordpress/components` | Already in WP | Gutenberg UI components |
| `@wordpress/api-fetch` | Already in WP | REST API client |
| `@wordpress/scripts` | Dev dependency | Build tooling for React app |

---

## 5. Operations

### 5.1 Catalog Setup (via WC Smooth Generator)

| Operation | Description | CLI |
|-----------|-------------|-----|
| Stock the lab | Create predefined product set (simple $25, variable $40-80, high-value $500) | `wp wcpay-dev test-lab stock` |
| Add customers | Generate N test customers with realistic data | `wp wcpay-dev test-lab customers --count=10` |

WC Smooth Generator auto-install: one-click download from GitHub releases + activate. UI shows install prompt if not present.

**Overwrite vs append behavior:** When stocking the lab, if Test Lab products already exist (detected via `_wcpay_test_lab` meta), the UI asks whether to:
- **Replace** — delete existing Test Lab products and create fresh ones
- **Add alongside** — keep existing and add more

CLI: `wp wcpay-dev test-lab stock` (default: asks interactively), `wp wcpay-dev test-lab stock --replace`, `wp wcpay-dev test-lab stock --append`.

### 5.2 Order + Payment Generation (Checkout Simulator)

| Operation | Description | Test Card | CLI |
|-----------|-------------|-----------|-----|
| Successful charges | N orders through WooPayments | `4242 4242 4242 4242` | `wp wcpay-dev test-lab charges --count=10` |
| Instant-balance charges | Funds bypass pending | `4000 0000 0000 0077` | `wp wcpay-dev test-lab charges --count=5 --instant` |
| Failed charges | Declined card | `4000 0000 0000 0002` | `wp wcpay-dev test-lab charges --count=3 --fail` |
| 3DS charges | Requires authentication | `4000 0000 0000 3220` | `wp wcpay-dev test-lab charges --count=2 --3ds` |
| Dispute charges | Auto-generates dispute | `4000 0000 0000 0259` | `wp wcpay-dev test-lab disputes --count=2` |

### 5.3 Post-Checkout Stripe Operations (Stripe PHP SDK)

| Operation | Description | CLI |
|-----------|-------------|-----|
| Create refunds | Full or partial on existing orders | `wp wcpay-dev test-lab refunds --count=3` |
| Create payouts | Manual payout from available balance | `wp wcpay-dev test-lab payouts --count=1` |
| Fail a payout | Swap external account to failure token, trigger payout | `wp wcpay-dev test-lab payouts --fail` |
| Set payout schedule | Change to manual/daily/weekly/monthly | `wp wcpay-dev test-lab payout-schedule --interval=manual` |

### 5.4 Payment Method Management (via WooPayments Internals)

| Operation | Description | CLI |
|-----------|-------------|-----|
| List payment methods | Show available methods and their status | `wp wcpay-dev test-lab payment-methods list` |
| Enable method | Enable a payment method (full WooPayments pipeline) | `wp wcpay-dev test-lab payment-methods enable --method=sepa_debit` |
| Disable method | Disable a payment method | `wp wcpay-dev test-lab payment-methods disable --method=bancontact` |

Uses WooPayments' own settings update logic to ensure WC gateway settings, Stripe capability requests, and account cache are all synchronized.

### 5.5 Experiments (Presets)

| Experiment | Recipe |
|-----------|--------|
| "The Basics" | Stock lab + 10 customers + 20 charges + 2 refunds |
| "Dispute Season" | The Basics + 5 disputes in various states |
| "Payday" | The Basics (instant balance) + 3 successful payouts |
| "Everything's on Fire" | Charges + disputes + failed payouts + payout block |
| "Clean Slate" | Cancel pending orders, delete test orders (via WooPayments' existing `delete_test_orders()`), clear transients |

Each experiment shows its full recipe and expected outcome before running.

### 5.6 Artifact Tagging

Every entity created by Test Lab carries metadata so it can be reliably identified for cleanup, filtering, and reset operations. This prevents confusion with manually created data and enables surgical cleanup.

**Meta key:** `_wcpay_test_lab` (present = created by Test Lab)

**Meta value:** JSON-encoded object with provenance:

```json
{
  "created_by": "test-lab",
  "created_at": "2026-02-10T14:32:00Z",
  "operation": "charges",
  "experiment": "the-basics"
}
```

The `operation` field records which operation created it (e.g., `stock`, `customers`, `charges`, `refunds`, `disputes`). The `experiment` field is set when the artifact was created as part of an experiment preset (null for individual operations).

**Tagging per entity type:**

| Entity | Meta Key | How Applied |
|--------|----------|-------------|
| Products | `_wcpay_test_lab` post meta | Set after Smooth Generator creates the product |
| Customers (WP users) | `_wcpay_test_lab` user meta | Set after Smooth Generator creates the customer |
| Orders | `_wcpay_test_lab` order meta | Set on the WC_Order before `process_payment()` |
| Refunds | `_wcpay_test_lab` on the refund order | Set when creating the refund |

**Usage in operations:**

- `get_random_lab_product()` queries products WITH `_wcpay_test_lab` meta — only uses Test Lab products for checkout simulation
- `get_random_lab_customer()` queries users WITH `_wcpay_test_lab` meta
- **Clean Slate** deletes all entities with `_wcpay_test_lab` meta (products, customers, orders) in addition to calling WooPayments' `delete_test_orders()` for any non-tagged test orders
- **Status bar** shows counts of Test Lab artifacts: "6 products, 10 customers, 45 orders"

**CLI support:**

```bash
# List all Test Lab artifacts
wp wcpay-dev test-lab artifacts list

# Clean only Test Lab products
wp wcpay-dev test-lab artifacts clean --type=products

# Clean everything Test Lab created
wp wcpay-dev test-lab clean
```

---

## 6. Checkout Simulator Design

### 6.1 Approach

Create WC orders manually, then call `WC_Payment_Gateway_WCPay::process_payment()` directly. This skips the HTTP-dependent parts of `WC_Checkout::process_checkout()` while still going through the real WooPayments payment gateway.

### 6.2 Flow

```php
// 1. Pick a random product and customer
$product = $this->get_random_lab_product();
$customer = $this->get_random_lab_customer();

// 2. Create order with line items
$order = wc_create_order(['customer_id' => $customer->get_id()]);
$order->add_product($product, random_int(1, 3));
$order->set_billing_address($customer->get_billing());
$order->set_payment_method('woocommerce_payments');
$order->calculate_totals();
$order->save();

// 3. Set up payment method context (test card token)
$_POST['wcpay-payment-method'] = 'pm_card_visa'; // or pm_card_bypassPending, etc.

// 4. Process payment through WooPayments
$gateway = WC()->payment_gateways()->get_available_payment_gateways()['woocommerce_payments'];
$result = $gateway->process_payment($order->get_id());

// 5. Order now has: _wcpay_mode, _intent_id, _charge_id, correct status
```

### 6.3 Implementation Note

If WooPayments' `process_payment()` requires a Stripe PaymentMethod created through frontend Stripe Elements (rather than accepting a token via `$_POST`), we'll create the PaymentMethod via the Stripe PHP SDK first and pass it in. The architecture supports either approach.

### 6.4 What This Ensures

- WC order and Stripe charge are linked via WooPayments metadata (`_wcpay_intent_id`, `_wcpay_charge_id`, `_wcpay_mode`)
- Order status reflects payment outcome (processing, failed, etc.)
- WooPayments' hooks and filters fire normally
- Analytics, reports, and admin views see consistent data

---

## 7. UI Layout

### 7.1 Page Structure

```
+---------------------------------------------------------------+
|  (flask icon) Test Lab                                         |
|  "Welcome to the laboratory!"                                  |
+---------------------------------------------------------------+
|                                                                |
|  +-- Account Status (always visible) -----------------------+  |
|  | Stripe: acct_xxx      Balance: $450 available             |  |
|  | Charges: (checkmark) enabled   Payouts: (checkmark) enabled|  |
|  | Schedule: Manual      Products: 6 stocked                 |  |
|  | Mode: Dev Mode        Account: Test Drive (Custom)        |  |
|  +----------------------------------------------------------+  |
|                                                                |
|  +-- Quick Experiments ---------------------------------------+ |
|  | [ The Basics ]  [ Dispute Season ]  [ Payday ]             | |
|  | [ Everything's on Fire ]  [ Clean Slate ]                  | |
|  |                                                             | |
|  | Each shows recipe + expected outcome on hover/expand        | |
|  +------------------------------------------------------------+ |
|                                                                |
|  +-- Individual Operations (collapsible panels) --------------+ |
|  | > Lab Supplies (products and customers)                     | |
|  | > Charges and Orders                                        | |
|  | > Refunds                                                   | |
|  | > Disputes                                                  | |
|  | > Payouts                                                   | |
|  | > Payment Methods                                           | |
|  | > Account Settings                                          | |
|  +------------------------------------------------------------+ |
|                                                                |
|  +-- Activity Log (bottom) ----------------------------------+  |
|  | 14:32 Created 10 orders (#1041-#1050)                      |  |
|  | 14:32 "What a fine experiment!"                             |  |
|  | 14:31 Stocked lab with 6 products                          |  |
|  | 14:30 Generated 5 customers                                |  |
|  +-----------------------------------------------------------+  |
+---------------------------------------------------------------+
```

### 7.2 UI Principles

- **Self-documenting:** Every control has titles, descriptions, and/or tooltips explaining what it does, why, and what to expect. No external documentation needed except tutorials.
- **Progressive disclosure:** Experiments are prominent (most common action). Individual operations are in collapsible panels.
- **Status always visible:** Account state, balance, mode, and capabilities shown at top so developers always know where they stand.
- **Activity log:** Shows what happened, with links to created orders/charges. Running log of actions taken.

### 7.3 Inline Documentation Example

Each operation card includes contextual explanation:

```
+---------------------------------------------------------------+
|  Successful Charges                                            |
|  Create WooCommerce orders processed through WooPayments       |
|  using test card 4242 4242 4242 4242. Each order picks a       |
|  random product from your lab supplies and generates a real     |
|  Stripe charge.                                                |
|                                                                |
|  Count: [10]                                                   |
|                                                                |
|  [ ] Instant balance                                           |
|    Uses card 4000 0000 0000 0077 -- funds go directly to       |
|    available balance, skipping the pending period.              |
|    Required before creating payouts.                           |
|                                                                |
|                                  [ Run Experiment (flask) ]    |
+---------------------------------------------------------------+
```

### 7.4 Dexter's Laboratory Theme

Subtle, functional, earns a smile without slowing anyone down:

- **Page title:** "Test Lab" with a flask icon
- **Empty state:** "My laboratory is empty! Let's fix that."
- **Destructive confirmations:** "DEE DEE! Don't touch that!" style warnings
- **Presets framed as:** "Experiments"
- **Success toasts:** "What a fine experiment!" / "Excellent!"
- **Product catalog setup:** "Stocking the lab supplies..."
- **Progress messages:** "Science requires patience..."

---

## 8. Guardrails

### 8.1 Three-Tier Protection Model

**Tier 1: Environment Gate (hard block)**

On page load, check `wp_get_environment_type()`. If not `development` or `staging`, block all operations:

> "Test Lab is designed for development environments. Current environment: `production`. All operations are disabled."

This prevents the "accidentally left plugin active on production" scenario.

**Tier 2: Mode + Account Type Awareness**

| State | Behavior |
|-------|----------|
| Dev mode + Test Drive (Custom) | Full access, no warnings. Happy path. |
| Dev mode + Sandbox (Express) | Full access, notes on limited operations (payout schedule, external accounts). |
| Dev mode + Live account | Warning banner. Bulk operations require confirmation. |
| Test mode (no dev mode) | Allowed with caution banner. |
| Live mode + Live account | Hard block on all mutating operations. |

**Tier 3: Account Type Capability Gating**

Express (Sandbox) accounts have post-onboarding restrictions:

| Operation | Test Drive (Custom) | Sandbox (Express) |
|-----------|-------------------|-------------------|
| Create payouts | Full control | Works if manual schedule |
| Set payout schedule | Direct API | May be restricted |
| Swap external accounts | Anytime | Blocked post-onboarding |
| Fail a payout (token swap) | Works | Disabled with explanation |

Disabled operations show inline explanation and suggest switching to a Test Drive account.

### 8.2 CLI Guardrails

```bash
# Test mode -- works normally
wp wcpay-dev test-lab charges --count=10

# Live mode -- refuses with explanation
wp wcpay-dev test-lab charges --count=10
# Error: LIVE MODE DETECTED (acct_live_xxx)
# Bulk charge creation is disabled on live accounts.

# View operations always work
wp wcpay-dev test-lab status

# Live mode single payout with explicit acknowledgment
wp wcpay-dev test-lab payouts --count=1 --live-mode-acknowledged
# Warning: Creating 1 payout on LIVE account acct_live_xxx
# Type "yes" to confirm:
```

### 8.3 Mode Detection

Uses WooPayments' own mode system (`WC_Payments::mode()`):

- `is_dev()` — environment-level forced test mode
- `is_test_mode_onboarding()` — onboarded in test mode
- `is_test()` — payment processing in test mode

Account type from cached account data:
- `is_live` + `is_test_drive` flags determine Live / Test Drive / Sandbox

### 8.4 WooPayments Mode Reference

```
dev_mode = true (wp-env default)
  +-> test_mode_onboarding = true (forced)
       +-> test_mode = true (forced)
```

Three account types:
- **Live** — Full production Stripe account (Express)
- **Test Drive** — Quick-start demo (Custom, no KYC, ~40s to create)
- **Sandbox** — Proper test account with KYC (Express, in test mode)

---

## 9. File Structure

```
woocommerce-payments-dev-tools/
|-- woocommerce-payments-dev-tools.php          # Existing main plugin file
|-- woocommerce-payments-dev-shortcuts.php      # Existing admin bar
|-- cli-commands.php                            # Existing CLI
|-- composer.json                               # Add stripe/stripe-php
|
|-- test-lab/                                   # NEW -- all Test Lab code
|   |-- class-test-lab.php                      # Bootstrap: registers page, REST, CLI
|   |
|   |-- operations/                             # Core operations library
|   |   |-- class-stripe-client.php             # Stripe PHP SDK wrapper (key management)
|   |   |-- class-environment.php               # Mode detection, account type, guardrails
|   |   |-- class-catalog-setup.php             # Product/customer creation via Smooth Generator
|   |   |-- class-checkout-simulator.php        # WC order + process_payment() flow
|   |   |-- class-charge-operations.php         # Charge variations (success, fail, 3DS, dispute)
|   |   |-- class-refund-operations.php         # Full/partial refunds on existing orders
|   |   |-- class-dispute-operations.php        # Dispute creation via test cards
|   |   |-- class-payout-operations.php         # Manual payouts, failure simulation
|   |   |-- class-account-operations.php        # Payout schedule, account state
|   |   |-- class-payment-method-operations.php # Enable/disable via WooPayments logic
|   |   |-- class-experiment-runner.php         # Preset recipes
|   |   +-- class-clean-slate.php              # Reset/cleanup using WCPay's delete_test_orders()
|   |
|   |-- rest-api/                               # REST endpoints for React UI
|   |   |-- class-status-controller.php         # GET environment, balance, account info
|   |   |-- class-operations-controller.php     # POST run individual operations
|   |   +-- class-experiments-controller.php    # POST run preset experiments
|   |
|   |-- cli/                                    # WP-CLI commands
|   |   +-- class-test-lab-command.php          # wp wcpay-dev test-lab <subcommand>
|   |
|   +-- admin/                                  # React app
|       |-- src/
|       |   |-- index.tsx                       # Entry point
|       |   |-- components/
|       |   |   |-- StatusBar.tsx               # Account/environment status
|       |   |   |-- ExperimentCards.tsx          # Preset experiment buttons
|       |   |   |-- OperationPanel.tsx           # Collapsible operation sections
|       |   |   |-- PaymentMethodToggles.tsx     # Enable/disable payment methods
|       |   |   |-- ActivityLog.tsx              # Running log of actions
|       |   |   +-- GuardrailBanner.tsx          # Environment/mode warnings
|       |   |-- hooks/
|       |   |   |-- useTestLabStatus.ts          # Polls account/environment state
|       |   |   +-- useOperation.ts              # Runs operation, tracks progress
|       |   +-- utils/
|       |       +-- api.ts                       # wp.apiFetch wrappers
|       |-- package.json
|       +-- tsconfig.json
```

---

## 10. WP-CLI Complete Surface

All operations output structured JSON when `--format=json` is passed.

```bash
# Status
wp wcpay-dev test-lab status                            # Full dashboard

# Catalog
wp wcpay-dev test-lab stock                             # Set up product catalog
wp wcpay-dev test-lab stock --install-dependencies      # Auto-install Smooth Generator first
wp wcpay-dev test-lab customers --count=10              # Generate customers

# Charges (checkout simulator)
wp wcpay-dev test-lab charges --count=10                # Successful charges
wp wcpay-dev test-lab charges --count=5 --instant       # Bypass-pending (instant balance)
wp wcpay-dev test-lab charges --count=3 --fail          # Failed/declined charges
wp wcpay-dev test-lab charges --count=2 --3ds           # 3D Secure charges

# Post-checkout operations
wp wcpay-dev test-lab refunds --count=3                 # Refunds on random orders
wp wcpay-dev test-lab disputes --count=2                # Disputes via test cards
wp wcpay-dev test-lab payouts --count=1                 # Manual payout
wp wcpay-dev test-lab payouts --fail                    # Failed payout simulation
wp wcpay-dev test-lab payout-schedule --interval=manual # Set payout schedule

# Payment methods
wp wcpay-dev test-lab payment-methods list              # List with status
wp wcpay-dev test-lab payment-methods enable --method=sepa_debit
wp wcpay-dev test-lab payment-methods disable --method=bancontact
wp wcpay-dev test-lab payment-methods enable --method=ideal,sepa_debit

# Experiments
wp wcpay-dev test-lab experiment the-basics             # Run preset
wp wcpay-dev test-lab experiment dispute-season
wp wcpay-dev test-lab experiment payday
wp wcpay-dev test-lab experiment everything-on-fire
wp wcpay-dev test-lab clean                             # Clean slate
```

---

## 11. Build and Development

### 11.1 PHP Dependencies

Add to Dev Tools `composer.json`:

```json
{
  "require": {
    "stripe/stripe-php": "^16.0"
  }
}
```

### 11.2 React App Build

Minimal build setup using `@wordpress/scripts`:

```json
{
  "scripts": {
    "build": "wp-scripts build",
    "dev": "wp-scripts start"
  }
}
```

Gutenberg component packages (`@wordpress/components`, `@wordpress/api-fetch`, `@wordpress/element`) are already loaded in WP admin. The npm dependencies are for TypeScript types only.

### 11.3 Development Workflow

```bash
# Working directory: ~/Work/a8c/woocommerce-payments/

# PHP changes: edit files, they're live in the Docker container
# React changes: run dev server for hot reload
cd docker/wordpress/wp-content/plugins/woocommerce-payments-dev-tools/test-lab/admin
npm run dev

# Test CLI commands
pnpm wp wcpay-dev test-lab status

# Build for "production" (other devs)
cd docker/wordpress/wp-content/plugins/woocommerce-payments-dev-tools/test-lab/admin
npm run build
```

---

## 12. Stripe Research Reference

Detailed Stripe research supporting this design is persisted at:

- `ciab-admin/.claude/docs/analysis/2026-02-09-stripe-test-data-consolidated.md` — Consolidated analysis
- `ciab-admin/.claude/docs/analysis/2026-02-09-stripe-cli-research.md` — CLI commands and capabilities
- `ciab-admin/.claude/docs/analysis/2026-02-09-stripe-connect-custom-accounts-test-mode.md` — Custom account creation and verification
- `ciab-admin/.claude/docs/analysis/2026-02-09-stripe-payouts-test-mode-research.md` — Payout operations in test mode
- `ciab-admin/.claude/docs/analysis/2026-02-09-stripe-test-data-seeding-strategies.md` — Seeding strategies and rate limits
- `ciab-admin/.claude/docs/analysis/2026-02-10-stripe-express-vs-custom-api-access.md` — Express vs Custom API limitations

### Key Test Values Quick Reference

| Value | Purpose |
|-------|---------|
| `pm_card_visa` / `4242 4242 4242 4242` | Successful charge (funds to pending) |
| `pm_card_bypassPending` / `4000 0000 0000 0077` | Instant available balance |
| `pm_card_chargeDeclined` / `4000 0000 0000 0002` | Declined charge |
| `4000 0000 0000 3220` | 3D Secure required |
| `4000 0000 0000 0259` | Fraudulent dispute auto-created |
| `4000 0000 0000 2685` | "Not received" dispute |
| `tok_visa_debit_us_transferSuccess` | Successful payout external account |
| `tok_visa_debit_us_transferFail` | Failed payout external account |
| `btok_us` | US test bank account token |
| SSN `000000000` / last-4 `0000` | Passes identity verification |
| DOB `1901-01-01` | Passes DOB verification |

### Rate Limits (Test Mode)

| Limit | Value |
|-------|-------|
| Global requests | 25/s |
| Account creation | 5/s |
| Payout creation | 15/s, 30 concurrent/merchant |

---

## 13. Open Questions for Implementation

1. **`process_payment()` input contract:** Does WooPayments accept a payment method token via `$_POST['wcpay-payment-method']`, or does it require a PaymentMethod created through Stripe Elements? Determines whether the checkout simulator needs an extra Stripe SDK step.

2. **Smooth Generator in Docker:** The Dev Tools plugin runs inside the Docker container. The auto-installer needs to download and activate Smooth Generator inside the container's plugin directory. Verify filesystem permissions and wp-cli plugin install path.

3. **Stripe key source:** The Dev Tools already stores a Stripe test key for Billing Clocks. Should the Test Lab reuse that, read from the platform's local secrets, or have its own setting? Recommendation: reuse existing Dev Tools key if present, otherwise prompt for it.

4. **React app build output:** Where should the built JS bundle live? Options: committed to repo (simpler for other devs) or built on setup. For a dev tool, committed build artifacts are pragmatic.

5. **Express account workarounds:** For Sandbox (Express) accounts where payout schedule changes are restricted, should we attempt the operation and handle the error gracefully, or check capabilities upfront and disable the control? Recommendation: attempt and handle error with clear explanation.
