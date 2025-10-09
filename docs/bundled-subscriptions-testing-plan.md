# Bundled Subscriptions Disable - Testing Plan

## Prerequisites

### 1. Test Environment Setup

- [ ] Fresh WordPress installation with WooCommerce
- [ ] WooPayments installed (development branch)
- [ ] Stripe test mode configured
- [ ] Test customer account created
- [ ] At least 2-3 existing bundled subscriptions with:
  - [ ] Active subscription
  - [ ] On-hold subscription
  - [ ] Cancelled subscription

### 2. Enable the Feature (Temporary Override)

Since we're not at version 10.2 yet, you need to force-enable the feature. Add this to `should_disable_bundled_subscriptions_ui()`:

```php
public static function should_disable_bundled_subscriptions_ui(): bool {
    // Temporary override for testing - remove before production
    return true;

    // Original logic:
    // return version_compare( WCPAY_VERSION_NUMBER, '10.2.0', '>=' )
    //     && self::is_wcpay_subscriptions_enabled()
    //     && ! class_exists( 'WC_Subscriptions' );
}
```

## Test Scenarios

### Scenario 1: Admin UI Restrictions

#### Test 1.1: Subscription Menu Hidden
**Steps:**
1. Log in as admin
2. Navigate to WooCommerce menu in wp-admin

**Expected:**
- [ ] "Subscriptions" menu item is NOT visible
- [ ] Cannot access via direct URL: `/wp-admin/edit.php?post_type=shop_subscription`
- [ ] Redirected to plugins page with warning notice

**Actual Result:**
```
<!-- Record what you see -->
```

---

#### Test 1.2: Cannot Create Subscription Products
**Steps:**
1. Go to Products → Add New
2. Look at Product Type dropdown

**Expected:**
- [ ] "Subscription" option is NOT in the dropdown
- [ ] "Variable Subscription" option is NOT in the dropdown
- [ ] Only regular product types visible (Simple, Variable, etc.)

**Actual Result:**
```
<!-- Record what you see -->
```

---

#### Test 1.3: Cannot Edit Existing Subscriptions
**Steps:**
1. Try to access subscription edit page via direct URL
2. Try `/wp-admin/post.php?post={subscription_id}&action=edit`

**Expected:**
- [ ] Redirected to plugins page
- [ ] Warning notice shown
- [ ] Cannot access subscription edit screen

**Actual Result:**
```
<!-- Record what you see -->
```

---

#### Test 1.4: Admin Notice on Settings Page
**Steps:**
1. Go to WooPayments → Settings

**Expected:**
- [ ] Blue info notice displayed
- [ ] Notice says "Subscription Management Disabled"
- [ ] Notice mentions installing WooCommerce Subscriptions
- [ ] Notice mentions renewals will continue automatically

**Actual Result:**
```
<!-- Record what you see -->
```

---

### Scenario 2: Customer UI Restrictions

#### Test 2.1: My Account Subscriptions Hidden
**Steps:**
1. Log out of admin
2. Log in as customer who has subscriptions
3. Go to My Account → Subscriptions (if visible)

**Expected:**
- [ ] Subscriptions tab/page NOT visible in My Account
- [ ] Cannot access via direct URL: `/my-account/subscriptions/`
- [ ] No subscription management options

**Actual Result:**
```
<!-- Record what you see -->
```

---

#### Test 2.2: Cannot Change Payment Method
**Steps:**
1. As customer, try to access change payment URL
2. Try `/my-account/payment-methods/`

**Expected:**
- [ ] Cannot change payment method for subscriptions
- [ ] Payment method options not shown for subscription context

**Actual Result:**
```
<!-- Record what you see -->
```

---

### Scenario 3: Renewals Still Work (CRITICAL)

#### Test 3.1: Manual Webhook Test - invoice.paid
**Steps:**
1. Install Stripe CLI or use webhook testing tool
2. Send test `invoice.paid` webhook for existing subscription
3. Check if renewal order is created

**Expected:**
- [ ] Renewal order created successfully
- [ ] Order status set to Processing/Completed
- [ ] Subscription dates updated
- [ ] Customer receives order confirmation email

**Webhook Payload Example:**
```json
{
  "type": "invoice.paid",
  "data": {
    "object": {
      "id": "in_test123",
      "subscription": "sub_test456",
      "amount_paid": 1000,
      "status": "paid"
    }
  }
}
```

