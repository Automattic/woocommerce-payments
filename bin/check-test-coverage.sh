#!/usr/bin/env bash

set -e

# Source .env.local if available for worktree-specific config
ENV_FILE_ARG=""
if [ -f ".env.local" ]; then
    ENV_FILE_ARG="--env-file .env.local"
fi

if [ "$1" == "src" ]; then
	CONFIGURATION_FILE=phpunit-src.xml.dist
	COVERAGE=100
else
	CONFIGURATION_FILE=phpunit-includes.xml.dist
	COVERAGE=60
fi

echo "Installing the test environment..."

docker compose ${ENV_FILE_ARG} exec -u www-data wordpress \
	/var/www/html/wp-content/plugins/woocommerce-payments/bin/install-wp-tests.sh

echo "Checking coverage..."

docker compose ${ENV_FILE_ARG} exec -u www-data wordpress \
        /var/www/html/wp-content/plugins/woocommerce-payments/vendor/bin/phpunit \
        --configuration "/var/www/html/wp-content/plugins/woocommerce-payments/$CONFIGURATION_FILE" \
        --coverage-html /var/www/html/php-test-coverage \
        --coverage-clover /var/www/html/clover.xml

./vendor/bin/coverage-check docker/wordpress/clover.xml $COVERAGE
