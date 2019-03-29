#!/usr/bin/env bash

# ./vendor/bin/phpunit

if [[ ${TRAVIS_PHP_VERSION:0:3} == "5.6" ]] || [[ ${TRAVIS_PHP_VERSION:0:3} == "7.0" ]]; then
	phpunit -c phpunit.xml
else
  ./vendor/bin/phpunit
fi

# WORKING_DIR="$PWD"
# cd "$WP_CORE_DIR/wp-content/plugins/woocommerce-payments/"
# phpunit --version
# phpunit -c phpunit.xml
# cd "$WORKING_DIR"
