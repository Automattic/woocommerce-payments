# WooCommerce Payments End-to-end tests

E2E tests can be run locally or in GitHub Actions. Github Actions are already configured and doesn't require any changes to run the tests.

## Setting up & running E2E tests

For running E2E tests locally, create a new file named `local.env` under `tests/e2e/config` folder with the following env variables (replace values as required).

<details>
<summary>Required env variables</summary>
<p>

```
WCP_SERVER_REPO='https://github.com/server-repo.git or git@github.com:org/server-repo.git'
WCP_DEV_TOOLS_REPO='https://github.com/dev-tools-repo.git or git@github.com:org/dev-tools-repo.git'

// Use WCPay server local instance (Default: true). To use live server, set value to false
E2E_USE_LOCAL_SERVER=true

// Optional to see verbose output
DEBUG=true
```

</p>
</details>

---

<details>
<summary>Choose WCPay Server instance</summary>
<p>

It is possible to use the live server or a local docker instance of WCPay server locally. On Github Actions, live server is used for tests. Add the following env variables to your `local.env` based on your preference (replace values as required).

**Using Local Server on Docker**

By default, the local E2E environment is configured to use WCPay local server instance. Add the following env variables to configure the local server instance.

```
// Stripe account data. Need to support level 3 data to run tests successfully.
E2E_WCPAY_STRIPE_TEST_PUBLIC_KEY=<stripe pk_test_xxx>
E2E_WCPAY_STRIPE_TEST_SECRET_KEY=<stripe sk_test_xxx>
E2E_WCPAY_STRIPE_TEST_WEBHOOK_SIGNATURE_KEY=<stripe whsec_xxx>
E2E_WCPAY_STRIPE_ACCOUNT_ID=<stripe acct_id>
```

**Using Live Server**

For using a live server, you'll need to add Jetpack blog token, user token & blog id from one of your test sites connected to WooCommerce Payments live account. On a connected test site, you can use the code below to extract the blog id & tokens.
```
Jetpack_Options::get_option( 'id' );
Jetpack_Options::get_option( 'blog_token' );
Jetpack_Options::get_option( 'user_tokens' );
```

Set the value of `E2E_USE_LOCAL_SERVER` to `false` to enable live server.

Once you have the blog id & tokens, add the following ev variables to your `local.env`.
```
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

If you wish to run E2E test for WC Subscriptions, Action Scheduler & WC Gutenberg Products Blocks, the following env variables needs to be added to your `local.env` (replace values as required).

For the `E2E_GH_TOKEN`, follow [these instructions to generate a GitHub Personal Access Token](https://docs.github.com/en/github/authenticating-to-github/creating-a-personal-access-token) and assign the `repo` scope to it.

```
E2E_GH_TOKEN='githubPersonalAccessToken'
WC_SUBSCRIPTIONS_REPO='{owner}/{repo}'
WC_ACTION_SCHEDULER_REPO='{owner}/{repo}'
WC_BLOCKS_REPO='{owner}/{repo}'
```

</p>
</details>

---

<details>
<summary>Skipping Plugins</summary>
<p>

If you wish to skip E2E tests for WC Subscriptions, Action Scheduler or WC Gutenberg Products Blocks, the following env variables needs to be added to your `local.env`.
```
SKIP_WC_SUBSCRIPTIONS_TESTS=1
SKIP_WC_ACTION_SCHEDULER_TESTS=1
SKIP_WC_BLOCKS_TESTS=1
```

</p>
</details>

---

<details>
<summary>Initialize E2E docker environment</summary>
<p>

  1. Make sure to run `npm install`,  `composer install` and `npm run build:client` before running setup script.
  2. Run setup script `npm run test:e2e-setup` to spin up E2E environment in docker containers.

  After the E2E environment is up, you can access the containers on:

  - WC E2E Client: http://localhost:8084
  - WC E2E Server: http://localhost:8088 (Available only when using local server)

  **Note:** Be aware that the server port may change in the `docker-compose.e2e.yml` configuration, so when you can't access the server, try running `docker port woocommerce_payments_server_wordpress_e2e 80` to find out the bound port of the E2E server container.

</p>
</details>

---

<details>
<summary>Running tests</summary>
<p>

There are two modes for running tests:
1. **Headless mode**: `npm run test:e2e`. In headless mode test runner executes all or specified specs without launching Chromium interface. This mode is used in CI environment.
2. **Dev mode**: `npm run test:e2e-dev`. Dev mode is interactive and launches Chromium UI. It's useful for developing, debugging and troubleshooting failing tests. There is a custom config used for `jest-puppeteer` to run tests in dev mode.

Handy utility scripts for managing environment:

* `npm run test:e2e-down` Stops E2E environment containers.
* `npm run test:e2e-cleanup` Removes fetched dependencies and docker volumes
* `npm run test:e2e-reset` Stops containers and performs cleanup.
* `npm run test:e2e-up` Starts containers without setting up again.

</p>
</details>

<br>

## Writing tests

Package `@automattic/puppeteer-utils` overrides `it` method to attach custom reporter for failed tests.
It is important to write test cases within `it()` rather than `test()` function to make sure failed tests are reported to Slack channel.

## Debugging tests

Create file `local.env` inside `tests/e2e/config` folder and set `E2E_DEBUG=true` env variable to pause test runner when test fails.

## Slack integration

Slack reporter requires custom jest config provided by `@automattic/puppeteer-utils` package. This config is only applied with `npm run test:e2e` command.

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
