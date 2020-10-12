# WooCommerce Payments

This is a feature plugin for accepting payments via a WooCommerce-branded payment gateway.

## Dependencies

- WooCommerce
- WooCommerce Admin
- Jetpack or Jetpack DNA Connection

## Development

### Install dependencies & build

- `npm install`
- `composer install`
- `npm run build:client`

## Setup

If you're using the Docker environment see setup instructions here:
https://github.com/Automattic/woocommerce-payments/blob/master/docker/README.md

Install The following plugins
- Jetpack
- WooCommerce
- WooCommerce Admin

Connect using jetpack. You will need a externally accessible url. You can use ngrok for this.

If you want to set up an account with test data, set the `dev` mode flag to true. In your site's `wp-config.php`:

```PHP
define( 'WCPAY_DEV_MODE', true );
```

See: https://github.com/Automattic/woocommerce-payments/blob/master/CONTRIBUTING.md (possibly move contents here for visibility sake)
