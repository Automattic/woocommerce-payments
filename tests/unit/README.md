# WooPayments PHP Unit Tests

PHPUnit tests for the backend PHP code in `includes/` and `src/`. This guide follows the [WooCommerce guide to unit tests](https://github.com/woocommerce/woocommerce/tree/trunk/plugins/woocommerce/tests).

For JavaScript unit tests, see [tests/js/README.md](../js/README.md). For an overview of all test suites, see [tests/README.md](../README.md).

## Running tests in the Docker containers

1. Start the WordPress container: `pnpm run up` (or `pnpm run up:recreate` for first-time setup)
   - This auto-starts shared infrastructure (database, phpMyAdmin) if not already running
2. Once the containers are up, run tests from the plugin root directory:
   - `pnpm run test:php` - Run PHP unit tests
   - `pnpm run test:php-watch` - PHP tests in watch mode
   - `pnpm run test:php-coverage` - PHP tests with coverage
3. Run a single test class or method:

```
docker compose exec -u www-data wordpress bash -c \
  "cd /var/www/html/wp-content/plugins/woocommerce-payments && \
  vendor/bin/phpunit --configuration phpunit.xml.dist --filter 'TestClassName::test_method_name'"
```

## Running tests locally (without Docker)

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
