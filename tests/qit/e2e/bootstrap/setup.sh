#!/bin/bash

set -euo pipefail
IFS=$'\n\t'

# QIT Bootstrap Setup for WooPayments E2E Tests
# This script runs before tests to configure the plugin environment

echo "Setting up WooPayments for E2E testing..."

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
wp option set woocommerce_coming_soon "no" --quiet 2>/dev/null || true
wp option set woocommerce_store_pages_only "no" --quiet 2>/dev/null || true

# Ensure Storefront theme is active for consistent storefront markup
if ! wp theme is-installed storefront > /dev/null 2>&1; then
    wp theme install storefront --force
fi
wp theme activate storefront

echo "Forcing classic (shortcode) Cart/Checkout…"

# Get WooCommerce version
WC_VER=$(wp plugin get woocommerce --field=version)
echo "WooCommerce version: $WC_VER"

NEED_WORKAROUND=$(wp eval "echo version_compare('$WC_VER','8.3','>=');")

# Helper: get numeric option value safely
get_option_id () {
  wp option get "$1" --format=json | tr -d '"' | awk '/^[0-9]+$/{print; exit}'
}

# Helper: ensure a page exists, contains shortcode, and is assigned in WC options
ensure_page_with_shortcode () {
  local TITLE="$1"
  local SHORTCODE="$2"
  local OPTION_KEY="$3"

  local PAGE_ID
  PAGE_ID="$(get_option_id "$OPTION_KEY")"

  if [[ -z "$PAGE_ID" ]]; then
    echo "No valid $TITLE page set. Creating…"
    PAGE_ID=$(wp post create --post_type=page --post_status=publish \
      --post_title="$TITLE" --post_content="$SHORTCODE" \
      --porcelain)
    wp option update "$OPTION_KEY" "$PAGE_ID"
  else
    echo "$TITLE page is $PAGE_ID. Updating content & publishing…"
    wp post update "$PAGE_ID" --post_content="$SHORTCODE" --post_status=publish
  fi

  echo "$TITLE page ID: $PAGE_ID"
}

if [[ "$NEED_WORKAROUND" = "1" ]]; then
  echo "WC >= 8.3 — creating both Blocks and shortcode checkout pages…"

  # Create shortcode-based cart page
  ensure_page_with_shortcode "Cart" "[woocommerce_cart]" "woocommerce_cart_page_id"

  # Create shortcode-based checkout page with specific slug
  echo "Creating shortcode checkout page at /checkout-wsc..."
  SHORTCODE_CHECKOUT_ID=$(wp post create --post_type=page --post_status=publish \
    --post_title="Checkout WSC" --post_name="checkout-wsc" \
    --post_content="[woocommerce_checkout]" --porcelain)
  echo "Shortcode checkout page created: ID=$SHORTCODE_CHECKOUT_ID at /checkout-wsc"

  # Set the shortcode checkout as the official WooCommerce checkout page
  wp option update "woocommerce_checkout_page_id" "$SHORTCODE_CHECKOUT_ID"
  echo "WooCommerce checkout page option set to shortcode page"

  # Safety: flush rewrites & caches
  wp rewrite flush --hard
  wp transient delete --all

  echo "Shortcode checkout available at /checkout-wsc (official WC checkout)"
  echo "Blocks checkout remains available at /checkout (default page)"
else
  echo "WC < 8.3 — classic checkout already default."
fi

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
    # Tokens are passed via environment variables for security (not command-line args)
    wp woopayments qit_setup "$E2E_JP_SITE_ID"

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
wp woopayments qit_status

# Enable development/test mode for better testing experience
wp option set wcpay_dev_mode 1 --quiet 2>/dev/null || true

# Disable proxy mode (we want direct production API access)
wp option set wcpaydev_proxy 0 --quiet 2>/dev/null || true

# Disable onboarding redirect for E2E testing
wp option set wcpay_should_redirect_to_onboarding 0 --quiet 2>/dev/null || true

echo "WooPayments configuration completed"
