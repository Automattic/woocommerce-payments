# Migration Guide: Legacy E2E Tests to QIT

This document provides a step-by-step guide for migrating existing Playwright E2E tests from `tests/e2e/specs/` to the new QIT-based structure in `tests/qit/e2e/`.

## Migration Benefits

Moving to QIT provides:

1. **Simplified Setup**: No Docker containers or complex environment management
2. **Built-in Helpers**: Authentication, WP-CLI, environment management out of the box
3. **Better Compatibility Testing**: Easy testing across WC/WP/PHP version matrices
4. **Production Testing**: Direct testing against production environments
5. **Cross-plugin Testing**: Test WooPayments alongside other plugins
6. **Improved Debugging**: Better screenshot capture and test reporting

## Migration Steps

### 1. Identify Test to Migrate

Choose tests from:
- `tests/e2e/specs/basic.spec.ts` ✅ (Completed)
- `tests/e2e/specs/wcpay/merchant/` (In Progress)
- `tests/e2e/specs/wcpay/shopper/`
- `tests/e2e/specs/subscriptions/`

### 2. Create New QIT Test File

**Naming Convention**:
- Remove directory structure: `wcpay/merchant/merchant-orders.spec.ts` → `merchant-orders.spec.js`
- Use JavaScript instead of TypeScript for QIT compatibility
- Keep descriptive names for easy identification

### 3. Update Imports and Setup

**Before (Legacy)**:
```typescript
import { test, expect } from '@playwright/test';
import { useMerchant, useShopper } from '../../../utils/helpers';

test.describe('Test Suite', () => {
    useMerchant(); // or useShopper()

    test('Test case', async ({ page }) => {
        // test code
    });
});
```

**After (QIT)**:
```javascript
/**
 * External dependencies
 */
import { test, expect } from '@playwright/test';
import qit from '/qitHelpers';

/**
 * Test description and migration notes
 */
test.describe('Test Suite', () => {
    test('Test case', async ({ page, testInfo }) => {
        await qit.loginAsAdmin(page); // or qit.loginAs()
        // test code with QIT helpers
    });
});
```

### 4. Replace Authentication Patterns

| Legacy Pattern | QIT Replacement |
|---|---|
| `useMerchant()` | `await qit.loginAsAdmin(page)` |
| `useShopper()` | `await qit.loginAs(page, 'username', 'password')` |
| Custom user creation | `await qit.wp('user create ...')` |

### 5. Update URL Patterns

**Before (Legacy)**:
```typescript
await page.goto('http://localhost:8084/wp-admin/admin.php?page=wc-admin');
await page.goto(BASE_URL + '/my-account');
```

**After (QIT)**:
```javascript
await page.goto('/wp-admin/admin.php?page=wc-admin');
await page.goto('/my-account');
```

### 6. Replace Custom Setup with QIT Helpers

**Before (Legacy)**:
```typescript
// Complex setup in utils/helpers.ts
await createTestProduct();
await configurePaymentGateway();
await setupTestUser();
```

**After (QIT)**:
```javascript
// Simple WP-CLI commands
await qit.wp('wc product create --name="Test" --type=simple --regular_price=10.00 --porcelain');
await qit.wp('option update woocommerce_woocommerce_payments_settings \'{"enabled":"yes"}\'');
await qit.wp('user create testuser test@example.com --role=customer --user_pass=testpass');
```

### 7. Add Enhanced Debugging

**New in QIT**:
```javascript
test('Test case', async ({ page, testInfo }) => {
    // Add screenshots at key points
    await qit.attachScreenshot(
        'step-description',
        { context: 'additional-info' },
        page,
        testInfo
    );

    // Use test steps for better organization
    await test.step('Step description', async () => {
        // test actions
    });
});
```

### 8. Update Environment Variable Usage

**Before (Legacy)**:
```typescript
const { BASE_URL, NODE_ENV } = process.env;
```

**After (QIT)**:
```javascript
const siteId = qit.getEnv('E2E_JP_SITE_ID');
const nodeEnv = qit.getEnv('NODE_ENV');
```

### 9. Handle Test Dependencies

**Before (Legacy)**:
```typescript
// Complex bootstrap setup in multiple files
```

**After (QIT)**:
```javascript
test.beforeEach(async ({ page }) => {
    // Simple setup using QIT helpers
    await qit.wp('cache flush');
    await qit.wp('option update setting_name value');
});
```

## Common Migration Patterns

### Merchant Tests Migration

**Legacy Structure**:
```
tests/e2e/specs/wcpay/merchant/
├── merchant-admin-account-balance.spec.ts
├── merchant-admin-analytics.spec.ts
├── merchant-orders-refund.spec.ts
└── ...
```

