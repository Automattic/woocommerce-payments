# WCPay Test Lab — Implementation Prompt

Give this prompt to an AI agent working from `~/Work/a8c/woocommerce-payments/`.

---

## Prompt

You are implementing "Test Lab" — a new developer tool for WooPayments that lets developers quickly populate test Stripe accounts with realistic data (charges, refunds, disputes, payouts) to test UI scenarios.

### Context

**Design document:** Read `.claude/docs/plans/2026-02-10-wcpay-test-lab-design.md` first. It contains the full architecture, operations, UI layout, guardrails, file structure, and open questions. This is your source of truth.

**Where the code lives:** The WCPay Dev Tools plugin is at `docker/wordpress/wp-content/plugins/woocommerce-payments-dev-tools/`. All new code goes under a `test-lab/` directory within that plugin. Read the existing plugin code (`woocommerce-payments-dev-tools.php`, `cli-commands.php`, `billing-clocks/`) to understand the current patterns — but do NOT follow the billing clocks implementation style. We are going for modern, clean architecture.

**WooPayments source** (reference, do not modify): The WooPayments plugin is at the repo root (`includes/`, `client/`). You will need to reference:
- `includes/class-wc-payment-gateway-wcpay.php` — the `process_payment()` method, how it reads payment method tokens, what metadata it sets on orders
- `includes/core/class-mode.php` — mode detection (`is_dev()`, `is_test()`, `is_test_mode_onboarding()`)
- `includes/class-wc-payments-account.php` — account data, `is_live`, `is_test_drive`
- `includes/admin/class-wc-rest-payments-settings-controller.php` — how payment method settings are updated
- `includes/class-wc-payments-status.php` — the existing `delete_test_orders()` logic

**Stripe research:** Background research on Stripe APIs is at `~/Work/a8c/ciab-admin/.claude/docs/analysis/2026-02-09-stripe-test-data-consolidated.md` and related files in that directory. Key test values, rate limits, and API patterns are documented there.

**WooPayments test mode analysis:** Full analysis of mode cascade, account types, and entity separation at `~/Work/a8c/ciab-admin/.claude/tmp/woopayments-test-mode-analysis.md`.

### Key Decisions Already Made

1. **Architecture:** Single PHP engine (operations layer), three entry points (React UI, REST API, WP-CLI). No Node.js.
2. **Stripe SDK:** Use `stripe/stripe-php` (Composer dependency). NOT raw `wp_remote_request()`.
3. **UI framework:** React with `@wordpress/components` (Gutenberg). Built with `@wordpress/scripts`. Registered as a WP admin sub-page under Dev Tools.
4. **Checkout simulation:** Create `WC_Order` manually, then call `WC_Payment_Gateway_WCPay::process_payment()` directly. Do NOT call `WC_Checkout::process_checkout()`.
5. **Product/customer generation:** Auto-install WC Smooth Generator (`woocommerce/wc-smooth-generator`) on demand from GitHub releases.
6. **Payment method toggles:** Use WooPayments' own settings update logic — do NOT call Stripe directly for capability changes.
7. **Clean Slate:** Use WooPayments' existing `delete_test_orders()` from `WC_Payments_Status`.
8. **Guardrails:** Environment check (`wp_get_environment_type()`) blocks non-dev/staging. Mode + account type determine operation availability. All enforced in the operations layer, not just the UI.
9. **Theme:** Subtle Dexter's Laboratory easter eggs — "Test Lab" name, experiment terminology, fun toast messages. Nothing overdone.

### Implementation Phases

Implement incrementally. Each phase should be fully functional before moving to the next.

**Phase 1: Foundation**
- Create the `test-lab/` directory structure per the design doc
- Add `stripe/stripe-php` to `composer.json`
- Implement `class-test-lab.php` (bootstrap: registers admin page, REST routes, CLI)
- Implement `class-environment.php` (mode detection, account type detection, guardrail checks)
- Implement `class-stripe-client.php` (Stripe PHP SDK wrapper with key management)
- Register a blank WP admin sub-page that loads the React app shell
- Set up the React app build (`@wordpress/scripts`, `package.json`, `tsconfig.json`)
- Build the `StatusBar` component that shows environment, mode, account type, balance
- Verify: admin page loads, status bar shows real data from the connected test account

**Phase 2: Checkout Simulator (core)**
- FIRST: Investigate how `process_payment()` accepts payment method tokens. Read the method carefully. Determine if `$_POST['wcpay-payment-method']` works or if you need to create a Stripe PaymentMethod via SDK first.
- Implement `class-checkout-simulator.php` — creates WC orders and processes payment
- Implement `class-charge-operations.php` — wraps checkout simulator with different test cards
- Add REST endpoint for charge creation
- Add WP-CLI `charges` subcommand
- Build the "Charges & Orders" UI panel
- Verify: `wp wcpay-dev test-lab charges --count=3` creates 3 real WC orders with Stripe charges

**Phase 3: Catalog Setup**
- Implement `class-catalog-setup.php` with Smooth Generator auto-installer
- Implement the "Lab Supplies" UI panel (install prompt + product/customer controls)
- Add WP-CLI `stock` and `customers` subcommands
- Verify: one-click install of Smooth Generator, products appear in WC

