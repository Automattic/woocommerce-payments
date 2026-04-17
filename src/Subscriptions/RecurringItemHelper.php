<?php
/**
 * Class RecurringItemHelper
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Subscriptions;

use WC_Cart;
use WC_Order;
use WC_Product;

/**
 * Helper class for recurring-item detection across checkout contexts.
 */
class RecurringItemHelper {
	const CONTEXT_ORDER   = 'order';
	const CONTEXT_CART    = 'cart';
	const CONTEXT_PRODUCT = 'product';

	const SUPPORTED_CONTEXTS = [
		self::CONTEXT_ORDER,
		self::CONTEXT_CART,
		self::CONTEXT_PRODUCT,
	];

	/**
	 * Request-scoped memo for order recurring-status keyed by order ID.
	 *
	 * @var array<int, bool>
	 */
	private static $order_recurring_cache = [];

	/**
	 * Request-scoped memo for is_subscriptions_enabled().
	 *
	 * @var bool|null
	 */
	private static $is_subscriptions_enabled_cache = null;

	/**
	 * Determine whether the checkout context contains recurring items.
	 *
	 * Supported contexts: `order`, `cart`, `product`. Any other value returns `false`
	 * without firing the filter.
	 *
	 * Expected `$subject` types per context:
	 * - `order`:   `WC_Order`, order ID, or `null`.
	 * - `cart`:    `WC_Cart` or `null` (falls back to the global cart).
	 * - `product`: `WC_Product`, product ID, or `null`.
	 *
	 * Side effect when this returns `true` for the `order` context: downstream
	 * `is_payment_recurring()` in the gateway will mark the payment information
	 * as `must_save_payment_method_to_store()`, which persists a reusable
	 * `WC_Payment_Token` (in addition to requesting `setup_future_usage=off_session`).
	 * Extensions returning `true` should be confident the shopper is opting into
	 * a future off-session charge.
	 *
	 * @param string $context Context in which the check is being performed.
	 * @param mixed  $subject Context-specific subject (order/cart/product).
	 *
	 * @return bool
	 */
	public static function has_recurring_items( string $context, $subject = null ): bool {
		if ( ! in_array( $context, self::SUPPORTED_CONTEXTS, true ) ) {
			return false;
		}

		$has_listener     = has_filter( 'wcpay_checkout_has_recurring_items' );
		$resolved_subject = $subject;
		$has_recurring    = false;

		switch ( $context ) {
			case self::CONTEXT_ORDER:
				if ( $has_listener ) {
					$resolved_subject = self::resolve_order_subject( $subject );
				}
				$has_recurring = self::order_has_recurring_items( $subject );
				break;
			case self::CONTEXT_CART:
				$has_recurring = self::cart_has_recurring_items( $subject );
				if ( $has_listener ) {
					$resolved_subject = self::resolve_cart_subject( $subject );
				}
				break;
			case self::CONTEXT_PRODUCT:
				if ( $has_listener ) {
					$resolved_subject = self::resolve_product_subject( $subject );
					$has_recurring    = self::product_has_recurring_items( $resolved_subject );
				} else {
					$has_recurring = self::product_has_recurring_items( $subject );
				}
				break;
		}

		if ( ! $has_listener ) {
			return $has_recurring;
		}

		/**
		 * Filters whether the current checkout context contains recurring items.
		 *
		 * Returning `true` for the `order` context forces the gateway to persist a
		 * reusable `WC_Payment_Token` and request `setup_future_usage=off_session`
		 * on the payment intent.
		 *
		 * @param bool   $has_recurring    Whether recurring items were detected.
		 * @param string $context          One of `order`, `cart`, `product`.
		 * @param mixed  $resolved_subject The resolved subject for the context.
		 */
		return (bool) apply_filters( 'wcpay_checkout_has_recurring_items', $has_recurring, $context, $resolved_subject );
	}

