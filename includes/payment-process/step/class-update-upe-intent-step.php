<?php
/**
 * Class Update_UPE_Intent_Step
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment_Process\Step;

use Exception;
use WC_Payments;
use WC_Payments_Utils;
use WCPay\Payment_Methods\UPE_Payment_Gateway;
use WCPay\Core\Exceptions\Amount_Too_Small_Exception;
use WCPay\Core\Server\Request\Update_Intention;
use WCPay\Payment_Process\Order_Payment;
use WCPay\Payment_Process\Payment;
use WCPay\Payment_Process\Payment_Method\Saved_Payment_Method;

/**
 * Updates intents upon `process_payment()` (submission of the checkout form).
 * At this point all order details are finalized, and need to be added to the intent.
 * The intent will be confirmed later, in JavaScript.
 */
class Update_UPE_Intent_Step extends Abstract_Step {
	/**
	 * Holds the WCPay gateway.
	 *
	 * @var UPE_Payment_Gateway
	 */
	protected $gateway;

	/**
	 * Returns the ID of the step.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'update-upe-intent';
	}

	/**
	 * Loads all needed dependencies.
	 */
	public function __construct() {
		$this->gateway = WC_Payments::get_gateway();
	}

	/**
	 * Checks if the step is applicable to the payment process.
	 *
	 * @param Payment $payment The payment object.
	 * @return bool
	 */
	public function is_applicable( Payment $payment ) {
		return $payment->is_flow( Payment::UPE_PROCESS_PAYMENT_FLOW )
			&& ! $payment->get_payment_method() instanceof Saved_Payment_Method; // Saved PMs fall back to the old process.
	}

	/**
	 * Performs all actions, related to the update of an intent with UPE.
	 *
	 * @param Payment $payment The payment, which we're working with.
	 */
	public function action( Payment $payment ) {
		if ( ! $payment instanceof Order_Payment ) {
			return; // Keep IDEs happy.
		}

		$order = $payment->get_order();
		if ( $order->get_total() > 0 ) {
			// Setup intents already contain enough information, only update Payment intents.
			$intent = $this->request_intent_update_from_server( $payment );
			$payment->set_var( 'intent', $intent );
		}

		$payment->complete( $this->get_redirect_array( $payment ) );
	}

	/**
	 * Requests an intent to be updated on the server.
	 *
	 * @param Order_Payment $payment     The payment object.
	 * @return WC_Payments_API_Intention The updated intention.
	 *
	 * @throws Amount_Too_Small_Exception If the amount is insufficient for the intent.
	 */
	protected function request_intent_update_from_server( Order_Payment $payment ) {
		$order           = $payment->get_order();
		$intent_id       = $payment->get_var( 'intent_id' );
		$selected        = (string) $payment->get_var( 'selected_upe_payment_type' );
		$payment_methods = $this->gateway->get_selected_upe_payment_methods( $selected, $this->gateway->get_payment_method_ids_enabled_at_checkout( null, true ) ?? [] );

		try {
			$request = Update_Intention::create( $intent_id );
			$request->set_currency_code( strtolower( $order->get_currency() ) );
			$request->set_amount( WC_Payments_Utils::prepare_amount( $order->get_total(), $order->get_currency() ) );
			$request->set_metadata( $payment->get_var( 'metadata' ) );
			$request->set_level3( $this->gateway->get_level3_data_from_order( $order ) );
			$request->set_payment_method_types( $payment_methods );
			$payment_country = $payment->get_var( 'payment_country' );
			if ( $payment_country ) {
				$request->set_payment_country( $payment_country );
			}
			if ( $payment->is( Payment::SAVE_PAYMENT_METHOD_TO_STORE ) ) {
				$request->setup_future_usage();
			}
			if ( $payment->get_var( 'customer_id' ) ) {
				$request->set_customer( $payment->get_var( 'customer_id' ) );
			}
			$payment_method_options = $this->gateway->get_mandate_params_for_order( $order );
			if ( $payment_method_options ) {
				$request->setup_future_usage();
				$request->set_payment_method_options( $payment_method_options );
			}

			return $request->send( 'wcpay_update_intention_request', $order, $intent_id );
		} catch ( Amount_Too_Small_Exception $e ) {
			// This code would only be reached if the cache has already expired.
			throw new Exception( WC_Payments_Utils::get_filtered_error_message( $e ) );
		}
	}

	/**
	 * Generates the array for redirection upon intent update.
	 *
	 * @param Order_Payment $payment The payment details.
	 * @return array
	 */
	protected function get_redirect_array( Order_Payment $payment ) {
		$order = $payment->get_order();

		return [
			'result'         => 'success',
			'payment_needed' => $order->get_total() > 0,
			'redirect_url'   => wp_sanitize_redirect(
				esc_url_raw(
					add_query_arg(
						[
							'order_id'            => $order->get_id(),
							'wc_payment_method'   => $this->gateway::GATEWAY_ID,
							'_wpnonce'            => wp_create_nonce( 'wcpay_process_redirect_order_nonce' ),
							'save_payment_method' => $payment->is( Payment::SAVE_PAYMENT_METHOD_TO_STORE ) ? 'yes' : 'no',
						],
						$this->gateway->get_return_url( $order )
					)
				)
			),
		];
	}
}
