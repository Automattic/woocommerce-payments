<?php
/**
 * Class Awaiting_UPE_Confirmatio-n_State
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment\State;

use WC_Payments;
use WC_Payments_API_Client;
use UPE_Payment_Gateway;
use WC_Payments_API_Intention;
use WC_Payments_API_Setup_Intention;
use WCPay\Constants\Payment_Intent_Status;
use WCPay\Core\Server\Request\Get_Intention;
use WCPay\Exceptions\Process_Payment_Exception;
use WCPay\Payment\Flags;
use WCPay\Payment\Payment;
use WCPay\Payment\Strategy\Redirect_If_Action_Is_Required;

/**
 * At this point UPE requires confirmation on the front-end.
 */
final class Awaiting_UPE_Confirmation_State extends Payment_State {
	use Redirect_If_Action_Is_Required;

	/**
	 * The client for connection with the server.
	 * Should be replaced with request classes soon.
	 *
	 * @var WC_Payments_API_Client
	 */
	protected $payments_api_client;

	/**
	 * Contains the gateway.
	 *
	 * @var UPE_Payment_Gateway
	 */
	protected $gateway;

	/**
	 * Instantiates the state.
	 *
	 * @param Payment $payment The context of the state.
	 */
	public function __construct( Payment $payment ) {
		parent::__construct( $payment );

		// @todo: Replace this with proper dependencies.
		$this->payments_api_client = WC_Payments::get_payments_api_client();
		$this->gateway             = WC_Payments::get_gateway();
	}

	/**
	 * Indicates if the state should interrupt the processing loop.
	 * Overwrite in states inc ase they are final, and should interrupt the process.
	 *
	 * @return bool
	 */
	public function is_processing_finished() {
		return true;
	}

	/**
	 * Loads an intent into the payment process once it has been confirmed,
	 * and transitions to the correct follow-up state.
	 *
	 * @param string $intent_id The ID of the intent to load.
	 */
	public function load_intent_after_confirmation( string $intent_id ) {
		$this->context->set_intent_id( $intent_id );

		// Load the intent and check for errors.
		// The process can only continue if the intent exists, and was successful.
		$intent = $this->get_intent_object();
		$this->check_intent_for_errors( $intent );
		$this->context->set_intent( $intent );

		// The process cannot be completed without a selected payment method either.
		$payment_method_type = $this->gateway->get_selected_payment_method( $intent->get_payment_method_type() );
		if ( ! $payment_method_type ) {
			throw new Process_Payment_Exception(
				__( 'Could not find an attached payment method.', 'woocommerce-payments' ),
				'upe_payment_intent_missing'
			);
		}

		// Make sure that if the payment method is not reusable, there are no attempts to save it in another step.
		if ( $this->context->is( Flags::SAVE_PAYMENT_METHOD_TO_STORE ) && ! $payment_method_type->is_reusable() ) {
			$this->context->unset_flag( Flags::SAVE_PAYMENT_METHOD_TO_STORE );
		}

		// Check for required actions (redirect to another service or 3DS modal).
		if ( Payment_Intent_Status::REQUIRES_ACTION === $intent->get_status() ) {
			$result = $this->redirect_if_action_is_required( $this->context, $intent );
			$this->context->set_response( $result );
			$this->context->switch_state( new Authentication_Required_State( $this->context ) );
			return;
		}

		$this->context->switch_state( new Processed_State( $this->context ) );
	}

	/**
	 * Loads a payment or setup intent for the payment.
	 *
	 * @return WC_Payments_API_Intention|WC_Payments_API_Setup_Intention
	 * @throws Process_Payment_Exception In case the intent could not be loaded.
	 */
	protected function get_intent_object() {
		$order = $this->context->get_order();

		// Get payment or setup intent to check the status of the payment.
		if ( 0 < $order->get_total() ) { // It's a payment only for positive amounts.
			$request = Get_Intention::create( $this->context->get_intent_id() );
			$intent  = $request->send( 'wcpay_get_intent_request', $order );
		} else {
			$intent = $this->payments_api_client->get_setup_intent( $this->context->get_intent_id() );
		}

		// If there is no intention for any reason, bail.
		if ( ! $intent instanceof WC_Payments_API_Intention && ! $intent instanceof WC_Payments_API_Setup_Intention ) {
			throw new Process_Payment_Exception(
				__( 'Could not load the intention for a redirect payment.', 'woocommerce-payments' ),
				'upe_payment_intent_missing'
			);
		}

		return $intent;
	}

	/**
	 * Checks if an intent contains errors.
	 *
	 * @param WC_Payments_API_Intention|WC_Payments_API_Setup_Intention $intent Intent to check.
	 * @throws Process_Payment_Exception In case there was an error.
	 */
	protected function check_intent_for_errors( $intent ) {
		// If there is an error, there's nothing we could do at this stage, throw an exception.
		$error = $intent instanceof WC_Payments_API_Intention
			? $intent->get_last_payment_error()
			: $intent->get_last_setup_error();

		if ( empty( $error ) ) {
			return;
		}

		throw new Process_Payment_Exception(
			sprintf(
				// Translators: %s is the reason for the error, as provided by the server.
				__( 'We are not able to process this payment. Please try again later. Reason: %s', 'woocommerce-payments' ),
				$error['message']
			),
			'upe_payment_intent_error'
		);
	}
}
