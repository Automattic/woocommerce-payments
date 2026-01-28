#!/bin/bash

# Exit if any command fails.
set -e

# Load worktree-specific config if available
if [ -f ".env.local" ]; then
    source .env.local
fi

# Determine container name (from .env.local or parameter or default)
if [ -n "$WORKTREE_ID" ]; then
    DEFAULT_CONTAINER="wcpay_wp_${WORKTREE_ID}"
else
    DEFAULT_CONTAINER="wcpay_wp_default"
fi
WP_CONTAINER=${1:-$DEFAULT_CONTAINER}

# Determine site URL (from .env.local or environment or default)
DEFAULT_PORT=${WP_PORT:-8082}
SITE_URL=${WP_URL:-"localhost:${DEFAULT_PORT}"}

redirect_output() {
	if [[ -z "$DEBUG" ]]; then
        "$@" > /dev/null
    else
        "$@"
    fi
}

cli()
{
	INTERACTIVE=''
	if [ -t 1 ] ; then
		INTERACTIVE='-it'
	fi

	redirect_output docker exec $INTERACTIVE --env-file default.env --user www-data $WP_CONTAINER "$@"
}

set +e
# Wait for containers to be started up before the setup.
# The db being accessible means that the db container started and the WP has been downloaded and the plugin linked
cli wp db check --skip_ssl --path=/var/www/html --quiet > /dev/null
while [[ $? -ne 0 ]]; do
	echo "Waiting until the service is ready..."
	sleep 5
	cli wp db check --skip_ssl --path=/var/www/html --quiet > /dev/null
done

# Check if WooCommerce plugin files exist (not just DB state)
# This handles shared DB setups where DB says plugins are active but files don't exist in this worktree
WOOCOMMERCE_EXISTS=$(docker exec $WP_CONTAINER test -d /var/www/html/wp-content/plugins/woocommerce && echo "yes" || echo "no")

# Check if WordPress is already installed in the database
cli wp core is-installed --path=/var/www/html > /dev/null 2>&1
WP_INSTALLED=$?

# If WooPayments is active AND WooCommerce files exist, we can skip setup entirely
cli wp plugin is-active woocommerce-payments > /dev/null
if [[ $? -eq 0 ]] && [[ "$WOOCOMMERCE_EXISTS" == "yes" ]]; then
	set -e
	echo
	echo "WooPayments is installed and active"
	echo "SUCCESS! You should now be able to access http://${SITE_URL}/wp-admin/"
	echo "You can login by using the username and password both as 'admin'"
	exit 0
fi

# Detect shared DB scenario: WordPress installed in DB but plugin files missing
SHARED_DB_MODE="no"
if [[ $WP_INSTALLED -eq 0 ]] && [[ "$WOOCOMMERCE_EXISTS" == "no" ]]; then
	SHARED_DB_MODE="yes"
	echo "Detected shared DB with missing plugin files. Installing files only (preserving DB settings)..."
fi

set -e

echo
echo "Setting up environment..."
echo

# Only run WordPress core install if not already installed
if [[ $WP_INSTALLED -ne 0 ]]; then
	echo "Setting up WordPress..."
	cli wp core install \
		--path=/var/www/html \
		--url=$SITE_URL \
		--title=${SITE_TITLE-"WooCommerce Payments Dev"} \
		--admin_name=${WP_ADMIN-admin} \
		--admin_password=${WP_ADMIN_PASSWORD-admin} \
		--admin_email=${WP_ADMIN_EMAIL-admin@example.com} \
		--skip-email

	echo "Updating WordPress to the latest version..."
	cli wp core update --quiet

	echo "Updating the WordPress database..."
	cli wp core update-db --quiet
else
	echo "WordPress already installed, skipping core setup..."
fi

# wp-config.php settings are per-worktree (file-based), so always set them
echo "Configuring WordPress to work with ngrok (in order to allow creating a Jetpack-WPCOM connection)";
cli wp config set DOCKER_HOST "\$_SERVER['HTTP_X_ORIGINAL_HOST'] ?? \$_SERVER['HTTP_HOST'] ?? 'localhost'" --raw
cli wp config set DOCKER_REQUEST_URL "( ! empty( \$_SERVER['HTTPS'] ) ? 'https://' : 'http://' ) . DOCKER_HOST" --raw
cli wp config set WP_SITEURL DOCKER_REQUEST_URL --raw
cli wp config set WP_HOME DOCKER_REQUEST_URL --raw

