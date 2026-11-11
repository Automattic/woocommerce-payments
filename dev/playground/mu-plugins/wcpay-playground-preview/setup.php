<?php
/**
 * One-time store setup for the Playground demo preview. The preview Blueprint runs this once,
 * after WooCommerce and WooPayments are installed.
 *
 * @package WooCommerce\Payments
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WCPAY_PLAYGROUND_PREVIEW' ) || ! function_exists( 'wc_get_container' ) ) {
	return;
}

// Land on the requested page instead of the WooCommerce setup wizard, with a storefront visitors can see.
delete_transient( '_wc_activation_redirect' );
update_option( 'woocommerce_onboarding_profile', [ 'skipped' => true ] );
update_option( 'woocommerce_coming_soon', 'no' );

// Match the fixture account, which is a US one settling in USD.
update_option( 'woocommerce_default_country', 'US:CA' );
update_option( 'woocommerce_currency', 'USD' );

// A blueprint-installed WooCommerce starts on the legacy order storage. Stores run on HPOS by default.
wc_get_container()->get( \Automattic\WooCommerce\Internal\DataStores\Orders\DataSynchronizer::class )->create_database_tables();
update_option( 'woocommerce_custom_orders_table_enabled', 'yes' );

$wcpay_playground_gateway_settings            = get_option( 'woocommerce_woocommerce_payments_settings', [] );
$wcpay_playground_gateway_settings['enabled'] = 'yes';
update_option( 'woocommerce_woocommerce_payments_settings', $wcpay_playground_gateway_settings );

$wcpay_playground_product = new WC_Product_Simple();
$wcpay_playground_product->set_name( 'Demo beanie' );
$wcpay_playground_product->set_regular_price( '18.00' );
$wcpay_playground_product->set_status( 'publish' );
$wcpay_playground_product->save();
