#!/bin/bash

# set strict mode for bash
set -euo pipefail
IFS=$'\n\t'

# set environment variables
WCPAY_DIR="$GITHUB_WORKSPACE"

# determine whether to test everything, or just src, and what coverage to require
if [ "$COVERAGE_DIR" == "src" ]; then
	CONFIGURATION_FILE=phpunit-src.xml.dist
	COVERAGE=100
else
	CONFIGURATION_FILE=phpunit-includes.xml.dist
	COVERAGE=60
fi

composer self-update && composer install --no-progress
sudo systemctl start mysql.service
bash bin/install-wp-tests.sh woocommerce_test root root localhost $WP_VERSION $WC_VERSION false
echo 'Running the tests...'
bash bin/phpunit.sh -c $CONFIGURATION_FILE --coverage-clover /tmp/clover.xml
vendor/bin/coverage-check /tmp/clover.xml $COVERAGE