echo "Enabling WordPress debug flags"
cli wp config set WP_DEBUG true --raw
# Disable display to prevent _load_textdomain_just_in_time errors from being displayed
cli wp config set WP_DEBUG_DISPLAY false --raw
cli wp config set WP_DEBUG_LOG true --raw
cli wp config set SCRIPT_DEBUG true --raw

echo "Enabling WordPress development environment (enforces Stripe testing mode)";
cli wp config set WP_ENVIRONMENT_TYPE development

# Only set DB-stored settings if this is a fresh install (not shared DB mode)
if [[ "$SHARED_DB_MODE" == "no" ]]; then
	echo "Updating permalink structure"
	cli wp rewrite structure '/%postname%/'
fi

echo "Installing WooCommerce..."
cli wp plugin install woocommerce --activate --force

echo "Installing Storefront theme..."
cli wp theme install storefront
# Only activate theme if not in shared DB mode (preserve existing theme choice)
if [[ "$SHARED_DB_MODE" == "no" ]]; then
	cli wp theme activate storefront
fi

# Only set WooCommerce settings if this is a fresh install
if [[ "$SHARED_DB_MODE" == "no" ]]; then
	echo "Adding basic WooCommerce settings..."
	cli wp option set woocommerce_store_address "60 29th Street"
	cli wp option set woocommerce_store_address_2 "#343"
	cli wp option set woocommerce_store_city "San Francisco"
	cli wp option set woocommerce_default_country "US:CA"
	cli wp option set woocommerce_store_postcode "94110"
	cli wp option set woocommerce_currency "USD"
	cli wp option set woocommerce_product_type "both"
	cli wp option set woocommerce_allow_tracking "no"

	echo "Deactivating Coming Soon mode in WooCommerce..."
	cli wp option set woocommerce_coming_soon "no"

	echo "Enabling company field as an optional parameter in checkout form..."
	cli wp option set woocommerce_checkout_company_field "optional"

	echo "Importing WooCommerce shop pages..."
	cli wp wc --user=admin tool run install_pages
fi

echo "Installing WordPress Importer plugin..."
cli wp plugin install wordpress-importer --activate --force

# Only import sample data if this is a fresh install
if [[ "$SHARED_DB_MODE" == "no" ]]; then
	echo "Importing some sample data..."
	cli wp import wp-content/plugins/woocommerce/sample-data/sample_products.xml --authors=skip
fi

echo "Activating the WooPayments plugin..."
cli wp plugin activate woocommerce-payments

# Only set WooPayments settings if this is a fresh install
if [[ "$SHARED_DB_MODE" == "no" ]]; then
	echo "Setting up WooPayments..."
	if [[ "0" == "$(cli wp option list --search=woocommerce_woocommerce_payments_settings --format=count)" ]]; then
		echo "Creating WooPayments settings"
		cli wp option add woocommerce_woocommerce_payments_settings --format=json '{"enabled":"yes"}'
	else
		echo "Updating WooPayments settings"
		cli wp option update woocommerce_woocommerce_payments_settings --format=json '{"enabled":"yes"}'
	fi
fi

echo "Installing Disable WordPress Updates plugin..."
cli wp plugin install disable-wordpress-updates --activate --force

echo "Installing dev tools plugin..."
set +e
if [[ ! -d "docker/wordpress/wp-content/plugins/woocommerce-payments-dev-tools" ]]; then
	git clone git@github.com:Automattic/woocommerce-payments-dev-tools.git docker/wordpress/wp-content/plugins/woocommerce-payments-dev-tools
fi
if [[ -d "docker/wordpress/wp-content/plugins/woocommerce-payments-dev-tools" ]]; then
	cli wp plugin activate woocommerce-payments-dev-tools
else
	echo
	echo "WARN: Could not clone the dev tools repository. Skipping the install."
fi
set -e

echo
echo "SUCCESS! You should now be able to access http://${SITE_URL}/wp-admin/"
echo "You can login by using the username and password both as 'admin'"
