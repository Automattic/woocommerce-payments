# Version Support Policy

We have officially announced the L-2 version support policy for WooCommerce and WordPress core [since May 2021](https://developer.woocommerce.com/2021/05/12/woocommerce-payments-is-adopting-a-new-version-support-policy/).

However, in the practice, we're really enforcing only WordPress core with the `Requires at least` in [`readme.txt`](https://github.com/Automattic/woocommerce-payments/blob/develop/readme.txt) and [`woocommerce-payments.php`](https://github.com/Automattic/woocommerce-payments/blob/develop/woocommerce-payments.php).

For WooCommerce, we are yet to fully support this L-2 policy. The real minimum WooCommerce version can be found on [`woocommerce-payments.php`](https://github.com/Automattic/woocommerce-payments/blob/develop/woocommerce-payments.php) with the `WC requires at least` tag. There are two main reasons for this:

- There is no reliable built-in functionality that prevents a merchant from installing WooPayments with an unsupported WooCommerce version. `WC_Payments_Dependency_Service` warns about unsupported versions without stopping initialization, even when the account cache is empty or cleared during an update. Missing WooCommerce or disabled WooCommerce Admin still prevents service initialization. This avoids a shutdown caused by the version check; it does not guarantee compatibility with every older version.
- Some of our internal consumers are also not yet using the latest version of WooCommerce. See paJDYF-3fF-p2#comment-9591

## What does this policy mean for contributors?

As a contributor to WCPay, you would be mindful when adding new features and functions to your PRs as your code needs to work within proper ranges of WordPress and WooCommerce versions. Although we have [a CI workflow](https://github.com/Automattic/woocommerce-payments/blob/develop/.github/workflows/compatibility.yml) to check the compatibility between WCPay and these ranges, it's still good for contributors to be aware of this policy.

## When will WCPay fully support L-2 for WooCommerce core?

We have no ETA for this yet, and you're recommended to check the current minimum WooCommerce version in the code at the moment of writing your code. That said, we're working on a few approaches to reduce the gap between the real minimum supported version and the L-2 version. paJDYF-3fF-p2
