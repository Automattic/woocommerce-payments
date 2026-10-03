#!/usr/bin/env bash

# Runs inside the WordPress container. Checks whether the PHPUnit environment installed by
# install-wp-tests.sh can be reused and prints the versions it contains.
#
# Exit codes: 0 ready, 1 needs installing, 2 database server unreachable.

DB_NAME="$1"

# Same defaults as install-wp-tests.sh.
TMPDIR=${TMPDIR-/tmp}
TMPDIR=$(echo $TMPDIR | sed -e "s/\/$//")
WP_TESTS_DIR=${WP_TESTS_DIR-$TMPDIR/wordpress-tests-lib}
WP_CORE_DIR=${WP_CORE_DIR-$TMPDIR/wordpress}
WP_CORE_DIR=$(echo $WP_CORE_DIR | sed -e "s/\/$//")

plugin_version() {
	grep -m1 -E '^ \* Version:' "$WP_CORE_DIR/wp-content/plugins/$1/$1.php" 2>/dev/null | sed -E 's/.*Version: *//'
}

needs_install() {
	echo "The test environment needs installing: $1"
	exit 1
}

if [ ! -f "$WP_CORE_DIR/wp-includes/version.php" ]; then
	needs_install "WordPress is missing from $WP_CORE_DIR."
fi
if [ ! -d "$WP_TESTS_DIR/includes" ] || [ ! -f "$WP_TESTS_DIR/wp-tests-config.php" ]; then
	needs_install "the WordPress test suite is missing from $WP_TESTS_DIR."
fi
if ! grep -q "'$DB_NAME'" "$WP_TESTS_DIR/wp-tests-config.php"; then
	needs_install "wp-tests-config.php uses a different database than $DB_NAME."
fi
WC_VERSION=$(plugin_version woocommerce)
if [ -z "$WC_VERSION" ]; then
	needs_install "WooCommerce is missing."
fi

DB_HOSTNAME=${WORDPRESS_DB_HOST%%:*}
DB_PORT=3306
if [[ $WORDPRESS_DB_HOST == *:* ]]; then
	DB_PORT=${WORDPRESS_DB_HOST##*:}
fi
if ! DATABASES=$(MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql --user=root --host="$DB_HOSTNAME" --port="$DB_PORT" \
	--protocol=tcp --skip-ssl -N -e "SHOW DATABASES LIKE '$DB_NAME'" 2>&1); then
	echo "Could not connect to the database server at $WORDPRESS_DB_HOST: $DATABASES"
	exit 2
fi
if [ -z "$DATABASES" ]; then
	needs_install "the $DB_NAME database does not exist."
fi

WP_VERSION=$(grep -E '^\$wp_version' "$WP_CORE_DIR/wp-includes/version.php" | sed -E "s/.*'(.*)'.*/\1/")
SUITE_TAG=$(svn info --show-item url "$WP_TESTS_DIR/includes" 2>/dev/null | sed -E 's#.*\.wordpress\.org/##; s#/tests/phpunit/includes##')
GUTENBERG_VERSION=$(plugin_version gutenberg)
INSTALLED_ON=$(date -r "$WP_TESTS_DIR/includes" +%Y-%m-%d)

echo "WordPress ${WP_VERSION} (test suite: ${SUITE_TAG:-unknown}), WooCommerce ${WC_VERSION}, Gutenberg ${GUTENBERG_VERSION:-not installed}. Installed on ${INSTALLED_ON}."

# x.y.0 releases are tagged as x.y in the test suite repository.
if [[ $SUITE_TAG == tags/* ]] && [ "${SUITE_TAG#tags/}" != "${WP_VERSION%.0}" ]; then
	echo "Warning: the test suite (${SUITE_TAG}) does not match WordPress ${WP_VERSION}. Rebuild with: pnpm run test:php -- --reinstall"
fi
