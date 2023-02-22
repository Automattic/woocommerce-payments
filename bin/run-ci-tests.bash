#!/bin/bash

# set strict mode for bash
set -euo pipefail
IFS=$'\n\t'

# set environment variables
WCPAY_DIR="$GITHUB_WORKSPACE"

composer self-update 2.0.6 && composer install --no-progress
sudo systemctl start mysql.service
mysql -uroot -proot -e "SHOW VARIABLES LIKE 'version%'"
bash bin/install-wp-tests.sh woocommerce_test root root localhost $WP_VERSION $WC_VERSION false $GUTENBERG_VERSION
echo 'Running the tests...'
bash bin/phpunit.sh
