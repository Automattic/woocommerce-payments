# WooPayments end-to-end tests 🎭

WooPayments e2e tests can be found in the `./tests/e2e/specs` directory. These tests run with Playwright and replaced the Puppeteer e2e tests when we completed the migration to Playwright in early 2025.

E2E tests can be run locally or in GitHub Actions. Github Actions are already configured and don't require any changes to run the tests.

## Setting up & running E2E tests

For running E2E tests locally, create a new file named `local.env` under `tests/e2e/config` folder with the following env variables (replace values as required).

<details>
<summary>Required env variables</summary>
<p>

```
# WooPayments Dev Tools Repo
WCP_DEV_TOOLS_REPO='git@github.com:org/dev-tools-repo.git'

# Optional to see additional verbose output. Default false.
DEBUG=false
```

</p>
</details>

---

<details>
<summary>Choose Transact Platform Server instance</summary>
<p>

It is possible to use the live server or a local docker instance of the Transact Platform Server when testing locally. On Github Actions, the live server is used for tests. Add the following env variables to your `local.env` based on your preference (replace values as required).

**Using Local Server on Docker**

By default, the local E2E environment is configured to use the Transact Platform local server instance. Add the following env variables to configure the local server instance.

```
# Transact Platform Server Repo
TRANSACT_PLATFORM_SERVER_REPO='git@github.com:org/server-repo.git'

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

**Using Live Server**

For using the live server, you'll need to add a Jetpack blog token, user token, & blog id from one of your test sites connected to a WooPayments live account. On a connected test site, you can use the code below to extract the blog id & tokens.
```
Jetpack_Options::get_option( 'id' );
Jetpack_Options::get_option( 'blog_token' );
Jetpack_Options::get_option( 'user_tokens' );
```

Set the value of `E2E_USE_LOCAL_SERVER` to `false` to enable live server.

Once you have the blog id & tokens, add the following env variables to your `local.env`.
```
# Set local server to false for using live server. Default: true.
E2E_USE_LOCAL_SERVER=false

