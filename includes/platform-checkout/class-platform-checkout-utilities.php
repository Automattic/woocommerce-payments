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
		$has_subscription_in_cart      = $this->is_subscription_item_in_cart();
		$disable_for_subscription      = ! WC_Payments_Features::is_platform_checkout_subscriptions_enabled() && $has_subscription_in_cart;

		$enable_platform_checkout = $is_platform_checkout_eligible && $is_platform_checkout_enabled && ! $disable_for_subscription;

		if ( $enable_platform_checkout && ! is_user_logged_in() ) {
			wp_localize_script(
				'WCPAY_CHECKOUT',
				'wcPayHasSubscriptionsInCart',
				$has_subscription_in_cart ? 1 : 0
			);
			wp_localize_script( 'WCPAY_CHECKOUT', 'wcPayUserExistsUrl', get_rest_url( null, '/wc/v3/users/exists' ) );
		}

		return $enable_platform_checkout;
	}
}
