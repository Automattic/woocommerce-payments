## Summary

This Linear issue (WOOPMNT-5763) was **already fixed and released**. No new changes are required.

The fix was implemented in PR #11382 (commit b58a6e90a) by @rtiodev on February 23, 2026, and released in version 10.6.0 on March 4, 2026.

Closes: WOOPMNT-5763

## Issue Details

**Problem:** Express checkout buttons (Apple Pay, Google Pay, Amazon Pay) displayed on block-based cart pages even when "Show on cart page" setting was unchecked.

**Root Cause:** The JavaScript `canMakePayment` functions in `client/express-checkout/blocks/index.js` did not check the `enabled_methods` array before rendering buttons.

## Existing Fix

The following changes are already present in the `develop` branch:

### Frontend (`client/express-checkout/blocks/index.js`)
- Added `enabled_methods` check to all three express payment methods
- Each `canMakePayment` function now verifies the method is in the enabled list before rendering

### Backend (`includes/express-checkout/class-wc-payments-express-checkout-button-handler.php`)
- Updated `get_express_checkout_params()` to use context-aware checks
- Properly passes `enabled_methods` array based on current page context (cart/checkout/product)

### Tests
- Comprehensive test coverage added in `client/express-checkout/blocks/__tests__/index.test.js`
- PHP unit tests updated in `tests/unit/express-checkout/test-class-wc-payments-express-checkout-button-handler.php`

### Changelog
- Entry was added as `changelog/agent-woopmnt-5763` and consumed during 10.6.0 release

## Verification

```bash
# Confirm fix commit exists
$ git log --oneline --all --grep="11382"
b58a6e90a fix: Respect express checkout location settings on block-based cart (#11382)

# Verify enabled_methods checks are present
$ grep -n "enabled_methods" client/express-checkout/blocks/index.js
77:  const enabledMethods = getExpressCheckoutData( 'enabled_methods' ) ?? [];
112: const enabledMethods = getExpressCheckoutData( 'enabled_methods' ) ?? [];
144: const enabledMethods = getExpressCheckoutData( 'enabled_methods' ) ?? [];
```

## Deviations from Plan

None — the plan correctly identified that this issue was already fixed. This branch was created for automated verification, but no new implementation was needed.

## Open Questions

None. The fix has been thoroughly tested and released.

## References

- **Original PR:** #11382 (https://github.com/Automattic/woocommerce-payments/pull/11382)
- **Fix Commit:** b58a6e90ab17be368ebbd4b88a99036cc22cafdb
- **Released In:** v10.6.0 (March 4, 2026)
- **Linear Issue:** https://linear.app/a8c/issue/WOOPMNT-5763
