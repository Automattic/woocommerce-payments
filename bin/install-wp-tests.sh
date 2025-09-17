#!/usr/bin/env bash

# Set environment variables to bypass SSL certificate issues in CI environments
export WP_CLI_DISABLE_SSL_VERIFY=1
export WP_CLI_HTTP_VERIFY_SSL=false
export CURL_INSECURE=1
export CURL_CA_BUNDLE=""
export SSL_VERIFY=false
export CURLOPT_SSL_VERIFYPEER=0
export CURLOPT_SSL_VERIFYHOST=0

if [ $# -lt 3 ] && [ -z $WCPAY_DIR ]; then
	echo "usage: $0 <db-name> <db-user> <db-pass> [db-host] [wp-version] [wc-version] [skip-database-creation]"
	exit 1
fi

DB_NAME=${1-wcpay_tests}
DB_USER=${2-root}
DB_PASS=${3-$MYSQL_ROOT_PASSWORD}
DB_HOST=${4-$WORDPRESS_DB_HOST}
WP_VERSION=${5-latest}
WC_VERSION=${6-latest}
SKIP_DB_CREATE=${7-false}
GUTENBERG_VERSION=${8-latest}

TMPDIR=${TMPDIR-/tmp}
TMPDIR=$(echo $TMPDIR | sed -e "s/\/$//")
WP_TESTS_DIR=${WP_TESTS_DIR-$TMPDIR/wordpress-tests-lib}
WP_CORE_DIR=${WP_CORE_DIR-$TMPDIR/wordpress/}

download() {
    if [ `which curl` ]; then
        curl -s "$1" > "$2";
    elif [ `which wget` ]; then
        wget -nv -O "$2" "$1"
    fi
}

wp() {
	WORKING_DIR="$PWD"
	cd "$WP_CORE_DIR"

	if [ ! -f $TMPDIR/wp-cli.phar ]; then
		download https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar  "$TMPDIR/wp-cli.phar"
	fi
	
	# Create a temporary script that sets environment variables and runs WP-CLI
	cat > "$TMPDIR/wp-cli-wrapper.sh" << 'EOF'
#!/bin/bash
export WP_CLI_DISABLE_SSL_VERIFY=1
export WP_CLI_HTTP_VERIFY_SSL=false
export CURL_INSECURE=1
export CURL_CA_BUNDLE=""
export SSL_VERIFY=false
export CURLOPT_SSL_VERIFYPEER=0
export CURLOPT_SSL_VERIFYHOST=0

# Run WP-CLI with PHP SSL bypass settings
php -d "curl.cainfo=" -d "openssl.cafile=" "$1" "${@:2}"
EOF
	
	chmod +x "$TMPDIR/wp-cli-wrapper.sh"
	
	# Run WP-CLI through our wrapper script
	"$TMPDIR/wp-cli-wrapper.sh" "$TMPDIR/wp-cli.phar" $@

	cd "$WORKING_DIR"
}

get_db_connection_flags() {
	# parse DB_HOST for port or socket references
	local DB_HOST_PARTS=(${DB_HOST//\:/ })
	local DB_HOSTNAME=${DB_HOST_PARTS[0]};
	local DB_SOCK_OR_PORT=${DB_HOST_PARTS[1]};
	local EXTRA_FLAGS=""

	if ! [ -z $DB_HOSTNAME ] ; then
		if [ $(echo $DB_SOCK_OR_PORT | grep -e '^[0-9]\{1,\}$') ]; then
			EXTRA_FLAGS=" --host=$DB_HOSTNAME --port=$DB_SOCK_OR_PORT --protocol=tcp"
		elif ! [ -z $DB_SOCK_OR_PORT ] ; then
			EXTRA_FLAGS=" --socket=$DB_SOCK_OR_PORT"
		elif ! [ -z $DB_HOSTNAME ] ; then
			EXTRA_FLAGS=" --host=$DB_HOSTNAME --protocol=tcp"
		fi
	fi
	echo "--user=$DB_USER --password=$DB_PASS $EXTRA_FLAGS";
}

wait_db() {
	local MYSQLADMIN_FLAGS=$(get_db_connection_flags)
	local WAITS=0

	set +e
	mysqladmin status $MYSQLADMIN_FLAGS > /dev/null
	while [[ $? -ne 0 ]]; do
		((WAITS++))
		if [ $WAITS -ge 6 ]; then
			echo "Maximum database wait time exceeded"
			exit 1
		fi;
		echo "Waiting until the database is available..."
		sleep 5s
		mysqladmin status $MYSQLADMIN_FLAGS > /dev/null
	done
	set -e
}

if [[ $WP_VERSION =~ ^[0-9]+\.[0-9]+\-(beta|RC)[0-9]+$ ]]; then
	WP_BRANCH=${WP_VERSION%\-*}
	WP_TESTS_TAG="branches/$WP_BRANCH"

elif [[ $WP_VERSION =~ ^[0-9]+\.[0-9]+$ ]]; then
	WP_TESTS_TAG="branches/$WP_VERSION"
elif [[ $WP_VERSION =~ [0-9]+\.[0-9]+\.[0-9]+ ]]; then
	if [[ $WP_VERSION =~ [0-9]+\.[0-9]+\.[0] ]]; then
		# version x.x.0 means the first release of the major version, so strip off the .0 and download version x.x
		WP_TESTS_TAG="tags/${WP_VERSION%??}"
	else
		WP_TESTS_TAG="tags/$WP_VERSION"
	fi
elif [[ $WP_VERSION == 'nightly' || $WP_VERSION == 'trunk' ]]; then
	WP_TESTS_TAG="trunk"
else
	# http serves a single offer, whereas https serves multiple. we only want one
	download http://api.wordpress.org/core/version-check/1.7/ /tmp/wp-latest.json
	grep '[0-9]+\.[0-9]+(\.[0-9]+)?' /tmp/wp-latest.json
	LATEST_VERSION=$(grep -o '"version":"[^"]*' /tmp/wp-latest.json | sed 's/"version":"//')
	if [[ -z "$LATEST_VERSION" ]]; then
		echo "Latest WordPress version could not be found"
		exit 1
	fi
	WP_TESTS_TAG="tags/$LATEST_VERSION"
fi
set -e

install_wp() {
	if [ -d $WP_CORE_DIR ]; then
		return;
	fi

	mkdir -p $WP_CORE_DIR

	wp core download --version=$WP_VERSION

	download https://raw.github.com/markoheijnen/wp-mysqli/master/db.php $WP_CORE_DIR/wp-content/db.php
}

configure_wp() {
	echo "Configuring WordPress..."
	
	# portable in-place argument for both GNU sed and Mac OSX sed
	if [[ $(uname -s) == 'Darwin' ]]; then
		local ioption='-i.bak'
	else
		local ioption='-i'
	fi

	WP_SITE_URL="http://local.wordpress.test"
	echo "Waiting for database connection..."
	wait_db

	if [[ ! -f "$WP_CORE_DIR/wp-config.php" ]]; then
		echo "Creating WordPress configuration..."
		wp core config --dbname=$DB_NAME --dbuser=$DB_USER --dbpass=$DB_PASS --dbhost=$DB_HOST --dbprefix=wptests_
	else
		echo "WordPress configuration already exists"
	fi

    # MySQL default reporting level has changed in PHP 8.1, here we forcing it to be the same in all tested PHP versions
    echo "Updating wp-config.php for PHP 8.1 compatibility..."
    sed $ioption -E "s/(Happy publishing.+)/\1\nmysqli_report(MYSQLI_REPORT_OFF);/" "$WP_CORE_DIR/wp-config.php"

	echo "Installing WordPress core..."
	wp core install --url="$WP_SITE_URL" --title="Example" --admin_user=admin --admin_password=password --admin_email=info@example.com --skip-email
	echo "WordPress configuration completed"
}

install_test_suite() {
	echo "Installing WordPress test suite..."
	
	# portable in-place argument for both GNU sed and Mac OSX sed
	if [[ $(uname -s) == 'Darwin' ]]; then
		local ioption='-i.bak'
	else
		local ioption='-i'
	fi

	# set up testing suite if it doesn't yet exist
	if [ ! -d $WP_TESTS_DIR ]; then
		echo "Creating test suite directory: $WP_TESTS_DIR"
		# set up testing suite
		mkdir -p $WP_TESTS_DIR
		echo "Downloading test suite includes from WordPress ${WP_TESTS_TAG}..."
		svn co --quiet https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/includes/ $WP_TESTS_DIR/includes
		echo "Downloading test suite data from WordPress ${WP_TESTS_TAG}..."
		svn co --quiet https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/data/ $WP_TESTS_DIR/data
	else
		echo "Test suite directory already exists: $WP_TESTS_DIR"
	fi

	if [ ! -f wp-tests-config.php ]; then
		echo "Creating wp-tests-config.php..."
		download https://develop.svn.wordpress.org/${WP_TESTS_TAG}/wp-tests-config-sample.php "$WP_TESTS_DIR"/wp-tests-config.php

		# MySQL default reporting level has changed in PHP 8.1, here we forcing it to be the same in all tested PHP versions
		echo "Updating wp-tests-config.php for PHP 8.1 compatibility..."
		sed $ioption -E "s/(youremptytestdbnamehere.+)/\1\nmysqli_report(MYSQLI_REPORT_OFF);/" "$WP_TESTS_DIR/wp-tests-config.php"

		# remove all forward slashes in the end
		WP_CORE_DIR=$(echo $WP_CORE_DIR | sed "s:/\+$::")
		echo "Configuring test database settings..."
		sed $ioption "s:dirname( __FILE__ ) . '/src/':'$WP_CORE_DIR/':" "$WP_TESTS_DIR"/wp-tests-config.php
		sed $ioption "s/youremptytestdbnamehere/$DB_NAME/" "$WP_TESTS_DIR"/wp-tests-config.php
		sed $ioption "s/yourusernamehere/$DB_USER/" "$WP_TESTS_DIR"/wp-tests-config.php
		sed $ioption "s/yourpasswordhere/$DB_PASS/" "$WP_TESTS_DIR"/wp-tests-config.php
		sed $ioption "s|localhost|${DB_HOST}|" "$WP_TESTS_DIR"/wp-tests-config.php
	else
		echo "wp-tests-config.php already exists"
	fi
	echo "WordPress test suite installation completed"
}

install_db() {
	if [ ${SKIP_DB_CREATE} = "true" ]; then
		return 0
	fi

	wait_db
	local MYSQLADMIN_FLAGS=$(get_db_connection_flags)

	# drop database if exists
	set +e
	mysqladmin drop --force $DB_NAME $MYSQLADMIN_FLAGS &> /dev/null
	set -e

	# create database
	mysqladmin create $DB_NAME $MYSQLADMIN_FLAGS
}

install_woocommerce() {
	WC_INSTALL_EXTRA=''
	INSTALLED_WC_VERSION=$(wp plugin get woocommerce --field=version)

	if [[ $WC_VERSION == 'beta' ]] || [[ $WC_VERSION == 'rc' ]]; then
		# Get the latest non-trunk version number from the .org repo. This will usually be the latest release, beta, or rc.
		WC_VERSION=$(curl https://api.wordpress.org/plugins/info/1.0/woocommerce.json | jq -r '.versions | with_entries(select(.key|match("'$WC_VERSION'";"i"))) | keys | sort_by( . | split("-")[0] | split(".") | map(tonumber) ) | last' --sort-keys)
	fi

	if [[ -n $INSTALLED_WC_VERSION ]] && [[ $WC_VERSION == 'latest' ]]; then
		# WooCommerce is already installed, we just must update it to the latest stable version
		wp plugin update woocommerce
		wp plugin activate woocommerce
	else
		if [[ $INSTALLED_WC_VERSION != $WC_VERSION ]]; then
			# WooCommerce is installed but it's the wrong version, overwrite the installed version
			WC_INSTALL_EXTRA+=" --force"
		fi
		if [[ $WC_VERSION != 'latest' ]] && [[ $WC_VERSION != 'beta' ]]; then
			WC_INSTALL_EXTRA+=" --version=$WC_VERSION"
		fi
		wp plugin install woocommerce --activate$WC_INSTALL_EXTRA

		# Work around to get database tables installed for WC 6.4.0-beta
		# See https://github.com/woocommerce/woocommerce-admin/pull/8384
		wp cron event run --due-now
	fi
}

install_gutenberg() {
	echo "Installing Gutenberg plugin..."
	
	# Debug: Show environment variables
	echo "Debug: Environment variables for SSL bypass:"
	echo "  WP_CLI_DISABLE_SSL_VERIFY: ${WP_CLI_DISABLE_SSL_VERIFY:-'not set'}"
	echo "  WP_CLI_HTTP_VERIFY_SSL: ${WP_CLI_HTTP_VERIFY_SSL:-'not set'}"
	echo "  CURL_INSECURE: ${CURL_INSECURE:-'not set'}"
	
	# Debug: Test WP-CLI connectivity first
	echo "Debug: Testing WP-CLI connectivity..."
	wp --info 2>&1 | head -5 || echo "WP-CLI info failed"
	
	INSTALLED_GUTENBERG_VERSION=$(wp plugin get gutenberg --field=version 2>/dev/null || echo "")
	echo "Current Gutenberg version: ${INSTALLED_GUTENBERG_VERSION:-'not installed'}"
	echo "Target Gutenberg version: $GUTENBERG_VERSION"

	if [[ -n $INSTALLED_GUTENBERG_VERSION ]] && [[ $GUTENBERG_VERSION == 'latest' ]]; then
		# Gutenberg is already installed, we just must update it to the latest stable version
		echo "Updating Gutenberg to latest version..."
		wp plugin update gutenberg
		echo "Activating Gutenberg..."
		wp plugin activate gutenberg
	else
		if [[ $INSTALLED_GUTENBERG_VERSION != $GUTENBERG_VERSION ]]; then
			# Gutenberg is installed but it's the wrong version, overwrite the installed version
			echo "Gutenberg version mismatch, will force reinstall..."
			GUTENBERG_INSTALL_EXTRA+=" --force"
		fi

		if [[ $GUTENBERG_VERSION != 'latest' ]]; then
			echo "Installing specific Gutenberg version: $GUTENBERG_VERSION"
			GUTENBERG_INSTALL_EXTRA+=" --version=$GUTENBERG_VERSION"
		else
			echo "Installing latest Gutenberg version..."
		fi

		echo "Running: wp plugin install gutenberg --activate$GUTENBERG_INSTALL_EXTRA"
		wp plugin install gutenberg --activate$GUTENBERG_INSTALL_EXTRA
	fi
	echo "Gutenberg installation completed"
}

install_wp
install_db
configure_wp
install_test_suite
install_gutenberg
install_woocommerce
