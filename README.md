# WooCommerce Payments

This is a feature plugin for accepting payments via a WooCommerce-branded payment gateway.

## Dependencies

-   WooCommerce

## Development

### Install dependencies & build

-   `npm install` 
-   `composer install`
-   `npm run build:client`, or if you're developing the client you can have it auto-update when changes are made: `npm start`

If you run into errors with `npm install` it may be due to node version, try `nvm install` followed by `nvm use` then try again.

When running the `composer install/update`, composer may prompt you for a GitHub OAuth token before it can fetch the `subscriptions-core` package from github.

```
Loading composer repositories with package information
GitHub API limit (0 calls/hr) is exhausted, could not fetch https://api.github.com/repos/automattic/woocommerce-subscriptions-core. Create a GitHub OAuth token to go over the API rate limit. You can also wait until ? for the rate limit to reset.

Head to https://github.com/settings/tokens/new?scopes=repo&description=Composer+XXXXXX to retrieve a token. It will be stored in "/Users/yourname/.composer/auth.json" for future use by Composer.
````

To fix this up, follow the link provided in the prompt and paste the token into the terminal window to continue.

## Setup

If you're using the Docker environment see setup instructions here:
https://github.com/Automattic/woocommerce-payments/blob/trunk/docker/README.md

Install the following plugins:

-   WooCommerce

## Test account setup

For setting up a test account follow [these instructions](https://woocommerce.com/document/payments/testing/dev-mode/).

You will need a externally accessible URL to set up the plugin. You can use ngrok for this.

`ngrok http 8082`

See: https://github.com/Automattic/woocommerce-payments/blob/trunk/CONTRIBUTING.md (possibly move contents here for visibility sake)

## Debugging

If you are following the Docker setup [here](https://github.com/Automattic/woocommerce-payments/blob/trunk/docker/README.md), Xdebug is ready to use for debugging.

Install [Xdebug Helper browser extension mentioned here](https://xdebug.org/docs/remote) to enable Xdebug on demand.
