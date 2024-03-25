<?php
/**
 * Class file for WCPay\Core\Server\Request\WooPay_Create_And_Confirm_Setup_Intention.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core\Server\Request;

/**
 * Request class for creating woopay setup intents.
 */
class WooPay_Create_And_Confirm_Setup_Intention extends Create_And_Confirm_Setup_Intention {
	const DEFAULT_PARAMS = [
		'save_in_platform_account'        => false,
		'is_platform_payment_method'      => false,
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
	 * Save to platform account.
	 *
	 * @param bool $save save to platform account or not.
	 */
	public function set_save_in_platform_account( $save = true ) {
		$this->set_param( 'save_in_platform_account', $save );
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
