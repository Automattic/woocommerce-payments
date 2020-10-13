# WooCommerce Payments

This is a feature plugin for accepting payments via a WooCommerce-branded payment gateway.

## Dependencies

- WooCommerce

## Development

### Install dependencies & build

- `npm install`
- `composer install`
- `npm run build:client`

## Setup

If you're using the Docker environment see setup instructions here:
https://github.com/Automattic/woocommerce-payments/blob/master/docker/README.md

Install the following plugins:
- WooCommerce

For setting up a test account follow [these instructions](https://docs.woocommerce.com/document/payments/testing/dev-mode/).

You will need a externally accessible URL to set up the plugin. You can use ngrok for this.

See: https://github.com/Automattic/woocommerce-payments/blob/master/CONTRIBUTING.md (possibly move contents here for visibility sake)
