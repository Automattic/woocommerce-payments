#!/bin/bash
CURRENT_PHP_MAJOR_VERSION=$(php -r 'echo PHP_MAJOR_VERSION;')

if [ CURRENT_PHP_MAJOR_VERSION -gt 7 ]; then
	 composer require --dev --ignore-platform-reqs --with-all-dependencies "phpunit/phpunit:9.0.0"
fi
