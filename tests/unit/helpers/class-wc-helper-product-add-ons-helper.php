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
class WC_Product_Addons_Helper {
	public static function get_product_addon_price_for_display( $price, $_unused_cart_item = null ) {
		return $price;
	}

	// is_product_supported() calls this only because this stub satisfies its class_exists() guard.
	public static function get_product_addons( $_unused_product_id ) {
		return [];
	}
}
