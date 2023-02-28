<?php
/**
 * Class file for WCPay\Core\Server\Request\WooPay_Create_Intent.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core\Server\Request;

/**
 * Extended create intent request for WooPay.
 */
class WooPay_Create_Intent extends Create_Intention {
	const IMMUTABLE_PARAMS = [ 'save_payment_method_to_platform' ];

	/**
	 * Indicates whether the payment method needs to be stored with the platform.
	 *
	 * @param bool $toggle         Whether to toggle the flag.
	 */
	public function set_save_payment_method_to_platform( bool $toggle ) {
		$this->set_param( 'save_payment_method_to_platform', $toggle );
	}
}
