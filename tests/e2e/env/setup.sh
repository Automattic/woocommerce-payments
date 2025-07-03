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

# Function to handle permissions in a cross-platform way
handle_permissions() {
    local path=$1
    if [[ "$(uname)" == "Darwin" ]]; then
        # For MacOS environments, use less strict permissions
        echo "Setting MacOS compatible permissions for $path"
        chmod -R 755 "$path"
    else
        # For Linux/CI environments
        echo "Setting Linux/CI permissions for $path"
        if ! sudo chown www-data:www-data -R "$path"; then
            echo "Failed to set permissions on $path"
            exit 1
        fi
    fi
}

# Variables
BLOG_ID=${E2E_JP_SITE_ID-111}
WC_GUEST_EMAIL=$(<"$USERS_CONFIG_JSON_PATH" jq -r '.users.guest.email')
WC_CUSTOMER_EMAIL=$(<"$USERS_CONFIG_JSON_PATH" jq -r '.users.customer.email')
WC_CUSTOMER_USERNAME=$(<"$USERS_CONFIG_JSON_PATH" jq -r '.users.customer.username')
WC_CUSTOMER_PASSWORD=$(<"$USERS_CONFIG_JSON_PATH" jq -r '.users.customer.password')
WP_ADMIN=$(<"$USERS_CONFIG_JSON_PATH" jq -r '.users.admin.username')
WP_ADMIN_PASSWORD=$(<"$USERS_CONFIG_JSON_PATH" jq -r '.users.admin.password')
WP_ADMIN_EMAIL=$(<"$USERS_CONFIG_JSON_PATH" jq -r '.users.admin.email')
WP_EDITOR=$(<"$USERS_CONFIG_JSON_PATH" jq -r '.users.editor.username')
WP_EDITOR_PASSWORD=$(<"$USERS_CONFIG_JSON_PATH" jq -r '.users.editor.password')
WP_EDITOR_EMAIL=$(<"$USERS_CONFIG_JSON_PATH" jq -r '.users.editor.email')
SITE_TITLE="WooPayments E2E site"
SITE_URL=$WP_URL

if [[ $FORCE_E2E_DEPS_SETUP ]]; then
	sudo rm -rf tests/e2e/deps
fi

# Setup Transact Platform local server instance.
# Only if E2E_USE_LOCAL_SERVER is present & equals to true.
if [[ "$E2E_USE_LOCAL_SERVER" != false ]]; then
	if [[ ! -d "$SERVER_PATH" ]]; then
		step "Fetching server (branch ${TRANSACT_PLATFORM_SERVER_BRANCH-trunk})"

		if [[ -z $TRANSACT_PLATFORM_SERVER_REPO ]]; then
			echo "TRANSACT_PLATFORM_SERVER_REPO env variable is not defined"
			exit 1;
		fi

		rm -rf "$SERVER_PATH"
		git clone --depth=1 --branch "${TRANSACT_PLATFORM_SERVER_BRANCH-trunk}" "$TRANSACT_PLATFORM_SERVER_REPO" "$SERVER_PATH"
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
	define( 'WOOPAY_URL', 'https://pay.woo.com' );
	define( 'WOOPAY_BLOG_ID', '$E2E_WOOPAY_BLOG_ID' );
	"
	printf "$SECRETS" > "local/secrets.php"
	echo "Secrets created"

	step "Starting SERVER containers"
	redirect_output docker compose -f docker-compose.yml -f docker-compose.e2e.yml up --build --force-recreate -d

	# Get WordPress instance port number from running containers, and print a debug line to show if it works.
	WP_LISTEN_PORT=$(docker ps | grep "$SERVER_CONTAINER" | sed -En "s/.*0:([0-9]+).*/\1/p")
	echo "WordPress instance listening on port ${WP_LISTEN_PORT}"

	if [[ -n $CI ]]; then
		echo "Setting docker folder permissions"
		handle_permissions "$SERVER_PATH/docker/wordpress"
		touch "$SERVER_PATH/logstash.log"
		handle_permissions "$SERVER_PATH/logstash.log"
	fi

	step "Setting up SERVER containers"
	"$SERVER_PATH"/local/bin/docker-setup.sh

	step "Configuring server with stripe account"
	"$SERVER_PATH"/local/bin/link-account.sh "$BLOG_ID" "$E2E_WCPAY_STRIPE_ACCOUNT_ID" test 1 1

	step "Ensuring the site has the required flags for the e2e tests running against the local server"
	"$SERVER_PATH"/local/bin/setup-account-metas.sh "$BLOG_ID"

	if [[ -n $CI ]]; then
		step "Disable Xdebug on server container"
		docker exec "$SERVER_CONTAINER" \
		sh -c 'echo "#zend_extension=xdebug" > /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini && echo "Xdebug disabled."'
	fi
