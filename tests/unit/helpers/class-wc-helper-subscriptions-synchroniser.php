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

	public static function is_syncing_enabled() {
		return self::$is_syncing_enabled;
	}

	public static function subscription_contains_synced_product( $subscription ) {
		return self::$subscription_contains_synced_product;
	}
}
