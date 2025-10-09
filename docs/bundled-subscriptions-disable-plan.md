# Bundled Subscriptions Disable Implementation Plan

**Target Version:** 10.2.0

## Objective

Disable bundled WooCommerce Subscriptions UI and management functionality while maintaining Stripe Billing renewal order creation.

## Architecture Summary

**Bundled subscriptions** are powered by:
1. **WooCommerce Subscriptions Core library** (`vendor/woocommerce/subscriptions-core/`) - provides subscription UI, data models, and functions
2. **Stripe Billing** - handles payment processing via webhooks
3. **WCPay Event Handlers** (`WC_Payments_Subscriptions_Event_Handler`) - process Stripe webhooks to create renewal orders

The subscriptions-core library is loaded in `woocommerce-payments.php:188-269` when:
- WooCommerce is active
- `WC_Payments_Features::is_wcpay_subscriptions_enabled()` returns true
- WooCommerce Subscriptions plugin is NOT installed

## Acceptance Criteria

✅ No merchant/customer data is lost when functionality is disabled
✅ Prevent admin from editing, creating or listing subscriptions
✅ Prevent customers from editing or creating subscriptions
✅ Keep Stripe billing renewal orders being created

## Implementation Approach: Conditional Component Disabling

Instead of completely preventing subscriptions-core from loading (which could break renewals), we'll:
1. Load subscriptions-core normally to preserve renewal functionality
2. Conditionally disable UI and management components
3. Keep webhook handlers and renewal processing active

## Components to Disable

### Admin UI
- `WCS_Admin_Post_Types` (subscription list pages)
- `WCS_Admin_Meta_Boxes` (subscription edit screens)
- `WC_Subscriptions_Admin::init()` (admin functionality)
- Subscription menu items

### Customer UI
- `WCS_Template_Loader::init()` (subscription templates)
- `WCS_My_Account_Payment_Methods::init()` (payment method management)
- `WCS_My_Account_Auto_Renew_Toggle::init()` (auto-renew toggle)
- Customer subscription management pages

### Product & Checkout Management
- `WC_Subscriptions_Product::init()` (prevent creating subscription products)
- `WC_Subscriptions_Cart::init()` (cart functionality)
- `WC_Subscriptions_Cart_Validator::init()` (cart validation)
- `WC_Subscriptions_Checkout::init()` (checkout processing)

## Components to Keep Active

### Data Models
- `WC_Subscription` class
- Core functions (`wcs_*` functions)
- Subscription data stores

### Renewal Processing
- `WC_Subscriptions_Renewal_Order::init()`
- `wcs_create_renewal_order()` function
- `WC_Subscriptions_Manager::init()` (renewal scheduling)

### Webhook Handlers
- `WC_Payments_Subscriptions_Event_Handler`:
  - `handle_invoice_paid()` - creates renewal orders
  - `handle_invoice_payment_failed()` - handles failed payments
  - `handle_invoice_upcoming()` - validates subscriptions

### Order Processing
- Classes needed for renewal order creation
- Order meta handling

## Implementation Steps

### 1. Add Feature Flag

**File:** `includes/class-wc-payments-features.php`

Add new method:
```php
/**
 * Check if bundled subscriptions UI should be disabled (10.2+)
 *
 * @return bool
 */
public static function should_disable_bundled_subscriptions_ui() {
    return version_compare( WCPAY_VERSION_NUMBER, '10.2.0', '>=' )
           && self::is_wcpay_subscriptions_enabled()
           && ! class_exists( 'WC_Subscriptions' );
}
```

### 2. Modify Subscriptions Core Initialization

**Option A:** Create a custom init override class
- Extend `WC_Subscriptions_Core_Plugin`
- Override `init()` method with conditional logic
- Load custom class instead of base class in `woocommerce-payments.php:267-268`

**Option B:** Use filters to prevent component initialization
- Add filters in subscriptions-core library
- Hook into filters from WooPayments to disable components

**Recommended:** Option A for better control

### 3. Add Capability Restrictions

**File:** `includes/subscriptions/class-wc-payments-subscriptions.php`

Add hooks to restrict capabilities:
```php
add_filter( 'user_has_cap', [ $this, 'remove_subscription_capabilities' ], 10, 3 );
```

### 4. Hide Admin Menu Items

Add hook to remove subscription menu items when UI is disabled.

### 5. Add Admin Notice

**File:** `includes/subscriptions/class-wc-payments-subscriptions-admin-notices.php`

Display notice explaining that subscription management is disabled and users should install WooCommerce Subscriptions.

### 6. Testing Checklist

- [ ] Existing subscriptions remain in database
- [ ] Stripe webhook `invoice.paid` creates renewal orders
- [ ] Stripe webhook `invoice.payment_failed` handles failures
- [ ] Admin cannot access subscription list pages
- [ ] Admin cannot edit existing subscriptions
- [ ] Admin cannot create new subscription products
- [ ] Customers cannot access subscription management pages
- [ ] Customers cannot create new subscriptions
- [ ] Renewal orders are created successfully
- [ ] Renewal orders process payment correctly

## Key Files to Modify

1. `includes/class-wc-payments-features.php` - Add feature flag
2. `woocommerce-payments.php` - Modify subscriptions-core loading logic
3. `includes/subscriptions/class-wc-payments-subscriptions.php` - Add capability restrictions
4. `vendor/woocommerce/subscriptions-core/includes/class-wc-subscriptions-core-plugin.php` - Create override class
5. `includes/subscriptions/class-wc-payments-subscriptions-admin-notices.php` - Add UI disabled notice

## Rollback Strategy

If issues arise:
1. Revert version check in `should_disable_bundled_subscriptions_ui()`
2. All components will re-enable automatically
3. No data loss occurs

## Migration Path for Merchants

Merchants using bundled subscriptions should:
1. Install WooCommerce Subscriptions plugin
2. Existing subscriptions continue to work
3. Full management capabilities restored
