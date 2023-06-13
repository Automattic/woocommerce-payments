<?php
/**
 * Class Processing_Failed_State
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment\State;

use WC_Payments_Action_Scheduler_Service;
use WC_Payments_Token_Service;
use WC_Payments_Order_Service;
use WC_Payment_Gateway_WCPay;
use WCPay\Core\Mode;
use WCPay\Payment\Payment;

/**
 * If processing a payment fails, it will be in this state.
 */
final class Processing_Failed_State extends Payment_State {

	/**
	 * Action scheduler service.
	 *
	 * @var WC_Payments_Action_Scheduler_Service
	 */
	protected $action_scheduler_service;

	/**
	 * The mode object.
	 *
	 * @var Mode;
	 */
	protected $mode;

	/**
	 * The WCpay token service.
	 *
	 * @var WC_Payments_Token_Service
	 */
	protected $token_service;

	/**
	 * The order service.
	 *
	 * @var WC_Payments_Order_Service
	 */
	protected $order_service;

	/**
	 * Gateway object.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	protected $gateway;

	/**
	 * Instantiates the state.
	 *
	 * @param Payment $payment The context of the state.
	 */
	public function __construct( Payment $payment ) {
		$this->context = $payment;

		// @todo Replace with DI.
		$this->gateway                  = WC_Payments::get_gateway();
		$this->action_scheduler_service = WC_Payments::get_action_scheduler_service();
		$this->mode                     = WC_Payments::mode();
		$this->token_service            = WC_Payments::get_token_service();
		$this->order_service            = WC_Payments::get_order_service();
	}

	public function a() {
		// meta...
		// Step\Verify_Minimum_Amount_Step::action(),
		// ( new Step\Bump_Transaction_Limiter_Step() )->complete();
	}

	public function update_order() {
		$order = $this->context->get_order();

		$this->order_service->set_payment_method_id_for_order( $order, $this->context->get_payment_method()->get_id() );
		$this->order_service->set_customer_id_for_order( $order, $this->context->get_customer_id() );

		// @todo: Store the test/live mode in the payment object.
		$this->order_service->set_mode_for_order( $order, $this->mode->is_test() ? 'test' : 'prod' );

		// From the update order step.
		$intent = $this->context->get_intent();

		// Prepare details from the intent.
		$intent_id  = $intent->get_id();
		$status     = $intent->get_status();
		$charge     = $intent->get_charge();
		$charge_id  = $charge ? $charge->get_id() : null;
		$currency   = $intent->get_currency();
		$processing = $intent->get_processing();

		// Associate the saved payment method to the order.
		$payment_method = $this->context->get_payment_method();

		// Attach the intent, exchange info, update the status, and add a notification note.
		$this->order_service->attach_intent_info_to_order( $order, $intent_id, $status, $payment_method ? $payment_method->get_id() : null, $this->context->get_customer_id(), $charge_id, $currency );
		$this->gateway->attach_exchange_info_to_order( $order, $charge_id );
		$this->gateway->update_order_status_from_intent( $order, $intent_id, $status, $charge_id );
		$this->gateway->maybe_add_customer_notification_note( $order, $processing );
	}
}
