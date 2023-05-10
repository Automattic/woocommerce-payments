<?php
/**
 * Class Payment_Method
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment;

/**
 * Base class, representing payment methods.
 */
abstract class Payment_Method {
	/**
	 * Returns the type of the payment method,
	 * as accepted by the API, and stored within tokens.
	 *
	 * @return string
	 */
	abstract public function get_type(): string;

	/**
	 * Indicates if the payment method supports in-person payments.
	 * Most payment methods do not, so this defaults to false.
	 *
	 * @return bool
	 */
	public function supports_in_person_payments(): bool {
		return false;
	}
}
