# WooCommerce Payments

This is a feature plugin for accepting payments via a WooCommerce-branded payment gateway.

## Dependencies

- WooCommerce
- WooCommerce Admin
- Jetpack or Jetpack DNA Connection

## Development

### NPM

- `npm install`
- `npm run build`

### Local host

To start a local development environment with the plugin locally enter this command:

`npm run dev`

Install and activate WooCommerce and Jetpack:

```
docker-compose exec -u www-data wordpress wp plugin install woocommerce jetpack --activate
```

After this succeeds you can access the local site at

http://localhost:8082/

See the instruction here to connect the client to the server.

https://github.com/Automattic/woocommerce-payments-server/tree/master/local

To get your blog ID run:

```
docker-compose exec -u www-data wordpress wp eval 'echo Jetpack_Options::get_option( "id" );'
```

## Setup
Install The following plugins
- Jetpack
- WooCommerce
- WooCommerce Admin

Connect using jetpack. You will need a externally accessible url. You can use ngrok for this.

See: https://github.com/Automattic/woocommerce-payments/blob/master/CONTRIBUTING.md (possibly move contents here for visibility sake)
