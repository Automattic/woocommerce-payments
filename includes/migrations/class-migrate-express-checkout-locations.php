<?php
/**
 * Class Migrate_Express_Checkout_Locations
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Migrations;

defined( 'ABSPATH' ) || exit;

/**
 * Class Migrate_Express_Checkout_Locations
 *
 * Migrates express checkout button location settings from method-centric to location-centric storage.
 *
 * Before: Each payment method stored its own list of enabled locations.
 *   - `payment_request_button_locations` = ['product', 'cart', 'checkout'] (Apple Pay/Google Pay)
 *   - `platform_checkout_button_locations` = ['product', 'cart'] (WooPay)
 *
 * After: Each location stores which payment methods are enabled there.
 *   - `express_checkout_product_methods` = ['payment_request', 'woopay']
 *   - `express_checkout_cart_methods` = ['payment_request', 'woopay']
 *   - `express_checkout_checkout_methods` = ['payment_request']
 *
 * @since 10.4.0
 */
class Migrate_Express_Checkout_Locations {

	/**
	 * Version in which this migration was introduced.
	 *
	 * @var string
	 */
	const VERSION_SINCE = '10.4.0';

	/**
	 * The locations where express checkout buttons can be displayed.
	 *
	 * @var array
	 */
	const LOCATIONS = [ 'product', 'cart', 'checkout' ];

	/**
	 * Execute the migration if upgrading from a version before the migration version
	 * and the old settings exist.
	 */
	public function maybe_migrate() {
		$previous_version = get_option( 'woocommerce_woocommerce_payments_version' );
		if ( version_compare( self::VERSION_SINCE, $previous_version, '<=' ) ) {
			return;
		}

		$card_settings = get_option( 'woocommerce_woocommerce_payments_settings', [] );

		// Check if migration is needed - if old settings exist and new ones don't.
		$has_old_settings = isset( $card_settings['payment_request_button_locations'] )
			|| isset( $card_settings['platform_checkout_button_locations'] );
		$has_new_settings = isset( $card_settings['express_checkout_product_methods'] );

		if ( ! $has_old_settings || $has_new_settings ) {
			return;
		}

		$this->migrate( $card_settings );
	}

	/**
	 * Does the actual migration as described in the class docblock.
	 *
	 * @param array $card_settings The card gateway settings.
	 */
	private function migrate( $card_settings ) {
		// Get old settings with defaults.
		$payment_request_locations = $card_settings['payment_request_button_locations'] ?? self::LOCATIONS;
		$woopay_locations          = $card_settings['platform_checkout_button_locations'] ?? self::LOCATIONS;

		// Build new location-centric settings.
		foreach ( self::LOCATIONS as $location ) {
			$methods = [];

			if ( in_array( $location, $payment_request_locations, true ) ) {
				$methods[] = 'payment_request';
			}

			if ( in_array( $location, $woopay_locations, true ) ) {
				$methods[] = 'woopay';
			}

			$card_settings[ "express_checkout_{$location}_methods" ] = $methods;
		}

		// Remove old settings.
		unset( $card_settings['payment_request_button_locations'] );
		unset( $card_settings['platform_checkout_button_locations'] );

		update_option( 'woocommerce_woocommerce_payments_settings', $card_settings );
	}
}
