# WooCommerce Payments E2E Test Package

E2E test package for WooCommerce Payments checkout flows, payment methods, and Jetpack integration.

## Quick Start

### Prerequisites

- QIT CLI installed (`composer global require "woocommerce/qit-cli:*"`)
- WooCommerce Payments plugin built (`.zip` file)
- Jetpack credentials (site ID, blog token, user token)

### Run Tests Locally

```bash
# From the woocommerce-payments directory, build the plugin
cd /path/to/woocommerce-payments
npm run build:release  # Creates woocommerce-payments.zip

# Run the test package
cd /path/to/woocommerce-payments-e2e-tests
qit run:e2e woocommerce-payments . \
  --source ../woocommerce-payments/woocommerce-payments.zip \
  --env E2E_JP_SITE_ID=your_site_id \
  --env E2E_JP_BLOG_TOKEN=your_blog_token \
  --env E2E_JP_USER_TOKEN=your_user_token
```

### Using qit.json

Alternatively, configure secrets in `qit.json` and run:

```bash
qit run:e2e woocommerce-payments . --config qit.json
```

## Structure

```
woocommerce-payments-e2e-tests/
├── qit-test.json              # Package manifest
├── package.json               # NPM dependencies
├── playwright.config.js       # Playwright configuration
├── qit.json                   # QIT configuration for local runs
├── bootstrap/
│   ├── setup.sh               # Environment setup script
│   ├── class-wp-cli-qit-dev-command.php
│   ├── qit-jetpack-connection.php
│   └── qit-jetpack-status.php
├── specs/                     # Test specs
│   ├── basic.spec.ts
│   └── woopayments/
│       └── shopper/
├── utils/                     # Test utilities
│   ├── shopper.ts
│   ├── merchant.ts
│   ├── devtools.ts
│   └── helpers.ts
├── fixtures/                  # Playwright fixtures
│   └── auth.ts
├── config/                    # Configuration files
│   ├── default.ts
│   └── users.json
└── results/                   # Test results output
```

## Configuration

### Required Secrets

The following environment variables are required:

- `E2E_JP_SITE_ID` - Jetpack site ID
- `E2E_JP_BLOG_TOKEN` - Jetpack blog token
- `E2E_JP_USER_TOKEN` - Jetpack user token

### Plugin Dependencies

- `woocommerce` >= 8.0.0
- `jetpack` >= 12.0.0

## Lifecycle Phases

1. **globalSetup**: Runs `bootstrap/setup.sh` to configure the WordPress environment
   - Installs WordPress importer and sample products
   - Configures WooCommerce settings
   - Sets up Jetpack connection
   - Creates test users

2. **setup**: Installs NPM dependencies and Playwright browsers
   ```bash
   npm ci
   npx playwright install chromium --with-deps
   ```

3. **run**: Executes Playwright tests
   ```bash
   npx playwright test
   ```

4. **teardown**: (Empty - no cleanup needed)

5. **globalTeardown**: (Empty - no cleanup needed)

## Test Results

Test results are output in the following formats:

- **CTRF JSON**: `./results/ctrf.json` (required by QIT)
- **HTML Report**: `./results/html/index.html`
- **Artifacts**: `./results/blob/` (screenshots, videos, traces)

## Development

### Run Tests with UI

```bash
npm run test:headed
```

### Debug Tests

```bash
npm run test:debug
```

### Run Specific Tests

```bash
npx playwright test specs/woopayments/shopper/shopper-checkout-purchase.spec.ts
```

## Publishing

To publish this test package to the QIT registry:

```bash
qit package:publish .
```

Once published, others can run your tests:

```bash
qit run:e2e their-plugin \
  --test-package woocommerce-payments/wcpay-e2e-tests:1.0.0
```

## Migration Notes

This test package was migrated from the legacy custom tests format. See [MIGRATION.md](./MIGRATION.md) for details on the migration process and how to migrate your own tests.

## Troubleshooting

### Tests can't authenticate with WooPayments

Ensure Jetpack credentials are correct and valid:
- Check that `E2E_JP_SITE_ID`, `E2E_JP_BLOG_TOKEN`, and `E2E_JP_USER_TOKEN` are set
- Verify the credentials are from the correct environment (sandbox vs production)

### Bootstrap setup fails

Check the bootstrap logs for specific errors:
- WordPress/WooCommerce installation issues
- Jetpack connection problems
- Sample data import failures

### Tests timeout

Increase the timeout in `qit-test.json`:
```json
{
    "timeout": 3600
}
```

Or in `playwright.config.js`:
```javascript
{
    timeout: 180 * 1000  // 3 minutes
}
```

## Additional Resources

- [QIT Documentation](https://qit.woo.com/docs/test-packages)
- [Playwright Documentation](https://playwright.dev)
- [Migration Guide](./MIGRATION.md)

## Support

- **Issues**: Report issues at https://github.com/woocommerce/qit-cli/issues
- **Contact**: qit@woocommerce.com
