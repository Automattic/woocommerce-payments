<?php
/**
 * Interface Payment_Method
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment_Process\Payment_Method;

interface Payment_Method {
	/**
	 * Retrieves the data of the payment method for storage.
	 *
	 * @return array
	 */
	public function get_data();
}
