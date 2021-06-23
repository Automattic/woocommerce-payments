<?php
/**
 * Class Giropay_Payment_Method
 *
 * @package WCPay\Payment_Methods
 */

namespace WCPay\Payment_Methods;

/**
 * Giropay Payment Method class extending UPE base class
 */
class Giropay_Payment_Method extends UPE_Payment_Method {

	const STRIPE_ID = 'giropay';

	const TITLE = 'Giropay';

	/**
	 * Returns boolean dependent on whether payment method
	 * can be used at checkout
	 *
	 * @var bool
	 */
	public function is_enabled_at_checkout() {
		return true;
	}

	/**
	 * Returns boolean dependent on whether payment method
	 * will support saved payments/subscription payments
	 *
	 * @return bool
	 */
	public function is_payment_method_reusable() {
		return false;
	}

}
