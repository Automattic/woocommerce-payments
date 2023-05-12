<?php
/**
 * Class UPE_Update_Intent_Strategy
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment\Strategy;

use Exception;
use Payment_State;
use WC_Payments_API_Intention;
use WC_Payments_Utils;
use WC_Payments;
use WCPay\Payment_Methods\UPE_Payment_Gateway;
use WCPay\Core\Server\Request\Update_Intention;
use WCPay\Exceptions\Amount_Too_Small_Exception;
use WCPay\Payment\Flags;
use WCPay\Payment\Payment;
use WCPay\Payment\State\Awaiting_UPE_Confirmation_State;

/**
 * Represents a payment processing strategy.
 */
class UPE_Update_Intent_Strategy extends Strategy {
	use Redirect_If_Action_Is_Required;

	/**
	 * Holds the gateway instance.
	 *
	 * @var UPE_Payment_Gateway
	 */
	protected $gateway;

	/**
	 * Instantiates the strategy.
	 */
	public function __construct() {
		// Load dependencies. @todo not here.
		$this->gateway = WC_Payments::get_gateway();
	}

	/**
	 * Processes the payment.
	 *
	 * @param  Payment $payment The payment to process.
	 * @return Payment_State    The next state the payment should transition to.
	 */
	public function process( Payment $payment ): Awaiting_UPE_Confirmation_State {
		$order = $payment->get_order();
		if ( $order->get_total() > 0 ) {
			// Setup intents already contain enough information, only update Payment intents.
			$intent = $this->request_intent_update_from_server( $payment );
			$payment->set_intent( $intent );
		}

		$payment->set_response( $this->get_redirect_array( $payment ) );
		return new Awaiting_UPE_Confirmation_State( $payment );
	}

	/**
	 * Requests an intent to be updated on the server.
	 *
	 * @param Payment $payment           The payment object.
	 * @return WC_Payments_API_Intention The updated intention.
	 *
	 * @throws Amount_Too_Small_Exception If the amount is insufficient for the intent.
	 */
	protected function request_intent_update_from_server( Payment $payment ) {
		$order           = $payment->get_order();
		$intent_id       = $payment->get_intent_id();
		$selected        = (string) $payment->get_selected_upe_payment_type();
		$payment_methods = $this->gateway->get_selected_upe_payment_methods( $selected, $this->gateway->get_payment_method_ids_enabled_at_checkout( null, true ) ?? [] );

		try {
			$request = Update_Intention::create( $intent_id );
			$request->set_currency_code( strtolower( $order->get_currency() ) );
			$request->set_amount( WC_Payments_Utils::prepare_amount( $order->get_total(), $order->get_currency() ) );
			$request->set_metadata( $payment->get_metadata() );
			$request->set_level3( $this->gateway->get_level3_data_from_order( $order ) );
			$request->set_payment_method_types( $payment_methods );
			$payment_country = $payment->get_payment_country();
			if ( $payment_country ) {
				$request->set_payment_country( $payment_country );
			}
			if ( $payment->is( Flags::SAVE_PAYMENT_METHOD_TO_STORE ) ) {
				$request->setup_future_usage();
			}
			if ( $payment->get_customer_id() ) {
				$request->set_customer( $payment->get_customer_id() );
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
	 * @param Payment $payment The payment details.
	 * @return array
	 */
	protected function get_redirect_array( Payment $payment ) {
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
							'save_payment_method' => $payment->is( Flags::SAVE_PAYMENT_METHOD_TO_STORE ) ? 'yes' : 'no',
						],
						$this->gateway->get_return_url( $order )
					)
				)
			),
		];
	}
}
