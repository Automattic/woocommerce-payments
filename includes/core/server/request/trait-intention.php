<?php
/**
 * Trait file for WCPay\Core\Server\Request\Intention.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core\Server\Request;

use WCPay\Fraud_Prevention\Buyer_Fingerprinting_Service;

/**
 * Trait for intention helpers.
 */
trait Intention {
	/**
	 * Returns a list of fingerprinting metadata to attach to order.
	 *
	 * @param string $fingerprint Fingerprint data.
	 *
	 * @return array List of fingerprinting metadata.
	 */
	private function get_fingerprint_metadata( $fingerprint = '' ): array {
		$customer_fingerprint_metadata                                    = Buyer_Fingerprinting_Service::get_instance()->get_hashed_data_for_customer( $fingerprint );
		$customer_fingerprint_metadata['fraud_prevention_data_available'] = true;

		return $customer_fingerprint_metadata;
	}

	/**
	 * Returns a formatted intention description.
	 *
	 * @param  string $order_number The order number (might be different from the ID).
	 * @return string               A formatted intention description.
	 */
	private function get_intent_description( $order_number ): string {
		$domain_name = str_replace( [ 'https://', 'http://' ], '', get_site_url() );
		$blog_id     = $this->get_blog_id();

		// Forgo i18n as this is only visible in the Stripe dashboard.
		return sprintf(
			'Online Payment%s for %s%s',
			0 !== $order_number ? " for Order #$order_number" : '',
			$domain_name,
			null !== $blog_id ? " blog_id $blog_id" : ''
		);
	}

	/**
	 * Gets the current WP.com blog ID, if the Jetpack connection has been set up.
	 *
	 * @return integer|NULL Current WPCOM blog ID, or NULL if not connected yet.
	 */
	private function get_blog_id() {
		return $this->is_server_connected() ? $this->http_interface->get_blog_id() : null;
	}

	/**
	 * Whether the site can communicate with the WCPay server (i.e. Jetpack connection has been established).
	 *
	 * @return bool
	 */
	private function is_server_connected() {
		return $this->http_interface->is_connected();
	}
}