fi

cd "$cwd"

if [[ ! -d "$DEV_TOOLS_PATH" ]]; then
	step "Fetching dev tools"
	if [[ -z $WCP_DEV_TOOLS_REPO ]]; then
		echo "WCP_DEV_TOOLS_REPO env variable is not defined"
		exit 1;
	fi

	rm -rf "$DEV_TOOLS_PATH"
	git clone --depth=1 --branch "${WCP_DEV_TOOLS_BRANCH-trunk}" "$WCP_DEV_TOOLS_REPO" "$DEV_TOOLS_PATH"
fi

step "Starting CLIENT containers"
redirect_output docker compose -f "$E2E_ROOT"/env/docker-compose.yml up --build --force-recreate -d wordpress
if [[ -z $CI ]]; then
	docker compose -f "$E2E_ROOT"/env/docker-compose.yml up --build --force-recreate -d phpMyAdmin
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
cli wp db check --skip_ssl --path=/var/www/html --quiet > /dev/null
while [[ $? -ne 0 ]]; do
	echo "Waiting until the service is ready..."
	sleep 5
	cli wp db check --skip_ssl --path=/var/www/html --quiet > /dev/null
done
echo "Client DB is up and running..."
set -e

echo
echo "Setting up environment..."
echo

if [[ -n $CI ]]; then
	echo "Setting docker folder permissions"
	handle_permissions "$E2E_ROOT/docker/wordpress/wp-content"
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

# Ensuring that the jetpack "account protection" feature is disabled,
# since the passwords for the locally run e2e tests can be allowed to be weak.
cli wp config set DISABLE_JETPACK_ACCOUNT_PROTECTION true --raw

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
cli wp plugin install https://github.com/WP-API/Basic-Auth/archive/master.zip --activate --force

echo "Installing and activating Storefront theme..."
cli wp theme install storefront --activate
cli wp theme install twentytwentyfour

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

echo "Deactivating Coming Soon mode in WooCommerce..."
cli wp option set woocommerce_coming_soon "no"

echo "Enabling company field as an optional parameter in checkout form..."
cli wp option set woocommerce_checkout_company_field "optional"

echo "Importing WooCommerce shop pages..."
cli wp wc --user=admin tool run install_pages

INSTALLED_WC_VERSION=$(cli_debug wp plugin get woocommerce --field=version)

# Start - Workaround for > WC 8.3 compatibility by updating cart & checkout pages to use shortcode.
# To be removed when WooPayments L-2 support is >= WC 8.3
IS_WORKAROUND_REQUIRED=$(cli_debug wp eval "echo version_compare(\"$INSTALLED_WC_VERSION\", \"8.3\", \">=\");")

if [[ "$IS_WORKAROUND_REQUIRED" = "1" ]]; then
	echo "Updating cart & checkout pages for WC > 8.3 compatibility..."
	# Get cart & checkout page IDs.
	CART_PAGE_ID=$(cli_debug wp option get woocommerce_cart_page_id)
	CHECKOUT_PAGE_ID=$(cli_debug wp option get woocommerce_checkout_page_id)

	CART_SHORTCODE="<!-- wp:shortcode -->[woocommerce_cart]<!-- /wp:shortcode -->"
	CHECKOUT_SHORTCODE="<!-- wp:shortcode -->[woocommerce_checkout]<!-- /wp:shortcode -->"

	# Ensuring that a "checkout-wcb" page exists, which is the one that will contain the "WooCommerce Blocks" checkout
	cli wp post create --from-post="$CHECKOUT_PAGE_ID" --post_type="page" --post_title="Checkout WCB" --post_status="publish" --post_name="checkout-wcb"
	CHECKOUT_WCB_PAGE_ID=$(cli_debug wp post url-to-id checkout-wcb)

	# Update cart & checkout pages to use shortcode.
	cli wp post update "$CART_PAGE_ID" --post_content="$CART_SHORTCODE"
	cli wp post update "$CHECKOUT_PAGE_ID" --post_content="$CHECKOUT_SHORTCODE"

	# making the checkout pages full width, so that the sidebar doesn't take too much room in the UI.
	cli wp post meta update "$CHECKOUT_PAGE_ID" _wp_page_template "template-fullwidth.php"
	cli wp post meta update "$CHECKOUT_WCB_PAGE_ID" _wp_page_template "template-fullwidth.php"
