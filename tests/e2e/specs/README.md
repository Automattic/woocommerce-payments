# WooCommerce Payments e2e tests - WooCommerce Subscriptions, Action Scheduler & WooCommerce Blocks

The tests included here require the WooCommerce Subscriptions plugin to be installed.

## Installing the WooCommerce Subscriptions plugin

The setup script requires the below env variables to be configured. Make sure you have the following set in your `local.env` in the `tests/e2e/config` folder:

```
E2E_GH_TOKEN='githubPersonalAccessToken'
WC_SUBSCRIPTIONS_REPO='{owner}/{repo}'
```

For the `E2E_GH_TOKEN`, follow [these instructions to generate a GitHub Personal Access Token](https://docs.github.com/en/github/authenticating-to-github/creating-a-personal-access-token) and assign the `repo` scope to it.

## Skipping WooCommerce Subscriptions tests

Adding the following line to your `local.env` in the `tests/e2e/config` folder and set it to `1` to skip the tests that rely on the plugin:

`SKIP_WC_SUBSCRIPTIONS_TESTS=1` 

With this set, any tests that require the WooCommerce Subscriptions plugin will be skipped.

## Installing the Action Scheduler plugin

The setup script requires the below env variables to be configured. Make sure you have the following set in your `local.env` in the `tests/e2e/config` folder:

```
WC_ACTION_SCHEDULER_REPO='{owner}/{repo}'
```

## Skipping Action Scheduler tests

Adding the following line to your `local.env` in the `tests/e2e/config` folder and set it to `1` to skip the tests that rely on the plugin:

`SKIP_WC_ACTION_SCHEDULER_TESTS=1` 

With this set, any tests that require the Action Scheduler plugin will be skipped.

## Installing the WooCommerce Blocks plugin

The setup script requires the below env variables to be configured. Make sure you have the following set in your `local.env` in the `tests/e2e/config` folder:

```
WC_BLOCKS_REPO='{owner}/{repo}'
```

## Skipping WooCommerce Blocks tests

Adding the following line to your `local.env` in the `tests/e2e/config` folder and set it to `1` to skip the tests that rely on the plugin:

`SKIP_WC_BLOCKS_TESTS=1` 

With this set, any tests that require the WooCommerce Blocks plugin will be skipped.
