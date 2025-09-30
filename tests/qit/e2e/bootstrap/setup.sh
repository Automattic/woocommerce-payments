#!/bin/bash

set -euo pipefail
IFS=$'\n\t'

# QIT Bootstrap Setup for WooPayments E2E Tests
# This script runs before tests to configure the plugin environment

echo "Setting up WooPayments for E2E testing..."

# Ensure environment is marked as development so dev-only CLI commands are available
wp config set WP_ENVIRONMENT_TYPE development --quiet 2>/dev/null || true

echo "Installing WordPress importer for sample data..."
if ! wp plugin is-installed wordpress-importer >/dev/null 2>&1; then
    wp plugin install wordpress-importer --activate
else
    wp plugin activate wordpress-importer
fi

WC_SAMPLE_DATA_PATH=$(wp eval 'echo trailingslashit( WP_CONTENT_DIR ) . "plugins/woocommerce/sample-data/sample_products.xml";' 2>/dev/null)
if [ -z "$WC_SAMPLE_DATA_PATH" ]; then
    echo "Unable to resolve WooCommerce sample data path; skipping import."
else
    if [ -f "$WC_SAMPLE_DATA_PATH" ]; then
        echo "Importing WooCommerce sample products from $WC_SAMPLE_DATA_PATH ..."
        wp import "$WC_SAMPLE_DATA_PATH" --authors=skip
    else
        echo "Sample data file not found at $WC_SAMPLE_DATA_PATH; skipping import."
    fi
fi

# Ensure WooCommerce core pages exist and capture IDs
echo "Ensuring WooCommerce core pages exist..."
wp wc --user=admin tool run install_pages >/dev/null 2>&1 || true

CHECKOUT_PAGE_ID=$(wp option get woocommerce_checkout_page_id)
CART_PAGE_ID=$(wp option get woocommerce_cart_page_id)

if [ -z "$CHECKOUT_PAGE_ID" ] || [ "$CHECKOUT_PAGE_ID" = "0" ]; then
    CHECKOUT_PAGE_ID=$(wp post list --post_type=page --name=checkout --field=ID --format=ids)
fi

if [ -z "$CART_PAGE_ID" ] || [ "$CART_PAGE_ID" = "0" ]; then
    CART_PAGE_ID=$(wp post list --post_type=page --name=cart --field=ID --format=ids)
fi

# Default to shortcode-based templates for classic checkout/cart flows
if [ -n "${CHECKOUT_PAGE_ID}" ] && [ -n "${CART_PAGE_ID}" ]; then
    echo "Configuring classic checkout and cart pages..."

    CHECKOUT_SHORTCODE="<!-- wp:shortcode -->[woocommerce_checkout]<!-- /wp:shortcode -->"
    CART_SHORTCODE="<!-- wp:shortcode -->[woocommerce_cart]<!-- /wp:shortcode -->"

    # Provision a dedicated WooCommerce Blocks checkout clone if it does not exist yet
    CHECKOUT_WCB_PAGE_ID=$(wp post list --post_type=page --name=checkout-wcb --field=ID --format=ids)
    if [ -z "$CHECKOUT_WCB_PAGE_ID" ]; then
        echo "Creating WooCommerce Blocks checkout page..."
        CHECKOUT_WCB_PAGE_ID=$(wp post create \
            --from-post="$CHECKOUT_PAGE_ID" \
            --post_type=page \
            --post_title="Checkout WCB" \
            --post_status=publish \
            --post_name="checkout-wcb" \
            --porcelain)
    else
        echo "WooCommerce Blocks checkout page already exists (ID: $CHECKOUT_WCB_PAGE_ID)"
    fi

    wp post update "$CART_PAGE_ID" --post_content="$CART_SHORTCODE"
    wp post update "$CHECKOUT_PAGE_ID" --post_content="$CHECKOUT_SHORTCODE"
    wp post meta update "$CHECKOUT_PAGE_ID" _wp_page_template "template-fullwidth.php" >/dev/null 2>&1 || true
    if [ -n "$CHECKOUT_WCB_PAGE_ID" ]; then
        wp post meta update "$CHECKOUT_WCB_PAGE_ID" _wp_page_template "template-fullwidth.php" >/dev/null 2>&1 || true
    fi