fi
# End - Workaround for > WC 8.3 compatibility by updating cart & checkout pages to use shortcode.

echo "Importing some sample data..."
cli wp import wp-content/plugins/woocommerce/sample-data/sample_products.xml --authors=skip

echo "Removing customer account if present ..."
cli wp user delete "$WC_CUSTOMER_EMAIL" --yes

echo "Removing guest account if present ..."
cli wp user delete "$WC_GUEST_EMAIL" --yes

echo "Adding customer account ..."
cli wp user create "$WC_CUSTOMER_USERNAME" "$WC_CUSTOMER_EMAIL" --role=customer --user_pass="$WC_CUSTOMER_PASSWORD"

echo "Adding editor account ..."
cli wp user create "$WP_EDITOR" "$WP_EDITOR_EMAIL" --role=editor --user_pass="$WP_EDITOR_PASSWORD"

# TODO: Build a zip and use it to install plugin to make sure production build is under test.
if [[ "$WCPAY_USE_BUILD_ARTIFACT" = true ]]; then
	echo "Creating WooPayments zip file from GitHub artifact..."
	mv "$WCPAY_ARTIFACT_DIRECTORY"/woocommerce-payments "$WCPAY_ARTIFACT_DIRECTORY"/woocommerce-payments-build
    cd "$WCPAY_ARTIFACT_DIRECTORY" && zip -r "$cwd"/woocommerce-payments-build.zip . && cd "$cwd"

	echo "Installing & activating the WooPayments plugin using the zip file created..."
	cli wp plugin install wp-content/plugins/woocommerce-payments/woocommerce-payments-build.zip --activate
else
	echo "Activating the WooPayments plugin..."
	cli wp plugin activate woocommerce-payments
fi

echo "Setting up WooPayments..."
if [[ "0" == "$(cli wp option list --search=woocommerce_woocommerce_payments_settings --format=count)" ]]; then
	echo "Creating WooPayments settings"
	cli wp option set woocommerce_woocommerce_payments_settings --format=json '{"enabled":"yes"}'
else
	echo "Updating WooPayments settings"
	cli wp option set woocommerce_woocommerce_payments_settings --format=json '{"enabled":"yes"}'
fi

echo "Activating dev tools plugin"
cli wp plugin activate "$DEV_TOOLS_DIR"

echo "Disabling WPCOM requests proxy"
cli wp option set wcpaydev_proxy 0

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
	cli wp wcpay_dev set_blog_id "$BLOG_ID" --blog_token="$E2E_JP_BLOG_TOKEN" --user_token="$E2E_JP_USER_TOKEN"
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

	echo "Moving the unzipped plugin files..."
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

echo "Removing some WooCommerce Core 'tour' options so they don't interfere with tests"
cli wp option set woocommerce_orders_report_date_tour_shown yes

echo "Creating screenshots directory"
mkdir -p $WCP_ROOT/screenshots
handle_permissions $WCP_ROOT/screenshots

echo "Disabling rate limiter for card declined in E2E tests"
cli wp option set wcpay_session_rate_limiter_disabled_wcpay_card_declined_registry yes

echo "Dismissing fraud protection welcome tour in E2E tests"
cli wp option set wcpay_fraud_protection_welcome_tour_dismissed 1

echo "Removing all coupons ..."
cli wp db query "DELETE p, m FROM wp_posts p LEFT JOIN wp_postmeta m ON p.ID = m.post_id WHERE p.post_type = 'shop_coupon'"

echo "Setting up a coupon for E2E tests"
cli wp wc --user=admin shop_coupon create --code=free --amount=100 --discount_type=percent --individual_use=true --free_shipping=true

# HPOS was officially released in WooCommerce 8.2.0, so we need to check if we should sync COT or HPOS data.
IS_HPOS_AVAILABLE=$(cli_debug wp eval "echo version_compare(\"$INSTALLED_WC_VERSION\", \"8.2\", \">=\");")

if [[ ${IS_HPOS_AVAILABLE} ]]; then
	echo "Syncing HPOS data"
	cli wp wc hpos sync
else
	echo "Syncing COT data"
	cli wp wc cot sync
fi

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
