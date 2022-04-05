<?php
/**
 * Class Platform_Checkout_Webhook
 *
 * @package WooCommerce\Payments
 */

defined( 'ABSPATH' ) || exit;

/**
 * Represents a single WooCommerce Webhook instance, with helper methods to determine
 * whether the Webhook is one that was created by the Platform Checkout.
 */
class Platform_Checkout_Webhook extends WC_Webhook {

	/**
	 * Whether or not the specified WooCommerce webhook is one that was created
	 * by the WooCommerce Platform Checkout integration.
	 *
	 * @return boolean
	 */
	public function is_platform_checkout_webhook() {
		$platform_checkout_host = defined( 'PLATFORM_CHECKOUT_HOST' ) ? PLATFORM_CHECKOUT_HOST : 'http://host.docker.internal:8090';

		if ( false === strpos( $this->get_name(), 'Platform Checkout' ) ) {
			return false;
		}

		if ( false === strpos( $this->get_delivery_url(), $platform_checkout_host ) ) {
			return false;
		}

		return true;
	}
}
