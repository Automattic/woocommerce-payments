<?php
/**
 * Class Add_Amazon_Pay_To_Express_Checkout_Locations
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Migrations;

defined( 'ABSPATH' ) || exit;

/**
 * Class Add_Amazon_Pay_To_Express_Checkout_Locations
 *
 * Adds Amazon Pay to express checkout location settings for existing installations.
 *
 * When Amazon Pay support is added, existing installations that already have
 * express_checkout_*_methods settings saved will not include 'amazon_pay' in
 * those arrays. This migration adds 'amazon_pay' to all locations so that
 * existing users get Amazon Pay enabled by default when they upgrade.
 *
 * @since 10.5.0
 */
class Add_Amazon_Pay_To_Express_Checkout_Locations {

	/**
	 * Version in which this migration was introduced.
	 *
	 * @var string
	 */
	const VERSION_SINCE = '10.5.0';

	/**
	 * The locations where express checkout buttons can be displayed.
	 *
	 * @var array
	 */
	const LOCATIONS = [ 'product', 'cart', 'checkout' ];

	/**
	 * Execute the migration if upgrading from a version before the migration version
	 * and the express checkout settings exist without amazon_pay.
	 */
	public function maybe_migrate() {
		$previous_version = get_option( 'woocommerce_woocommerce_payments_version' );
		if ( version_compare( self::VERSION_SINCE, $previous_version, '<=' ) ) {
			return;
		}

		$card_settings = get_option( 'woocommerce_woocommerce_payments_settings', [] );

		// Only migrate if express checkout settings exist (meaning user has saved settings before).
		if ( ! isset( $card_settings['express_checkout_product_methods'] ) ) {
			return;
		}

		$this->migrate( $card_settings );
	}

	/**
	 * Adds amazon_pay to all express checkout location settings.
	 *
	 * @param array $card_settings The card gateway settings.
	 */
	private function migrate( $card_settings ) {
		$updated = false;

		foreach ( self::LOCATIONS as $location ) {
			$option_name = "express_checkout_{$location}_methods";
			$methods     = $card_settings[ $option_name ] ?? [];

			if ( ! in_array( 'amazon_pay', $methods, true ) ) {
				$methods[]                     = 'amazon_pay';
				$card_settings[ $option_name ] = $methods;
				$updated                       = true;
			}
		}

		if ( $updated ) {
			update_option( 'woocommerce_woocommerce_payments_settings', $card_settings );
		}
	}
}
