# WooCommerce Payments End-to-end tests

At the moment E2E tests can be run only locally.

## Setup

1. Make sure the site is up and running locally, including Jetpack connection setup, test products and initial WC Payments plugin configuration.
2. Update config file `tests/e2e/config/default.json` or create `test.json` file in the same folder with required params. For local testing create file `local.json` (ignored by version control) with `url` param matching local site under test, e.g. `http://localhost:8082`.

## Running tests

There are two modes for running tests:

1. Headless mode: `npm run test:e2e`. In headless mode test runner executes all or specified specs without launching Chromium interface. This mode is supposed to be used in CI environment in the future.

2. Dev mode: `npm run test:e2e:dev`. Dev mode is interractive and launches Chromium UI. It's useful for developing, debugging and troubleshooting failing tests. There is a custom config used for `jest-puppeter` to run tests in dev mode.

## Writing tests

Package `@automattic/puppeter-utils` overrides `it` method to attach custom reporter for failed tests.
It is important to write test cases within `it()` rather than `test()` function to make sure failed tests are reported to Slack channel.

## Debugging tests

Create file `local.env` inside `tests/e2e/config` folder and set `E2E_DEBUG=true` env variable to pause test runner when test fails.

## Slack integration

Slack reporter requires custom jest config provided by `@automattic/puppeteer-utils` package. This config is only applied in with `npm run test:e2e` command.

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

