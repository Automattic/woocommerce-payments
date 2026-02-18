# WooPayments Mode System

**Last updated:** 2026-02-13

Reference for how WooPayments determines its operating mode (live, test, dev). Source: `includes/core/class-mode.php`.

## Mode Hierarchy

Dev mode sits at the top — when active, it forces everything below it to `true`:

```
dev_mode = true
  └─ test_mode_onboarding = true  (always, when dev)
      └─ test_mode = true         (always, when test_mode_onboarding)
```

### How Each Flag Is Set (in `maybe_init()`)

1. **`dev_mode`** — `true` if any of:
   - `WCPAY_DEV_MODE` constant is defined and truthy
   - `wp_get_environment_type()` returns `'development'` or `'staging'`
   - `wp_get_development_mode()` returns a non-empty string
   - The `wcpay_dev_mode` filter returns `true` (used by WCPay Dev Tools plugin)

2. **`test_mode_onboarding`** — `true` if:
   - `dev_mode` is `true`, **OR**
   - `WC_Payments_Onboarding_Service::is_test_mode_enabled()` returns `true` (checks `wcpay_onboarding_test_mode` DB option)
   - Can be overridden by `wcpay_test_mode_onboarding` filter

3. **`test_mode`** — `true` if:
   - `test_mode_onboarding` is `true` (forced to `true`), **OR**
   - The gateway setting `test_mode` is `'yes'` (from `woocommerce_woocommerce_payments_settings` option)
   - Can be overridden by `wcpay_test_mode` filter

### Key Implication

**In any dev environment, `is_test_mode_onboarding()` is always `true`.** This is by design (line 85) but has significant UI consequences — see below.

## UI Consequences

The admin UI renders different messaging depending on which mode flags are set:

| Condition | Settings Page | Overview Banner |
|-----------|--------------|-----------------|
| `is_dev()` only | Shows dev mode notice, test mode checkbox visible | Dev mode banner |
| `is_test_mode_onboarding()` | Test mode checkbox **hidden** (forced on) | Sandbox/test-account messaging |
| `is_test()` only | Test mode checkbox checked | Test mode banner |

Because dev mode forces `is_test_mode_onboarding() = true`, you will **never** see the dev-mode-specific UI in a standard dev environment — it's masked by the test-mode-onboarding UI. To see dev-mode UI, you must override `is_test_mode_onboarding()` to return `false` (see Debugging section below).

## Frontend Data Flow

Mode state reaches the frontend through two independent paths that must agree:

### 1. `wcpaySettings` JS Global

Set in `WC_Payments_Admin::get_js_settings()` (`includes/admin/class-wc-payments-admin.php`, ~line 972). Contains `devMode`, `testModeOnboarding`, `testMode` booleans.

Read by utility functions:
- `isInDevMode()` — checks `wcpaySettings.devMode`
- `isInTestMode()` — checks `wcpaySettings.testMode`
- `isInTestModeOnboarding()` — checks `wcpaySettings.testModeOnboarding`

### 2. `@wordpress/data` Store (REST API)

The settings store fetches from `GET /wc/v3/payments/settings`. The REST response includes `is_dev_mode_enabled`, `is_test_mode_onboarding`, `is_test_mode_enabled`.

Read by hooks:
- `useDevMode()` — from settings store
- `useTestModeOnboarding()` — from settings store
- `useTestMode()` — from settings store

### Common Pitfall

If you change mode logic in PHP but only check the `wcpaySettings` global in the frontend (or vice versa), the UI may appear correct on initial load but break after a store fetch, or vice versa. Always verify both paths.

## WCPay Dev Tools Plugin

The [WCPay Dev Tools](https://github.com/nickarges/wcpay-dev-tools) plugin interacts with the mode system:

- Hooks `wcpay_dev_mode` filter to force dev mode on/off
- Manages the `wcpay_onboarding_test_mode` DB option (toggling test mode onboarding)
- Provides a UI panel in the WCPay admin to toggle these settings

When debugging mode issues, check whether Dev Tools is active and what state it has set.

## Debugging Tips

### See Dev-Mode UI in a Dev Environment

Since `is_test_mode_onboarding()` masks dev-mode UI, temporarily override it:

```php
// In class-mode.php, temporarily:
public function is_test_mode_onboarding(): bool {
    return false; // Override to see dev-mode-specific UI
}
```

Then restart Apache to clear OPcache:

```bash
docker compose exec wordpress apache2ctl graceful
```

### Verify Current Mode State

Check the REST API directly:

```
GET /wp-json/wc/v3/payments/settings
```

Look for `is_dev_mode_enabled`, `is_test_mode_onboarding`, `is_test_mode_enabled` in the response.

Or in PHP:

```php
$mode = WC_Payments::mode();
error_log( 'dev=' . $mode->is_dev() . ' tmo=' . $mode->is_test_mode_onboarding() . ' test=' . $mode->is_test() );
```

### Filters for Testing

```php
// Force dev mode off (even in dev environment):
add_filter( 'wcpay_dev_mode', '__return_false' );

// Force test mode onboarding off:
add_filter( 'wcpay_test_mode_onboarding', '__return_false' );

// Force test mode on:
add_filter( 'wcpay_test_mode', '__return_true' );
```
