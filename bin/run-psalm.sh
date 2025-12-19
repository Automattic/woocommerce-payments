#!/usr/bin/env bash

set -e

# Check if we should run Psalm (legacy) or PHPStan
if [ "$1" = "--legacy" ]; then
    echo "Running legacy Psalm analysis..."
    shift
    ./vendor/bin/psalm $*
else
    echo "Running PHPStan analysis..."
    ./vendor/bin/phpstan analyse $*
fi
