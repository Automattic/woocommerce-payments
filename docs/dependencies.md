# Handling Dependabot PRs
We use [Dependabot](https://github.com/Automattic/woocommerce-payments/security/dependabot) to monitor our PHP and JavaScript package dependencies.
Dependabot will open a PR when an update is available for a package. We can then review the PR, test the change, and
merge to apply the update. The testing and review needed will depend on the package being updated. This document aims
to catalog our packages and provide guidance to a developer who wants to test and merge Dependabot PRs.

* [Open issues from Dependabot](https://github.com/Automattic/woocommerce-payments/security/dependabot).
* [Open PRs from Dependabot](https://github.com/Automattic/woocommerce-payments/pulls/app%2Fdependabot).
  * Currently open PRs are limited to 5 for each `package-ecosystem`.
* [Dependabot config](https://github.com/Automattic/woocommerce-payments/blob/develop/.github/dependabot.yml).

## Some Notes on Packages
* A lot of our JavaScript dev dependencies are provided by WordPress or WooCommerce globally at runtime, we include them
  as dev dependencies so that we aren't duplicating them in our build bundle but our unit tests can still pass by
  having them available. This means we should keep the versions of these packages on the highest version available in our
  minimum supported versions of [WordPress](https://github.com/WordPress/wordpress-develop/blob/x.y.z/package.json) and [WooCommerce](https://github.com/woocommerce/woocommerce/blob/x.y.z/plugins/woocommerce/package.json) (replace `x.y.z` by the minimum supported version in the link), giving us the best chance of catching any issues with the bundled
  packages early.
* Following on from above, we use the `@woocommerce/dependency-extraction-webpack-plugin` to make WebPack aware of what
  can be found globally at runtime. The configuration for this can be found in
  [`webpack.config.js`](https://github.com/Automattic/woocommerce-payments/blob/develop/webpack.config.js). Any `wordpress/*`, `woocommerce/*`
  and [some other packages](https://www.npmjs.com/package/@woocommerce/dependency-extraction-webpack-plugin) are removed from
  the built bundle by default, in addition to any packages listed in the configuration file. Returning `null` in the configuration
  indicates that we want to bundle the package rather than using the globally available one.

## Review Process
1. Check the tables below for the package you’re reviewing.
2. Add it if isn’t already present (you can add the commit to the Dependabot PR).
3. Check the usage summary and update as necessary (i.e. has our usage of the package dramatically increased, or have we
   stopped using it?).
4. Perform any suggested testing based on the size of the update (major / minor / patch).
5. Check that our CI tests have all passed, realistically these should find 90% of problems for us.
6. Merge the PR. If there are any lingering concerns, consider merging immediately after a release so that we have the
   most time to do further testing before the next release.

### PHP Dev Dependencies
| Package Name | Usage Summary | Testing | Notes |
| ------------ | ------------- | ------- | ----- |
| [vimeo/psalm](https://github.com/vimeo/psalm) |  Used for type checking our PHP code. | Run the tool, make sure it completes and produces output. | Run with `npm run psalm`. |

### JavaScript Dev Dependencies
| Package Name | Usage Summary | Testing | Notes |
| ------------ | ------------- | ------- | ----- |
| [husky](https://www.npmjs.com/package/husky) |  Used to run hooks pre/post commit, like automatically running PHPCS. | Check out another branch `composer install` should run automatically. |  |
| [lodash](https://www.npmjs.com/package/lodash) | Lodash makes JavaScript easier by taking the hassle out of working with arrays, numbers, objects, strings, etc. | JS tests should pass. |  |
| [node](https://www.npmjs.com/package/node) | Not a package, but we declare the supported version of node in our `.nvmrc` file. We use node to build the JavaScript for the plugin and run the JavaScript unit tests. | Ensure you're running the new version of node by running the `nvm use` command or manually setting up the correct version. For minor and patch upgrades testing that the build runs is sufficient. For major versions, smoke testing the running plugin would be advised. | |
| [@woocommerce/currency](https://www.npmjs.com/package/@woocommerce/currency) | A collection of utilities to display and work with currency values. | JS tests should pass.	 | |
| [@wordpress/url](https://www.npmjs.com/package/@wordpress/url) | A collection of utilities to manipulate URLs.| JS unit tests are passing| |
| [@wordpress/data](https://www.npmjs.com/package/@wordpress/data) | It serves as a hub to manage application state for both plugins and WordPress itself, providing tools to manage data within and between distinct modules.| JS unit tests are passing| |
| [@wordpress/i18n](https://www.npmjs.com/package/@wordpress/i18n) | Internationalization utilities for client-side localization.| JS unit tests are passing. | The `wpi18n` used in `postbuild:client` script comes from `node-wp-i18n` and is thus separate from this. |
| [@wordpress/date](https://www.npmjs.com/package/@wordpress/date) | Date module for WordPress.| JS unit tests are passing| From v4.6.0, the `TZ` env var from the Jest global setup is not taken into account anymore, hence unit tests fail. |
| [@wordpress/api-fetch](https://www.npmjs.com/package/@wordpress/api-fetch) | Utility to make WordPress REST API requests. | JS unit tests are passing. | |
| [@woocommerce/date](https://www.npmjs.com/package/@woocommerce/date) | A collection of utilities to display and work with date values. | JS unit tests are passing. | Though there is no direct use of this package, it is used by [jest-test-file-setup.js](https://github.com/Automattic/woocommerce-payments/blob/b64178138d44d3bd3aa2a692d1c84e4d91e521b9/tests/js/jest-test-file-setup.js#L25)  |
| [@wordpress/hooks](https://www.npmjs.com/package/@wordpress/hooks) | A lightweight & efficient EventManager for JavaScript.| JS unit tests are passing| |
| [@wordpress/plugins](https://www.npmjs.com/package/@wordpress/plugins) | Plugins module for WordPress.| JS unit tests are passing| |
| [@wordpress/icons](https://www.npmjs.com/package/@wordpress/icons) | WordPress Icons Library. | JS unit tests are passing and UI isn't affected at places of usage. | Only case in current usage where a `@wordpress/x` doesn't come from WordPress directly. |
| [@wordpress/element](https://www.npmjs.com/package/@wordpress/element) | An abstraction layer atop React. | JS unit tests are passing. | Removed [@types/wordpress__element](https://www.npmjs.com/package/@types/wordpress__element) since @wordpress/element has built-in types |
| [@wordpress/scripts](https://www.npmjs.com/package/@wordpress/scripts) | Collection of reusable scripts tailored for WordPress development. | JS tests and E2E pipeline works, which uses `wp-scripts` | `>=20` requires `jest >=27`, we're currently at `v26.x` so updated to  `v19.2.3`  |
| [@woocommerce/explat](https://www.npmjs.com/package/@woocommerce/explat/) | Component and utility functions that can be used to run A/B Tests in WooCommerce dashboard and reports pages. | JS unit tests are passing | After update ensure types are still correct for `ExperimentProps`, or use a DefinitelyTyped package if available.  |
| [@woocommerce/experimental](https://www.npmjs.com/package/@wordpress/plugins) | A collection of component imports and exports that are aliases for components transitioning from experimental to non-experimental.| JS unit tests are passing| Needed to install `@types/react-transition-group` as JS linting could not find a declaration file for module `react-transition-group/CSSTransition`. |
| [@wordpress/dom-ready](https://www.npmjs.com/package/@wordpress/dom-ready) | Execute callback after the DOM is loaded.| JS unit tests are passing. ||
| [@wordpress/html-entities](https://www.npmjs.com/package/@wordpress/html-entities) | HTML entity utilities for WordPress. | JS unit tests are passing. ||
| [@wordpress/blocks](https://www.npmjs.com/package/@wordpress/blocks) | Blocks utilities for WordPress. | JS unit tests are passing. ||
| [@wordpress/block-editor](https://www.npmjs.com/package/@wordpress/block-editor) | Allows you to create and use standalone block editors. | JS unit tests are passing. ||
| [@wordpress/jest-preset-default](https://www.npmjs.com/package/@wordpress/jest-preset-default) | Default Jest preset for WordPress development. | JS unit tests are passing. ||
| [@wordpress/babel-plugin-makepot](https://www.npmjs.com/package/@wordpress/babel-plugin-makepot) | Babel plugin used to scan JavaScript files for use of localization functions. | Ensure `languages/woocommerce-payments.pot` is created correctly on building release. ||


### PHP Runtime Dependencies
| Package Name | Usage Summary | Testing | Notes |
| ------------ | ------------- | ------- | ----- |
| [automattic/jetpack-autoloader](https://github.com/Automattic/jetpack-autoloader) | Loads other Automattic Composer packages. For example, jetpack-connection. | Testing that packages load correctly should be sufficient. For major version upgrades testing this package in a number of scenarios is advised, to rule out bugs in configurations we might not usually run in development environments. | As the number of packages we’re loading with jetpack-autoloader increases, so will the required testing. |

### JavaScript Runtime Dependencies
| Package Name | Usage Summary | Testing | Notes |
| ------------ | ------------- | ------- | ----- |
| [@stripe/stripe-js](https://www.npmjs.com/package/@stripe/stripe-js) | Powers all the direct communication with Stripe in the user's browser | Reviewing Stripe's changelog is a good idea, then tailoring your testing to that. Testing payments, saving payment information, different payment methods, UPE on and off are all good options. | We configure which version of the Stripe API we want to use, although we aim to stay up to date there it's also worth ensuring the new version of this package is compatible. |
