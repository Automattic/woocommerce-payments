# WooCommerce Payments End-to-end tests

At the moment E2E tests can be run only locally.

## Setup

1. Make sure the site is up and running locally, including jetpack connection setup, test products and initial WC Payments plugin configuration.
2. Update config file `tests/e2e/config/defaul.json` or create `test.json` file in the same folder with required params.

## Running tests

There are two modes for running tests:

1. Headless mode: `npm run test:e2e`. In headless mode test runner executes all or specified specs without launching Chromium interface. This mode is supposed to be used in CI environment in the future.
2. Dev mode: `npm run test:e2e:dev`. Dev mode is interractive and launches Chromium UI. It's useful for developing, debugging and troubleshooting failing tests. There is a custom config used for `jest-puppeter` to run tests in dev mode.


