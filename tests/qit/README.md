## WooPayments QIT Tests

We use the [QIT toolkit](https://qit.woo.com/docs/) for automated testing including security, PHPStan, and E2E tests.

### Setup

1. Create `local.env` inside the `tests/qit/config/` directory by copying the variables from `default.env`.
2. To get the actual values for local config, refer to this [secret store](https://mc.a8c.com/secret-store/?secret_id=11043) link.
3. Use standard `KEY=VALUE` format (no `export` keyword needed).
4. Once configured, the first time you run a test command, it will create a local auth file for subsequent runs.

### Running Tests

#### Security and PHPStan tests

```bash
npm run test:qit-security
npm run test:qit-phpstan
npm run test:qit-phpstan-local  # Against local development build
```

#### E2E Tests

E2E tests use the [QIT Test Packages](https://qit.woo.com/docs/test-packages/) approach. Tests are located in `tests/qit/test-package/`.

```bash
# Run all E2E tests
npm run test:qit-e2e

# Run tests matching a pattern (e.g., @shopper tag, test name, or file path)
npm run test:qit-e2e:args -- --grep "@shopper"
npm run test:qit-e2e:args -- --grep "shopper-checkout-purchase"
```

You can also run QIT directly for more control:

```bash
# Run all tests
./vendor/bin/qit run:e2e woocommerce-payments \
  --config tests/qit/qit.json \
  --profile=default \
  --env_file tests/qit/config/local.env

# Run specific test file via Playwright options
./vendor/bin/qit run:e2e woocommerce-payments \
  --config tests/qit/qit.json \
  --profile=default \
  --env_file tests/qit/config/local.env \
  --pw_options "woopayments/shopper/shopper-checkout-purchase.spec.ts"

# Run tests with specific tag
./vendor/bin/qit run:e2e woocommerce-payments \
  --config tests/qit/qit.json \
  --profile=default \
  --env_file tests/qit/config/local.env \
  --pw_test_tag="@critical"
```

**Note:** E2E tests require valid Jetpack credentials in `local.env` (`E2E_JP_SITE_ID`, `E2E_JP_BLOG_TOKEN`, `E2E_JP_USER_TOKEN`).

### Analyzing Results

- Once the test run completes, you'll see a result URL along with the test summary.
- Look at any errors that might have been surfaced and associate with PRs that introduced them using `git blame`.
- Ping the author for fixing the error, or fix it yourself if it is straightforward enough.
- For failed tests, check the artifacts directory for screenshots and error context.