**QIT Structure**:
```
tests/qit/e2e/
├── merchant-admin-account-balance.spec.js
├── merchant-admin-analytics.spec.js
├── merchant-orders-refund.spec.js
└── ...
```

### Shopper Tests Migration

**Before (Legacy)**:
```typescript
test.describe('Checkout Tests', () => {
    useShopper();

    test('Complete checkout', async ({ page }) => {
        await page.goto('/checkout/');
        // checkout steps
    });
});
```

**After (QIT)**:
```javascript
test.describe('Checkout Tests', () => {
    test('Complete checkout', async ({ page, testInfo }) => {
        // Create customer if needed
        await qit.wp('user create customer customer@test.com --role=customer --user_pass=testpass');
        await qit.loginAs(page, 'customer', 'testpass');

        await page.goto('/checkout/');
        // checkout steps with enhanced debugging
        await qit.attachScreenshot('checkout-loaded', {}, page, testInfo);
    });
});
```

### Test Data Setup

**Before (Legacy)**:
```typescript
// Reliant on pre-existing test data or complex setup scripts
```

**After (QIT)**:
```javascript
test.beforeEach(async () => {
    // Create test data on demand
    const productId = await qit.wp(
        'wc product create --name="Test Product" --type=simple --regular_price=10.00 --status=publish --porcelain'
    );

    // Set up payment gateway
    await qit.wp('option update woocommerce_woocommerce_payments_settings \'{"enabled":"yes","test_mode":"yes"}\'');
});
```

## Test Tags and Organization

Add consistent tagging to organize tests:

```javascript
test('Test case', {
    tag: ['@critical', '@merchant', '@payments'],
}, async ({ page, testInfo }) => {
    // test implementation
});
```

**Available Tags**:
- `@critical`: Core functionality tests
- `@merchant`: Admin/merchant-facing tests
- `@shopper`: Customer-facing tests
- `@payments`: Payment-related tests
- `@subscriptions`: Subscription functionality
- `@blocks`: WooCommerce Blocks integration
- `@todo`: Tests needing implementation
- `@skip`: Temporarily disabled tests

## Migration Checklist

For each test being migrated:

- [ ] Create new `.spec.js` file in `tests/qit/e2e/`
- [ ] Update imports to use QIT helpers
- [ ] Replace authentication with `qit.loginAsAdmin()` or `qit.loginAs()`
- [ ] Convert absolute URLs to relative paths
- [ ] Replace custom setup with `qit.wp()` commands
- [ ] Add `testInfo` parameter and screenshot attachments
- [ ] Use `test.step()` for better organization
- [ ] Add appropriate test tags
- [ ] Test the migration by running with `npm run test:qit-e2e`
- [ ] Update or remove the original legacy test file

## Testing the Migration

### Local Testing

```bash
# Test specific file
qit run:e2e woocommerce-payments ./tests/qit/e2e/your-migrated-test.spec.js --source ./woocommerce-payments.zip

# Test with UI for debugging
npm run test:qit-e2e-ui

# Test all migrated tests
npm run test:qit-e2e
```

### Validation Steps

1. **Functionality**: Ensure test covers same functionality as original
2. **Performance**: Verify test runs faster due to simplified setup
3. **Debugging**: Check that screenshots and logs provide good debugging info
4. **Compatibility**: Test with different WC/PHP versions using QIT configuration

## Troubleshooting Common Issues

### QIT Helper Import Errors
The `/qitHelpers` import may show lint errors but works in QIT environment.

### Authentication Issues
Ensure E2E credentials are properly set in `local.env`:
```env
E2E_JP_SITE_ID=your_site_id
E2E_JP_BLOG_TOKEN=your_blog_token
E2E_JP_USER_TOKEN=your_user_token
```

### Test Timing Issues
QIT environment may be faster/slower than local Docker. Adjust timeouts as needed:
```javascript
await expect(element).toBeVisible({ timeout: 30000 });
```

### WordPress CLI Issues
Some WP-CLI commands may behave differently. Test commands individually:
```javascript
// Test WP-CLI command
const result = await qit.wp('option get woocommerce_store_address');
console.log('Store address:', result);
```

## Migration Priority

Suggested order for migration:

1. **Basic tests** ✅ (Completed)
2. **Critical merchant tests** (In Progress)
3. **Critical shopper tests**
4. **Payment method specific tests**
5. **Admin interface tests**
6. **Subscription tests** (if applicable)
7. **Edge case and error handling tests**

## Getting Help

- Check QIT documentation: https://qit.woo.com/docs/
- Review existing migrated tests for patterns
- Use `npm run test:qit-e2e-ui` for visual debugging
- Test individual QIT commands with `qit wp 'command'`
