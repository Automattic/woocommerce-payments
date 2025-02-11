<?php
/**
 * Generate Payment Method Configurations
 *
 * This script reads the payment method definitions and exports them as JSON
 * for use in generating TypeScript types and constants.
 *
 * @package WCPay\PaymentMethods\Configs\Scripts
 */

namespace WCPay\PaymentMethods\Configs\Scripts;

use WCPay\PaymentMethods\Configs\Definitions;
use WCPay\PaymentMethods\Configs\Constants\Payment_Method_Capability;
use WCPay\PaymentMethods\Payment_Method_Definition_Registry;

// Define ABSPATH if not already defined.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/var/www/html/' );
}

// Load WordPress.
require_once ABSPATH . 'wp-load.php';

// Load WooCommerce functions.
require_once ABSPATH . 'wp-content/plugins/woocommerce/includes/wc-core-functions.php';

/**
 * Get all payment method definitions without translations
 *
 * @return array Array of payment method definitions
 */
function get_payment_method_definitions(): array {
	$definitions = [];

	$registry = Payment_Method_Definition_Registry::instance();

	$payment_method_definitions = $registry->get_all_payment_method_definitions();

	foreach ( $payment_method_definitions as $definition ) {
		$definitions[ $definition->get_id() ] = [
			'id'           => $definition->get_id(),
			'stripeId'     => $definition->get_stripe_id(),
			'title'        => $definition->get_title(), // This will be untranslated.
			'description'  => $definition->get_description(), // This will be untranslated.
			'capabilities' => $definition->get_capabilities(),
			'currencies'   => $definition->get_supported_currencies(),
			'countries'    => $definition->get_supported_countries(),
			'icons'        => [
				'default' => [
					'path' => str_replace( plugin_dir_url( WCPAY_PLUGIN_FILE ), '', $definition->get_icon_url() ),
				],
				'dark'    => [
					'path' => str_replace( plugin_dir_url( WCPAY_PLUGIN_FILE ), '', $definition->get_dark_icon_url() ),
				],
			],
		];
	}

	return $definitions;
}

/**
 * Get payment method capabilities as constants
 *
 * @return array Array of capability constants
 */
function get_capability_constants(): array {
	$reflection = new \ReflectionClass( Payment_Method_Capability::class );
	return $reflection->getConstants();
}

// Generate the configurations.
$output = [
	'paymentMethods' => get_payment_method_definitions(),
	'capabilities'   => get_capability_constants(),
];

// Create the build directory if it doesn't exist.
$build_dir = dirname( dirname( dirname( dirname( __DIR__ ) ) ) ) . '/build/payment-methods';
if ( ! file_exists( $build_dir ) ) {
	// This runs as part of the build process - not in production.
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
	mkdir( $build_dir, 0755, true );
}

// Write the JSON file.
// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
file_put_contents(
	$build_dir . '/definitions.json',
	wp_json_encode( $output, JSON_PRETTY_PRINT )
);
