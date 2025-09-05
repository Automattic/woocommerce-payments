# WooPayments end-to-end tests 🎭

WooPayments e2e tests can be found in the `./tests/e2e/specs` directory. These tests run with Playwright and replaced the Puppeteer e2e tests when we completed the migration to Playwright in early 2025.

E2E tests can be run locally or in GitHub Actions. Github Actions are already configured and don't require any changes to run the tests.

## Retry strategy

We don't use Playwright's built-in retries in this repo (retries are `0`). Instead, CI re-runs only the spec files that failed in the first run:

- First run: Execute the full suite and generate a `results.json` file
- Selective re-run: If some specs failed, CI re-runs just those spec files once
- Local runs: No automatic retries

This approach is intentional. Playwright’s `--last-failed` flag was evaluated but led to instability, likely due to some tests depending on previous steps within the same file. For example, a spec may first save a card, then process a payment with it, and finally delete the card. Retrying only the “delete the card” step without the earlier context will fail.

Note: The Playwright config sets `video: on-first-retry`, which applies only if you enable built-in retries locally.

## Dynamic matrix generation

- L-1 policy: Tests run against the latest WooCommerce version and the L-1 (previous major) version
- Dynamic version resolution: Automatically fetches latest WC, RC, and beta versions from WordPress.org API
- Optimized PHP strategy: Reduces job count while maintaining comprehensive coverage
- Business continuity: Maintains support for WC 7.7.0 for significant TPV reasons

CI coverage differences:

- Pull requests: Run against WC latest and WC L-1 on PHP 8.3
- Scheduled/full runs: Include WC 7.7.0 (PHP 7.3), WC L-1 (PHP 8.3), WC latest (PHP 8.3), optional WC beta (PHP 8.3), and WC RC (PHP 8.4 when applicable)

## Setting up & running E2E tests

For running E2E tests locally, create a new file named `local.env` under `tests/e2e/config` folder with the following env variables (replace values as required).

### Required env variables

```bash
# WooPayments Dev Tools Repo
WCP_DEV_TOOLS_REPO='https://github.com/dev-tools-repo.git or git@github.com:org/dev-tools-repo.git'

# Optional to see additional verbose output. Default false.
DEBUG=false
```

### Choose Transact Platform Server instance

It is possible to use the live server or a local docker instance of the Transact Platform Server when testing locally. On GitHub Actions, the live server is used for tests. Add the following env variables to your `local.env` based on your preference (replace values as required).

#### Using Local Server on Docker

By default, the local E2E environment is configured to use the Transact Platform local server instance. Add the following env variables to configure the local server instance.

```bash
# Transact Platform Server Repo
TRANSACT_PLATFORM_SERVER_REPO='https://github.com/server-repo.git or git@github.com:org/server-repo.git'

# Stripe account data. Need to support level 3 data to run tests successfully.
# These values can be obtained from the Stripe Dashboard: https://dashboard.stripe.com/test/apikeys
E2E_WCPAY_STRIPE_TEST_PUBLIC_KEY=<stripe pk_test_xxx>
E2E_WCPAY_STRIPE_TEST_SECRET_KEY=<stripe sk_test_xxx>
# This value can be obtained by running `npm run listen` in your local server, which should print your webhook signature key.
E2E_WCPAY_STRIPE_TEST_WEBHOOK_SIGNATURE_KEY=<stripe whsec_xxx>
# This should be the Stripe Account ID of a connected merchant account. For example, after onboarding an account, you can obtain the ID from WCPay Dev Tools.
E2E_WCPAY_STRIPE_ACCOUNT_ID=<stripe acct_id>
E2E_WOOPAY_BLOG_ID=<WPCOM Site ID for https://pay.woo.com>
```

#### Using Live Server

For using the live server, you'll need to add a Jetpack blog token, user token, & blog id from one of your test sites connected to a WooPayments test account. On a connected test site, you can use the code below to extract the blog id & tokens.

```php
Jetpack_Options::get_option( 'id' );
Jetpack_Options::get_option( 'blog_token' );
Jetpack_Options::get_option( 'user_tokens' );
```

Set the value of `E2E_USE_LOCAL_SERVER` to `false` to enable live server.

