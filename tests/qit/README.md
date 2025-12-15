## WooPayments QIT Tests

We use the [QIT toolkit](https://qit.woo.com/docs/) for automated testing including security, PHPStan, and E2E tests.

### Setup

1. Create `local.env` inside the `tests/qit/config/` directory by copying the variables from `default.env`.
2. Use standard `KEY=VALUE` format (no `export` keyword needed).
3. Configure the required credentials:
   - **QIT authentication**: Get credentials from the [secret store](https://mc.a8c.com/secret-store/?secret_id=11043). These authenticate you with the QIT service.
   - **E2E Jetpack credentials** (`E2E_JP_SITE_ID`, `E2E_JP_BLOG_TOKEN`, `E2E_JP_USER_TOKEN`): Get these from a Jurassic Ninja site already onboarded in test mode.
4. Once configured, the first time you run a test command, it will create a local auth file for subsequent runs.

#### Note on qit-cli version

The project uses `woocommerce/qit-cli:dev-trunk` in `composer.json` because [test packages](https://qit.woo.com/docs/test-packages/) (used for E2E tests) are not yet available in stable releases.

Since `qit-cli` requires PHP 7.4+ but the project has `config.platform.php: 7.3` to ensure production compatibility, all `composer install` commands in the project use `--ignore-platform-req=php`. This is safe because `qit-cli` is a dev-only tool that never ships to users.

### Running Tests

#### Security and PHPStan tests

```bash
npm run test:qit-security
npm run test:qit-phpstan
npm run test:qit-phpstan-local  # Against local development build
```

#### E2E Tests

E2E tests use the [QIT Test Packages](https://qit.woo.com/docs/test-packages/) approach. Tests are located in `tests/qit/test-package/`.

Before running E2E tests, build the plugin package:

```bash
npm run build:release
```

This creates `woocommerce-payments.zip` which is used by QIT. Then run the tests with the required environment variables:

```bash
# Run all E2E tests (prepend with env vars from local.env)
E2E_JP_SITE_ID='<value>' E2E_JP_BLOG_TOKEN='<value>' E2E_JP_USER_TOKEN='<value>' npm run test:qit-e2e

# Run specific test file (passthrough to Playwright)
E2E_JP_SITE_ID='<value>' E2E_JP_BLOG_TOKEN='<value>' E2E_JP_USER_TOKEN='<value>' npm run test:qit-e2e -- -- shopper-checkout-purchase.spec.ts
# The first -- passes args to npm script, second -- passes to Playwright

# Run tests filtered by tag (e.g., @blocks, @shopper)
E2E_JP_SITE_ID='<value>' E2E_JP_BLOG_TOKEN='<value>' E2E_JP_USER_TOKEN='<value>' npm run test:qit-e2e -- -- --grep "@blocks"

# Run a specific test project (e.g., merchant, shopper)
E2E_JP_SITE_ID='<value>' E2E_JP_BLOG_TOKEN='<value>' E2E_JP_USER_TOKEN='<value>' npm run test:qit-e2e -- -- --project=merchant
# Available projects are defined in playwright.config.js
```

**Tip:** You can export the variables once per shell session instead of prepending each command:

```bash
set -a && source tests/qit/config/local.env && set +a
npm run test:qit-e2e
```

### Analyzing Results

- Once the test run completes, you'll see a result URL along with the test summary.
- Look at any errors that might have been surfaced and associate with PRs that introduced them using `git blame`.
- Ping the author for fixing the error, or fix it yourself if it is straightforward enough.
- For failed tests, check the artifacts directory for screenshots and error context.

### Troubleshooting

#### "Card testing attempt detected" errors

If checkout tests fail with "Card testing attempt detected" errors, the test account may need server-side configuration to disable fraud protection for E2E testing. Contact the payments team for assistance.
