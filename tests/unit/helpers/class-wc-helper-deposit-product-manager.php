<?php
/**
 * Product Add Ons helpers.
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * Class WC_Product_Addons_Helper.
 *
 * This helper class should ONLY be used for unit tests!.
 */
class WC_Deposits_Product_Manager {
	public static function get_deposit_type( WC_Product_Simple $product ) {
		return $product->get_meta( '_wc_deposit_type' );
	}
	public static function deposits_enabled( WC_Product_Simple $product ) {
		return true === $product->get_meta( '_wc_deposits_enabled' );
	}
}