	/**
	 * Determine whether the provided order has recurring items.
	 *
	 * Memoizes the result per order ID within the request to avoid repeated
	 * `wcs_order_contains_subscription()` calls on the checkout critical path.
	 *
	 * @param mixed $order Order object or ID.
	 *
	 * @return bool
	 */
	public static function order_has_recurring_items( $order ): bool {
		if ( ! self::is_subscriptions_enabled() || ! function_exists( 'wcs_order_contains_subscription' ) ) {
			return false;
		}

		$cache_key = null;
		if ( $order instanceof WC_Order ) {
			$cache_key = $order->get_id();
		} elseif ( is_numeric( $order ) ) {
			$cache_key = (int) $order;
		}

		if ( null !== $cache_key && isset( self::$order_recurring_cache[ $cache_key ] ) ) {
			return self::$order_recurring_cache[ $cache_key ];
		}

		$result = (bool) wcs_order_contains_subscription( $order );

		if ( null !== $cache_key ) {
			self::$order_recurring_cache[ $cache_key ] = $result;
		}

		return $result;
	}

	/**
	 * Determine whether the provided (or current) cart has recurring items.
	 *
	 * When a `WC_Cart` instance is passed, its items are inspected directly.
	 * When no cart is provided, falls back to the global cart via
	 * `WC_Subscriptions_Cart::cart_contains_subscription()` and
	 * `wcs_cart_contains_renewal()`.
	 *
	 * @param WC_Cart|null $cart Optional cart instance.
	 *
	 * @return bool
	 */
	public static function cart_has_recurring_items( $cart = null ): bool {
		if ( ! self::is_subscriptions_enabled() ) {
			return false;
		}

		$is_global_cart = null === $cart
			|| ( function_exists( 'WC' ) && \WC()->cart === $cart );

		if ( $is_global_cart ) {
			if ( class_exists( 'WC_Subscriptions_Cart' ) && \WC_Subscriptions_Cart::cart_contains_subscription() ) {
				return true;
			}

			return function_exists( 'wcs_cart_contains_renewal' ) && (bool) wcs_cart_contains_renewal();
		}

		if ( ! $cart instanceof WC_Cart || ! class_exists( 'WC_Subscriptions_Product' ) ) {
			return false;
		}

		foreach ( $cart->get_cart() as $cart_item ) {
			if ( isset( $cart_item['data'] ) && \WC_Subscriptions_Product::is_subscription( $cart_item['data'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Determine whether the provided product is recurring.
	 *
	 * @param mixed $product Product object or ID.
	 *
	 * @return bool
	 */
	public static function product_has_recurring_items( $product ): bool {
		if ( ! class_exists( 'WC_Subscriptions_Product' ) || ! $product ) {
			return false;
		}

		return (bool) \WC_Subscriptions_Product::is_subscription( $product );
	}

	/**
	 * Determine whether Woo Subscriptions support is available.
	 *
	 * Cached for the duration of the request; the installed plugin set cannot
	 * change mid-request.
	 *
	 * @return bool
	 */
	public static function is_subscriptions_enabled(): bool {
		if ( null !== self::$is_subscriptions_enabled_cache ) {
			return self::$is_subscriptions_enabled_cache;
		}

		if ( class_exists( 'WC_Subscriptions' ) ) {
			self::$is_subscriptions_enabled_cache = version_compare( \WC_Subscriptions::$version, '2.2.0', '>=' );
		} else {
			self::$is_subscriptions_enabled_cache = class_exists( 'WC_Subscriptions_Core_Plugin' );
		}

		return self::$is_subscriptions_enabled_cache;
	}

	/**
	 * Clear the request-scoped caches. Intended for test tear-down.
	 */
	public static function reset_cache(): void {
		self::$order_recurring_cache          = [];
		self::$is_subscriptions_enabled_cache = null;
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
	 * Resolve the cart subject to a WC_Cart instance when possible.
	 *
	 * @param mixed $subject Cart object or null.
	 *
	 * @return WC_Cart|null
	 */
	private static function resolve_cart_subject( $subject ) {
		if ( $subject instanceof WC_Cart ) {
			return $subject;
		}

		if ( function_exists( 'WC' ) && \WC()->cart instanceof WC_Cart ) {
			return \WC()->cart;
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
}
