#!/usr/bin/env bash

set -e

WATCH_FLAG=false

# pnpm passes the -- from `pnpm run test:php -- <args>` through to the script.
if [ "$1" == "--" ]; then
	shift
fi

# Checked before getopts, which would match single letters inside PHPUnit options such as --filter.
PREPARE_ARGS=()
if [ "$1" == "--reinstall" ]; then
	PREPARE_ARGS=(--reinstall)
	shift
fi

while getopts ':w' OPTION; do
	case $OPTION in
		w)
		WATCH_FLAG=true
		shift
		;;
	esac
done

"$(dirname "$0")/prepare-test-env.sh" "${PREPARE_ARGS[@]}"

if $WATCH_FLAG; then
	echo "Running the tests on watch mode..."

	# Change directory to WooCommerce Payments' root in order to have access to .phpunit-watcher.yml
	docker compose exec -u www-data wordpress bash -c \
		"cd /var/www/html/wp-content/plugins/woocommerce-payments && \
		./vendor/bin/phpunit-watcher watch --configuration ./phpunit.xml.dist $*"
else
	echo "Running the tests..."

	STATUS=0
	docker compose exec -u www-data wordpress \
		/var/www/html/wp-content/plugins/woocommerce-payments/vendor/bin/phpunit \
		--configuration /var/www/html/wp-content/plugins/woocommerce-payments/phpunit.xml.dist \
		$* || STATUS=$?

	if [ $STATUS -ne 0 ]; then
		echo "If the failures look environment-related (database errors, missing WordPress or WooCommerce classes), rebuild the test environment with: pnpm run test:php -- --reinstall"
	fi
	exit $STATUS
fi
