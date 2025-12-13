## WooCommerce Payments QIT tests

We use the [QIT toolkit](https://qit.woo.com/docs/) for automated testing including security, PHPStan, and E2E tests.

#### Setup
- Create `local.env` inside the `tests/qit/config/` directory by copying the variables from `default.env`.
- To get the actual values for local config, refer to this [secret store](https://mc.a8c.com/secret-store/?secret_id=11043) link.
- Use standard `KEY=VALUE` format (no `export` keyword needed).
- Once configured, the first time you run a test command, it will create a local auth file for subsequent runs.

#### Running Tests

**Security and PHPStan tests:**
```bash
npm run test:qit-security
npm run test:qit-phpstan
npm run test:qit-phpstan-local  # Against local development build
```

**E2E tests:**
```bash
# Run all E2E tests
npm run test:qit-e2e

# Run specific test file
npm run test:qit-e2e tests/qit/e2e/specs/woopayments/shopper/shopper-checkout-purchase.spec.ts

# Run tests with specific tag
npm run test:qit-e2e -- --tag=@basic
```

**Note:** E2E tests require valid Jetpack credentials in `local.env` (`E2E_JP_SITE_ID`, `E2E_JP_BLOG_TOKEN`, `E2E_JP_USER_TOKEN`).

- The commands use `build:release` to create `woocommerce-payments.zip` at the root of the directory which is then uploaded and used for the QIT tests.


#### Analysing results
- Once the test run is done, you'll see a result URL along with the test summary.
- Look at any errors that might have been surfaced and associate with PRs that has introduced the same by using `git blame`.
- Ping the author for fixing the error, or fix it yourself if it is straightforward enough.
