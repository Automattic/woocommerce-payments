#!/usr/bin/env bash

set -e

WATCH_FLAG=false

while getopts ':w' OPTION; do
	case $OPTION in
		w)
		WATCH_FLAG=true
		shift
		;;
	esac
done

echo "Installing the test environment..."

docker-compose exec -u www-data wordpress \
	/var/www/html/wp-content/plugins/woocommerce-payments/bin/install-wp-tests.sh

echo "Checking coverage..."

docker-compose exec -u www-data wordpress \
	php -d xdebug.remote_autostart=on \
	/var/www/html/wp-content/plugins/woocommerce-payments/vendor/bin/phpunit \
	--configuration /var/www/html/wp-content/plugins/woocommerce-payments/phpunit.xml.dist \
	--coverage-html /var/www/html/html
	$*
