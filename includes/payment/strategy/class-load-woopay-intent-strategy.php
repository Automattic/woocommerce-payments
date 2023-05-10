<?php
/**
 * Class Load_WooPay_Intent_Strategy
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment\Strategy;

use WC_Payments;
use WCPay\Core\Server\Request\Get_Intention;
use WCPay\Exceptions\Intent_Authentication_Exception;
use WCPay\Payment\Payment;
use WCPay\Payment\State\Payment_State;
use WCPay\Payment\State\Processed_State;

/**
 * Loads the WooPay intent from the request into the payment.
 */
class Load_WooPay_Intent_Strategy extends Strategy {
	/**
	 * The active gateway.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	protected $gateway;

	/**
	 * Instantiates the step.
	 */
	public function __construct() {
		// @todo: Change this with proper dependencies.
		$this->gateway = WC_Payments::get_gateway();
	}

	/**
	 * Processes the payment.
	 *
	 * @param  Payment $payment The payment to process.
	 * @return Payment_State    The next state of the payment.
	 * @throws Intent_Authentication_Exception If an intent ID is provided, but the intent is missing.
	 */
	public function process( Payment $payment ): Payment_State {
		$order = $payment->get_order();

		// @todo: Code here is identical for payment and setup intents, we just need the right call.
		$request = Get_Intention::create( $payment->get_intent_id() );
		$intent  = $request->send( 'wcpay_get_intent_request', $order );

		$intent_meta_order_id_raw = $intent->get_metadata()['order_id'] ?? '';
		$intent_meta_order_id     = is_numeric( $intent_meta_order_id_raw ) ? intval( $intent_meta_order_id_raw ) : 0;
		if ( $intent_meta_order_id !== $order->get_id() ) {
			// Example: Another state could be added here.
			throw new Intent_Authentication_Exception(
				__( "We're not able to process this payment. Please try again later.", 'woocommerce-payments' ),
				'order_id_mismatch'
			);
		}

		// Store the intent. Other steps will use it.
		$payment->set_intent( $intent );

		// This is the happy path.
		return new Processed_State( $payment );
	}
}
