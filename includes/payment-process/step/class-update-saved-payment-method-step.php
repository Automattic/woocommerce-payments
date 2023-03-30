<?php
/**
 * Class Update_Saved_Payment_Method_Step
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment_Process\Step;

use WC_Payment_Gateway_WCPay;
use WC_Payments;
use WCPay\Payment_Process\Order_Payment;
use WCPay\Payment_Process\Payment;
use WCPay\Payment_Process\Payment_Method\Saved_Payment_Method;

/**
 * Schedules an action to update a saved payment method based on the new order.
 */
class Update_Saved_Payment_Method_Step extends Abstract_Step {
	/**
	 * Action scheduler service.
	 *
	 * @var WC_Payments_Action_Scheduler_Service
	 */
	protected $action_scheduler_service;

	/**
	 * The mode object.
	 *
	 * @var WCPay\Core\Mode;
	 */
	protected $mode;

	/**
	 * Instantiates the step.
	 */
	public function __construct() {
		// @todo: Change this with proper dependencies.
		$this->action_scheduler_service = WC_Payments::get_action_scheduler_service();
		$this->mode                     = WC_Payments::mode();
	}

	/**
	 * Checks if the step is applicable to a given payment.
	 *
	 * @param Payment $payment A payment, which will be processed.
	 * @return bool
	 */
	public function is_applicable( Payment $payment ) {
		return $payment instanceof Order_Payment
			&& $payment->get_payment_method() instanceof Saved_Payment_Method;
	}

	/**
	 * Schedules the action.
	 *
	 * @param Payment $payment The payment, which is being processed.
	 */
	public function complete( Payment $payment ) {
		// @todo: Check if the payment is successful before doing this.
		if ( ! $payment instanceof Order_Payment ) {
			return;
		}

		$this->action_scheduler_service->schedule_job(
			time(),
			WC_Payment_Gateway_WCPay::UPDATE_SAVED_PAYMENT_METHOD,
			[
				'payment_method' => $payment->get_payment_method()->get_id(),
				'order_id'       => $payment->get_order()->get_id(),
				'is_test_mode'   => $this->mode->is_test(),
			]
		);
	}
}
