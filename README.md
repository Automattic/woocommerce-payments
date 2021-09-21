# WooCommerce Payments

This is a feature plugin for accepting payments via a WooCommerce-branded payment gateway.

## Dependencies

-   WooCommerce

## Development

### Install dependencies & build

-   `npm install`
-   `composer install`
-   `npm run build:client`, or if you're developing the client you can have it auto-update when changes are made: `npm start`

## Setup

If you're using the Docker environment see setup instructions here:
https://github.com/Automattic/woocommerce-payments/blob/trunk/docker/README.md

Install the following plugins:

-   WooCommerce

## Test account setup

For setting up a test account follow [these instructions](https://docs.woocommerce.com/document/payments/testing/dev-mode/).

You will need a externally accessible URL to set up the plugin. You can use ngrok for this.

`ngrok http 8082`

See: https://github.com/Automattic/woocommerce-payments/blob/trunk/CONTRIBUTING.md (possibly move contents here for visibility sake)

## Debugging

If you are following the Docker setup [here](https://github.com/Automattic/woocommerce-payments/blob/trunk/docker/README.md), Xdebug is ready to use for debugging.

Install [Xdebug Helper browser extension mentioned here](https://xdebug.org/docs/remote) to enable Xdebug on demand.
