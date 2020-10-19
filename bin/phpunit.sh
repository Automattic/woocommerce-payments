#!/bin/bash

if [[ $RUN_PHPCS == 1 || $SHOULD_DEPLOY == 1 ]]; then
	exit
fi

PHP_VERSION=$(php -r 'echo PHP_VERSION;')
if [[ ${PHP_VERSION:0:3} == '7.4' ]]; then
  wget -O phpunit https://phar.phpunit.de/phpunit-8.phar && chmod +x phpunit
  ./phpunit
else
  ./vendor/bin/phpunit
fi
