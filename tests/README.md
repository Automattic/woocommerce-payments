# WooCommerce Payments Unit Tests

This guide follows the [WooCommerce guide to unit tests](https://github.com/woocommerce/woocommerce/tree/master/tests).

## Setup for running tests in the docker containers

1. From the plugin directory, run `npm run up` or `docker-compose up -d`
2. Once the containers are up, run the tests from the plugin root directory using `npm test`

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
