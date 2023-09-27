<?php
/**
 * Interface Payment_Method
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Payment\PaymentMethod;

interface PaymentMethodInterface {
	/**
	 * Retrieves the data of the payment method for storage.
	 *
	 * @return array
	 */
	public function get_data(): array;

	/**
	 * Returns the ID of the payment method.
	 *
	 * @return string
	 */
	public function get_id(): string;
}
