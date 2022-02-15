#!/bin/bash

if [[ $RUN_PHPCS == 1 || $SHOULD_DEPLOY == 1 ]]; then
	exit
fi

CURRENT_PHP_MAJOR_VERSION=$(php -r 'echo PHP_MAJOR_VERSION;')
# The PHPUnit version inside composer.json is not compatible with PHP 8
# Update this constant if you wish to bump supported PHP major version
# Consider this bump when PHPUnit inside composer.json is bumped
SUPPORTED_PHP_MAJOR_VERSION_FOR_PHPUNIT_INSTALLED_VIA_COMPOSER_JSON=7

if [ $CURRENT_PHP_MAJOR_VERSION -gt SUPPORTED_PHP_MAJOR_VERSION_FOR_PHPUNIT_INSTALLED_VIA_COMPOSER ]; then
	wget -O phpunit https://phar.phpunit.de/phpunit-9.phar
	chmod +x phpunit
	./phpunit -c phpunit.xml.dist $@

else
	./vendor/bin/phpunit $@;
fi

