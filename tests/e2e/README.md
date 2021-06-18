# WooCommerce Payments End-to-end tests

E2E tests can be run locally or in Travis CI.

## Setup

Setup script requires the following env variables to be configured:

```
WCP_SERVER_REPO='https://github.com/server-repo.git or git@github.com:org/server-repo.git'
WCP_DEV_TOOLS_REPO='https://github.com/dev-tools-repo.git or git@github.com:org/dev-tools-repo.git'

// Stripe account data. Need to support level 3 data to run tests successfully.
E2E_WCPAY_STRIPE_TEST_PUBLIC_KEY=<stripe pk_test_xxx>
E2E_WCPAY_STRIPE_TEST_SECRET_KEY=<stripe sk_test_xxx>
E2E_WCPAY_STRIPE_TEST_CLIENT_ID=<stripe ca_xxx>
E2E_WCPAY_STRIPE_TEST_WEBHOOK_SIGNATURE_KEY=<stripe whsec_xxx>
E2E_WCPAY_STRIPE_ACCOUNT_ID=<stripe acct_id>

// Optional to see verbose output
DEBUG=true
```

For local setup:

1. Create file `local.env` in the `tests/e2e/config` folder with required values.
    * If you have access to the Subscriptions plugin, please follow the instructions from `tests/e2e/specs/subscriptions/README.md` before running the setup. Make sure to include also Action Scheduler plugin from the same document.
    * If you don't, you may skip Subscriptions setup and tests by adding `SKIP_WC_SUBSCRIPTIONS_TESTS=1` to your `local.env` in the `tests/e2e/config` folder.

1. Make sure to run `npm install`,  `composer install` and `npm run build:client` before running setup script.

1. Run setup script `npm run test:e2e-setup` to spin up E2E environment in docker containers.

After you set the E2E environment up, you can access to the containers on:

- WC E2E Client: http://localhost:8084
- WC E2E Server: http://localhost:8088 

**Note:** Be aware that the server port may change in the `docker-compose.e2e.yml` configuration, so when you can't access the server, try running `docker port woocommerce_payments_server_wordpress_e2e 80` to find out the bound port of the E2E server container.

Handy utility scripts for managing environment:

* `npm run test:e2e-down` Stops E2E environment containers.
* `npm run test:e2e-cleanup` Removes fetched dependencies and docker volumes
* `npm run test:e2e-reset` Stops containers and performs cleanup.

## Running tests

There are two modes for running tests:

1. Headless mode: `npm run test:e2e`. In headless mode test runner executes all or specified specs without launching Chromium interface. This mode is used in CI environment.

2. Dev mode: `npm run test:e2e-dev`. Dev mode is interactive and launches Chromium UI. It's useful for developing, debugging and troubleshooting failing tests. There is a custom config used for `jest-puppeteer` to run tests in dev mode.

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
