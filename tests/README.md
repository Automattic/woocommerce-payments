# WooPayments Unit Tests

This guide follows the [WooCommerce guide to unit tests](https://github.com/woocommerce/woocommerce/tree/master/tests).

## Setup for running tests in the docker containers

1. Start the WordPress container: `npm run up` (or `npm run up:recreate` for first-time setup)
   - This auto-starts shared infrastructure (database, phpMyAdmin) if not already running
2. Once the containers are up, run tests from the plugin root directory:
   - `npm run test:php` - Run PHP unit tests only
   - `npm run test:js` - Run JavaScript unit tests only
   - `npm test` - Run both JS and PHP tests
3. Watch mode for iterative development:
   - `npm run test:php-watch` - PHP tests in watch mode
   - `npm run test:watch` - JavaScript tests in watch mode

## Initial Setup for running tests locally

1. From the plugin directory, run `composer install` if you have not already:

```
$ composer install
```

2. Install WordPress and the WP Unit Test lib using the `bin/install-wp-tests.sh` script. From the plugin root directory type:

```
$ bin/install-wp-tests.sh <db-name> <db-user> <db-password> [db-host]
```

Tip: try using `127.0.0.1` for the DB host if the default `localhost` isn't working.

3. Run the tests from the plugin root directory using

```
$ ./vendor/bin/phpunit
```
