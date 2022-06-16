<?php
/**
 * Name Your Price helpers.
 *
 * @package WooCommerce\Payments\Tests
 */

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound

/**
 * Mock WC_Name_Your_Price function that returns WC_Name_Your_Price instance.
 */
function WC_Name_Your_Price() {  // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
	return new WC_Name_Your_Price();
}

/**
 * Class WC_Name_Your_Price.
 *
 * Mock class for test usage.
 */
class WC_Name_Your_Price {
	public function __construct() {
		$this->cart = new WC_Name_Your_Price_Cart();
	}
}

/**
 * Class WC_Name_Your_Price_Cart.
 *
 * Mock class for test usage.
 */
class WC_Name_Your_Price_Cart {
	// For mock/test purposes we just want to return the cart item.
	public function set_cart_item( $cart_item ) {
		return $cart_item;
	}
}

/**
 * Class WC_Name_Your_Price_Helpers.
 *
 * This helper class should ONLY be used for unit tests!.
 */
class WC_Name_Your_Price_Helpers {
	/**
	 * is_nyp mock.
	 *
	 * @var function
	 */
	public static $is_nyp = false;

	/**
	 * Mock is_nyp method for use in tests.
	 *
	 * @param mixed|bool $value If bool is passed, sets the is_nyp property.
	 *
	 * @return bool
	 */
	public static function is_nyp( $value ) {
		if ( is_bool( $value ) ) {
			self::$is_nyp = $value;
		}
		return self::$is_nyp;
	}
}
