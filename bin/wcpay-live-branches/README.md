## WCPay Live Branches

**wcpay-live-branches.user.js** is a Tampermonkey script that allows you to preview a site with a given Pull Request changeset.

### Installation

* Install Tampermonkey or equivalent user script extension.
* Click https://github.com/Automattic/woocommerce-payments/raw/develop/bin/wcpay-live-branches/wcpay-live-branches.user.js
* Once installed, it should be able to auto-update.
* Expect to see the new `WCPay Live Branches` heading in the PR description [like this](https://user-images.githubusercontent.com/10045087/227102466-6c9e5918-07ae-42cd-9dd9-20d1e355091d.png).

### Development

1. Make sure you update the version in the **wcpay-live-branches.user.js** file. That will trigger auto-update for consumers.
2. Create a PR in this repo.

### Additional Info

The "Live Branch" concept was first introduced by [Jetpack](https://github.com/Automattic/jetpack/tree/trunk/tools/jetpack-live-branches), then adopted by [WooCommerce core](https://github.com/woocommerce/woocommerce/tree/trunk/plugins/woocommerce-beta-tester/userscripts), and now we are. 
