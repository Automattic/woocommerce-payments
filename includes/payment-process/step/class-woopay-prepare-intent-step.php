<?php
/**
 * Class WooPay_Prepare_intent_Step
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment_Process\Step;

use WC_Payments;
use WCPay\Core\Server\Request\Get_Intention;
use WCPay\Exceptions\Intent_Authentication_Exception;
use WCPay\Payment_Process\Order_Payment;
use WCPay\Payment_Process\Payment;

/**
 * Preloads the platform intent.
 */
final class WooPay_Prepare_Intent_Step extends Abstract_Step {
	/**
	 * Gateway object.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	protected $gateway;

	/**
	 * Loads all required dependencies.
	 */
	public function __construct() {
		$this->gateway = WC_Payments::get_gateway();
	}

	/**
	 * Checks if the step is applicable.
	 *
	 * @param Payment $payment A payment, which is being processed.
	 * @return bool
	 */
	public function is_applicable( Payment $payment ) {
		return $payment->is_flow( Payment::WOOPAY_CHECKOUT_FLOW );
	}

	/**
	 * If the intent is included in the request use that intent.
	 *
	 * @param Payment $payment The payment object.
	 * @throws Intent_Authentication_Exception If an intent ID is provided, but the intent is missing.
	 */
	public function action( Payment $payment ) {
		if ( ! $payment instanceof Order_Payment ) {
			return; // keep IDEs happy.
		}

		$order = $payment->get_order();

		// @todo: Code here is identical for payment and setup intents, we just need the right call.
		$request = Get_Intention::create( $payment->get_intent_id() );
		$intent  = $request->send( 'wcpay_get_intent_request', $order );

		$intent_meta_order_id_raw = $intent->get_metadata()['order_id'] ?? '';
		$intent_meta_order_id     = is_numeric( $intent_meta_order_id_raw ) ? intval( $intent_meta_order_id_raw ) : 0;
		if ( $intent_meta_order_id !== $order->get_id() ) {
			throw new Intent_Authentication_Exception(
				__( "We're not able to process this payment. Please try again later.", 'woocommerce-payments' ),
				'order_id_mismatch'
			);
		}

		// Store the intent. Other steps will use it.
		$payment->set_intent( $intent );

		// This is the happy path.
		$payment->complete(
			[
				'result'   => 'success',
				'redirect' => $this->gateway->get_return_url( $payment->get_order() ),
			]
		);
	}
}
