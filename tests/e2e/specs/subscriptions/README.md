# WooCommerce Payments e2e tests - WooCommerce Subscriptions

The tests included here require the WooCommerce Subscriptions plugin to be installed.

## Installing the plugin

To install the plugin, make sure you have the following set in your `local.env` in the `tests/e2e/config` folder:

```
WC_SUBSCRIPTIONS_REPO='https://github.com/woocommerce-subscriptions or git@github.com:org/woocommerce-subscriptions.git
```

The setup script requires the above env variable to be configured in order to pull in the plugin.

## Skipping tests

Adding the following line to your `local.env` in the `tests/e2e/config` folder and set it to `1` to skip the tests that rely on the plugin:

`SKIP_WC_SUBSCRIPTIONS_TESTS=1` 

With this set, any tests that require the WooCommerce Subscriptions plugin will be skipped.
