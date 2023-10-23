#!/bin/bash

# set strict mode for bash
set -euo pipefail
IFS=$'\n\t'

# set environment variables
WCPAY_DIR="$GITHUB_WORKSPACE"

composer self-update && composer install --no-progress
sudo systemctl start mysql.service
bash bin/install-wp-tests.sh woocommerce_test root root localhost $WP_VERSION $WC_VERSION false
echo 'Running the tests...'
bash bin/phpunit.sh -c phpunit.xml.dist --coverage-clover /tmp/clover.xml
vendor/bin/coverage-check /tmp/clover.xml 60

