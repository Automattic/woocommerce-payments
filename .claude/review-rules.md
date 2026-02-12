# WooPayments Review Rules

Review expectations specific to the WooPayments codebase. Loaded by the review agent (both local and pipeline) to provide repo-specific context.

These rules supplement generic review practices with WCPay conventions, known pitfalls, and lessons from past incidents.

**WooCommerce core context:** Many WCPay issues only surface when you understand what WooCommerce does with our hooks. Always check WC core when reviewing code that changes order statuses, hooks into `admin_init`/`init`, or triggers emails. WC code is available at `docker/wordpress/wp-content/plugins/woocommerce/` (always present) or `../woocommerce/plugins/woocommerce/` (if the full repo is checked out).

---

## Architecture Compliance

### Payment Flow Layers

Every payment flows through these layers in order. Never skip a layer.

```
Gateway → Request Classes → API Client → HTTP/Jetpack → Transact-API → Stripe
```

- [ ] Payment flow changes go through the correct layers
- [ ] No direct API client calls from gateway or feature code — always use typed Request classes
- [ ] New Request classes extend `Request`, define `REQUIRED_PARAMS`, `IMMUTABLE_PARAMS`, proper `get_api()`, `get_method()`
- [ ] Gateway methods orchestrate but do NOT contain business logic

### Code Placement

- [ ] New PHP code goes in `src/` (PSR-4, dependency injection) unless extending existing legacy classes in `includes/`
- [ ] Migrations go in `includes/migrations/` (established pattern, exception to `src/` preference)
- [ ] Frontend: `@wordpress/data` stores for shared state, not local component state
- [ ] Dependency injection used where the container supports it

### WordPress Hooks

- [ ] Hooks use correct priority and hook name
- [ ] New hooks follow the `wcpay_` prefix convention
- [ ] Action Scheduler hooks registered in `init()` method

---

## Security

- [ ] SQL queries use `$wpdb->prepare()` — no raw string interpolation
- [ ] User input sanitized: `sanitize_text_field()`, `absint()`, `wp_kses()`, etc.
- [ ] Output escaped: `esc_html()`, `esc_attr()`, `esc_url()`, `wp_json_encode()`
- [ ] Nonce verification on form submissions and AJAX handlers
- [ ] Capability checks (`current_user_can()`) on admin actions
- [ ] No sensitive data in logs (card numbers, tokens, secrets)
- [ ] CSRF protection on state-changing operations
- [ ] Payment amounts validated server-side (not trusting client-provided values)
- [ ] Stripe webhook signatures verified (if modifying webhook handling)

---

## Performance

### Per-Request Hook Guard (CRITICAL)

**Any code that executes on `admin_init`, `init`, `wp_loaded`, `shutdown`, or other per-request hooks MUST NOT perform direct database queries.** This is the single most impactful performance rule.

If a database check is needed on a per-request hook, it must use one of:
- A cached `get_option()` value (autoloaded)
- An Action Scheduler async job that caches the result
- A transient with appropriate TTL

**When reviewing, always trace new code paths back to their trigger hook.** If the code ultimately runs on a per-request hook, apply heightened scrutiny for database queries, external API calls, or any I/O operations.

### Meta Query Caution

Order meta queries and `wp_wc_orders_meta` / `wp_postmeta` JOINs are inherently expensive at scale. They must:
- Be triple-checked during review
- Always run in an async environment / background job, never inline on page load
- Use appropriate indexes

### WooCommerce Admin Notes `can_be_added()` Trap

The `NoteTraits::possibly_add_note()` pattern only short-circuits via `note_exists()` when the note has been previously saved. When `can_be_added()` returns `false`, the note is never saved, so the check runs on **every `admin_init` indefinitely**. Any expensive logic in `can_be_added()` becomes a permanent per-request performance tax.

### General Performance Checklist

- [ ] No N+1 query patterns (queries inside loops)
- [ ] Database queries use indexes
- [ ] Transient/cache used for expensive operations that don't change per-request
- [ ] No unnecessary API calls to WCPay server (each round-trip is ~200-500ms)
- [ ] Frontend: no unnecessary re-renders, proper `useMemo`/`useCallback`
- [ ] Frontend: large lists use pagination
- [ ] No blocking operations in the checkout critical path
- [ ] Action Scheduler used for deferred work instead of synchronous processing
- [ ] Database migrations are safe (no locking large tables)
- [ ] `update_option()` calls are not inside loops — accumulate in memory and flush once

### Batch Operation Side Effects

When changing order statuses in bulk/batch operations, WooCommerce hooks fire for each status change. These can trigger external API calls (e.g., Stripe cancellation via `cancel_authorizations_on_order_status_change`) and email notifications (e.g., `WC_Email_Cancelled_Order`).

- [ ] Unnecessary hooks removed (`remove_action()`) before batch processing and restored after
- [ ] Email hooks specifically considered during bulk status changes
- [ ] External API call hooks disabled during remediation/migration operations

### Local Testing is Insufficient

Local environments have fast MySQL and small datasets. A query imperceptible locally can add 24+ seconds on production stores with large order volumes. Performance-sensitive code must be evaluated against realistic data volumes (tens of thousands of orders).

---

## Test Coverage

- [ ] New PHP code has corresponding PHPUnit tests
- [ ] New JS components have Jest tests
- [ ] Tests use `mock_wcpay_request()` for API mocking (not manual API client mocking)
- [ ] Tests use helper classes (`WC_Helper_Order`, `WC_Helper_Intention`) instead of manual setup
- [ ] Tests extend `WCPAY_UnitTestCase` (not `WP_UnitTestCase` directly)
- [ ] Tests use `set_up()` / `tear_down()` (snake_case, not camelCase)
- [ ] Edge cases covered: empty values, invalid input, error responses
- [ ] If payment flow changed: happy path AND error path tested
- [ ] If UI changed: component renders correctly, user interactions work
- [ ] Existing tests still relevant (no stale assertions after code changes)

---

## Incident-Derived Rules

These rules were codified from production incidents. They carry extra weight during review.

### INC-001: Expensive queries on admin_init (SEV-2, 2026-02)

**Pattern:** `admin_init` → `add_woo_admin_notes()` → `possibly_add_note()` → `can_be_added()` → expensive SQL JOIN on orders/meta tables. Caused 25-35 second admin page loads for all merchants.

**Rule:** Never put database queries in `can_be_added()` or any method called from per-request hooks. Use Action Scheduler to run the query async and cache the result in an autoloaded option.

### INC-001b: Batch status change side effects

**Pattern:** Remediation tool changed order statuses in bulk, triggering `cancel_authorizations_on_order_status_change` (unnecessary Stripe API calls) and WooCommerce cancellation emails for every order.

**Rule:** Always `remove_action()` for hooks that trigger external API calls and emails before batch/migration processing. Restore hooks after.
