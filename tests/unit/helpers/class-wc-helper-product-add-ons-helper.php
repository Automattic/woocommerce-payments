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

	// Required by tests that exercise the real should_show_express_checkout_button()
	// flow: its private is_product_supported() check (which can't be mocked) calls
	// WC_Product_Addons_Helper::get_product_addons() before the purchasability gate.
	public static function get_product_addons( $_unused_product_id ) {
		return [];
	}
}
