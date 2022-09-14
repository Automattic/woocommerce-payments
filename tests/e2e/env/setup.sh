#!/usr/bin/env bash

set -e

# Check for script dependencies.
# Exit if dependencies are not met.
if ! command -v jq &> /dev/null
then
    echo "The script requires jq library to be installed. For more info visit https://stedolan.github.io/jq/download/."
    exit 1
fi

. ./tests/e2e/env/shared.sh

if [[ -f "$E2E_ROOT/config/local.env" ]]; then
	echo "Loading local env variables"
	. "$E2E_ROOT/config/local.env"
fi

# Variables
BLOG_ID=${E2E_BLOG_ID-111}
WC_GUEST_EMAIL=$(<"$E2E_ROOT/config/test.json" jq -r '.users.guest.email')
WC_CUSTOMER_EMAIL=$(<"$E2E_ROOT/config/test.json" jq -r '.users.customer.email')
WC_CUSTOMER_USERNAME=$(<"$E2E_ROOT/config/test.json" jq -r '.users.customer.username')
WC_CUSTOMER_PASSWORD=$(<"$E2E_ROOT/config/test.json" jq -r '.users.customer.password')
WP_ADMIN=$(<"$E2E_ROOT/config/test.json" jq -r '.users.admin.username')
WP_ADMIN_PASSWORD=$(<"$E2E_ROOT/config/test.json" jq -r '.users.admin.password')
WP_ADMIN_EMAIL=$(<"$E2E_ROOT/config/test.json" jq -r '.users.admin.email')
SITE_TITLE="WooCommerce Payments E2E site"
SITE_URL=$WP_URL

# Setup WCPay local server instance.
# Only if E2E_USE_LOCAL_SERVER is present & equals to true.
if [[ "$E2E_USE_LOCAL_SERVER" != false ]]; then
	if [[ $FORCE_E2E_DEPS_SETUP || ! -d "$SERVER_PATH" ]]; then
		step "Fetching server (branch ${WCP_SERVER_BRANCH-trunk})"

		if [[ -z $WCP_SERVER_REPO ]]; then
			echo "WCP_SERVER_REPO env variable is not defined"
			exit 1;
		fi

		rm -rf "$SERVER_PATH"
		git clone --depth=1 --branch "${WCP_SERVER_BRANCH-trunk}" "$WCP_SERVER_REPO" "$SERVER_PATH"
	else
		echo "Using cached server at ${SERVER_PATH}"
	fi

	cd "$SERVER_PATH"

	step "Creating server secrets"
	SECRETS="<?php
	define( 'WCPAY_STRIPE_TEST_PUBLIC_KEY', '$E2E_WCPAY_STRIPE_TEST_PUBLIC_KEY' );
	define( 'WCPAY_STRIPE_TEST_SECRET_KEY', '$E2E_WCPAY_STRIPE_TEST_SECRET_KEY' );
	define( 'WCPAY_STRIPE_TEST_WEBHOOK_SIGNATURE_KEY', '$E2E_WCPAY_STRIPE_TEST_WEBHOOK_SIGNATURE_KEY' );
	define( 'WCPAY_STRIPE_LIVE_PUBLIC_KEY', 'pk_live_XXXXXXX' );
	define( 'WCPAY_STRIPE_LIVE_SECRET_KEY', 'sk_live_XXXXXXX' );
	define( 'WCPAY_ONBOARDING_ENCRYPT_KEY', str_repeat( 'a', SODIUM_CRYPTO_SECRETBOX_KEYBYTES ) );
	"
	printf "$SECRETS" > "local/secrets.php"
	echo "Secrets created"

	step "Starting SERVER containers"
	redirect_output docker-compose -f docker-compose.yml -f docker-compose.e2e.yml up --build --force-recreate -d

	# Get WordPress instance port number from running containers, and print a debug line to show if it works.
	WP_LISTEN_PORT=$(docker ps | grep "$SERVER_CONTAINER" | sed -En "s/.*0:([0-9]+).*/\1/p")
	echo "WordPress instance listening on port ${WP_LISTEN_PORT}"

	if [[ -n $CI ]]; then
		echo "Setting docker folder permissions"
		redirect_output sudo chown www-data:www-data -R ./docker/wordpress
		redirect_output ls -al ./docker
	fi

	step "Setting up SERVER containers"
	"$SERVER_PATH"/local/bin/docker-setup.sh

	step "Configuring server with stripe account"
	"$SERVER_PATH"/local/bin/link-account.sh "$BLOG_ID" "$E2E_WCPAY_STRIPE_ACCOUNT_ID" test 1 1

	if [[ -n $CI ]]; then
		step "Disable Xdebug on server container"
		docker exec "$SERVER_CONTAINER" \
		sh -c 'echo "#zend_extension=xdebug" > /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini && echo "Xdebug disabled."'
	fi
