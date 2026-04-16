<?php
/**
 * Class WC_Payments_Recurring_Item_Helper
 *
 * @package WooCommerce\Payments
 */

defined( 'ABSPATH' ) || exit;

/**
 * Helper class for recurring-item detection across checkout contexts.
 */
class WC_Payments_Recurring_Item_Helper {
	/**
	 * Determine whether the checkout context contains recurring items.
	 *
	 * Supported contexts:
	 * - order
	 * - cart
	 * - product
	 *
	 * @param string $context Context in which the check is being performed.
	 * @param mixed  $subject Context-specific subject (order/cart/product).
	 *
	 * @return bool
	 */
	public static function has_recurring_items( $context, $subject = null ) {
		$resolved_subject = $subject;
		$has_recurring    = false;

		switch ( $context ) {
			case 'order':
				$resolved_subject = self::resolve_order_subject( $subject );
				$has_recurring    = self::order_has_recurring_items( $resolved_subject );
				break;
			case 'cart':
				$resolved_subject = self::resolve_cart_subject( $subject );
				$has_recurring    = self::cart_has_recurring_items();
				break;
			case 'product':
				$resolved_subject = self::resolve_product_subject( $subject );
				$has_recurring    = self::product_has_recurring_items( $resolved_subject );
				break;
		}

		return (bool) apply_filters( 'wcpay_checkout_has_recurring_items', $has_recurring, $context, $resolved_subject );
	}

	/**
	 * Determine whether the provided order has recurring items.
	 *
	 * @param mixed $order Order object or ID.
	 *
	 * @return bool
	 */
	public static function order_has_recurring_items( $order ) {
		if ( ! self::is_subscriptions_enabled() || ! function_exists( 'wcs_order_contains_subscription' ) ) {
			return false;
		}

		return (bool) wcs_order_contains_subscription( $order );
	}

	/**
	 * Determine whether the current cart has recurring items.
	 *
	 * @return bool
	 */
	public static function cart_has_recurring_items() {
		if ( ! self::is_subscriptions_enabled() ) {
			return false;
		}

		$has_recurring_items = false;

		if ( class_exists( 'WC_Subscriptions_Cart' ) ) {
			$has_recurring_items = (bool) WC_Subscriptions_Cart::cart_contains_subscription();
		}

		if ( ! $has_recurring_items && function_exists( 'wcs_cart_contains_renewal' ) ) {
			$has_recurring_items = (bool) wcs_cart_contains_renewal();
		}

		return $has_recurring_items;
	}

	/**
	 * Determine whether the provided product is recurring.
	 *
	 * @param mixed $product Product object or ID.
	 *
	 * @return bool
	 */
	public static function product_has_recurring_items( $product ) {
		if ( ! class_exists( 'WC_Subscriptions_Product' ) || ! $product ) {
			return false;
		}

		return (bool) WC_Subscriptions_Product::is_subscription( $product );
	}

	/**
	 * Resolve the order subject for filtering.
	 *
	 * @param mixed $subject Order object or ID.
	 *
	 * @return mixed
	 */
	private static function resolve_order_subject( $subject ) {
		if ( $subject instanceof WC_Order ) {
			return $subject;
		}

		if ( is_numeric( $subject ) ) {
			$order = wc_get_order( $subject );
			return $order ? $order : $subject;
		}

		return $subject;
	}

	/**
	 * Resolve the cart subject for filtering.
	 *
	 * @param mixed $subject Cart object or null.
	 *
	 * @return mixed
	 */
	private static function resolve_cart_subject( $subject ) {
		if ( null !== $subject ) {
			return $subject;
		}

		if ( function_exists( 'WC' ) && WC()->cart ) {
			return WC()->cart;
		}

		return null;
	}

	/**
	 * Resolve the product subject for filtering.
	 *
	 * @param mixed $subject Product object or ID.
	 *
	 * @return mixed
	 */
	private static function resolve_product_subject( $subject ) {
		if ( $subject instanceof WC_Product ) {
			return $subject;
		}

		if ( is_numeric( $subject ) ) {
			$product = wc_get_product( $subject );
			return $product ? $product : $subject;
		}

		return $subject;
	}

	/**
	 * Determine whether Woo Subscriptions support is available.
	 *
	 * @return bool
	 */
	private static function is_subscriptions_enabled() {
		if ( class_exists( 'WC_Subscriptions' ) ) {
			return version_compare( WC_Subscriptions::$version, '2.2.0', '>=' );
		}

		return class_exists( 'WC_Subscriptions_Core_Plugin' );
	}
}
