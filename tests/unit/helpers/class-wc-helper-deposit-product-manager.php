<?php
/**
 * WooCommerce Deposits helpers.
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * Class WC_Deposits_Product_Manager.
 *
 * This helper class should ONLY be used for unit tests!.
 */
class WC_Deposits_Product_Manager {
	/**
	 * @param WC_Product_Simple|int $product
	 * @return mixed
	 */
	public static function get_deposit_type( $product ) {
		if ( is_int( $product ) ) {
			$product = wc_get_product( $product );
		}
		return $product->get_meta( '_wc_deposit_type' );
	}

	/**
	 * @param WC_Product_Simple|int $product
	 * @return bool
	 */
	public static function deposits_enabled( $product ) {
		if ( is_int( $product ) ) {
			$product = wc_get_product( $product );
		}
		return true === $product->get_meta( '_wc_deposits_enabled' );
	}
}
