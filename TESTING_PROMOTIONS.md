# Testing Instructions for Promotions Feature

## Overview

This PR adds client-side infrastructure to fetch and manage payment method promotions. Since the wcpay-server endpoints don't exist yet, you'll need to apply a test patch with mock data.

## Setup

1. Checkout this branch and run `npm install && npm start`
2. Apply the test patch:
   ```bash
   curl -o promotions-testing.patch https://gist.githubusercontent.com/dmallory42/152ba4be3dd00c6f3177fe0bdeb38ec4/raw/promotions-testing.patch
   git apply promotions-testing.patch
   ```

## Testing

1. **Navigate to WooPayments > Overview**
   - You should see a "🧪 Promotions Test Display" card with 2 sample promotions

2. **Test Activate**
   - Click "Activate" on a promotion and confirm
   - Should show success notice and move promotion to "Active Promotions" section

3. **Test Dismiss**
   - Click "Dismiss" on a promotion and confirm
   - Should show success notice and remove promotion from list

4. **Test Persistence**
   - Refresh the page after activating a promotion
   - The activated promotion should remain in "Active Promotions" with the same timestamp

## What's Being Tested

- REST controller with 3 endpoints (GET, POST activate, POST dismiss)
- 5-minute caching with WordPress transients
- Local state persistence for activated/dismissed promotions
- Complete TypeScript data layer with Redux integration
- React hooks: `usePromotions()` and `usePromotionActions()`
- PHP unit tests for controller helper methods

## Cleanup

After testing, remove the test patches:

```bash
git restore includes/admin/class-wc-rest-payments-promotions-controller.php client/overview/index.js
rm client/overview/promotions-test-display.tsx promotions-testing.patch
```

## Notes

- The implementation is production-ready and will work automatically when wcpay-server endpoints are available
- Just remove the test patches - no other code changes needed
- View the test patch at: https://gist.github.com/dmallory42/152ba4be3dd00c6f3177fe0bdeb38ec4
