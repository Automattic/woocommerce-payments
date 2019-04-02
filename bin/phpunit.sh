#!/usr/bin/env bash

if [[ ${RUN_PHPCS} == 1 ]]; then
	exit
fi

if [[ ${TRAVIS_PHP_VERSION:0:3} == "5.2" ]] ||
	[[ ${TRAVIS_PHP_VERSION:0:3} == "5.3" ]] ||
	[[ ${TRAVIS_PHP_VERSION:0:3} == "5.6" ]] ||
	[[ ${TRAVIS_PHP_VERSION:0:3} == "7.0" ]]; then
	phpunit -c phpunit.xml.dist
else
  ./vendor/bin/phpunit
fi
