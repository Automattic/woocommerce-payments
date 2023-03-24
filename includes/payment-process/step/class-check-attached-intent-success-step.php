<?php
/**
 * Class Check_Attached_Intent_Success_Step
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment_Process\Step;

use Exception;
use WC_Payment_Gateway_WCPay;
use WC_Payments;
use WCPay\Core\Server\Request\Get_Intention;
use WCPay\Logger;
use WCPay\Payment_Process\Order_Payment;
use WCPay\Payment_Process\Payment;

/**
 * Checks whether an order already has an attached successful intent.
 *
 * If there is an intent attached, and it is already in a successful state,
 * this means that the payment should already be completed.
 */
class Check_Attached_Intent_Success_Step extends Abstract_Step {
	/**
	 * Flag to indicate that a previous intention attached to the order was successful.
	 */
	const FLAG_PREVIOUS_SUCCESSFUL_INTENT = 'wcpay_previous_successful_intent';

	/**
	 * Holds the active hateway.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	protected $gateway;

	/**
	 * Gathers all required dependencies.
	 */
	public function __construct() {
		$this->gateway = WC_Payments::get_gateway();
	}

	/**
	 * Returns the ID of the step.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'check-attached-intent-success';
	}

	/**
	 * Checks if the step is applicable at all.
	 *
	 * @param Payment $payment The payment process.
	 * @return bool
	 */
	public function is_applicable( Payment $payment ) {
		return $payment instanceof Order_Payment;
	}

	/**
	 * Checks if the attached payment intent was successful for the current order.
	 *
	 * @param Payment $payment Payment proccess.
	 */
	public function action( Payment $payment ) {
		if ( ! $payment instanceof Order_Payment ) {
			return; // keep IDEs happy.
		}

		$order = $payment->get_order();

		$intent_id = (string) $order->get_meta( '_intent_id', true );
		if ( empty( $intent_id ) ) {
			return;
		}

		// We only care about payment intent.
		$is_payment_intent = 'pi_' === substr( $intent_id, 0, 3 );
		if ( ! $is_payment_intent ) {
			return;
		}

		try {
			$request = Get_Intention::create( $intent_id );
			$intent  = $request->send( 'wcpay_get_intention_request' );

			$intent_status = $intent->get_status();
		} catch ( Exception $e ) {
			Logger::error( 'Failed to fetch attached payment intent: ' . $e );
			return;
		};

		if ( ! in_array( $intent_status, WC_Payment_Gateway_WCPay::SUCCESSFUL_INTENT_STATUS, true ) ) {
			return;
		}

		// @todo: This meta key, this way, is susceptible to change.
		$intent_meta_order_id_raw = $intent->get_metadata()['order_id'] ?? '';
		$intent_meta_order_id     = is_numeric( $intent_meta_order_id_raw ) ? intval( $intent_meta_order_id_raw ) : 0;
		if ( $intent_meta_order_id !== $order->get_id() ) {
			return;
		}

		// The rest will be done by other steps.
		$payment->set_var( 'intent', $intent );

		$return_url = $this->gateway->get_return_url( $order );
		$return_url = add_query_arg( self::FLAG_PREVIOUS_SUCCESSFUL_INTENT, 'yes', $return_url );
		$payment->complete(
			[
				'result'                               => 'success',
				'redirect'                             => $return_url,
				'wcpay_upe_previous_successful_intent' => 'yes', // This flag is needed for UPE flow.
			]
		);
	}
}