fi

# Double check option points to the classic checkout page
if [ -n "$CHECKOUT_PAGE_ID" ]; then
    wp option update woocommerce_checkout_page_id "$CHECKOUT_PAGE_ID"
fi

# Configure WooCommerce for testing
wp option update woocommerce_currency "USD"
wp option update woocommerce_enable_guest_checkout "yes"
wp option update woocommerce_force_ssl_checkout "no"
wp option set woocommerce_checkout_company_field "optional" --quiet 2>/dev/null || true
wp option set woocommerce_coming_soon "no" --quiet 2>/dev/null || true
wp option set woocommerce_store_pages_only "no" --quiet 2>/dev/null || true

# Ensure Storefront theme is active for consistent storefront markup
if ! wp theme is-installed storefront > /dev/null 2>&1; then
    wp theme install storefront --force
fi
wp theme activate storefront



# Create a test customer
wp user create testcustomer test@example.com \
    --role=customer \
    --user_pass=testpass123 \
    --first_name="Test" \
    --last_name="Customer" \
    --quiet

echo "Setting up WooPayments configuration..."

# Enable WooPayments settings (same as main E2E tests)
echo "Creating/updating WooPayments settings"
wp option set woocommerce_woocommerce_payments_settings --format=json '{"enabled":"yes"}'

# Check required environment variables for basic Jetpack authentication
if [ -n "${E2E_JP_SITE_ID:-}" ] && [ -n "${E2E_JP_BLOG_TOKEN:-}" ] && [ -n "${E2E_JP_USER_TOKEN:-}" ]; then
    echo "Configuring WCPay with Jetpack authentication..."

    # Set up Jetpack connection and refresh account data from server
    # Environment variables are automatically available to PHP via getenv()
    # Note: /qit/bootstrap is a volume mount defined in qit.yml pointing to ./e2e/bootstrap
    wp eval-file /qit/bootstrap/qit-jetpack-connection.php

    echo "✅ WooPayments connection configured - account data fetched from server"

else
    echo "No Jetpack credentials configured - WooPayments will show Connect screen"
    echo "WooPayments will show Connect screen"
    echo ""
    echo "For basic connectivity testing, set in tests/qit/config/local.env:"
    echo "  E2E_JP_SITE_ID=123456789"
    echo "  E2E_JP_BLOG_TOKEN=123.ABC.QIT"
    echo "  E2E_JP_USER_TOKEN=123.ABC.QIT.1"
    echo ""
fi

# Always check the setup status
echo ""
echo "Current WooPayments setup status:"
# Note: /qit/bootstrap is a volume mount defined in qit.yml pointing to ./e2e/bootstrap
wp eval-file /qit/bootstrap/qit-jetpack-status.php

# Enable development/test mode for better testing experience
wp option set wcpay_dev_mode 1 --quiet 2>/dev/null || true

# Disable proxy mode (we want direct production API access)
wp option set wcpaydev_proxy 0 --quiet 2>/dev/null || true

# Disable onboarding redirect for E2E testing
wp option set wcpay_should_redirect_to_onboarding 0 --quiet 2>/dev/null || true

echo "Dismissing fraud protection welcome tour in E2E tests"
wp option set wcpay_fraud_protection_welcome_tour_dismissed 1 --quiet 2>/dev/null || true

echo "Resetting coupons and creating standard free coupon"
wp post delete $(wp post list --post_type=shop_coupon --format=ids) --force --quiet 2>/dev/null || true
wp db query "DELETE FROM wp_postmeta WHERE post_id NOT IN (SELECT ID FROM wp_posts)" --skip-column-names 2>/dev/null || true
wp wc --user=admin shop_coupon create \
    --code=free \
    --amount=100 \
    --discount_type=percent \
    --individual_use=true \
    --free_shipping=true

echo "WooPayments configuration completed"
