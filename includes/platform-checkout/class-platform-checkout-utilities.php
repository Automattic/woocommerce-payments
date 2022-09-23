<?php
/**
 * Platform Checkout
 *
 * @package WCPay\Platform_Checkout
 */

namespace WCPay\Platform_Checkout;

use WC_Payments_Features;
use WC_Payments_Subscriptions_Utilities;

/**
 * Platform_Checkout
 */
class Platform_Checkout_Utilities {
	use WC_Payments_Subscriptions_Utilities;

	/**
	 * Check various conditions to determine if we should enable platform checkout.
	 *
	 * @param \WC_Payment_Gateway_WCPay $gateway Gateway instance.
	 * @return boolean
	 */
	public function should_enable_platform_checkout( $gateway ) {
		$is_platform_checkout_eligible = WC_Payments_Features::is_platform_checkout_eligible(); // Feature flag.
		$is_platform_checkout_enabled  = 'yes' === $gateway->get_option( 'platform_checkout', 'no' );

		return $is_platform_checkout_eligible && $is_platform_checkout_enabled;
	}

	/**
	 * Generates a hash based on the store's blog token, merchant ID, and the time step window.
	 *
	 * @return string
	 */
	public function get_platform_checkout_request_signature() {
		$store_blog_token = \Jetpack_Options::get_option( 'blog_token' );
		$time_step_window = floor( time() / 30 );

		return hash_hmac( 'sha512', \Jetpack_Options::get_option( 'id' ) . $time_step_window, $store_blog_token );
	}
}
