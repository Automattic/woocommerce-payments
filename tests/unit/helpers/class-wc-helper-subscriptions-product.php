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
class WC_Subscriptions_Product extends WC_Product {
	/**
	 * Subscription product period.
	 *
	 * @var string
	 */
	public static $subscription_product_period = null;

	/**
	 * Subscription product interval.
	 *
	 * @var int
	 */
	public static $subscription_product_interval = null;

	/**
	 * Is a subscription product.
	 *
	 * @var bool
	 */
	public static $is_subscription = true;

	/**
	 * Whether product needs one time shipping.
	 *
	 * @var bool
	 */
	public static $one_time_shipping = false;

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
	 * Mock for static get_sign_up_fee.
	 *
	 * @param Product $product WC Product.
	 */
	public static function get_sign_up_fee( $product ) {
		return 0;
	}

	/**
	 * Mock for static get_trial_length.
	 *
	 * @param Product $product WC Product.
	 */
	public static function get_trial_length( $product ) {
		return 0;
	}

	/**
	 * Mock for static is_subscription.
	 *
	 * @param Product $product WC Product.
	 */
	public static function is_subscription( $product ) {
		return self::$is_subscription;
	}

	/**
	 * Mock for static needs_one_time_shipping.
	 *
	 * @param Product $product WC Product.
	 */
	public static function needs_one_time_shipping( $product ) {
		return self::$one_time_shipping;
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

	/**
	 * Setter for needs_one_time_shipping.
	 *
	 * @param bool $result Result for needs_one_time_shipping.
	 */
	public static function set_needs_one_time_shipping( $result ) {
		self::$one_time_shipping = $result;
	}
}
