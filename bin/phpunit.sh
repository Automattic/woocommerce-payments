#!/usr/bin/env bash

if [[ $RUN_PHPCS == 1 || $SHOULD_DEPLOY == 1 ]]; then
	exit
fi

if [[ ${TRAVIS_PHP_VERSION:0:3} == "7.0" ]]; then
	php phpunit-6.5.14.phar -c phpunit.xml.dist
	exit
fi

./vendor/bin/phpunit
