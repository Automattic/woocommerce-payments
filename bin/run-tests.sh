#!/usr/bin/env bash

set -e

# Source .env if available for worktree-specific config (needed for WORKTREE_ID)
WORKTREE_ID="default"
if [ -f ".env" ]; then
    source .env
fi

# Generate unique test database name per worktree
TEST_DB_NAME="wcpay_tests_${WORKTREE_ID}"

WATCH_FLAG=false

while getopts ':w' OPTION; do
	case $OPTION in
		w)
		WATCH_FLAG=true
		shift
		;;
	esac
done

echo "Installing the test environment..."
echo "Using test database: ${TEST_DB_NAME}"

docker compose exec -u www-data wordpress \
	/var/www/html/wp-content/plugins/woocommerce-payments/bin/install-wp-tests.sh "${TEST_DB_NAME}"

if $WATCH_FLAG; then
	echo "Running the tests on watch mode..."

	# Change directory to WooCommerce Payments' root in order to have access to .phpunit-watcher.yml
	docker compose exec -u www-data wordpress bash -c \
		"cd /var/www/html/wp-content/plugins/woocommerce-payments && \
		./vendor/bin/phpunit-watcher watch --configuration ./phpunit.xml.dist $*"
else
	echo "Running the tests..."

	docker compose exec -u www-data wordpress \
		/var/www/html/wp-content/plugins/woocommerce-payments/vendor/bin/phpunit \
		--configuration /var/www/html/wp-content/plugins/woocommerce-payments/phpunit.xml.dist \
		$*
fi
