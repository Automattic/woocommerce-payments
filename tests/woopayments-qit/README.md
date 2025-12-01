# WooPayments QIT E2E Test Packages

This directory contains end-to-end tests for WooPayments, organized as QIT test subpackages.

## Package Structure

```
woopayments-qit/
├── shared/          # Utility package: bootstrap, config, utilities
│   ├── bootstrap/   # Environment setup scripts
│   ├── config/      # Test configuration
│   ├── fixtures/    # Playwright fixtures
│   ├── utils/       # Test utilities
│   └── qit-helpers/ # QIT helper utilities
├── shopper/         # Test package: customer checkout tests
│   ├── specs/       # Shopper test specs
│   └── package.json # Package configuration
├── merchant/        # Test package: admin/merchant tests (coming soon)
└── subscriptions/   # Test package: subscription tests (coming soon)
```

## Running Tests

### Individual Package

Run tests from a specific package:

```bash
# Run shopper tests
cd shopper
npm ci
npx playwright install chromium --with-deps
npx playwright test
```

### With QIT

Run using QIT CLI:

```bash
# Run shopper tests via QIT
qit run:e2e woocommerce-payments ./shopper \
  --source path/to/woocommerce-payments.zip \
  --env E2E_JP_SITE_ID=your_site_id \
  --env E2E_JP_BLOG_TOKEN=your_blog_token \
  --env E2E_JP_USER_TOKEN=your_user_token
```

## Package Details

### Shared Utility Package (`shared/`)

Contains shared resources used by all test packages:
- **bootstrap/**: Environment setup scripts for WP/WC configuration
- **config/**: Test configuration (users, products, cards)
- **fixtures/**: Playwright fixtures for authentication
- **utils/**: Test helper utilities (shopper, merchant, devtools)
- **qit-helpers/**: QIT-specific utilities for WP-CLI and REST API

### Shopper Test Package (`shopper/`)

E2E tests for customer checkout flows:
- Basic checkout purchases
- Card payment failures
- 3DS authentication
- Coupon handling
- Saved card functionality
- UPE payment methods
- Site editor checkout

## Configuration

### Required Secrets

The following environment variables are required:

- `E2E_JP_SITE_ID` - Jetpack site ID
- `E2E_JP_BLOG_TOKEN` - Jetpack blog token
- `E2E_JP_USER_TOKEN` - Jetpack user token

### Plugin Dependencies

- `woocommerce` >= 8.0.0
- `jetpack` >= 12.0.0

## Development

### Run Tests with UI

```bash
cd shopper
npx playwright test --headed
```

### Debug Tests

```bash
cd shopper
npx playwright test --debug
```

### Run Specific Tests

```bash
cd shopper
npx playwright test specs/shopper-checkout-purchase.spec.ts
```

## Adding New Test Packages

To add a new test package (e.g., merchant tests):

1. Create directory: `mkdir merchant`
2. Create `qit-test.json` manifest
3. Create `package.json` with dependencies
4. Create `playwright.config.js`
5. Add specs in `specs/` directory
6. Import shared utilities from `../../shared/`

## Test Results

Test results are output in the following formats:

- **CTRF JSON**: `./results/ctrf.json` (required by QIT)
- **HTML Report**: `./results/html/index.html`
- **Artifacts**: `./results/blob/` (screenshots, videos, traces)

## Additional Resources

- [QIT Documentation](https://qit.woo.com/docs/test-packages)
- [Playwright Documentation](https://playwright.dev)
- [GETTING_STARTED.md](./GETTING_STARTED.md) - Detailed setup guide
- [CODE_CHANGES.md](./CODE_CHANGES.md) - Migration notes
