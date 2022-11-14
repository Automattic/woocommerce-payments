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
	const DEFAULTS = [
		'is_platform_payment_method' => false,
		'woopay_has_subscription'    => false,
	];

	/**
	 * Toggles the flag, which indicates that this is a platform payment method.
	 *
	 * @param bool $is Whether it is indeed a platform payment method (Optional).
	 * @return static  An instance of the class, ready for method chaining.
	 */
	public function set_is_platform_payment_method( $is = true ) {
		$this->set_param( 'is_platform_payment_method', $is );
		return $this;
	}

	/**
	 * Toggles the flag, which indicates that there is a WooPay subscription.
	 *
	 * @param bool $has Whether there is a subscription (Optional).
	 * @return static   An instance of the class, ready for method chaining.
	 */
	public function set_has_woopay_subscription( $has = true ) {
		$this->set_param( 'woopay_has_subscription', $has );
		return $this;
	}
}
