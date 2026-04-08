<?php
/**
 * Class LoggerContext
 *
 * @package WooCommerce\Payments
 */

namespace WCPay;

use WCPay\Internal\LoggerContext as InternalLoggerContext;

defined( 'ABSPATH' ) || exit; // block direct access.

/**
 * A wrapper class for accessing LoggerContext as a singletone.
 */
class Logger_Context {
	/**
	 * Sets a context value.
	 *
	 * @param string                     $key   The key to set.
	 * @param string|int|float|bool|null $value The value to set. Null removes value.
	 *
	 * @return void
	 */
	public static function set_value( $key, $value ) {
		wcpay_get_container()->get( InternalLoggerContext::class )->set_value( $key, $value );
	}

	/**
	 * Gets a context.
	 *
	 * @return array<string, string>
	 */
	public static function get_context() {
		return wcpay_get_container()->get( InternalLoggerContext::class )->get_context();
	}
}
