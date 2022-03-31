<?php
/**
 * Subscriptions helpers.
 *
 * @package WooCommerce\Payments\Tests
 */

// Set up subscriptions mocks.
function wcs_order_contains_subscription( $order ) {
	if ( ! WC_Subscriptions::$wcs_order_contains_subscription ) {
		return;
	}
	return ( WC_Subscriptions::$wcs_order_contains_subscription )( $order );
}

function wcs_get_subscriptions_for_order( $order ) {
	if ( ! WC_Subscriptions::$wcs_get_subscriptions_for_order ) {
		return [];
	}
	return ( WC_Subscriptions::$wcs_get_subscriptions_for_order )( $order );
}

function wcs_get_subscriptions_for_renewal_order( $order ) {
	if ( ! WC_Subscriptions::$wcs_get_subscriptions_for_renewal_order ) {
		return;
	}
	return ( WC_Subscriptions::$wcs_get_subscriptions_for_renewal_order )( $order );
}

function wcs_is_subscription( $order ) {
	if ( ! WC_Subscriptions::$wcs_is_subscription ) {
		return;
	}
	return ( WC_Subscriptions::$wcs_is_subscription )( $order );
}

function wcs_get_subscription( $subscription ) {
	if ( ! WC_Subscriptions::$wcs_get_subscription ) {
		return;
	}
	return ( WC_Subscriptions::$wcs_get_subscription )( $subscription );
}

function wcs_cart_contains_renewal() {
	if ( ! WC_Subscriptions::$wcs_cart_contains_renewal ) {
		return;
	}
	return ( WC_Subscriptions::$wcs_cart_contains_renewal )();
}

function wcs_get_order_type_cart_items( $order_type ) {
	if ( ! WC_Subscriptions::$wcs_get_order_type_cart_items ) {
		return [];
	}
	return ( WC_Subscriptions::$wcs_get_order_type_cart_items )( $order_type );
}

function wcs_cart_contains_resubscribe() {
	if ( ! WC_Subscriptions::$wcs_cart_contains_resubscribe ) {
		return;
	}
	return ( WC_Subscriptions::$wcs_cart_contains_resubscribe )();
}

function wcs_create_renewal_order( $subscription ) {
	if ( ! WC_Subscriptions::$wcs_create_renewal_order ) {
		return;
	}
	return ( WC_Subscriptions::$wcs_create_renewal_order )( $subscription );
}

/**
 * Class WC_Subscriptions.
 *
 * This helper class should ONLY be used for unit tests!.
 */
class WC_Subscriptions {
	/**
	 * Subscriptions version, defaults to 3.0.7
	 *
	 * @var string
	 */
	public static $version = '3.0.7';

	/**
	 * wcs_order_contains_subscription mock.
	 *
	 * @var function
	 */
	public static $wcs_order_contains_subscription = null;

	/**
	 * wcs_get_subscriptions_for_order mock.
	 *
	 * @var function
	 */
	public static $wcs_get_subscriptions_for_order = null;

	/**
	 * wcs_get_subscriptions_for_renewal_order mock.
	 *
	 * @var function
	 */
	public static $wcs_get_subscriptions_for_renewal_order = null;

	/**
	 * wcs_is_subscription mock.
	 *
	 * @var function
	 */
	public static $wcs_is_subscription = null;

	/**
	 * wcs_get_subscription mock.
	 *
	 * @var function
	 */
	public static $wcs_get_subscription = null;

	/**
	 * wcs_cart_contains_renewal mock.
	 *
	 * @var function
	 */
	public static $wcs_cart_contains_renewal = null;

	/**
	 * wcs_get_order_type_cart_items mock.
	 *
	 * @var function
	 */
	public static $wcs_get_order_type_cart_items = null;

	/**
	 * wcs_cart_contains_resubscribe mock.
	 *
	 * @var function
	 */
	public static $wcs_cart_contains_resubscribe = null;

	/**
	 * wcs_create_renewal_order mock.
	 *
	 * @var function
	 */
	public static $wcs_create_renewal_order = null;

	public static function set_wcs_order_contains_subscription( $function ) {
		self::$wcs_order_contains_subscription = $function;
	}

	public static function set_wcs_get_subscriptions_for_order( $function ) {
		self::$wcs_get_subscriptions_for_order = $function;
	}

	public static function set_wcs_get_subscriptions_for_renewal_order( $function ) {
		self::$wcs_get_subscriptions_for_renewal_order = $function;
	}

	public static function set_wcs_is_subscription( $function ) {
		self::$wcs_is_subscription = $function;
	}

	public static function set_wcs_get_subscription( $function ) {
		self::$wcs_get_subscription = $function;
	}

	public static function wcs_cart_contains_renewal( $function ) {
		self::$wcs_cart_contains_renewal = $function;
	}

	public static function wcs_get_order_type_cart_items( $function ) {
		self::$wcs_get_order_type_cart_items = $function;
	}

	public static function wcs_cart_contains_resubscribe( $function ) {
		self::$wcs_cart_contains_resubscribe = $function;
	}

	public static function wcs_create_renewal_order( $function ) {
		self::$wcs_create_renewal_order = $function;
	}
}