fi

cd "$cwd"

if [[ $FORCE_E2E_DEPS_SETUP || ! -d "$DEV_TOOLS_PATH" ]]; then
	step "Fetching dev tools"
	if [[ -z $WCP_DEV_TOOLS_REPO ]]; then
		echo "WCP_DEV_TOOLS_REPO env variable is not defined"
		exit 1;
	fi

	rm -rf "$DEV_TOOLS_PATH"
	git clone --depth=1 --branch "${WCP_DEV_TOOLS_BRANCH-trunk}" "$WCP_DEV_TOOLS_REPO" "$DEV_TOOLS_PATH"
fi

step "Starting CLIENT containers"
redirect_output docker-compose -f "$E2E_ROOT"/env/docker-compose.yml up --build --force-recreate -d wordpress
if [[ -z $CI ]]; then
	docker-compose -f "$E2E_ROOT"/env/docker-compose.yml up --build --force-recreate -d phpMyAdmin
fi

if [[ -n $CI ]]; then
	step "Disabling Xdebug on client container"
	docker exec "$CLIENT_CONTAINER" \
	sh -c 'echo "#zend_extension=xdebug" > /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini && echo "Xdebug disabled."'
fi

echo
step "Setting up CLIENT site"

# Wait for containers to be started up before the setup.
# The db being accessible means that the db container started and the WP has been downloaded and the plugin linked
set +e
cli wp db check --path=/var/www/html --quiet > /dev/null
while [[ $? -ne 0 ]]; do
	echo "Waiting until the service is ready..."
	sleep 5
	cli wp db check --path=/var/www/html --quiet > /dev/null
done
echo "Client DB is up and running..."
set -e

echo
echo "Setting up environment..."
echo

if [[ -n $CI ]]; then
	echo "Setting docker folder permissions"
	redirect_output sudo chown www-data:www-data -R "$E2E_ROOT"/docker/wordpress/wp-content
	redirect_output ls -al "$E2E_ROOT"/docker/wordpress
fi

echo "Pulling the WordPress CLI docker image..."
docker pull wordpress:cli > /dev/null

echo "Setting up WordPress..."
cli wp core install \
	--path=/var/www/html \
	--url="$SITE_URL" \
	--title="$SITE_TITLE" \
	--admin_name="${WP_ADMIN-admin}" \
	--admin_password="${WP_ADMIN_PASSWORD-password}" \
	--admin_email="${WP_ADMIN_EMAIL-admin@example.com}" \
	--skip-email

if [[ -n "$E2E_WP_VERSION" && "$E2E_WP_VERSION" != "latest" ]]; then
	echo "Installing specified WordPress version..."
	cli wp core update --version="$E2E_WP_VERSION" --force --quiet
else
	echo "Updating WordPress to the latest version..."
	cli wp core update --quiet
fi

echo "Updating the WordPress database..."
cli wp core update-db --quiet

# Disable displaying errors & log to file with WP_DEBUG when DEBUG flag is not present or false.
if [[ "$DEBUG" != true ]]; then
	cli wp config set WP_DEBUG_DISPLAY false --raw
	cli wp config set WP_DEBUG_LOG true --raw
fi

echo "Updating permalink structure"
cli wp rewrite structure '/%postname%/'

echo "Installing and activating WordPress Importer..."
cli wp plugin install wordpress-importer --activate