Once you have the blog id & tokens, add the following env variables to your `local.env`.

```bash
# Set local server to false for using live server. Default: true.
E2E_USE_LOCAL_SERVER=false

E2E_JP_BLOG_TOKEN='<jetpack_blog_token>'
E2E_JP_USER_TOKEN='<jetpack_user_token>'
E2E_JP_SITE_ID='<blog_id>'
```

### Installing plugins

If you wish to run E2E tests for WC Subscriptions, the following env variables need to be added to your `local.env` (replace values as required).

For the `E2E_GH_TOKEN`, follow [these instructions to generate a GitHub Personal Access Token](https://docs.github.com/en/github/authenticating-to-github/creating-a-personal-access-token) and assign the `repo` scope to it.

```bash
E2E_GH_TOKEN='githubPersonalAccessToken'
WC_SUBSCRIPTIONS_REPO='{owner}/{repo}'
```

### Skipping plugins

If you wish to skip E2E tests for WC Subscriptions, Action Scheduler, or WC Gutenberg Products Blocks, the following env variables need to be added to your `local.env`.

```bash
SKIP_WC_SUBSCRIPTIONS_TESTS=1
SKIP_WC_ACTION_SCHEDULER_TESTS=1
SKIP_WC_BLOCKS_TESTS=1
```

### Using a specific version of WordPress or WooCommerce

To use a specific version of WordPress or WooCommerce for testing, the following env variables need to be added to your `local.env`.

```bash
E2E_WP_VERSION='<wordpress_version>'
E2E_WC_VERSION='<woocommerce_version>'
```

### Initialize E2E docker environment

1. Make sure to run `npm install`, `composer install` and `npm run build:client` before running the setup script.
2. Run the setup script `npm run test:e2e-setup` to spin up E2E environment in docker containers.

After the E2E environment is up, you can access the containers on:

- WC E2E Client: <http://localhost:8084>
- WC E2E Server: <http://localhost:8088> (Available only when using local server)

Note: Be aware that the server port may change in the `docker-compose.e2e.yml` configuration, so when you can't access the server, try running `docker port transact_platform_server_wordpress_e2e 80` to find out the bound port of the E2E server container.

### Running tests

There are two modes for running tests:

1. Headless mode: `npm run test:e2e`. In headless mode the test runner executes all or specified specs without launching a Chromium user interface.
2. UI mode: `npm run test:e2e-ui`. UI mode is interactive and launches a Chromium user interface. It's useful for developing, debugging, and troubleshooting failing tests. For more information about Playwright UI mode, see the [Playwright UI Mode docs](https://playwright.dev/docs/test-ui-mode#introduction).

Additional options:

- Filter by path or glob: `npm run test:e2e tests/e2e/specs/**/checkout*.spec.ts`
- Filter by test title: `npm run test:e2e -- -g "Checkout"` (or `--grep`)
- Update snapshots (optionally with a filter):
  - `npm run test:e2e -- --update-snapshots`
  - `npm run test:e2e -- --update-snapshots tests/e2e/specs/**/deposits*.spec.ts`

#### Running only a single test suite

If you would like to run only one test suite, you can pass the relative path to the test file along with any of the modes mentioned above. e.g. `npm run test:e2e-ui path/to/test.spec.ts`.

#### Running tests in group

By adding additional env variables to your `local.env` file, it is possible to run a group of tests. e.g.

- Adding `E2E_GROUP='wcpay'` and `E2E_BRANCH='merchant'` to your `local.env` file, then running `npm run test:e2e-ui` runs the WooPayments merchant tests for WCPay in UI mode.
- Adding `E2E_GROUP='wcpay'` and `E2E_BRANCH='shopper'` to your `local.env` file, then running `npm run test:e2e-ui` runs WooPayments shopper tests for WCPay in UI mode.
- Adding just `E2E_GROUP='wcpay'` to your `local.env` file, then running `npm run test:e2e-ui` runs WooPayments merchant & shopper tests for WCPay in UI mode.
- Available groups are `wcpay`, `subscriptions`, and `blocks`.
- Available branches are `merchant` and `shopper`.

#### Running tests by tags

You can also run tests using Playwright's tag filtering:

```bash
# Run only WooCommerce Blocks tests
npm run test:e2e -- --grep @blocks

# Run WCPay tests excluding blocks tests
npm run test:e2e wcpay/ -- --grep-invert @blocks

# Run all critical tests (includes blocks tests)
npm run test:e2e -- --grep @critical
```

It is also possible to run the groups using the relative path to the tests. e.g.

- `npm run test:e2e-ui tests/e2e/specs/wcpay/merchant` runs merchant tests for WCPay in UI mode.
- `npm run test:e2e-ui tests/e2e/specs/wcpay/shopper` runs shopper tests for WCPay in UI mode.
- `npm run test:e2e-ui tests/e2e/specs/wcpay` runs merchant & shopper tests for WCPay in UI mode.

Handy utility scripts for managing your E2E environment:

- `npm run test:e2e-down` Stops E2E environment containers.
- `npm run test:e2e-cleanup` Removes fetched dependencies and docker volumes.
- `npm run test:e2e-reset` Stops containers and performs cleanup.
- `npm run test:e2e-up` Starts containers without setting up again.

### Running on Atomic site

For running E2E tests on the Atomic site, follow the same guidelines mentioned above, and add `NODE_ENV=atomic` to your `local.env` file. Then bring up your E2E environment. Lastly, run tests using `npm run test:e2e` or `npm run test:e2e-ui`.

## Writing tests

Place new spec files in the appropriate directory under `tests/e2e/specs`. The directory structure is as follows:

- **Subscriptions Merchant**: `tests/e2e/specs/subscriptions/merchant` - Subscription related tests for the merchant role.
- **Subscriptions Shopper**: `tests/e2e/specs/subscriptions/shopper` - Subscription related tests for the shopper role.
- **WooPayments Merchant**: `tests/e2e/specs/wcpay/merchant` - Tests for the merchant role in WooPayments.
- **WooPayments Shopper**: `tests/e2e/specs/wcpay/shopper` - Tests for the shopper role in WooPayments (includes WooCommerce Blocks tests tagged with `@blocks`).

## Debugging tests

The best way to debug tests is to use the Playwright UI mode. This mode allows you to see the browser and interact with it after the test runs. You can use the locator functionality to help correctly determine the locator syntax to correctly target the HTML element you need. Lastly, you can also use `console.log()` to assist with debugging tests in UI mode. To run tests in UI mode, use the `npm run test:e2e-ui path/to/test.spec` command.

### Understanding test failures and re-runs

In CI, failed spec files are re-run once in a second pass:

1. First run: Execute the full matrix and produce `results.json`
2. Selective re-run: CI re-runs only the spec files that failed
3. Final results: The workflow finishes after the selective re-run and uploads artifacts

## Slack integration

The Slack reporter is a custom reporter that sends e2e test failures to a public Slack channel (search Slack channel ID `CQ0Q6N62D`). The reporter is configured to only send the first failure of a test to Slack. If the retry also fails it will not be sent to prevent spamming the channel.

**Configuration steps:**

1. Create public Slack channel for reporting.
2. [Create Slack app.](https://api.slack.com/apps/)
3. Add OAuth permissions to the app:
    - `chat:write`
    - `files:write`
4. Install app into channel. `Settings > Install App` page.
5. Go to slack channel and manually invite created slack app by mentioning app bot username. User name can be found and configured on app config page `Features > App Home` page.
6. Set following env variables either locally or in CI:

```bash
CI=true
WC_E2E_SCREENSHOTS=1
E2E_SLACK_TOKEN='<bot token, starts with xoxb- >'
E2E_SLACK_CHANNEL_ID='<public slack channel id>'
```

## FAQs

**I'm getting errors that host.docker.internal is not found.**

This is because the `host.docker.internal` alias is not available on Linux. You can use the `localhost` alias instead. To apply it, create a file called `docker-compose.override.yml` in the `tests/e2e` directory and add the following content:

```yaml
services:
  playwright:
    environment:
      - BASE_URL=http://localhost:8084
```

**How do I wait for a page or element to load?**

Since [Playwright automatically waits](https://playwright.dev/docs/actionability) for elements to be present in the page before interacting with them, you probably don't need to explicitly wait for elements to load. For example, all of the following locators will automatically wait for the element to be present and stable before asserting or interacting with it:

```ts
await expect( page.getByRole( 'heading', { name: 'Sign up' } ) ).toBeVisible();
await page.getByRole( 'checkbox', { name: 'Subscribe' } ).check();
await page.getByRole( 'button', { name: /submit/i } ).click();
```

In some cases, you may need to wait for the page to reach a certain load state before interacting with it. You can use `await page.waitForLoadState( 'domcontentloaded' );` to wait for the page to finish loading.

**What is the best way to target elements in the page?**

Prefer the use of [user-facing attribute or test-id locators](https://playwright.dev/docs/locators#locating-elements) to target elements in the page. This will make the tests more resilient to changes to implementation details, such as class names.

```ts
// Prefer locating by role, label, text, or test id when possible. See https://playwright.dev/docs/locators
await page.getByRole( 'button', { name: 'All payouts' } ).click();
await page.getByLabel( 'Select a deposit status' ).selectOption( 'Pending' );
await expect( page.getByText( 'Order received' ) ).toBeVisible();
await page.getByTestId( 'accept-dispute-button' ).click();

// Use CSS selectors as a last resort
await page.locator( 'button.components-button.is-secondary' );
```

**How do I create a visual regression test?**

Visual regression tests are captured by the [`toHaveScreenshot()` function](https://playwright.dev/docs/api/class-pageassertions#page-assertions-to-have-screenshot-2). This function takes a screenshot of a page or element and compares it to a reference image. If the images are different, the test will fail.

```ts
await expect( page ).toHaveScreenshot();

await expect(
  page.getByRole( 'button', { name: 'All payouts' } )
).toHaveScreenshot();
```

**How can I act as shopper or merchant in a test?**

1. To switch between `shopper` and `merchant` role in a test, use the `getShopper` and `getMerchant` function:

```ts
import { getShopper, getMerchant } from './utils/helpers';

test( 'should do things as shopper and merchant', async ( { browser } ) => {
  const { shopperPage } = await getShopper( browser );
  const { merchantPage } = await getMerchant( browser );

  // do things as shopper
  await shopperPage.goto( '/cart/' );

  // do things as merchant
  await merchantPage.goto( '/wp-admin/admin.php?page=wc-settings' );
} );
```

1. To act as `shopper` **or** `merchant` for an entire test suite (`describe`), use the helper function `useShopper` or `useMerchant` from `tests/e2e-pw/utils/helpers.ts`:

```ts
import { useShopper } from '../utils/helpers';

test.describe( 'Sign in as customer', () => {
  useShopper();
  test( 'Load customer my account page', async ( { page } ) => {
    // do things as shopper
    await page.goto( '/my-account' );
  } );
} );
```

**How does the dynamic matrix generation work?**

The E2E test matrix is dynamically generated using the `.github/scripts/generate-wc-matrix.sh` script:

- L-1 policy: Automatically tests against the latest WooCommerce version and the L-1 (previous major) version
- Version resolution: Fetches latest WC, RC, and beta versions from WordPress.org API
- PHP strategy:
  - WC 7.7.0: PHP 7.3 (legacy support)
  - WC L-1 & latest: PHP 8.3 (stable)
  - WC RC: PHP 8.4 (latest)
- Business continuity: Maintains WC 7.7.0 support for significant TPV reasons

This ensures comprehensive test coverage while optimizing CI execution time and resource usage.

**How can I investigate and interact with a test failure?**

- GitHub Action test runs
  - View GitHub checks in the "Checks" tab of a PR
  - The E2E test matrix is generated dynamically. PRs cover WC latest and L-1 on PHP 8.3; scheduled runs also include 7.7.0 (PHP 7.3), RC (PHP 8.4), and beta (when available)
  - Click on the details link to the right of the failed job to see the summary
  - In the job summary, click on the "Run tests, upload screenshots & logs" section
  - Click on the artifact download link at the end of the section, then extract and copy the `playwright-report` directory to the root of the WooPayments repository
  - Run `npx playwright show-report` to open the report in a browser
  - Alternatively, after extracting the artifact you can open the `playwright-report/index.html` file in a browser
- Local test runs
  - Local test reports will output in the `playwright-report` directory
  - Run `npx playwright show-report` to open the report in a browser
