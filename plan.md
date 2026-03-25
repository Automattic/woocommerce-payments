# Plan: Fix Express Checkout Display on Block-Based Cart

**Linear Issue:** WOOPMNT-5763
**Type:** bug fix
**Risk:** low
**Status:** ✅ ALREADY FIXED (PR #11382, commit b58a6e90a)

## Problem Statement

Express checkout buttons (Apple Pay, Google Pay, Amazon Pay) were displaying on block-based cart pages even when the "Show on cart page" setting was unchecked in WooPayments Express Checkout display settings. The issue was specific to the block-based cart — classic cart pages respected the settings correctly.

**Customer Impact:**
- Merchants who configured express checkout to display only on checkout page (with cart unchecked) still saw buttons on the cart block
- This violates merchant's explicit display preferences and could confuse customers
- Affects only sites using the Cart block (not classic cart)

## Root Cause / Design Rationale

The JavaScript registration functions for express checkout payment methods in `client/express-checkout/blocks/index.js` did not check location-specific settings before rendering buttons. The `canMakePayment` functions only verified:
1. Whether `wcpayExpressCheckoutParams` was defined
2. Whether the payment method was available via `checkPaymentMethodIsAvailable()`

They failed to check the `enabled_methods` array, which contains the list of payment methods enabled for the current page context based on merchant settings (`express_checkout_cart_methods`, `express_checkout_checkout_methods`, etc.).

**Why this only affected block-based cart:**
- Classic cart uses traditional WordPress hooks (`woocommerce_proceed_to_checkout`) with server-side rendering
- Server-side code in `WC_Payments_Express_Checkout_Button_Display_Handler::display_express_checkout_buttons()` correctly checks `should_show_express_checkout_button()`
- Block-based cart uses WooCommerce Blocks' `registerExpressPaymentMethod()` API with client-side `canMakePayment` checks
- The client-side checks were incomplete

**Regression introduced by:** PR #11267 (commit d5b3b0d34d99731bc3b0da4ca98315d30b311ac6) which added Amazon Pay Express Checkout Element in version 10.5.0. While this PR didn't directly break existing functionality, it exposed the gap — Amazon Pay was added without location checks, making the pattern inconsistent. The issue was present but latent before this PR.

## Research Findings

### Code Flow Analysis

**Server-side (PHP):**
1. `WC_Payments_Express_Checkout_Button_Handler::payment_fields_js_config()` populates JS config
2. Calls `$this->express_checkout_helper->get_enabled_express_checkout_methods_for_context()`
3. Which calls `$this->get_button_context()` to determine current page (cart/checkout/product/pay_for_order)
4. Then calls `$this->is_express_checkout_method_enabled_at( $context, $method_id )` for each method
5. Returns array of enabled method IDs (e.g., `['payment_request']` or `['payment_request', 'amazon_pay']`)
6. This array is exposed as `wcpayExpressCheckoutParams.enabled_methods` in JavaScript

**Client-side (JS):**
1. Block registration exports `expressCheckoutElementApplePay`, `expressCheckoutElementGooglePay`, `expressCheckoutElementAmazonPay`
2. Each has a `canMakePayment` function that WooCommerce Blocks calls to determine if button should render
3. **Before fix:** Only checked `typeof wcpayExpressCheckoutParams !== 'undefined'`
4. **After fix:** Also checks `getExpressCheckoutData('enabled_methods')` includes the method ID

### Key Files

| File | Role |
|------|------|
| `client/express-checkout/blocks/index.js` | Express payment method registration for WooCommerce Blocks |
| `client/express-checkout/utils/express-checkout-data.ts` | Data accessor (`getExpressCheckoutData`) |
| `includes/express-checkout/class-wc-payments-express-checkout-button-handler.php` | JS config generation, passes `enabled_methods` to frontend |
| `includes/express-checkout/class-wc-payments-express-checkout-button-helper.php` | Helper methods: `get_enabled_express_checkout_methods_for_context()`, `is_express_checkout_method_enabled_at()`, `get_button_context()` |

### Settings Storage

Location-specific enable/disable stored as gateway options:
- `express_checkout_cart_methods` (array)
- `express_checkout_checkout_methods` (array)
- `express_checkout_product_methods` (array)

Each array contains method IDs: `payment_request` (Apple Pay/Google Pay) and/or `amazon_pay`.

## Affected Files

The fix has already been implemented. These are the files that were changed:

| File | Action | Description |
|------|--------|-------------|
| `client/express-checkout/blocks/index.js` | modify | Added `enabled_methods` checks to all three `canMakePayment` functions |
| `includes/express-checkout/class-wc-payments-express-checkout-button-handler.php` | modify | Updated `payment_fields_js_config()` to use context-aware checks instead of hardcoded 'checkout' |
| `tests/unit/express-checkout/test-class-wc-payments-express-checkout-button-handler.php` | modify | Added tests for context-aware config |
| `client/express-checkout/blocks/__tests__/index.test.js` | create | New test file for block registration logic |
| `changelog/agent-woopmnt-5763` | create | Changelog entry |

## Implementation Steps

✅ **ALREADY COMPLETED** in PR #11382 (commit b58a6e90ab17be368ebbd4b88a99036cc22cafdb, Feb 23 2026)

The implementation involved:

1. **Frontend Changes (`client/express-checkout/blocks/index.js`):**
   - Import `getExpressCheckoutData` utility
   - Add `enabled_methods` check to `expressCheckoutElementApplePay.canMakePayment`:
     ```js
     const enabledMethods = getExpressCheckoutData('enabled_methods') ?? [];
     if (!enabledMethods.includes('payment_request')) {
         return false;
     }
     ```
   - Add same check to `expressCheckoutElementGooglePay.canMakePayment`
   - Add same check to `expressCheckoutElementAmazonPay.canMakePayment` (with `'amazon_pay'` ID)

2. **Backend Changes (`class-wc-payments-express-checkout-button-handler.php`):**
   - Replace hardcoded `'checkout'` context with dynamic context from `$this->express_checkout_helper->get_button_context()`
   - Add fallback: if context is empty, allow all methods (backwards compatibility for edge cases)
   - Updated logic:
     ```php
     $context = $this->express_checkout_helper->get_button_context();

     $config['isPaymentRequestEnabled'] = $this->gateway->is_payment_request_enabled()
         && (
             empty( $context )
             || $this->express_checkout_helper->is_express_checkout_method_enabled_at( $context, 'payment_request' )
         );
     ```

3. **Tests:**
   - Added comprehensive test coverage in `client/express-checkout/blocks/__tests__/index.test.js`
   - Tests verify `canMakePayment` returns false when method not in `enabled_methods`
   - PHP unit tests updated to cover context-aware config generation

4. **Changelog:**
   - Added entry: "Fix express checkout buttons appearing on block-based cart when the cart location is unchecked in display settings"

## Test Strategy

✅ **Tests were added as part of the fix:**

### Unit Tests (JavaScript)

File: `client/express-checkout/blocks/__tests__/index.test.js`

Tests cover:
- [ ] Apple Pay `canMakePayment` returns false when `payment_request` not in `enabled_methods`
- [ ] Google Pay `canMakePayment` returns false when `payment_request` not in `enabled_methods`
- [ ] Amazon Pay `canMakePayment` returns false when `amazon_pay` not in `enabled_methods`
- [ ] All methods return true when their ID is in `enabled_methods`
- [ ] All methods return false when `wcpayExpressCheckoutParams` is undefined

Run with:
```bash
npm run test:js -- client/express-checkout/blocks/__tests__/index.test.js
```

### Unit Tests (PHP)

File: `tests/unit/express-checkout/test-class-wc-payments-express-checkout-button-handler.php`

Tests cover:
- [ ] `payment_fields_js_config` returns correct flags based on context
- [ ] Empty context allows all methods (fallback behavior)
- [ ] Cart context respects cart location settings
- [ ] Checkout context respects checkout location settings

Run with:
```bash
npm run test:php -- --filter=WC_Payments_Express_Checkout_Button_Handler
```

### Manual Testing

To verify the fix:

1. **Setup:**
   - Enable WooPayments Express Checkout (Apple Pay/Google Pay)
   - Use a block-based Cart page (WooCommerce Cart block)
   - Go to WooPayments > Settings > Express Checkout

2. **Test Case 1: Cart unchecked, Checkout checked**
   - Uncheck "Cart page"
   - Check "Checkout page"
   - Save settings
   - Visit cart page → buttons should NOT appear
   - Visit checkout page → buttons SHOULD appear

3. **Test Case 2: Cart checked, Checkout unchecked**
   - Check "Cart page"
   - Uncheck "Checkout page"
   - Save settings
   - Visit cart page → buttons SHOULD appear
   - Visit checkout page → buttons should NOT appear

4. **Test Case 3: All unchecked**
   - Uncheck both
   - Save settings
   - Visit cart page → buttons should NOT appear
   - Visit checkout page → buttons should NOT appear

5. **Test Case 4: Classic cart (regression check)**
   - Switch to classic cart shortcode
   - Repeat test cases 1-3
   - Should work identically (settings respected)

### E2E Test Recommendation

While tests were added, consider adding E2E coverage:

```typescript
// tests/e2e/specs/wcpay/shopper/express-checkout-location-settings.spec.ts
test('Express checkout respects cart location setting', async ({ page }) => {
    // 1. Configure settings: checkout only (cart unchecked)
    // 2. Add product to cart
    // 3. Navigate to cart block page
    // 4. Assert express checkout buttons are NOT visible
    // 5. Navigate to checkout
    // 6. Assert express checkout buttons ARE visible
});
```

## Success Criteria

✅ All criteria met by the fix:

- [x] Express checkout buttons respect "Show on cart page" setting on block-based cart
- [x] Unchecking cart in settings prevents buttons from showing on cart block
- [x] Checking cart in settings allows buttons to show on cart block
- [x] Classic cart continues to work correctly (no regression)
- [x] Checkout page display settings continue to work
- [x] Product page display settings continue to work
- [x] All existing tests pass
- [x] New tests added for changed behavior
- [x] Fix applies to all three express checkout methods (Apple Pay, Google Pay, Amazon Pay)

## Risk Assessment

**Risk Level: Low**

**Why low risk:**
1. Fix is isolated to display logic only — doesn't touch payment processing
2. Changes are purely conditional checks (if method not enabled, don't render)
3. Adds safety fallback (empty context allows all methods)
4. Doesn't change any API contracts or data structures
5. Classic cart path unchanged (server-side rendering continues to work)
6. Comprehensive test coverage added

**Regression areas to watch:**
- [ ] Classic cart express checkout display (should be unaffected — verify manually)
- [ ] Product page express checkout display (should be unaffected — uses same pattern)
- [ ] Pay for order page express checkout (should be unaffected — maps to 'checkout' context)
- [ ] Edge case: sites with custom hooks modifying `wcpayExpressCheckoutParams` (fallback handles)

**What could go wrong:**
- If `enabled_methods` is incorrectly populated on the server side, buttons won't render even when they should
  - **Mitigation:** Extensive existing tests for `get_enabled_express_checkout_methods_for_context()`
- If a third-party plugin modifies the context detection logic, buttons might show/hide unexpectedly
  - **Mitigation:** Empty context fallback ensures buttons appear in unknown contexts

## Open Questions

None — the issue has been fully resolved.

## References

- **Linear Issue:** https://linear.app/a8c/issue/WOOPMNT-5763
- **Fix PR:** #11382 (https://github.com/Automattic/woocommerce-payments/pull/11382)
- **Fix Commit:** b58a6e90ab17be368ebbd4b88a99036cc22cafdb
- **Regression Source:** PR #11267, commit d5b3b0d34d99731bc3b0da4ca98315d30b311ac6 (Amazon Pay feature in 10.5.0)
- **Version Affected:** 10.5.0, 10.5.1, 10.6.0-rc
- **Version Fixed:** 10.6.0 (final release, merged Feb 23 2026)

## Notes for Future Maintainers

**Pattern to follow when adding new express checkout methods:**

When adding a new express checkout method that integrates with WooCommerce Blocks:

1. **Always check location settings in `canMakePayment`:**
   ```js
   canMakePayment: ({ cart }) => {
       if (typeof wcpayExpressCheckoutParams === 'undefined') {
           return false;
       }

       // Check location settings
       const enabledMethods = getExpressCheckoutData('enabled_methods') ?? [];
       if (!enabledMethods.includes('your_method_id')) {
           return false;
       }

       return checkPaymentMethodIsAvailable('yourMethod', cart, api);
   }
   ```

2. **Add corresponding server-side logic:**
   - Update `WC_Payments_Express_Checkout_Button_Helper::get_enabled_express_checkout_methods_for_context()`
   - Add check for your method: `if ($this->can_use_your_method() && $this->is_express_checkout_method_enabled_at($context, 'your_method_id'))`

3. **Add location settings to gateway settings:**
   - Add checkboxes to `express_checkout_cart_methods`, `express_checkout_checkout_methods`, `express_checkout_product_methods` arrays

4. **Write tests:**
   - JS test: verify `canMakePayment` respects `enabled_methods`
   - PHP test: verify config generation includes method based on context

This pattern ensures merchants' display preferences are always respected across all page types.