E2E_BLOG_TOKEN='<jetpack_blog_token>'
E2E_USER_TOKEN='<jetpack_user_token>'
E2E_BLOG_ID='<blog_id>'
```

</p>
</details>

---

<details>
<summary>Installing Plugins</summary>
<p>

If you wish to run E2E tests for WC Subscriptions, the following env variables need to be added to your `local.env` (replace values as required).

For the `E2E_GH_TOKEN`, follow [these instructions to generate a GitHub Personal Access Token](https://docs.github.com/en/github/authenticating-to-github/creating-a-personal-access-token) and assign the `repo` scope to it.

```
E2E_GH_TOKEN='githubPersonalAccessToken'
WC_SUBSCRIPTIONS_REPO='{owner}/{repo}'
```

</p>
</details>

---

<details>
<summary>Skipping Plugins</summary>
<p>

If you wish to skip E2E tests for WC Subscriptions, Action Scheduler, or WC Gutenberg Products Blocks, the following env variables need to be added to your `local.env`.
```
SKIP_WC_SUBSCRIPTIONS_TESTS=1
SKIP_WC_ACTION_SCHEDULER_TESTS=1
SKIP_WC_BLOCKS_TESTS=1
```

</p>
</details>

---

<details>
<summary>Using a specific version of WordPress or WooCommerce</summary>
<p>

To use a specific version of WordPress or WooCommerce for testing, the following env variables need to be added to your `local.env`.
```
E2E_WP_VERSION='<wordpress_version>'
E2E_WC_VERSION='<woocommerce_version>'
```

</p>
</details>

---

<details>
<summary>Initialize E2E docker environment</summary>
<p>

  1. Make sure to run `npm install`,  `composer install` and `npm run build:client` before running the setup script.
  2. Run the setup script `npm run test:e2e-setup` to spin up E2E environment in docker containers.

  After the E2E environment is up, you can access the containers on:

  - WC E2E Client: http://localhost:8084
  - WC E2E Server: http://localhost:8088 (Available only when using local server)

  **Note:** Be aware that the server port may change in the `docker-compose.e2e.yml` configuration, so when you can't access the server, try running `docker port transact_platform_server_wordpress_e2e 80` to find out the bound port of the E2E server container.

</p>
</details>

---

<details>
<summary>Running tests</summary>
<p>

There are two modes for running tests:

1. **Headless mode**: `npm run test:e2e`. In headless mode test runner executes all or specified specs without launching a Chromium user interface.
2. **UI mode**: `npm run test:e2e-ui`. UI mode is interactive and launches a Chromium user interface. It's useful for developing, debugging, and troubleshooting failing tests. For more information about Playwright UI mode, see [Playwright UI Mode docs](https://playwright.dev/docs/test-ui-mode#introduction).

#### Running only a single test suite

If you would like to run only one test suite, you can pass the relative path to the test file along with any of the modes mentioned above. e.g. `npm run test:e2e-ui path/to/test`.

#### Running tests in group

By adding additional env variables, it is possible to run a group of tests. e.g.

- `E2E_GROUP='wcpay' E2E_BRANCH='merchant' npm run test:e2e-ui` runs merchant tests for WCPay in UI mode.
- `E2E_GROUP='wcpay' E2E_BRANCH='shopper' npm run test:e2e-ui` runs shopper tests for WCPay in UI mode.
- `E2E_GROUP='wcpay' npm run test:e2e-ui` runs merchant & shopper tests for WCPay in UI mode.

Handy utility scripts for managing your E2E environment:

- `npm run test:e2e-down` Stops E2E environment containers.
- `npm run test:e2e-cleanup` Removes fetched dependencies and docker volumes.
- `npm run test:e2e-reset` Stops containers and performs cleanup.
- `npm run test:e2e-up` Starts containers without setting up again.

</p>
</details>

<br>

#### Running on Atomic site

For running E2E tests on the Atomic site, follow the same guidelines mentioned above, and add `NODE_ENV=atomic` to your `local.env` file. Then run bring up your E2E environment. Lastly, run tests using `npm run test:e2e` or `npm run test:e2e-ui`.

## Writing tests

Place new spec files in the appropriate directory under `tests/e2e/specs`. The directory structure is as follows:

- **Subscriptions Merchant**: `tests/e2e/specs/subscriptions/merchant` - Subscription related tests for the merchant role.
- **Subscriptions Shopper**: `tests/e2e/specs/subscriptions/shopper` - Subscription related tests for the shopper role.
- **WooPayments Merchant**: `tests/e2e/specs/wcpay/merchant` - Tests for the merchant role in WooPayments.
- **WooPayments Shopper**: `tests/e2e/specs/wcpay/shopper` - Tests for the shopper role in WooPayments, including Blocks E2E tests.

## Debugging tests

Set `E2E_DEBUG=true` env variable in your `local.env` file inside the `tests/e2e/config` directory to pause the test runner when test fails.

## Slack integration

Slack reporter requires custom jest config provided by `@woocommerce/e2e-environment` package. This config is only applied with `npm run test:e2e` command.

**Configuration steps:**

1. Create public Slack channel for reporting.
2. [Create Slack app.](https://api.slack.com/apps/)
3. Add OAuth permissions to the app:
    * `chat:write`
    * `files:write`
4. Install app into channel. `Settings > Install App` page.
5. Go to slack channel and manually invite created slack app by mentioning app bot username. User name can be found and configured on app config page `Features > App Home` page.
6. Set following env variables either locally or in CI:
```
CI=true
E2E_SLACK_TOKEN='<bot token, starts with xoxb- >'
E2E_CHANNEL_NAME='<public slack channel name>'
E2E_SLACKBOT_USER='<bot user name>'
```
