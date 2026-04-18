<?php
/**
 * Stub for WC_Subscriptions_Change_Payment_Gateway.
 *
 * This class ships with WooCommerce Subscriptions and is not available in the
 * unit test environment. The stub exposes static call-tracking properties so
 * tests can assert which arguments were passed.
 *
 * @package WooCommerce\Payments\Tests
 */

if ( ! class_exists( 'WC_Subscriptions_Change_Payment_Gateway' ) ) {
	/**
	 * Stub class for WC_Subscriptions_Change_Payment_Gateway.
	 */
	class WC_Subscriptions_Change_Payment_Gateway {
		/** @var array */
		public static $update_payment_method_calls = [];
		/** @var array */
		public static $update_all_calls = [];
		/** @var bool */
		public static $will_update_all_return = false;
		/** @var bool */
		public static $update_all_return = false;

		/**
		 * Stub for update_payment_method.
		 *
		 * @param WC_Order $order      The order.
		 * @param string   $gateway_id The gateway ID.
		 */
		public static function update_payment_method( $order, $gateway_id ) {
			self::$update_payment_method_calls[] = [
				'order'      => $order,
				'gateway_id' => $gateway_id,
			];
		}

		/**
		 * Stub for will_subscription_update_all_payment_methods.
		 *
		 * @param WC_Order $order The order.
		 * @return bool
		 */
		public static function will_subscription_update_all_payment_methods( $order ) {
			return self::$will_update_all_return;
		}

		/**
		 * Stub for update_all_payment_methods_from_subscription.
		 *
		 * @param WC_Order $order      The order.
		 * @param string   $gateway_id The gateway ID.
		 * @return bool
		 */
		public static function update_all_payment_methods_from_subscription( $order, $gateway_id ) {
			self::$update_all_calls[] = [
				'order'      => $order,
				'gateway_id' => $gateway_id,
			];
			return self::$update_all_return;
		}
	}
}
