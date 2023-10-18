#!/usr/bin/env bash

set -e

echo "Installing the test environment..."

docker-compose exec -u www-data wordpress \
	/var/www/html/wp-content/plugins/woocommerce-payments/bin/install-wp-tests.sh

echo "Checking coverage..."

docker-compose exec -u www-data wordpress \
	/var/www/html/wp-content/plugins/woocommerce-payments/vendor/bin/phpunit \
	--configuration /var/www/html/wp-content/plugins/woocommerce-payments/phpunit-src.xml.dist \
	--coverage-html /var/www/html/php-test-coverage \
	--coverage-clover /var/www/html/clover.xml
	$*

./vendor/bin/coverage-check docker/wordpress/clover.xml 100
