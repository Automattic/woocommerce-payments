#!/bin/bash

set -euo pipefail
IFS=$'\n\t'

# QIT Bootstrap Setup for WooCommerce Payments E2E Tests
# This script runs before tests to configure the plugin environment

echo "Setting up WooCommerce Payments for E2E testing..."

# Ensure environment is marked as local so dev-only CLI commands are available
wp config set WP_ENVIRONMENT_TYPE local --quiet 2>/dev/null || true

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

echo "Setting up WooCommerce Payments configuration..."

# NOTE: Jetpack connection setup will be added in future PRs
# For now, WooPayments will run in development mode
echo "Running WooPayments without a Jetpack connection (Jetpack connection setup in upcoming PRs)"

# Enable development/test mode for better testing experience
wp option set wcpay_dev_mode 1 --quiet 2>/dev/null || true

# Disable proxy mode (we want direct production API access)
wp option set wcpaydev_proxy 0 --quiet 2>/dev/null || true

# Disable onboarding redirect for E2E testing
wp option set wcpay_should_redirect_to_onboarding 0 --quiet 2>/dev/null || true

echo "WooPayments configuration completed"
