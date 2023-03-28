<?php
/**
 * Class Update_Order_Step
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment_Process\Step;

use WC_Cart;
use WC_Payment_Gateway_WCPay;
use WC_Payments;
use WC_Payments_Order_Service;
use WC_Payments_API_Intention;
use WCPay\Payment_Process\Payment;
use WCPay\Payment_Process\Order_Payment;
use WCPay\Payment_Process\Payment_Method\Saved_Payment_Method;

/**
 * Updates the order with all the necessary details, incl. its status.
 */
class Update_Order_Step extends Abstract_Step {
	/**
	 * Gateway object.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	protected $gateway;

	/**
	 * WCPay order service.
	 *
	 * @var WC_Payments_Order_Service
	 */
	protected $order_service;

	/**
	 * Loads all required dependencies.
	 */
	public function __construct() {
		$this->gateway       = WC_Payments::get_gateway();
		$this->order_service = WC_Payments::get_order_service();
	}

	/**
	 * Returns the ID of the step.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'store-order-data';
	}

	/**
	 * Checks if the step is applicable.
	 *
	 * @param Payment $payment A payment, which is being processed.
	 * @return bool
	 */
	public function is_applicable( Payment $payment ) {
		return $payment instanceof Order_Payment;
	}

	/**
	 * While completing a payment, stores the payment method as a token.
	 *
	 * @param Payment $payment The payment object.
	 */
	public function complete( Payment $payment ) {
		if ( ! $payment instanceof Order_Payment ) {
			return; // keep IDEs happy.
		}

		$order  = $payment->get_order();
		$intent = $this->get_intent_from_payment( $payment );

		// Prepare details from the intent.
		$intent_id  = $intent->get_id();
		$status     = $intent->get_status();
		$charge     = $intent->get_charge();
		$charge_id  = $charge ? $charge->get_id() : null;
		$currency   = $intent->get_currency();
		$processing = $intent->get_processing();

		// Associate the saved payment method to the order.
		$payment_method = $payment->get_payment_method();
		if ( $payment_method instanceof Saved_Payment_Method ) {
			$this->gateway->add_token_to_order( $order, $payment_method->get_token() );
		}

		// Attach the intent, exchange info, update the status, and add a notification note.
		$this->order_service->attach_intent_info_to_order( $order, $intent_id, $status, $payment_method ? $payment_method->get_id() : null, $payment->get_customer_id(), $charge_id, $currency );
		$this->gateway->attach_exchange_info_to_order( $order, $charge_id );
		// @todo: For some payments (ex. update status after 3DS), `REQUIRES_PAYMENT_METHOD` should fail the order, instead of marking it as started.
		$this->gateway->update_order_status_from_intent( $order, $intent_id, $status, $charge_id );
		$this->gateway->maybe_add_customer_notification_note( $order, $processing );

		// ToDo: Verify if this check should happen here.
		if ( Payment::STATUS_SUCCESSFUL !== $payment->get_status() ) {
			return;
		}

		wc_reduce_stock_levels( $payment->get_order()->get_id() );

		// For standard (non-merchant-initiated) payments, clear the cart as well.
		if ( ! $payment->is( Payment::MERCHANT_INITIATED ) ) {
			$cart = $this->get_wc_cart();
			if ( isset( $cart ) ) {
				$cart->empty_cart();
			}
		}

		$this->set_payment_method_title_for_order( $payment, $intent );
	}

	/**
	 * Changes the name of the payment method on various screens, incl. order received page.
	 *
	 * @param Order_Payment             $payment Processing payment.
	 * @param WC_Payments_API_Intention $intent  The intent, which was used.
	 */
	protected function set_payment_method_title_for_order( Order_Payment $payment, WC_Payments_API_Intention $intent ) {
		$order = $payment->get_order();

		if ( $order->get_total() > 0 ) {
			$charge                 = $intent ? $intent->get_charge() : null;
			$payment_method_details = $charge ? $charge->get_payment_method_details() : [];
			$payment_method_type    = $payment_method_details ? $payment_method_details['type'] : null;

			// @todo: There is additional WooPay logic here, check the gateway.
		} else {
			$payment_method_details = false;
			$payment_method_options = isset( $intent['payment_method_options'] ) ? array_keys( $intent['payment_method_options'] ) : null;
			$payment_method_type    = $payment_method_options ? $payment_method_options[0] : null;
		}

		// @todo: Instead of checking $_POST directly, use a flag in the payment.
		if ( empty( $_POST['payment_request_type'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$this->gateway->set_payment_method_title_for_order(
				$order,
				$payment_method_type,
				$payment_method_details
			);
		}
	}

	/**
	 * Retrieves the intent from the payment with the correct return class.
	 *
	 * If this is needed too often, we might need to switch away from vars.
	 *
	 * @param Order_Payment $payment Payment object.
	 * @return WC_Payments_API_Intention
	 */
	protected function get_intent_from_payment( Order_Payment $payment ) {
		return $payment->get_intent();
	}

	/**
	 * Retrieves the WC cart object. Separate method for unit tests.
	 *
	 * @return WC_Cart
	 */
	protected function get_wc_cart() {
		return WC()->cart;
	}
}
