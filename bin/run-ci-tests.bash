#!/bin/bash

# set strict mode for bash
set -euo pipefail
IFS=$'\n\t'

# set environment variables
WCPAY_DIR="$GITHUB_WORKSPACE"

echo 'Updating composer version & Install dependencies...'
composer self-update && composer install --no-progress --ignore-platform-req=php

# SVN is needed when installing WP.
if ! [ -x "$(command -v svn)" ]; then
	echo 'Installing SVN...'
	sudo apt-get install -y subversion
fi

echo 'Starting MySQL service...'
sudo systemctl start mysql.service

echo 'Setting up test environment...'
bash bin/install-wp-tests.sh woocommerce_test root root localhost $WP_VERSION $WC_VERSION false $GUTENBERG_VERSION

echo 'Running the tests...'
bash bin/phpunit.sh -c phpunit.xml.dist
