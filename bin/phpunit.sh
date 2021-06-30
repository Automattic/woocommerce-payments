#!/bin/bash

# set strict mode for bash
set -euo pipefail
IFS=$'\n\t'

if [[ $RUN_PHPCS == 1 || $SHOULD_DEPLOY == 1 ]]; then
	exit
fi

if [ -f "phpunit.phar" ]; then php phpunit.phar -c phpunit.xml.dist $; else ./vendor/bin/phpunit $; fi;
