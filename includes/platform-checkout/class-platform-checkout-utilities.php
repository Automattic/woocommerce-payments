<?php
/**
 * Platform Checkout
 *
 * @package WCPay\Platform_Checkout
 */

namespace WCPay\Platform_Checkout;

use WC_Cart;
use WC_Customer;
use WC_Payments_Features;
use WC_Payments_Subscriptions_Utilities;
use WC_Session_Handler;

/**
 * Platform_Checkout
 */
class Platform_Checkout_Utilities {
	use WC_Payments_Subscriptions_Utilities;

	public function is_platform_checkout_enabled( $gateway = null ) {
		$is_platform_checkout_enabled         = true;
		$is_platform_checkout_eligible = WC_Payments_Features::is_platform_checkout_eligible(); // Feature flag.
		if ( ! empty( $gateway ) ) {
			$is_platform_checkout_enabled = 'yes' === $gateway->get_option( 'platform_checkout', 'no' );
		};

		if ( empty( WC()->cart ) ) {
		}
		$is_subscription_item_in_cart = $this->is_subscription_item_in_cart();

		return $is_platform_checkout_eligible && $is_platform_checkout_enabled && ! $is_subscription_item_in_cart;
	}
}
