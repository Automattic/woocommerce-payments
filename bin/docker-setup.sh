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

# Seed shared volumes from local directories if volumes are empty
bash bin/docker-seed-volumes.sh

# Check if WooCommerce plugin files exist (not just DB state)
# This handles shared DB setups where DB says plugins are active but files don't exist in this worktree
WOOCOMMERCE_EXISTS=$(docker exec $WP_CONTAINER test -d /var/www/html/wp-content/plugins/woocommerce && echo "yes" || echo "no")

# Check if WordPress is already installed in the database
cli wp core is-installed --path=/var/www/html > /dev/null 2>&1
WP_INSTALLED=$?

# wp-config.php settings are per-worktree (file-based), so always set them
# This must run BEFORE the early exit check since each container has its own wp-config.php
echo "Configuring wp-config.php for this worktree..."
cli wp config set DOCKER_HOST "\$_SERVER['HTTP_X_FORWARDED_HOST'] ?? \$_SERVER['HTTP_X_ORIGINAL_HOST'] ?? \$_SERVER['HTTP_HOST'] ?? 'localhost'" --raw
# Ensure $_SERVER['HTTP_HOST'] is overwritten with DOCKER_HOST (only adding this line if not already present)
docker exec $WP_CONTAINER bash -c "grep -q '\\\$_SERVER\[.HTTP_HOST.\] = DOCKER_HOST' /var/www/html/wp-config.php || sed -i \"/define.*'DOCKER_HOST'/a \\\\\\\$_SERVER['HTTP_HOST'] = DOCKER_HOST;\" /var/www/html/wp-config.php"
cli wp config set DOCKER_REQUEST_URL "( ! empty( \$_SERVER['HTTPS'] ) ? 'https://' : 'http://' ) . DOCKER_HOST" --raw
cli wp config set WP_SITEURL DOCKER_REQUEST_URL --raw
cli wp config set WP_HOME DOCKER_REQUEST_URL --raw

cli wp config set WP_DEBUG true --raw
cli wp config set WP_DEBUG_DISPLAY false --raw
cli wp config set WP_DEBUG_LOG true --raw
cli wp config set SCRIPT_DEBUG true --raw
cli wp config set WP_ENVIRONMENT_TYPE development

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
# Check if plugin exists in the container (shared volume)
DEV_TOOLS_EXISTS=$(docker exec $WP_CONTAINER test -d /var/www/html/wp-content/plugins/woocommerce-payments-dev-tools && echo "yes" || echo "no")
if [[ "$DEV_TOOLS_EXISTS" == "no" ]]; then
	echo "Dev tools plugin not found in shared volume, attempting to install..."
	# Clone to a temp directory and copy into the container
	TEMP_DIR=$(mktemp -d)
	echo "Cloning dev tools to $TEMP_DIR..."
	if git clone --depth 1 git@github.com:Automattic/woocommerce-payments-dev-tools.git "$TEMP_DIR/woocommerce-payments-dev-tools"; then
		echo "Copying plugin to container..."
		docker cp "$TEMP_DIR/woocommerce-payments-dev-tools" "$WP_CONTAINER:/var/www/html/wp-content/plugins/"
		echo "Setting permissions..."
		docker exec $WP_CONTAINER chown -R www-data:www-data /var/www/html/wp-content/plugins/woocommerce-payments-dev-tools
		DEV_TOOLS_EXISTS="yes"
		echo "Dev tools plugin installed successfully."
	else
		echo "WARN: git clone failed. You may need to set up SSH keys for github.com"
	fi
	rm -rf "$TEMP_DIR"
fi
if [[ "$DEV_TOOLS_EXISTS" == "yes" ]]; then
	cli wp plugin activate woocommerce-payments-dev-tools
else
	echo
	echo "WARN: Could not install the dev tools plugin. You can install it manually later."
fi
set -e

# Health check with retries
HEALTH_CHECK_RETRIES=${HEALTH_CHECK_RETRIES:-3}
HEALTH_CHECK_DELAY=${HEALTH_CHECK_DELAY:-10}
HEALTH_CHECK_URL="http://${SITE_URL}/wp-admin/"

echo "Running health check..."
health_passed=false
for i in $(seq 1 $HEALTH_CHECK_RETRIES); do
    http_code=$(curl -s -o /dev/null -w "%{http_code}" "$HEALTH_CHECK_URL" 2>/dev/null || echo "000")
    if [[ "$http_code" == "200" ]] || [[ "$http_code" == "302" ]]; then
        health_passed=true
        break
    fi
    if [[ $i -lt $HEALTH_CHECK_RETRIES ]]; then
        echo "Health check attempt $i failed (HTTP $http_code), retrying in ${HEALTH_CHECK_DELAY}s..."
        sleep $HEALTH_CHECK_DELAY
    fi
done

if [[ "$health_passed" != "true" ]]; then
    echo
    echo "ERROR: Health check failed after $HEALTH_CHECK_RETRIES attempts"
    echo "Could not reach $HEALTH_CHECK_URL"
    echo ""
    echo "Troubleshooting:"
    echo "  1. Check container logs: docker logs $WP_CONTAINER"
    echo "  2. Verify port is not in use: lsof -i :$DEFAULT_PORT"
    echo "  3. Try restarting: npm run down && npm run up:recreate"
    exit 1
fi

echo
echo "SUCCESS! You should now be able to access http://${SITE_URL}/wp-admin/"
echo "You can login by using the username and password both as 'admin'"
