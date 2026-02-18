#!/bin/bash

# Exit if any command fails.
set -e

# Load worktree-specific config if available
if [ -f ".env" ]; then
    source .env
fi

# Determine container name (from .env or parameter or default)
if [ -n "$WORKTREE_ID" ]; then
    DEFAULT_CONTAINER="wcpay_wp_${WORKTREE_ID}"
else
    DEFAULT_CONTAINER="wcpay_wp_default"
fi
WP_CONTAINER=${1:-$DEFAULT_CONTAINER}

# Determine site URL (from .env or environment or default)
DEFAULT_PORT=${WORDPRESS_PORT:-8082}
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

# wp-config.php settings are per-worktree (file-based), so always set them
# This must run BEFORE the early exit check since each container has its own wp-config.php
echo "Configuring wp-config.php for this worktree..."
cli wp config set DOCKER_HOST "\$_SERVER['HTTP_X_FORWARDED_HOST'] ?? \$_SERVER['HTTP_X_ORIGINAL_HOST'] ?? \$_SERVER['HTTP_HOST'] ?? 'localhost'" --raw
# Ensure $_SERVER['HTTP_HOST'] is overwritten with DOCKER_HOST (only adding this line if not already present)
docker exec $WP_CONTAINER bash -c "grep -q '\\\$_SERVER\[.HTTP_HOST.\] = DOCKER_HOST' /var/www/html/wp-config.php || sed -i \"/define.*'DOCKER_HOST'/a \\\\\\\$_SERVER['HTTP_HOST'] = DOCKER_HOST;\" /var/www/html/wp-config.php"
cli wp config set DOCKER_REQUEST_URL "( ! empty( \$_SERVER['HTTPS'] ) ? 'https://' : 'http://' ) . DOCKER_HOST" --raw
cli wp config set WP_SITEURL DOCKER_REQUEST_URL --raw
cli wp config set WP_HOME DOCKER_REQUEST_URL --raw

echo "Enabling WordPress debug flags"
cli wp config set WP_DEBUG true --raw
# WP_DEBUG_DISPLAY=false to avoid issues with plugins that output content before headers
# Errors are still logged to wp-content/debug.log when WP_DEBUG_LOG is true
cli wp config set WP_DEBUG_DISPLAY false --raw
cli wp config set WP_DEBUG_LOG true --raw
cli wp config set SCRIPT_DEBUG true --raw

echo "Enabling WordPress development environment (enforces Stripe testing mode)"
cli wp config set WP_ENVIRONMENT_TYPE development

# If the plugin is already active then return early
cli wp plugin is-active woocommerce-payments > /dev/null
if [[ $? -eq 0 ]]; then
	set -e
	echo
	echo "WooPayments is installed and active"
	echo "SUCCESS! You should now be able to access http://${SITE_URL}/wp-admin/"
	echo "You can login by using the username and password both as 'admin'"
	exit 0
fi

set -e

echo
echo "Setting up environment..."
echo

# Check if WordPress is already installed in the database
cli wp core is-installed --path=/var/www/html 2>/dev/null
WP_INSTALLED=$?

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

echo "Updating permalink structure"
cli wp rewrite structure '/%postname%/'

echo "Installing and activating WooCommerce..."
cli wp plugin install woocommerce --activate

echo "Installing and activating Storefront theme..."
cli wp theme install storefront --activate

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

echo "Installing and activating the WordPress Importer plugin..."
cli wp plugin install wordpress-importer --activate

echo "Importing some sample data..."
cli wp import wp-content/plugins/woocommerce/sample-data/sample_products.xml --authors=skip

echo "Activating the WooPayments plugin..."
cli wp plugin activate woocommerce-payments

echo "Setting up WooPayments..."
if [[ "0" == "$(cli wp option list --search=woocommerce_woocommerce_payments_settings --format=count)" ]]; then
	echo "Creating WooPayments settings"
	cli wp option add woocommerce_woocommerce_payments_settings --format=json '{"enabled":"yes"}'
else
	echo "Updating WooPayments settings"
	cli wp option update woocommerce_woocommerce_payments_settings --format=json '{"enabled":"yes"}'
fi

echo "Installing and activating Disable WordPress Updates..."
cli wp plugin install disable-wordpress-updates --activate

echo "Installing dev tools plugin..."
# Load local.env for custom dev-tools path (same file the post-merge hook uses).
if [[ -f "$(pwd)/local.env" ]]; then
	source "$(pwd)/local.env"
fi
DEV_TOOLS_CLONE_PATH=${LOCAL_WCPAY_DEV_TOOLS_PLUGIN_REPO_PATH:-"docker/wordpress/wp-content/plugins/woocommerce-payments-dev-tools"}

set +e
if [[ -d "$DEV_TOOLS_CLONE_PATH/.git" ]]; then
	echo "Dev tools already cloned at $DEV_TOOLS_CLONE_PATH — skipping clone."
	cli wp plugin activate woocommerce-payments-dev-tools
else
	git clone git@github.com:Automattic/woocommerce-payments-dev-tools.git "$DEV_TOOLS_CLONE_PATH"
	if [[ $? -eq 0 ]]; then
		cli wp plugin activate woocommerce-payments-dev-tools
	else
		echo
		echo "WARN: Could not clone the dev tools repository. Skipping the install."
	fi
fi;
set -e

echo
echo "SUCCESS! You should now be able to access http://${SITE_URL}/wp-admin/"
echo "You can login by using the username and password both as 'admin'"