**Phase 4: Post-Checkout Operations**
- Implement `class-refund-operations.php` (full/partial refunds on existing orders)
- Implement `class-dispute-operations.php` (creates charges with dispute test cards)
- Implement `class-payout-operations.php` (manual payouts, failure simulation)
- Implement `class-account-operations.php` (payout schedule control)
- Add REST endpoints and WP-CLI commands for each
- Build UI panels for refunds, disputes, payouts, account settings
- Verify: each operation works via CLI and UI

**Phase 5: Payment Methods**
- Implement `class-payment-method-operations.php` — uses WooPayments' own settings logic
- Build `PaymentMethodToggles` component
- Add WP-CLI `payment-methods` subcommand
- Verify: toggling a method in Test Lab matches what the WooPayments Settings page does

**Phase 6: Experiments & Polish**
- Implement `class-experiment-runner.php` with preset recipes
- Implement `class-clean-slate.php` using WooPayments' `delete_test_orders()`
- Build `ExperimentCards` component with recipe preview
- Build `ActivityLog` component
- Add `GuardrailBanner` component with all tier 1/2/3 warnings
- Add Dexter's Lab easter eggs (toast messages, empty states, confirmation dialogs)
- Verify: "The Basics" experiment runs end-to-end, Clean Slate resets everything

### Open Questions to Investigate During Phase 1-2

1. **`process_payment()` input contract:** Read `WC_Payment_Gateway_WCPay::process_payment()` and trace how it gets the payment method. Does it read `$_POST['wcpay-payment-method']`? Does it need a Stripe PaymentMethod ID (pm_xxx)? Or a token? This determines the checkout simulator design.

2. **Stripe key access:** The Dev Tools plugin has a Stripe secret key setting for Billing Clocks. Check `woocommerce-payments-dev-tools.php` for how it's stored/accessed. Reuse that mechanism for the Test Lab's Stripe client. If not set, the Test Lab should prompt for it.

3. **Smooth Generator in Docker:** Verify that `Plugin_Upgrader` can download and install plugins inside the wp-env container. Check filesystem permissions at `docker/wordpress/wp-content/plugins/`.

### Visual Verification

**IMPORTANT:** Use the `browser-interaction` skill to visually verify your work after each phase. The Dev Tools plugin is accessible at:

- **Dev Tools page:** http://localhost:8082/wp-admin/admin.php?page=wcpaydev
- **WP Admin login:** user `admin`, password `admin`
- **Shop (for manual checkout testing):** http://localhost:8082/shop/

After implementing UI changes:
1. Navigate to the Test Lab page in the browser
2. Take a screenshot to verify the UI renders correctly
3. Test interactive elements (buttons, toggles, form submissions)
4. Verify that operations complete successfully and results appear in the Activity Log
5. Check for console errors

After implementing CLI commands:
1. Run the command via `pnpm wp wcpay-dev test-lab <command>`
2. Then open the WP admin to verify the results are visible (orders created, account state changed, etc.)

After implementing experiments:
1. Run an experiment via the UI
2. Screenshot the result
3. Verify in WooCommerce orders list that orders were created with correct payment metadata

Do not consider a phase complete until you have visually verified it works in the browser.

### Artifact Tagging (Critical Requirement)

Every entity created by Test Lab MUST carry a `_wcpay_test_lab` meta key with a JSON value recording provenance:

```json
{
  "created_by": "test-lab",
  "created_at": "2026-02-10T14:32:00Z",
  "operation": "charges",
  "experiment": "the-basics"
}
```

This applies to: products (post meta), customers/WP users (user meta), orders (order meta), refunds (refund order meta).

**Why:** Enables reliable programmatic cleanup. The "Clean Slate" operation deletes entities by querying for this meta. The checkout simulator only uses Test Lab-tagged products and customers (via `get_random_lab_product()` and `get_random_lab_customer()`).

**Catalog overwrite vs append:** When "Stock the lab" is called and Test Lab products already exist (detected via `_wcpay_test_lab` meta), the UI must ask whether to **Replace** (delete existing, create fresh) or **Add alongside** (keep existing, add more). CLI: `--replace` or `--append` flags.

See design doc section 5.6 for full details.

### Constraints

- **Do NOT modify WooPayments source code** (`includes/`, `client/`). Reference only.
- **Do NOT modify the Transact Platform Server.** All operations go through existing APIs or direct to Stripe.
- **WordPress Coding Standards** for PHP. Use PHPCS if available.
- **TypeScript strict mode** for the React app.
- **Every UI operation must have a CLI equivalent.** No UI-only operations.
- **All CLI commands must support `--format=json`** for AI agent consumption.
- **Inline documentation in the UI** — every control explains itself. See design doc section 7.3.
- **Tag all generated artifacts** with `_wcpay_test_lab` meta. See "Artifact Tagging" section above.
- **Test with both Test Drive (Custom) and Sandbox (Express) accounts** where relevant — some operations behave differently.

### Running Commands

```bash
# WP-CLI (from WooPayments repo root since that is the entry point for the Docker setup)
npm wp wcpay-dev test-lab status

# React dev server
cd docker/wordpress/wp-content/plugins/woocommerce-payments-dev-tools/test-lab/admin
npm run dev

# PHP dependencies
cd docker/wordpress/wp-content/plugins/woocommerce-payments-dev-tools
composer install
```
