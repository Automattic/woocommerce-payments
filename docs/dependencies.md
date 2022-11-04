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
  having them available. This means we should keep the versions of these packages on the highest version avaiable in our
  supported versions of WordPress and WooCommerce, giving us the best chance of catching any issues with the bundled
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

### PHP Runtime Dependencies
| Package Name | Usage Summary | Testing | Notes |
| ------------ | ------------- | ------- | ----- |
| [automattic/jetpack-autoloader](https://github.com/Automattic/jetpack-autoloader) | Loads other Automattic Composer packages. For example, jetpack-connection. | Testing that packages load correctly should be sufficient. For major version upgrades testing this package in a number of scenarios is advised, to rule out bugs in configurations we might not usually run in development environments. | As the number of packages we’re loading with jetpack-autoloader increases, so will the required testing. |

### JavaScript Runtime Dependencies
| Package Name | Usage Summary | Testing | Notes |
| ------------ | ------------- | ------- | ----- |
| [@stripe/stripe-js](https://www.npmjs.com/package/@stripe/stripe-js) | Powers all the direct communication with Stripe in the user's browser | Reviewing Stripe's changelog is a good idea, then tailoring your testing to that. Testing payments, saving payment information, different payment methods, UPE on and off are all good options. | We configure which version of the Stripe API we want to use, although we aim to stay up to date there it's also worth ensuring the new version of this package is compatible. |
