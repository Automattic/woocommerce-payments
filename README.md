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

### Connect to the Server running locally
Create a plugin or mu-plugin with the following code:

Notably the address of the server should be: `host.docker.internal:8086`

```php
add_filter( 'pre_http_request', function( $preempt, $args, $url ) {
	if ( false !== $preempt ) {
		return $preempt;
	}

	// detect the wcpay requests and route them to localhost
	if ( 1 === preg_match( '/^https?:\/\/public-api\.wordpress\.com\/(.+?wcpay.+)/', $url, $matches ) ) {
		return wp_remote_request( 'http://host.docker.internal:8086/wp-json/' . $matches[1], $args ); // use host.docker.internal:8086 if running the client in Docker
	}

	return $preempt;
}, 10, 3 );

add_filter( 'wc_stripe_show_payment_request_on_checkout', '__return_true' );
```

See the rest of the instructionr here to connect the client to the server below. You will need our blog ID. 
To get your blog ID run:

```
docker-compose exec -u www-data wordpress wp eval 'echo Jetpack_Options::get_option( "id" );'
```

For the rest of the setup instructions see this link:
https://github.com/Automattic/woocommerce-payments-server/tree/master/local


## Setup
Install The following plugins
- Jetpack
- WooCommerce
- WooCommerce Admin

Connect using jetpack. You will need a externally accessible url. You can use ngrok for this.

See: https://github.com/Automattic/woocommerce-payments/blob/master/CONTRIBUTING.md (possibly move contents here for visibility sake)
