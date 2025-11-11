#!/bin/bash

set -euo pipefail
IFS=$'\n\t'

# QIT Bootstrap Setup for WooPayments E2E Tests
# This script runs before tests to configure the plugin environment

echo "Setting up WooPayments for E2E testing..."

# Ensure environment is marked as development so dev-only CLI commands are available
wp config set WP_ENVIRONMENT_TYPE development --quiet 2>/dev/null || true

# Create a test product for payment testing
PRODUCT_ID=$(wp post create \
    --post_title="Test Product for Payments" \
    --post_content="A simple test product for QIT payment testing" \
    --post_status=publish \
    --post_type=product \
    --porcelain)

# Set product meta data properly
wp post meta update $PRODUCT_ID _price "10.00"
wp post meta update $PRODUCT_ID _regular_price "10.00"
wp post meta update $PRODUCT_ID _virtual "yes"
wp post meta update $PRODUCT_ID _manage_stock "no"

# Ensure WooCommerce checkout page exists and is properly configured
wp option update woocommerce_checkout_page_id $(wp post list --post_type=page --post_name=checkout --field=ID --format=ids)

# Configure WooCommerce for testing
wp option update woocommerce_currency "USD"
wp option update woocommerce_enable_guest_checkout "yes"
wp option update woocommerce_force_ssl_checkout "no"

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

echo "WooPayments configuration completed"
