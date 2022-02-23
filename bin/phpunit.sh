#!/bin/bash

if [[ $RUN_PHPCS == 1 || $SHOULD_DEPLOY == 1 ]]; then
	exit
fi

CURRENT_PHP_MAJOR_VERSION=$(php -r 'echo PHP_MAJOR_VERSION;')
CURRENT_PHP_MINOR_VERSION=$(php -r 'echo PHP_MINOR_VERSION;')
# The PHPUnit version inside composer.json is not compatible with PHP 7.2 and above
# Update this constant if you wish to bump supported PHP major version
SUPPORTED_PHP_MAJOR_VERSION_FOR_PHPUNIT_INSTALLED_VIA_COMPOSER_JSON=7
SUPPORTED_PHP_MINOR_VERSION_FOR_PHPUNIT_INSTALLED_VIA_COMPOSER_JSON=3

if [ $CURRENT_PHP_MAJOR_VERSION -gte $SUPPORTED_PHP_MAJOR_VERSION_FOR_PHPUNIT_INSTALLED_VIA_COMPOSER_JSON -a $CURRENT_PHP_MINOR_VERSION -gte $SUPPORTED_PHP_MINOR_VERSION_FOR_PHPUNIT_INSTALLED_VIA_COMPOSER_JSON ]; then
	./vendor/bin/phpunit $@;
else
	chmod +x phpunit6
    ./phpunit6 -c phpunit.xml.dist $@
fi