# Install WooCommerce
if [[ -n "$E2E_WC_VERSION" && $E2E_WC_VERSION != 'latest' ]]; then
	# If specified version is 'beta', fetch the latest beta version from WordPress.org API
	if [[ $E2E_WC_VERSION == 'beta' ]]; then
		E2E_WC_VERSION=$(curl https://api.wordpress.org/plugins/info/1.0/woocommerce.json | jq -r '.versions | with_entries(select(.key|match("beta";"i"))) | keys[-1]' --sort-keys)
	fi

	echo "Installing and activating specified WooCommerce version..."
	cli wp plugin install woocommerce --version="$E2E_WC_VERSION" --activate
else
	echo "Installing and activating latest WooCommerce version..."
	cli wp plugin install woocommerce --activate
fi

echo "Installing basic auth plugin for interfacing with the API"
cli wp plugin install https://github.com/WP-API/Basic-Auth/archive/master.zip --activate

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

echo "Importing some sample data..."
cli wp import wp-content/plugins/woocommerce/sample-data/sample_products.xml --authors=skip

echo "Removing customer account if present ..."
cli wp user delete "$WC_CUSTOMER_EMAIL" --yes

echo "Removing guest account if present ..."
cli wp user delete "$WC_GUEST_EMAIL" --yes

echo "Adding customer account ..."
cli wp user create "$WC_CUSTOMER_USERNAME" "$WC_CUSTOMER_EMAIL" --role=customer --user_pass="$WC_CUSTOMER_PASSWORD"

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
cli wp plugin activate "$DEV_TOOLS_DIR"

echo "Disabling WPCOM requests proxy"
cli wp option update wcpaydev_proxy 0

if [[ "$E2E_USE_LOCAL_SERVER" != false ]]; then
	echo "Setting redirection to local server"
	# host.docker.internal is not available in linux. Use ip address for docker0 interface to redirect requests from container.
	if [[ -n $CI ]]; then
		DOCKER_HOST=$(ip -4 addr show docker0 | grep -Po 'inet \K[\d.]+')
	fi
	cli wp wcpay_dev redirect_to "http://${DOCKER_HOST-host.docker.internal}:${WP_LISTEN_PORT}/wp-json/"

	echo "Setting Jetpack blog_id"
	cli wp wcpay_dev set_blog_id "$BLOG_ID"

	echo "Refresh WCPay Account Data"
	cli wp wcpay_dev refresh_account_data
else
	echo "Setting Jetpack blog_id"
	cli wp wcpay_dev set_blog_id "$BLOG_ID" --blog_token="$E2E_BLOG_TOKEN" --user_token="$E2E_USER_TOKEN"
fi

if [[ ! ${SKIP_WC_SUBSCRIPTIONS_TESTS} ]]; then
	echo "Install and activate the latest release of WooCommerce Subscriptions"
	cd "$E2E_ROOT"/deps

	LATEST_RELEASE_ASSET_ID=$(curl -H "Authorization: token $E2E_GH_TOKEN" https://api.github.com/repos/"$WC_SUBSCRIPTIONS_REPO"/releases/latest | jq -r '.assets[0].id')

	curl -LJ \
		-H "Authorization: token $E2E_GH_TOKEN" \
		-H "Accept: application/octet-stream" \
		--output woocommerce-subscriptions.zip \
		https://api.github.com/repos/"$WC_SUBSCRIPTIONS_REPO"/releases/assets/"$LATEST_RELEASE_ASSET_ID"

	unzip -qq woocommerce-subscriptions.zip -d woocommerce-subscriptions-source

	echo "Moving the unzipped plugin files. This may require your admin password"
	sudo mv woocommerce-subscriptions-source/woocommerce-subscriptions/* woocommerce-subscriptions

	cli wp plugin activate woocommerce-subscriptions

	rm -rf woocommerce-subscriptions-source

	echo "Import WooCommerce Subscription products"
	cli wp import wp-content/plugins/woocommerce-payments/tests/e2e/env/wc-subscription-products.xml --authors=skip

else
	echo "Skipping install of WooCommerce Subscriptions"
fi

if [[ ! ${SKIP_WC_ACTION_SCHEDULER_TESTS} ]]; then
	echo "Install and activate the latest release of Action Scheduler"
	cli wp plugin install action-scheduler --activate
else
	echo "Skipping install of Action Scheduler"
fi

if [[ ! ${SKIP_WC_BLOCKS_TESTS} ]]; then
	echo "Install and activate the latest release of WooCommerce Blocks"
	cli wp plugin install woo-gutenberg-products-block --activate
else
	echo "Skipping install of WooCommerce Blocks"
fi

echo "Creating screenshots directory"
mkdir -p $WCP_ROOT/screenshots

echo "Disabling rate limiter for card declined in E2E tests"
cli wp option add wcpay_session_rate_limiter_disabled_wcpay_card_declined_registry yes

# Log test configuration for visibility
echo
echo "*******************************************************"
echo "Current test configuration"
echo "*******************************************************"

echo
echo "WordPress version:"
cli_debug wp core version

echo
echo "WooCommerce version:"
cli_debug wp plugin get woocommerce --field=version

echo
echo "Docker environment:"
cli_debug wp cli info

echo
echo "*******************************************************"

step "Client site is up and running at http://${WP_URL}/wp-admin/"
