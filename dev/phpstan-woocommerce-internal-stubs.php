<?php
/**
 * PHPStan stubs for WooCommerce classes that the published WooCommerce stubs leave out.
 *
 * The `php-stubs/woocommerce-stubs` package skips WooCommerce's `Internal` namespace. Code that
 * calls an internal class must cope with it changing or going away.
 *
 * Consumers: PHPStan only (`phpstan.neon` → `scanFiles`). PHPUnit and production load the real classes.
 *
 * @package WooCommerce\Payments
 */

namespace Automattic\WooCommerce\Internal\Utilities;

/**
 * Stub of WooCommerce's user utilities, from WooCommerce 8.0.
 */
class Users {
	/**
	 * Whether a visitor must confirm an order's billing email before seeing the order.
	 *
	 * @param int         $order_id       The order ID.
	 * @param string|null $supplied_email The email the visitor submitted, if any.
	 * @param string      $context        Where the check runs, such as 'order-pay' or 'order-received'.
	 *
	 * @return bool
	 */
	public static function should_user_verify_order_email( $order_id, $supplied_email = null, $context = 'view' ) {
		return true;
	}
}
