# Version Support Policy

We have officially announced the L-2 version support policy for WooCommerce and WordPress core [since May 2021](https://developer.woocommerce.com/2021/05/12/woocommerce-payments-is-adopting-a-new-version-support-policy/): we support the current major version and the two before it.

The minimum supported versions are declared in [`woocommerce-payments.php`](https://github.com/Automattic/woocommerce-payments/blob/develop/woocommerce-payments.php):

- `WC requires at least` for WooCommerce.
- `Requires at least` for WordPress core (also in [`readme.txt`](https://github.com/Automattic/woocommerce-payments/blob/develop/readme.txt)).

Both move to L-2 as part of each release.

## How the minimum is enforced

- **Below the minimum, the plugin still loads and takes payments.** The merchant sees a warning notice asking them to update. Version incompatibilities never disable the plugin; only a missing WooCommerce install does. See `WC_Payments_Dependency_Service`.
- **Updates are gated.** When a new WooPayments version requires a newer WooCommerce version than the site runs, the update is not offered (`WC_Payments_Dependency_Service::gate_plugin_updates()`), and a notice on the plugins screen explains that WooCommerce must be updated first. This mirrors how WordPress core handles updates that require a newer PHP version.
- **WordPress core enforces the WP minimum.** Sites below `Requires at least` are not offered the update by WordPress itself.

## What does this policy mean for contributors?

Your code needs to work across the supported WooCommerce and WordPress range (L, L-1, L-2). The [compatibility CI workflow](https://github.com/Automattic/woocommerce-payments/blob/develop/.github/workflows/compatibility.yml) tests against the declared minimums; keep its `WC_MIN_SUPPORTED_VERSION` and `WP_MIN_SUPPORTED_VERSION` values in sync with the plugin headers when the floor moves.
