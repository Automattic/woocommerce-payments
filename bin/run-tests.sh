#!/usr/bin/env bash

set -e

WATCH_FLAG=false

while getopts ':w' OPTION; do
	case $OPTION in
		w)
		WATCH_FLAG=true
		;;
	esac
done

shift "$(($OPTIND -1))"

echo "Installing the test environment..."

docker-compose exec -u www-data wordpress \
	/var/www/html/wp-content/plugins/woocommerce-payments/bin/install-wp-tests.sh

if $WATCH_FLAG; then
	echo "Running the tests..."

	docker-compose exec -u www-data wordpress \
		php -d xdebug.remote_autostart=on \
		/var/www/html/wp-content/plugins/woocommerce-payments/vendor/bin/phpunit \
		--configuration /var/www/html/wp-content/plugins/woocommerce-payments/phpunit.xml.dist \
		$*
else
	echo "Running the tests on watch mode..."

	docker-compose exec -u www-data wordpress \
		php -d xdebug.remote_autostart=on \
		/var/www/html/wp-content/plugins/woocommerce-payments/vendor/bin/phpunit-watcher watch \
		--configuration /var/www/html/wp-content/plugins/woocommerce-payments/phpunit.xml.dist \
		$*
fi
