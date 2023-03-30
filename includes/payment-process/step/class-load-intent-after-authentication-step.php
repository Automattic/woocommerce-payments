<?php
/**
 * Class Load_Intent_After_Authentication_Step
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment_Process\Step;

use WC_Payments;
use WC_Payments_Order_Service;
use WCPay\Core\Server\Request\Get_Intention;
use WCPay\Exceptions\Intent_Authentication_Exception;
use WCPay\Payment_Process\Order_Payment;
use WCPay\Payment_Process\Payment;

/**
 * A part of AJAX request after authenticating payment at checkout.
 *
 * This step is used to update the order status after the user has
 * been asked to authenticate their payment.
 */
final class Load_Intent_After_Authentication_Step extends Abstract_Step {
	/**
	 * Order service.
	 *
	 * @var WC_Payments_Order_Service
	 */
	protected $order_service;

	/**
	 * Holds the gateway instance.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	protected $gateway;

	/**
	 * Contains the API client.
	 *
	 * @var WC_Payments_API_Client
	 */
	protected $payments_api_client;

	/**
	 * Gathers all required dependencies.
	 */
	public function __construct() {
		$this->order_service       = WC_Payments::get_order_service();
		$this->gateway             = WC_Payments::get_gateway();
		$this->payments_api_client = WC_Payments::get_payments_api_client();
	}

	/**
	 * Checks if the step is applicable to a certain process.
	 *
	 * @param Payment $payment Payment process.
	 * @return bool
	 */
	public function is_applicable( Payment $payment ) {
		return $payment instanceof Order_Payment
			&& $payment->is_flow( Payment::POST_CHECKOUT_REDIRECT_FLOW );
	}

	/**
	 * Checks if there is a payment intent to load, and loads it.
	 *
	 * @param Payment $payment Payment process.
	 * @throws Exception Whenever the limiter is hit, and the process cannot continue.
	 */
	public function action( Payment $payment ) {
		if ( ! $payment instanceof Order_Payment ) {
			return; // Keep IDEs happy.
		}

		// Make sure we're working with a valid intent.
		$this->compare_received_and_stored_intents( $payment );

		// Load the payment/setup intent, and make it available for the rest of the process.
		$intent = $this->get_intent_from_server( $payment );
		$payment->set_intent( $intent );

		$payment->complete(
			[
				'return_url' => $this->gateway->get_return_url( $payment->get_order() ),
			]
		);
	}

	/**
	 * Checks that the received intent ID matches the processing order.
	 *
	 * @param Order_Payment $payment The payment process.
	 * @throws Intent_Authentication_Exception If something smells fishy.
	 */
	protected function compare_received_and_stored_intents( Order_Payment $payment ) {
		$order = $payment->get_order();

		// There must be a stored intent ID from `process_payment()`.
		$stored_intent_id = $this->order_service->get_intent_id_for_order( $order );
		if ( empty( $stored_intent_id ) ) {
			throw new Intent_Authentication_Exception(
				__( "We're not able to process this payment. Please try again later.", 'woocommerce-payments' ),
				'empty_intent_id'
			);
		}

		// Check that the intent saved in the order matches the intent used as part of the
		// authentication process. The ID of the intent used is sent with
		// the AJAX request. We are about to use the status of the intent saved in
		// the order, so we need to make sure the intent that was used for authentication
		// is the same as the one we're using to update the status.
		if ( $stored_intent_id !== $payment->get_intent_id() ) {
			throw new Intent_Authentication_Exception(
				__( "We're not able to process this payment. Please try again later.", 'woocommerce-payments' ),
				'intent_id_mismatch'
			);
		}
	}

	/**
	 * Loads the intent from the server.
	 *
	 * @param Order_Payment $payment The payment process.
	 * @return WC_Payments_API_Intention|WC_Payments_API_Setup_Intention
	 */
	protected function get_intent_from_server( Order_Payment $payment ) {
		$order = $payment->get_order();
		if ( $order->get_total() > 0 ) {
			// An exception is thrown if an intent can't be found for the given intent ID.
			$request = Get_Intention::create( $payment->get_intent_id() );
			return $request->send( 'wcpay_get_intent_request', $order );
		} else {
			// For $0 orders, fetch the Setup Intent instead.
			return $this->payments_api_client->get_setup_intent( $payment->get_intent_id() );
		}
	}
}
