#!/bin/bash

# This is an isolated setup script that runs before your plugin's tests.
# Use it to:
# - Create test data specific to your plugin
# - Set up plugin settings
# - Create temporary files
# - Set up test users or other resources needed for the tests

# echo "Running isolated setup for the plugin..."
# wp post create --post_title="Test Post" # Create a test post
# wp user create testuser test@example.com # Create a test user

# Add your setup commands here

set -e

#USERS_CONFIG_JSON_PATH="../../env/users.json"

# Variables
#BLOG_ID=${E2E_BLOG_ID-111}
#WC_GUEST_EMAIL=$(<"$USERS_CONFIG_JSON_PATH" jq -r '.users.guest.email')
#WC_CUSTOMER_EMAIL=$(<"$USERS_CONFIG_JSON_PATH" jq -r '.users.customer.email')
#WC_CUSTOMER_USERNAME=$(<"$USERS_CONFIG_JSON_PATH" jq -r '.users.customer.username')
#WC_CUSTOMER_PASSWORD=$(<"$USERS_CONFIG_JSON_PATH" jq -r '.users.customer.password')
#WP_ADMIN=$(<"$USERS_CONFIG_JSON_PATH" jq -r '.users.admin.username')
#WP_ADMIN_PASSWORD=$(<"$USERS_CONFIG_JSON_PATH" jq -r '.users.admin.password')
#WP_ADMIN_EMAIL=$(<"$USERS_CONFIG_JSON_PATH" jq -r '.users.admin.email')
#WP_EDITOR=$(<"$USERS_CONFIG_JSON_PATH" jq -r '.users.editor.username')
#WP_EDITOR_PASSWORD=$(<"$USERS_CONFIG_JSON_PATH" jq -r '.users.editor.password')
#WP_EDITOR_EMAIL=$(<"$USERS_CONFIG_JSON_PATH" jq -r '.users.editor.email')
#SITE_TITLE="WooPayments E2E site"
#SITE_URL=$WP_URL

WC_GUEST_EMAIL="e2e-wcpay-guest@woocommerce.com"
WC_CUSTOMER_EMAIL="e2e-wcpay-customer@woocommerce.com"
WC_CUSTOMER_USERNAME="customer"
WC_CUSTOMER_PASSWORD="password"
WP_ADMIN="admin"
WP_ADMIN_PASSWORD="password"
WP_ADMIN_EMAIL="e2e-wcpay-admin@woocommerce.com"
WP_EDITOR="editor"
WP_EDITOR_PASSWORD="password"
WP_EDITOR_EMAIL="e2e-wcpay-editor@woocommerce.com"
BLOG_ID=${E2E_BLOG_ID-111}
SITE_TITLE="WooPayments E2E site"
SITE_URL=$WP_URL

echo "Updating site title"
wp option set blogname "$SITE_TITLE"

echo "Installing basic auth plugin for interfacing with the API"
wp plugin install https://github.com/WP-API/Basic-Auth/archive/master.zip --activate --force

echo "Activating Storefront theme"
wp theme activate storefront

echo "Adding basic WooCommerce settings..."
wp option set woocommerce_store_address '60 29th Street'
wp option set woocommerce_store_address_2 '#343'
wp option set woocommerce_store_city 'San Francisco'
wp option set woocommerce_default_country 'US:CA'
wp option set woocommerce_store_postcode '94110'
wp option set woocommerce_currency 'USD'
wp option set woocommerce_product_type 'both'
wp option set woocommerce_allow_tracking 'no'
wp option set woocommerce_enable_signup_and_login_from_checkout 'yes'

echo "Deactivating Coming Soon mode in WooCommerce..."
wp option set woocommerce_coming_soon 'no'

echo "Enabling company field as an optional parameter in checkout form..."
wp option set woocommerce_checkout_company_field 'optional'

echo "Importing WooCommerce shop pages..."
wp wc --user=admin tool run install_pages

echo "Updating cart & checkout pages for WC > 8.3 compatibility..."
# Get cart & checkout page IDs.
CART_PAGE_ID=$(wp option get woocommerce_cart_page_id)
CHECKOUT_PAGE_ID=$(wp option get woocommerce_checkout_page_id)

CART_SHORTCODE="<!-- wp:shortcode -->[woocommerce_cart]<!-- /wp:shortcode -->"
CHECKOUT_SHORTCODE="<!-- wp:shortcode -->[woocommerce_checkout]<!-- /wp:shortcode -->"

# Update cart & checkout pages to use shortcode.
wp post update $CART_PAGE_ID --post_content='$CART_SHORTCODE'
wp post update $CHECKOUT_PAGE_ID --post_content='$CHECKOUT_SHORTCODE'

echo "Importing some sample data..."
wp import wp-content/plugins/woocommerce/sample-data/sample_products.xml --authors=skip --quiet

echo "Removing customer account if present..."
wp user delete $WC_CUSTOMER_USERNAME --yes

echo "Adding customer account..."
wp user create $WC_CUSTOMER_USERNAME $WC_CUSTOMER_EMAIL --role=customer --user_pass=$WC_CUSTOMER_PASSWORD

echo "Adding editor account..." 
wp user create $WP_EDITOR $WP_EDITOR_EMAIL --role=editor --user_pass=$WP_EDITOR_PASSWORD

echo "Setting up WooPayments..."
if [[ "0" == "$(wp option list --search=woocommerce_woocommerce_payments_settings --format=count)" ]]; then
	echo "Creating WooPayments settings"
	wp option set woocommerce_woocommerce_payments_settings --format=json '{"enabled":"yes"}'
else
	echo "Updating WooPayments settings"
	wp option set woocommerce_woocommerce_payments_settings --format=json '{"enabled":"yes"}'
fi

echo "Disabling WPCOM requests proxy"
wp option set wcpaydev_proxy 0

echo "Setting Jetpack blog_id"
wp wcpay_dev set_blog_id $BLOG_ID --blog_token=$E2E_BLOG_TOKEN --user_token=$E2E_USER_TOKEN

#echo "Install and activate the latest release of WooCommerce Subscriptions"

echo "Disabling rate limiter for card declined in E2E tests"
wp option set wcpay_session_rate_limiter_disabled_wcpay_card_declined_registry yes

echo "Removing all coupons..."
wp db query "DELETE p, m FROM wp_posts p LEFT JOIN wp_postmeta m ON p.ID = m.post_id WHERE p.post_type = \"shop_coupon\""

echo "Setting up a coupon for E2E tests"
wp wc --user=admin shop_coupon create --code=free --amount=100 --discount_type=percent --individual_use=true --free_shipping=true

echo "Syncing HPOS data"
wp wc hpos sync
