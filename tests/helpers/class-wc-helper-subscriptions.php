<?php
/**
 * Subscription helpers.
 *
 * @package WooCommerce\Payments\Tests
 */

// Set up subscriptions mocks.
function wcs_order_contains_subscription( $order ) {
	return call_user_func( WCS_Mock::$wcs_order_contains_subscription, $order );
}

/**
 * Class WCS_Mock.
 *
 * This helper class should ONLY be used for unit tests!.
 */
class WCS_Mock {
	/**
	 * wcs_order_contains_subscription mock.
	 *
	 * @var function
	 */
	public static $wcs_order_contains_subscription = null;

	public static function set_wcs_order_contains_subscription( $function ) {
		self::$wcs_order_contains_subscription = $function;
	}
}
