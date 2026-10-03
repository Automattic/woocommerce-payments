#!/usr/bin/env bash

# Prepares the PHPUnit environment in the WordPress container, reusing the existing install when it
# is usable. The WordPress test bootstrap recreates the tables on every run, so installing is only
# needed once, or with --reinstall to rebuild it with the latest WordPress, WooCommerce and Gutenberg.
#
# Usage: bin/prepare-test-env.sh [--reinstall]

set -e

# Source .env if available for worktree-specific config (needed for WORKTREE_ID)
WORKTREE_ID="default"
if [ -f ".env" ]; then
	source .env
fi

# Generate unique test database name per worktree
TEST_DB_NAME="wcpay_tests_${WORKTREE_ID}"
PLUGIN_DIR=/var/www/html/wp-content/plugins/woocommerce-payments

if [ -z "$(docker compose ps -q --status running wordpress)" ]; then
	echo "The WordPress container is not running. Start it with: pnpm run up"
	exit 1
fi

echo "Using test database: ${TEST_DB_NAME}"

install() {
	echo "Installing the test environment..."
	docker compose exec -u www-data wordpress "$PLUGIN_DIR/bin/install-wp-tests.sh" "${TEST_DB_NAME}"
}

check_status() {
	STATUS=0
	docker compose exec -T -u www-data wordpress "$PLUGIN_DIR/bin/test-env-status.sh" "${TEST_DB_NAME}" || STATUS=$?
}

if [ "$1" == "--reinstall" ]; then
	echo "Removing the existing test environment..."
	# The installer skips WordPress and the test suite when their directories exist.
	docker compose exec -T -u www-data wordpress bash -c \
		'TMPDIR=${TMPDIR:-/tmp}; rm -rf "${WP_CORE_DIR:-$TMPDIR/wordpress}" "${WP_TESTS_DIR:-$TMPDIR/wordpress-tests-lib}"'
	install
	check_status
else
	check_status
	if [ $STATUS -eq 1 ]; then
		install
		check_status
	elif [ $STATUS -eq 0 ]; then
		echo "Using the existing test environment. Rebuild it with: pnpm run test:php -- --reinstall"
	fi
fi

if [ $STATUS -eq 2 ]; then
	echo "Check that the database container is running (docker compose ps), or restart the environment with: pnpm run up"
	exit 1
elif [ $STATUS -ne 0 ]; then
	echo "The test environment is still not usable after installing. Rebuild it with: pnpm run test:php -- --reinstall"
	exit 1
fi
