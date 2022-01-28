#!/bin/bash

if [[ $RUN_PHPCS == 1 || $SHOULD_DEPLOY == 1 ]]; then
	exit
fi

CURRENT_PHP_MAJOR_VERSION=$(php -r 'echo PHP_MAJOR_VERSION;')

if [ $CURRENT_PHP_MAJOR_VERSION -gt 7 ]; then
	wget -O phpunit https://phar.phpunit.de/phpunit-9.phar
	chmod +x phpunit
	./phpunit -c phpunit.xml.dist $@

else
	./vendor/bin/phpunit $@;
fi

