<?php
namespace WCPay\Payment_Process\Step;

use WC_Payments;
use WCPay\Payment_Process\Payment;
use WCPay\Payment_Process\Order_Payment;

class Store_Metadata_Step extends Abstract_Step {
	public function get_id() {
		return 'store-metadata';
	}

	public function is_applicable( Payment $payment ) {
		return is_a( $payment, Order_Payment::class );
	}

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
