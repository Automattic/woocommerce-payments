# Testing Patterns — Reference Guide

Conventions and patterns for writing tests in WooPayments. Read this before writing or modifying tests.

## PHP Unit Tests

### Base Class

All PHP tests extend `WCPAY_UnitTestCase` (in `tests/WCPAY_UnitTestCase.php`), which extends `WP_UnitTestCase`.

**What the base class provides:**
- Intercepts all external HTTP requests (returns 503 by default — forces proper mocking)
- Mocks geolocation data from `tests/unit/test-data/ip-geolocation.json`
- `mock_wcpay_request()` — the primary way to mock API requests (see below)
- Reflection helpers: `get_payment_gateway_map()`, `set_payment_gateway_map()`, etc.

### Mocking WCPay Requests

**Always use `mock_wcpay_request()` for API calls.** This is the standard pattern:

```php
// Mock a Get_Intention request that returns a specific intent
$payment_intent = WC_Helper_Intention::create_intention( [
    'status' => Intent_Status::PROCESSING,
    'metadata' => [ 'order_id' => (string) $order->get_id() ],
] );

$request = $this->mock_wcpay_request(
    Get_Intention::class,    // Request class to mock
    1,                       // Expected number of calls
    'pi_mock',               // Constructor ID (intent_id)
    $payment_intent          // Response to return
);

// Now call the code that triggers the request
$this->card_gateway->process_redirect_payment( $order, 'pi_mock', false );
```

**Method signature:**
```php
mock_wcpay_request(
    string $request_class,
    int $total_api_calls = 1,
    $request_class_constructor_id = null,
    $response = null,
    $api_client_mock = null,
    $http_mock = null,
    bool $force_request_mock = false
): Request|MockObject
```

### Test Setup Pattern

```php
class My_Feature_Test extends WCPAY_UnitTestCase {

    public function set_up() {
        parent::set_up();  // Always call parent

        // 1. Backup global state
        $this->original_gateway_map = $this->get_payment_gateway_map();

        // 2. Create mocks
        $this->mock_api_client = $this->getMockBuilder( 'WC_Payments_API_Client' )
            ->disableOriginalConstructor()
            ->setMethods( [ 'is_server_connected' ] )
            ->getMock();

        // 3. Configure mock behavior
        $this->mock_api_client->expects( $this->any() )
            ->method( 'is_server_connected' )
            ->willReturn( true );

        // 4. Replace container services if needed
        wcpay_get_test_container()->replace( SomeService::class, $mock_service );
    }

    public function tear_down() {
        // 1. Restore global state
        $this->set_payment_gateway_map( $this->original_gateway_map );

        // 2. Reset container
        wcpay_get_test_container()->reset_all_replacements();

        // 3. Clean up options/transients
        delete_option( 'woocommerce_woocommerce_payments_settings' );

        parent::tear_down();  // Always call parent
    }
}
```

**Important:** Use `set_up()` and `tear_down()` (snake_case), not `setUp()` and `tearDown()`.

### Test Helpers

Located in `tests/unit/helpers/`:

| Helper | Key Methods |
|--------|-------------|
| `WC_Helper_Order` | `create_order($customer_id, $total, $product)` — creates complete test order with shipping/billing |
| `WC_Helper_Intention` | `create_intention($data)` — creates mock payment intent (default: 5000 cents, USD, succeeded) |
| `WC_Helper_Intention` | `create_charge($data)` — creates mock charge object |
| `WC_Helper_Intention` | `create_setup_intention($data)` — creates mock setup intent |
| `WC_Helper_Product` | `create_simple_product()` — creates WC_Product |
| `WC_Helper_Subscription` | Creates subscription products and customer subscriptions |
| `WC_Helper_Token` | Creates payment method tokens |

### Running PHP Tests

```bash
npm run test:php                              # All tests in Docker
npm run test:php -- --filter=ClassName        # Specific test class
npm run test:php -- --filter=test_method_name # Specific test method
npm run test:php-watch                        # Watch mode
```

## JavaScript Tests

### Configuration

- Config: `tests/js/jest.config.js`
- Uses `@wordpress/scripts` Jest preset
- Module alias: `wcpay` maps to `client/` (so `import { useSettings } from 'wcpay/data'` works in tests)
- MSW (Mock Service Worker) set up globally for API mocking

### Test File Location

Co-located with source: `client/feature-area/__tests__/component-name.test.tsx`

### Standard Pattern

