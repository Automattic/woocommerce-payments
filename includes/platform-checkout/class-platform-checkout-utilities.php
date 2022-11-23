<?php
/**
 * Platform Checkout
 *
 * @package WCPay\Platform_Checkout
 */

namespace WCPay\Platform_Checkout;

use WC_Payments_Features;
use WC_Payments_Subscriptions_Utilities;
use Platform_Checkout_Extension;

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
	 * Check conditions to determine if woopay express checkout is enabled.
	 *
	 * @return boolean
	 */
	public function is_woopay_express_checkout_enabled() {
		return WC_Payments_Features::is_woopay_express_checkout_enabled(); // Feature flag.
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

	/**
	 * Check session to determine if we should create a platform customer.
	 *
	 * @return boolean
	 */
	public function should_save_platform_customer() {
		$session_data = WC()->session->get( Platform_Checkout_Extension::PLATFORM_CHECKOUT_SESSION_KEY );

		return ( isset( $_POST['save_user_in_platform_checkout'] ) && filter_var( wp_unslash( $_POST['save_user_in_platform_checkout'] ), FILTER_VALIDATE_BOOLEAN ) ) || ( isset( $session_data['save_user_in_platform_checkout'] ) && filter_var( $session_data['save_user_in_platform_checkout'], FILTER_VALIDATE_BOOLEAN ) ); // phpcs:ignore WordPress.Security.NonceVerification
	}

	/**
	 * Get phone number for creating platform checkout customer.
	 *
	 * @return mixed|string
	 */
	public function get_platform_checkout_phone() {
		$session_data = WC()->session->get( Platform_Checkout_Extension::PLATFORM_CHECKOUT_SESSION_KEY );

		if ( ! empty( $_POST['platform_checkout_user_phone_field']['full'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return wc_clean( wp_unslash( $_POST['platform_checkout_user_phone_field']['full'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		} elseif ( ! empty( $session_data['platform_checkout_user_phone_field']['full'] ) ) {
			return $session_data['platform_checkout_user_phone_field']['full'];
		}

		return '';
	}
}
