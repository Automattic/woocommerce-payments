<?php
/**
 * Subscription WC_Subscriptions_Product helper.
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * Class WC_Subscriptions_Product.
 *
 * This helper class should ONLY be used for unit tests!.
 */
class WC_Subscriptions_Product {
	/**
	 * Subscription product period.
	 *
	 * @var string
	 */
	public static $subscription_product_period = null;

	/**
	 * Subsscription product interval.
	 *
	 * @var int
	 */
	public static $subscription_product_interval = null;

	/**
	 * Mock for static get_period.
	 *
	 * @param Product $product WC Product.
	 */
	public static function get_period( $product ) {
		return self::$subscription_product_period;
	}

	/**
	 * Mock for static get_interval.
	 *
	 * @param Product $product WC Product.
	 */
	public static function get_interval( $product ) {
		return self::$subscription_product_interval;
	}

	/**
	 * Mock for static is_subscription.
	 *
	 * @param Product $product WC Product.
	 */
	public static function is_subscription( $product ) {
		return true;
	}

	/**
	 * Setter for get_period.
	 *
	 * @param string $result Result for get_period.
	 */
	public static function set_period( $result ) {
		self::$subscription_product_period = $result;
	}

	/**
	 * Setter for get_interval.
	 *
	 * @param int $result Result for get_interval.
	 */
	public static function set_interval( $result ) {
		self::$subscription_product_interval = $result;
	}
}
