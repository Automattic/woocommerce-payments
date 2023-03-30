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
final class Store_Metadata_Step extends Abstract_Step {
	/**
	 * The mode object for the gateway.
	 *
	 * @var WCPay\Core\Mode
	 */
	protected $mode;

	/**
	 * The order service.
	 *
	 * @var WC_Payments_Order_Service
	 */
	protected $order_service;

	/**
	 * Loads all needed dependencies.
	 */
	public function __construct() {
		$this->order_service = WC_Payments::get_order_service();
		$this->mode          = WC_Payments::mode();
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
	 * @todo: Originally, this was not a complete, but a preparation step.
	 *
	 * @param Payment $payment The processed payment.
	 */
	public function complete( Payment $payment ) {
		if ( ! $payment instanceof Order_Payment ) {
			return; // Keep IDEs happy.
		}

		$order = $payment->get_order();

		// UPE orders might have a customer, and mode, but not have a payment method yet.
		if ( ! is_null( $payment->get_payment_method() ) ) {
			$this->order_service->set_payment_method_id_for_order( $order, $payment->get_payment_method()->get_id() );
		}

		$this->order_service->set_customer_id_for_order( $order, $payment->get_customer_id() );

		// @todo: Store the test/live mode in the payment object.
		$this->order_service->set_mode_for_order( $order, $this->mode->is_test() ? 'test' : 'prod' );
	}
}
