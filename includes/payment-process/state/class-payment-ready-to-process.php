<?php
/**
 * Payment_Ready_To_Process class.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment_Process\State;

use WCPay\Payment_Process\Order_Payment;
use WCPay\Payment_Process\Payment_Method\Payment_Method_Factory;
use WCPay\Payment_Process\Storage\Payment_Storage;

/**
 * Represents payments in a state, which requires setup.
 */
class Payment_Ready_To_Process extends Order_Payment {
	/**
	 * Instantiates the state, and sets up all required dependencies.
	 *
	 * @param Payment_Storage        $storage                Storage to load/save payments from/to.
	 * @param Payment_Method_Factory $payment_method_factory Factory for payment methods.
	 */
	public function __construct(
		Payment_Storage $storage,
		Payment_Method_Factory $payment_method_factory
	) {
		parent::__construct( $storage, $payment_method_factory );

		// Load local dependencies. This should be done better.

	}


}
