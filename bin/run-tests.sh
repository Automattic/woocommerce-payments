#!/usr/bin/env bash

set -e

echo "Installing the test environment..."

docker-compose exec -u www-data wordpress \
	/var/www/html/wp-content/plugins/woocommerce-payments/bin/install-wp-tests.sh

echo "Running the tests..."

docker-compose exec -u www-data wordpress \
	php -d xdebug.remote_autostart=on \
	/var/www/html/wp-content/plugins/woocommerce-payments/vendor/bin/phpunit \
	--configuration /var/www/html/wp-content/plugins/woocommerce-payments/phpunit.xml.dist \
	$*
