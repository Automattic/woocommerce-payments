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
class WC_Subscriptions_Synchroniser {

	/**
	 * Is syncing enabled setter.
	 *
	 * @var bool
	 */
	public static $is_syncing_enabled = false;

	/**
	 * Subscriptions contains synced product setter.
	 *
	 * @var bool
	 */
	public static $subscription_contains_synced_product = false;

	/**
	 * Is the product synced.
	 *
	 * @var bool
	 */
	public static $is_product_synced = false;

	/**
	 * Is there any upfront payments.
	 *
	 * @var bool
	 */
	public static $is_payment_upfront = false;
	/**
	 * Is today.
	 *
	 * @var bool
	 */
	public static $is_today = false;

	public static function is_syncing_enabled() {
		return self::$is_syncing_enabled;
	}

	public static function subscription_contains_synced_product( $subscription ) {
		return self::$subscription_contains_synced_product;
	}

	public static function is_product_synced( $product ) {
		return self::$is_product_synced;
	}

	public static function is_payment_upfront( $product ) {
		return self::$is_payment_upfront;
	}

	public static function is_today( $date ) {
		return self::$is_today;
	}

	public static function calculate_first_payment_date( $product ) {
		return time();
	}


}