**Actual Result:**
```
<!-- Record renewal order ID and details -->
```

---

#### Test 3.2: Manual Webhook Test - invoice.payment_failed
**Steps:**
1. Send test `invoice.payment_failed` webhook
2. Check subscription status

**Expected:**
- [ ] Subscription status changes to On-Hold
- [ ] Renewal order created with Failed status
- [ ] Order note added with failure details
- [ ] Customer receives failed payment email

**Actual Result:**
```
<!-- Record what happens -->
```

---

#### Test 3.3: Check Webhook Handlers Still Active
**Steps:**
1. Check if webhook handler class is loaded
2. Navigate to WooPayments → Settings → Advanced
3. Look for webhook status

**Expected:**
- [ ] `WC_Payments_Subscriptions_Event_Handler` is instantiated
- [ ] Webhooks are configured and active
- [ ] No errors in webhook processing

**Verification Command:**
```php
// Run in WordPress console or add to test file
var_dump( class_exists( 'WC_Payments_Subscriptions_Event_Handler' ) );
```

**Actual Result:**
```
<!-- Record result -->
```

---

### Scenario 4: Data Integrity

#### Test 4.1: Existing Subscriptions Data Preserved
**Steps:**
1. Query database for subscriptions
2. Check subscription post meta

**SQL Query:**
```sql
SELECT * FROM wp_posts WHERE post_type = 'shop_subscription' LIMIT 5;
SELECT * FROM wp_postmeta WHERE post_id IN (
    SELECT ID FROM wp_posts WHERE post_type = 'shop_subscription'
) LIMIT 20;
```

**Expected:**
- [ ] All subscription posts still exist
- [ ] All subscription meta data intact
- [ ] No data loss or corruption

**Actual Result:**
```
<!-- Record count and sample data -->
```

---

#### Test 4.2: Subscription Functions Available
**Steps:**
1. Test core subscription functions still work

**PHP Test:**
```php
// Add to functions.php temporarily or run in console
$subscription = wcs_get_subscription( 123 ); // Use real ID
var_dump( $subscription->get_id() );
var_dump( $subscription->get_status() );
var_dump( $subscription->get_date( 'next_payment' ) );
```

**Expected:**
- [ ] `wcs_get_subscription()` works
- [ ] Can read subscription data
- [ ] Functions don't throw errors

**Actual Result:**
```
<!-- Record output -->
```

---

### Scenario 5: REST API Restrictions

#### Test 5.1: Cannot Create Subscription via API
**Steps:**
1. Use REST API client (Postman/cURL)
2. Try to create subscription via WooCommerce API

**Request:**
```bash
curl -X POST https://your-site.test/wp-json/wc/v3/subscriptions \
  -u consumer_key:consumer_secret \
  -H "Content-Type: application/json" \
  -d '{
    "customer_id": 1,
    "billing_interval": "month",
    "status": "active"
  }'
```

**Expected:**
- [ ] HTTP 403 Forbidden response
- [ ] Error message: "Subscription management is disabled"

**Actual Result:**
```
<!-- Record response -->
```

---

#### Test 5.2: Cannot Edit Subscription via API
**Steps:**
1. Try to update existing subscription via API

**Request:**
```bash
curl -X PUT https://your-site.test/wp-json/wc/v3/subscriptions/123 \
  -u consumer_key:consumer_secret \
  -H "Content-Type: application/json" \
  -d '{"status": "on-hold"}'
```

**Expected:**
- [ ] HTTP 403 Forbidden response
- [ ] Error message shown

**Actual Result:**
```
<!-- Record response -->
```

---

### Scenario 6: Migration to WooCommerce Subscriptions

#### Test 6.1: Install WC Subscriptions Plugin
**Steps:**
1. Note current state (restrictions active)
2. Install WooCommerce Subscriptions plugin
3. Activate the plugin
4. Clear all caches

**Expected:**
- [ ] Restrictions automatically disabled
- [ ] Subscription menu reappears
- [ ] Can access subscription pages
- [ ] All subscription data still intact
- [ ] No PHP errors or warnings

**Actual Result:**
```
<!-- Record what happens -->
```

---

