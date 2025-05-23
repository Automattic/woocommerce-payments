#!/bin/bash

# This is an isolated setup script that runs before your plugin's tests.
# Use it to:
# - Create test data specific to your plugin
# - Set up plugin settings
# - Create temporary files
# - Set up test users or other resources needed for the tests

# The WordPress installation is at "/var/www/html"
# This file is at: "/qit/tests/e2e/<your-plugin-slug>/<your-test-tag>/bootstrap/setup.sh"
# You can use relative paths to access files in your test.
# WP CLI is already configured, there's no need to use "--path /var/www/html"

# echo "Running isolated setup for the plugin..."
# wp post create --post_title="Test Post" # Create a test post
# wp user create testuser test@example.com # Create a test user

# Add your setup commands here
wp theme activate storefront

echo "Set up WooCommerce settings via WP-CLI"
wp option set woocommerce_store_address "60 29th Street"
wp option set woocommerce_store_address_2 "#343"
wp option set woocommerce_store_city "San Francisco"
wp option set woocommerce_default_country "US:CA"
wp option set woocommerce_store_postcode "94110"
wp option set woocommerce_currency "USD"
wp option set woocommerce_product_type "both"
wp option set woocommerce_allow_tracking "no"
wp option set woocommerce_enable_signup_and_login_from_checkout "yes"

echo "Import sample products"
wp import /var/www/html/wp-content/plugins/woocommerce/sample-data/sample_products.xml --authors=skip

echo "Removing some WooCommerce Core 'tour' options so they don't interfere with tests"
wp option set woocommerce_orders_report_date_tour_shown yes

echo "Disabling rate limiter for card declined in E2E tests"
wp option set wcpay_session_rate_limiter_disabled_wcpay_card_declined_registry yes

echo "Dismissing fraud protection welcome tour in E2E tests"
wp option set wcpay_fraud_protection_welcome_tour_dismissed 1

echo "Enabling company field as an optional parameter in checkout form..."
wp option set woocommerce_checkout_company_field "optional"

echo "Importing WooCommerce shop pages..."
wp wc --user=admin tool run install_pages

echo "Set environment type to 'development'"
wp config set WP_ENVIRONMENT_TYPE development

# Ensuring that the jetpack "account protection" feature is disabled,
# since the passwords for the locally run e2e tests can be allowed to be weak.
wp config set DISABLE_JETPACK_ACCOUNT_PROTECTION true --raw

echo "Setting Jetpack blog_id"
wp woopayments set_blog_id "$E2E_JP_SITE_ID" --blog_token="'$E2E_JP_BLOG_TOKEN'" --user_token="'$E2E_JP_USER_TOKEN'"

echo "Initialize WooPayments test drive account"
wp woopayments init-test-drive-account

echo "Disabling rate limiter for card declined in E2E tests"
wp option set wcpay_session_rate_limiter_disabled_wcpay_card_declined_registry yes

echo "Dismissing fraud protection welcome tour in E2E tests"
wp option set wcpay_fraud_protection_welcome_tour_dismissed 1

echo "Deactivating Coming Soon mode in WooCommerce..."
wp option set woocommerce_coming_soon "no"