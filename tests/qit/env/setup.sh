#!/usr/bin/env bash

set -e

USERS_CONFIG_JSON_PATH="./users.json"

# Variables
BLOG_ID=${E2E_BLOG_ID-111}
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

echo "Setting up QIT environment"
npm run qit env:up --tunnel cloudflared-binary

echo "Updating site title"
npm run qit env:exec "wp option set blogname $SITE_TITLE"

echo "Installing basic auth plugin for interfacing with the API"
npm run qit env:exec "wp plugin install https://github.com/WP-API/Basic-Auth/archive/master.zip --activate --force"

echo "Activating Storefront theme"
npm run qit env:exec "wp theme activate storefront"

echo "Adding basic WooCommerce settings..."
npm run qit env:exec "wp option set woocommerce_store_address '60 29th Street'"
npm run qit env:exec "wp option set woocommerce_store_address_2 '#343'"
npm run qit env:exec "wp option set woocommerce_store_city 'San Francisco'"
npm run qit env:exec "wp option set woocommerce_default_country 'US:CA'"
npm run qit env:exec "wp option set woocommerce_store_postcode '94110'"
npm run qit env:exec "wp option set woocommerce_currency 'USD'"
npm run qit env:exec "wp option set woocommerce_product_type 'both'"
npm run qit env:exec "wp option set woocommerce_allow_tracking 'no'"
npm run qit env:exec "wp option set woocommerce_enable_signup_and_login_from_checkout 'yes'"

echo "Deactivating Coming Soon mode in WooCommerce..."
npm run qit env:exec "wp option set woocommerce_coming_soon 'no'"

echo "Enabling company field as an optional parameter in checkout form..."
npm run qit env:exec "wp option set woocommerce_checkout_company_field 'optional'"

echo "Importing WooCommerce shop pages..."
npm run qit env:exec "wp wc --user=admin tool run install_pages"

echo "Updating cart & checkout pages for WC > 8.3 compatibility..."
# Get cart & checkout page IDs.
CART_PAGE_ID=$(npm run qit env:exec "wp option get woocommerce_cart_page_id")
CHECKOUT_PAGE_ID=$(npm run qit env:exec "wp option get woocommerce_checkout_page_id")

CART_SHORTCODE="<!-- wp:shortcode -->[woocommerce_cart]<!-- /wp:shortcode -->"
CHECKOUT_SHORTCODE="<!-- wp:shortcode -->[woocommerce_checkout]<!-- /wp:shortcode -->"

# Update cart & checkout pages to use shortcode.
npm run qit env:exec "wp post update $CART_PAGE_ID --post_content='$CART_SHORTCODE'"
npm run qit env:exec "wp post update $CHECKOUT_PAGE_ID --post_content='$CHECKOUT_SHORTCODE'"

echo "Importing some sample data..."
npm run qit env:exec "wp import wp-content/plugins/woocommerce/sample-data/sample_products.xml --authors=skip --quiet"

echo "Removing customer account if present..."
npm run qit env:exec "wp user delete $WC_CUSTOMER_USERNAME --yes"

echo "Adding customer account..."
npm run qit env:exec "wp user create $WC_CUSTOMER_USERNAME $WC_CUSTOMER_EMAIL --role=customer --user_pass=$WC_CUSTOMER_PASSWORD"

echo "Adding editor account..." 
npm run qit env:exec "wp user create $WP_EDITOR $WP_EDITOR_EMAIL --role=editor --user_pass=$WP_EDITOR_PASSWORD"

echo "Setting up WooPayments..."
if [[ "0" == "$(npm run qit env:exec "wp option list --search=woocommerce_woocommerce_payments_settings --format=count")" ]]; then
	echo "Creating WooPayments settings"
	npm run qit env:exec "wp option set woocommerce_woocommerce_payments_settings --format=json '{\"enabled\":\"yes\"}'"
else
	echo "Updating WooPayments settings"
	npm run qit env:exec "wp option set woocommerce_woocommerce_payments_settings --format=json '{\"enabled\":\"yes\"}'"
fi

echo "Disabling WPCOM requests proxy"
npm run qit env:exec "wp option set wcpaydev_proxy 0"

echo "Setting Jetpack blog_id"
npm run qit env:exec "wp wcpay_dev set_blog_id $BLOG_ID --blog_token=$E2E_BLOG_TOKEN --user_token=$E2E_USER_TOKEN"

#echo "Install and activate the latest release of WooCommerce Subscriptions"

echo "Disabling rate limiter for card declined in E2E tests"
npm run qit env:exec "wp option set wcpay_session_rate_limiter_disabled_wcpay_card_declined_registry yes"

echo "Removing all coupons..."
npm run qit env:exec 'wp db query "DELETE p, m FROM wp_posts p LEFT JOIN wp_postmeta m ON p.ID = m.post_id WHERE p.post_type = \"shop_coupon\""' 

echo "Setting up a coupon for E2E tests"
npm run qit env:exec "wp wc --user=admin shop_coupon create --code=free --amount=100 --discount_type=percent --individual_use=true --free_shipping=true"

echo "Syncing HPOS data"
npm run qit env:exec "wp wc hpos sync"