#### Test 6.2: Verify Full Functionality Restored
**Steps:**
1. Access Subscriptions menu
2. Try to edit a subscription
3. Try to create a new subscription product

**Expected:**
- [ ] Full admin UI restored
- [ ] Can edit subscriptions
- [ ] Can create subscription products
- [ ] Customer can manage subscriptions
- [ ] All WC Subscriptions features work

**Actual Result:**
```
<!-- Record status -->
```

---

#### Test 6.3: Deactivate WC Subscriptions
**Steps:**
1. Deactivate WooCommerce Subscriptions plugin
2. Reload admin pages

**Expected:**
- [ ] Restrictions immediately re-applied
- [ ] UI disabled again
- [ ] Renewals still work

**Actual Result:**
```
<!-- Record behavior -->
```

---

## Edge Cases to Test

### Edge Case 1: Multisite Installation
**Steps:**
1. Test on WordPress Multisite
2. Check if restrictions apply per-site

**Expected:**
- [ ] Restrictions work independently per site
- [ ] No cross-site interference

---

### Edge Case 2: Different User Roles
**Steps:**
1. Test as Shop Manager
2. Test as Editor
3. Test as Customer

**Expected:**
- [ ] All admin roles blocked from subscription management
- [ ] Customers blocked from subscription management
- [ ] No capability bypasses

**Actual Results:**
```
Shop Manager:
Editor:
Customer:
```

---

### Edge Case 3: Cached Pages
**Steps:**
1. Enable page caching plugin
2. Visit subscription pages
3. Clear cache

**Expected:**
- [ ] Restrictions work even with caching
- [ ] No cached admin pages shown

---

### Edge Case 4: AJAX Requests
**Steps:**
1. Check if AJAX operations are blocked
2. Try to trigger subscription status changes via browser console

**Expected:**
- [ ] AJAX calls fail gracefully
- [ ] No PHP errors in browser console

---

## Performance Testing

### Test 1: Page Load Times
**Steps:**
1. Measure admin page load time before/after restrictions
2. Measure frontend page load time

**Expected:**
- [ ] No significant performance degradation
- [ ] Admin pages load normally

**Measurements:**
```
Before:
After:
```

---

### Test 2: Database Queries
**Steps:**
1. Enable Query Monitor plugin
2. Check number of queries on admin pages

**Expected:**
- [ ] No excessive queries
- [ ] No N+1 query issues

---

## Logging and Debugging

### Enable Debug Logging
Add to `wp-config.php`:
```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
```

### Check Logs For:
- [ ] PHP warnings/errors
- [ ] WooCommerce logs in `wp-content/woocommerce/logs/`
- [ ] Webhook processing logs

---

## Rollback Test

### Test: Revert Feature Flag
**Steps:**
1. Remove temporary override from `should_disable_bundled_subscriptions_ui()`
2. Or set it to return `false`
3. Reload pages

**Expected:**
- [ ] Restrictions immediately removed
- [ ] Full functionality restored
- [ ] No data loss
- [ ] No errors

---

## Automated Tests (If Applicable)

### Unit Tests to Write
- [ ] Test `should_disable_bundled_subscriptions_ui()` logic
- [ ] Test capability filtering
- [ ] Test REST API blocking
- [ ] Test webhook handlers still execute

### E2E Tests to Write
- [ ] Admin cannot access subscriptions
- [ ] Customer cannot manage subscriptions
- [ ] Renewal webhooks create orders

---

## Sign-Off Checklist

Before marking as complete, verify:

- [ ] All admin UI restrictions working
- [ ] All customer UI restrictions working
- [ ] Renewals confirmed working via webhook testing
- [ ] No data loss or corruption
- [ ] Migration to WC Subscriptions works seamlessly
- [ ] No PHP errors in logs
- [ ] Performance acceptable
- [ ] Documentation updated
- [ ] QA team notified

---

## Notes and Issues Found

### Issue 1
**Date:**
**Description:**
**Severity:** Critical/High/Medium/Low
**Resolution:**

### Issue 2
**Date:**
**Description:**
**Severity:**
**Resolution:**

---

## Test Environment Details

**WordPress Version:**
**WooCommerce Version:**
**WooPayments Version:**
**PHP Version:**
**Database:**
**Server:**
**Test Date:**
**Tester:**
