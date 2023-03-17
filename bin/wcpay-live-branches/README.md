## WCPay Live Branches

**wcpay-live-branches.user.js** is a Tampermonkey script that allows you to preview a site with a given Pull Request changeset.

### Installation

* Install Tampermonkey or equivalent user script extension.
* Click https://github.com/Automattic/woocommerce-payments/raw/develop/bin/wcpay-live-branches/wcpay-live-branches.user.js
* Once installed, it should be able to auto-update.

### Development

1. Make sure you update the version in the **wcpay-live-branches.user.js** file. That will trigger auto-update for consumers.
2. Create a PR in this same repo.

### Additional Info

The "Live Branch" concept was first introduced by [Jetpack](https://github.com/Automattic/jetpack/tree/trunk/tools/jetpack-live-branches), then adopted by [WooCommerce core](https://github.com/woocommerce/woocommerce/tree/trunk/plugins/woocommerce-beta-tester/userscripts), and now we are. 
