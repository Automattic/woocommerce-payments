#!/usr/bin/env bash

if [[ $RUN_PHPCS == 1 || $SHOULD_DEPLOY == 1 ]]; then
	exit
fi

if [[ ${TRAVIS_PHP_VERSION:0:3} == "5.2" ]] ||
	[[ ${TRAVIS_PHP_VERSION:0:3} == "5.3" ]] ||
	[[ ${TRAVIS_PHP_VERSION:0:3} == "5.6" ]]; then
	phpunit -c phpunit.xml.dist
elif [[ ${TRAVIS_PHP_VERSION:0:3} == "7.0" ]]; then
	# We can't run phpunit with PHP 7.0, nor composer
	# So, run the latest supported version of PHPUnit with PHP 7.0
	# h/t for the idea:
	# https://github.com/travis-ci/travis-ci/issues/406#issuecomment-292213119
	# https://github.com/OpenACalendar/OpenACalendar-Web-Core/commit/84abf236c14c4abbd36a95790449d5dee59db12c
	php phpunit-6.5.14.phar -c phpunit.xml.dist
else
  ./vendor/bin/phpunit
fi
