<?php
/**
 * Class file for WCPay\Core\Server\Request\Create_And_Confirm_Intention.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core\Server\Request;

/**
 * Request class for creating intents.
 */
class WooPay_Create_And_Confirm_Intention extends Create_And_Confirm_Intention {
	const DEFAULT_PARAMS = [
		'is_platform_payment_method'      => false,
		'woopay_has_subscription'         => false,
		'save_payment_method_to_platform' => false,
	];

	/**
	 * Toggles the flag, which indicates that this is a platform payment method.
	 *
	 * @param bool $is Whether it is indeed a platform payment method (Optional).
	 */
	public function set_is_platform_payment_method( $is = true ) {
		$this->set_param( 'is_platform_payment_method', $is );
	}

	/**
	 * Toggles the flag, which indicates that there is a WooPay subscription.
	 *
	 * @param bool $has Whether there is a subscription (Optional).
	 */
	public function set_has_woopay_subscription( $has = true ) {
		$this->set_param( 'woopay_has_subscription', $has );
	}

	/**
	 * Save payment method to platform.
	 *
	 * @param bool $save save payment method to platform.
	 */
	public function set_save_payment_method_to_platform( $save = true ) {
		$this->set_param( 'save_payment_method_to_platform', $save );
	}

}
