<?php
/**
 * Sets the request context WooCommerce core announces, for tests.
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * Sets the request context of the WooCommerce instance, as WooCommerce classifies the current request.
 */
class WCPay_Test_Request_Context {

	/**
	 * Whether the original context has been stored.
	 *
	 * @var bool
	 */
	private static $captured = false;

	/**
	 * The context WooCommerce had before the first set() call.
	 *
	 * @var string|null
	 */
	private static $original = null;

	/**
	 * Sets the request context.
	 *
	 * @param string|null $context One of store_api, rest, cron, ajax, admin or frontend, or null when unknown.
	 */
	public static function set( ?string $context ): void {
		$woocommerce = WC();
		$property    = new ReflectionProperty( WooCommerce::class, 'request_context' );

		if ( ! self::$captured ) {
			self::$original = $property->getValue( $woocommerce );
			self::$captured = true;
		}

		$property->setValue( $woocommerce, $context );
	}

	/**
	 * Restores the context WooCommerce had before the tests changed it.
	 */
	public static function reset(): void {
		if ( ! self::$captured ) {
			return;
		}

		( new ReflectionProperty( WooCommerce::class, 'request_context' ) )->setValue( WC(), self::$original );
		self::$captured = false;
		self::$original = null;
	}
}
