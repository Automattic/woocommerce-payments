#!/usr/bin/env bash

set -e

. ./tests/e2e/env/shared.sh

BLOG_ID=${E2E_BLOG_ID-111}

if [[ -f "$E2E_ROOT/config/local.env" ]]; then
	echo "Loading local env variables"
	. "$E2E_ROOT/config/local.env"
fi

if [[ $FORCE_E2E_DEPS_SETUP || ! -d $SERVER_PATH ]]; then
	step "Fetching server"

	if [[ -z $WCP_SERVER_REPO ]]; then
		echo "WCP_SERVER_REPO env variable is not defined"
		exit 1;
	fi

	rm -rf $SERVER_PATH
	git clone --depth=1 --branch ${WCP_SERVER_BRANCH-master} $WCP_SERVER_REPO $SERVER_PATH

else
	echo "Using cached server at ${SERVER_PATH}"
fi

cd $SERVER_PATH

step "Creating server secrets"
SECRETS="<?php
define( 'WCPAY_STRIPE_TEST_PUBLIC_KEY', '$E2E_WCPAY_STRIPE_TEST_PUBLIC_KEY' );
define( 'WCPAY_STRIPE_TEST_SECRET_KEY', '$E2E_WCPAY_STRIPE_TEST_SECRET_KEY' );
define( 'WCPAY_STRIPE_TEST_CLIENT_ID', '$E2E_WCPAY_STRIPE_TEST_CLIENT_ID' );
define( 'WCPAY_STRIPE_TEST_WEBHOOK_SIGNATURE_KEY', '$E2E_WCPAY_STRIPE_TEST_WEBHOOK_SIGNATURE_KEY' );
define( 'WCPAY_STRIPE_LIVE_PUBLIC_KEY', 'pk_live_XXXXXXX' );
define( 'WCPAY_STRIPE_LIVE_SECRET_KEY', 'sk_live_XXXXXXX' );
define( 'WCPAY_STRIPE_LIVE_CLIENT_ID', 'ca_live_XXXXXXX' );
define( 'WCPAY_OAUTH_ENCRYPT_KEY', str_repeat( 'a', SODIUM_CRYPTO_SECRETBOX_KEYBYTES ) );
"
printf "$SECRETS" > "local/secrets.php"
echo "Secrets created"

step "Starting server containers"
redirect_output docker-compose up --build --force-recreate -d

if [[ -n $CI ]]; then
	echo "Setting docker folder permissions"
	redirect_output sudo chown www-data:www-data -R ./docker/wordpress
	redirect_output ls -al ./docker
fi

step "Setting up server containers"
redirect_output local/bin/docker-setup.sh

step "Configuring server with stripe account"
redirect_output $SERVER_PATH/local/bin/link-account.sh $BLOG_ID $E2E_WCPAY_STRIPE_ACCOUNT_ID test 1

cd $cwd

if [[ $FORCE_E2E_DEPS_SETUP || ! -d $DEV_TOOLS_PATH ]]; then
	step "Fetching dev tools"
	if [[ -z $WCP_DEV_TOOLS_REPO ]]; then
		echo "WCP_DEV_TOOLS_REPO env variable is not defined"
		exit 1;
	fi

	rm -rf $DEV_TOOLS_PATH
	git clone --depth=1 --branch ${WCP_DEV_TOOLS_BRANCH-master} $WCP_DEV_TOOLS_REPO $DEV_TOOLS_PATH
fi

step "Starting client containers"
redirect_output docker-compose -f "$E2E_ROOT/env/docker-compose.yml" up --build --force-recreate -d wordpress
if [[ -z $CI ]]; then
	docker-compose -f "$E2E_ROOT/env/docker-compose.yml" up --build --force-recreate -d phpMyAdmin
fi

echo
step "Setting up client site"
# Need to use those credentials to comply with @woocommerce/e2e-environment
WP_ADMIN=admin
WP_ADMIN_PASSWORD=password
WP_CONTAINER="wcp_e2e_wordpress"
SITE_URL=$WP_URL
SITE_TITLE="WooCommerce Payments E2E site"

set +e
# Wait for containers to be started up before the setup.
# The db being accessible means that the db container started and the WP has been downloaded and the plugin linked
cli wp db check --path=/var/www/html --quiet > /dev/null
while [[ $? -ne 0 ]]; do
	echo "Waiting until the service is ready..."
	sleep 5s
	cli wp db check --path=/var/www/html --quiet > /dev/null
done

set -e

echo
echo "Setting up environment..."
echo

if [[ -n $CI ]]; then
	echo "Setting docker folder permissions"
	redirect_output sudo chown www-data:www-data -R $E2E_ROOT/docker/wordpress/wp-content
	redirect_output ls -al $E2E_ROOT/docker/wordpress
fi

echo "Pulling the WordPress CLI docker image..."
docker pull wordpress:cli > /dev/null

echo "Setting up WordPress..."
cli wp core install \
	--path=/var/www/html \
	--url="$SITE_URL" \
	--title="$SITE_TITLE" \
	--admin_name=${WP_ADMIN-admin} \
	--admin_password=${WP_ADMIN_PASSWORD-password} \
	--admin_email="${WP_ADMIN_EMAIL-admin@example.com}" \
	--skip-email

echo "Updating WordPress to the latest version..."
cli wp core update --quiet

echo "Updating the WordPress database..."
cli wp core update-db --quiet

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
cli wp option set woocommerce_enable_signup_and_login_from_checkout "yes"

echo "Importing WooCommerce shop pages..."
cli wp wc --user=admin tool run install_pages

echo "Installing and activating the WordPress Importer plugin..."
cli wp plugin install wordpress-importer --activate

echo "Importing some sample data..."
cli wp import wp-content/plugins/woocommerce/sample-data/sample_products.xml --authors=skip

# TODO: Build a zip and use it to install plugin to make sure production build is under test.
echo "Activating the WooCommerce Payments plugin..."
cli wp plugin activate woocommerce-payments

echo "Setting up WooCommerce Payments..."
if [[ "0" == "$(cli wp option list --search=woocommerce_woocommerce_payments_settings --format=count)" ]]; then
	echo "Creating WooCommerce Payments settings"
	cli wp option add woocommerce_woocommerce_payments_settings --format=json '{"enabled":"yes"}'
else
	echo "Updating WooCommerce Payments settings"
	cli wp option update woocommerce_woocommerce_payments_settings --format=json '{"enabled":"yes"}'
fi

echo "Activating dev tools plugin"
cli wp plugin activate $DEV_TOOLS_DIR

echo "Setting Jetpack blog_id"
cli wp wcpay_dev set_blog_id $BLOG_ID

echo "Setting redirection to local server"

# host.docker.internal is not available in linux. Use ip address for docker0 interface to redirect requests from container.
if [[ -n $CI ]]; then
	DOCKER_HOST=$(ip -4 addr show docker0 | grep -Po 'inet \K[\d.]+')
fi
cli wp wcpay_dev redirect_to "http://${DOCKER_HOST-host.docker.internal}:8086/wp-json/"

echo
step "Client site is up and running at http://${WP_URL}/wp-admin/"