```tsx
import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import MyComponent from '../my-component';
import { useMyHook } from 'wcpay/data';

// 1. Mock hooks/modules
jest.mock( 'wcpay/data', () => ( {
    useMyHook: jest.fn(),
} ) );

const mockUseMyHook = useMyHook as jest.MockedFunction< typeof useMyHook >;

describe( 'MyComponent', () => {
    beforeEach( () => {
        // 2. Configure mock return values
        mockUseMyHook.mockReturnValue( [ 'value', jest.fn() ] );
    } );

    it( 'renders correctly', () => {
        render( <MyComponent /> );
        expect( screen.getByText( 'Expected text' ) ).toBeInTheDocument();
    } );

    it( 'handles user interaction', () => {
        const setter = jest.fn();
        mockUseMyHook.mockReturnValue( [ 'old', setter ] );

        render( <MyComponent /> );
        fireEvent.click( screen.getByRole( 'button', { name: 'Submit' } ) );

        expect( setter ).toHaveBeenCalledWith( 'new-value' );
    } );
} );
```

### Context Providers in Tests

When testing components that need context:

```tsx
const renderWithProvider = ( ui ) =>
    render(
        <WCPaySettingsContext.Provider
            value={ { featureFlags: { amazonPay: false } } }
        >
            { ui }
        </WCPaySettingsContext.Provider>
    );
```

### MSW for API Mocking

MSW is configured globally in `tests/js/jest-msw-setup.js`. To mock specific API calls in a test:

```tsx
import { rest } from 'msw';
import { server } from '../../tests/js/utilities/msw-server';

beforeEach( () => {
    server.use(
        rest.get( '/wc/v3/payments/settings', ( req, res, ctx ) => {
            return res( ctx.json( { enabled: true } ) );
        } )
    );
} );
```

### Running JS Tests

```bash
npm run test:js                     # All tests
npm run test:watch                  # Watch mode
npm run test:js -- --testPathPattern=settings  # Tests matching pattern
npm run test:update-snapshots       # Update snapshots
```

## E2E Tests (Playwright)

### Configuration

- Config: `tests/e2e/playwright.config.ts`
- Tests in `tests/e2e/specs/`
- Single worker, 120s timeout per test
- Screenshots on failure, video on first retry

### Auth Pattern

Auth state is cached in `tests/e2e/.auth/` (merchant.json, customer.json). Refreshed every 3 hours or when `CI` env var is set.

Setup project in `tests/e2e/specs/auth.setup.ts` runs before test projects.

### Test Pattern

```typescript
import { test, expect, Page } from '@playwright/test';
import { getMerchant, getShopper } from '../../../utils/helpers';

test.describe( 'Feature Name', { tag: '@critical' }, () => {
    let merchantPage: Page;
    let shopperPage: Page;

    test.beforeAll( async ( { browser } ) => {
        merchantPage = ( await getMerchant( browser ) ).merchantPage;
        shopperPage = ( await getShopper( browser ) ).shopperPage;
    } );

    test( 'does the thing', async () => {
        // Navigate
        await merchantPage.goto( '/wp-admin/admin.php?page=wc-settings' );

        // Act
        await merchantPage.getByRole( 'button', { name: 'Save' } ).click();

        // Assert
        await expect( merchantPage.getByText( 'Settings saved' ) ).toBeVisible();
    } );
} );
```

### Running E2E Tests

```bash
npm run test:e2e                    # Run all
npm run test:e2e-ui                 # Playwright UI mode
npm run test:e2e-up                 # Set up test environment
npm run test:e2e-down               # Tear down
```

## Test Fixtures

Located in `tests/fixtures/captured-payments/`:
- `foreign-card.json`, `fx.json`, `subscription.json`, `jpy-payment.json`, etc.
- Contain realistic Stripe-like response data with fee breakdowns

## Key Conventions

1. **Isolation:** Tests never call external APIs. All HTTP is mocked.
2. **State cleanup:** Every test restores global state in `tear_down()`. Reset container, options, and WC session.
3. **Use helpers:** Don't manually construct orders/intents — use `WC_Helper_Order` and `WC_Helper_Intention`.
4. **Use `mock_wcpay_request()`:** This is the standard for mocking any WCPay server request. Don't mock the API client directly for request testing.
5. **Data providers:** Use `@dataProvider` (PHP) or `it.each()` (JS) for parametric tests.
6. **Snake_case lifecycle:** PHP uses `set_up()` / `tear_down()`, not `setUp()` / `tearDown()`.
