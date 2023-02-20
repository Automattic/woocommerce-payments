<?php
/**
 * Class Store_Metadata_Step
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment_Process\Step;

use WC_Payments;
use WCPay\Payment_Process\Payment;
use WCPay\Payment_Process\Order_Payment;

/**
 * Step for storing order metadata.
 */
class Store_Metadata_Step extends Abstract_Step {
	/**
	 * Returns the ID of the step.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'store-metadata';
	}

	/**
	 * Checks if the step is applicable to a specific payment.
	 *
	 * @param Payment $payment Processing payment.
	 * @return bool
	 */
	public function is_applicable( Payment $payment ) {
		return is_a( $payment, Order_Payment::class );
	}

	/**
	 * Stores the data, completing the step.
	 *
	 * @param Payment $payment The processed payment.
	 */
	public function complete( Payment $payment ) {
		if ( ! $payment instanceof Order_Payment ) {
			return; // Keep IDEs happy.
		}

		$order = $payment->get_order();
		$order->update_meta_data( '_payment_method_id', $payment->get_payment_method()->get_id() );
		$order->update_meta_data( '_stripe_customer_id', $payment->get_var( 'customer_id' ) );

		// @todo: Store the test/live mode in the payment object.
		$order->update_meta_data( '_wcpay_mode', WC_Payments::get_gateway()->is_in_test_mode() ? 'test' : 'prod' );
	}
}
