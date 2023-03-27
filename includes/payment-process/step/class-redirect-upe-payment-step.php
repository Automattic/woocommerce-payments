<?php
/**
 * Class Redirect_UPE_Payment_Step
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment_Process\Step;

use WC_Payments;
use WCPay\Constants\Order_Status;
use WCPay\Constants\Payment_Intent_Status;
use WCPay\Core\Server\Request\Get_Intention;
use WCPay\Payment_Process\Order_Payment;
use WCPay\Payment_Process\Payment;
use WC_Payments_API_Client;
use WC_Payments_Account;
use WC_Payments_API_Intention;
use WC_Payments_API_Setup_Intention;
use WCPay\Exceptions\Process_Payment_Exception;
use WCPay\Payment_Methods\UPE_Payment_Gateway;

/**
 * Handles the creation of intents in order to display fields on the checkout page.
 * At this point typically there is no order, and even if there was one, it might change.
 */
class Redirect_UPE_Payment_Step extends Abstract_Step {
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
	 * Collects all needed dependencies.
	 */
	public function __construct() {
		// @todo: Replace this with proper dependencies.
		$this->payments_api_client = WC_Payments::get_payments_api_client();
		$this->gateway             = WC_Payments::get_gateway();
	}

	/**
	 * Returns the ID of the step.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'redirect-upe-payment';
	}
	/**
	 * Checks if the step is applicable to the given payment process.
	 *
	 * @param Payment $payment The process.
	 * @return bool
	 */
	public function is_applicable( Payment $payment ) {
		if ( ! $payment instanceof Order_Payment ) {
			return false;
		}

		// This step only matters during UPE redirects.
		if ( ! $payment->is_flow( Payment::UPE_PROCESS_REDIRECT_FLOW ) ) {
			return false;
		}

		// The order needs to be awaiting payment for this step to work.
		return ! $payment->get_order()->has_status(
			[
				Order_Status::PROCESSING,
				Order_Status::COMPLETED,
				Order_Status::ON_HOLD,
			]
		);
	}

	/**
	 * Performs all actions, related to the creation of an intent.
	 *
	 * @param Payment $payment The payment, which we're working with.
	 * @throws Process_Payment_Exception In case the itnent or PM could not be loaded, or error in the intent.
	 */
	public function action( Payment $payment ) {
		if ( ! $payment instanceof Order_Payment ) {
			return; // keep IDEs happy.
		}

		// Load the intent and check for errors.
		// The process can only continue if the intent exists, and was successful.
		$intent = $this->get_intent_object( $payment );
		$this->check_intent_for_errors( $intent );
		$payment->set_intent( $intent );

		// The process cannot be completed without a selected payment method either.
		$payment_method_type = $this->gateway->get_selected_payment_method( $intent->get_payment_method_type() );
		if ( ! $payment_method_type ) {
			throw new Process_Payment_Exception(
				__( 'Could not find an attached payment method.', 'woocommerce-payments' ),
				'upe_payment_intent_missing'
			);
		}

		// Make sure that if the payment method is not reusable, there are no attempts to save it in another step.
		if ( $payment->is( Payment::SAVE_PAYMENT_METHOD_TO_STORE ) && ! $payment_method_type->is_reusable() ) {
			$payment->unset_flag( Payment::SAVE_PAYMENT_METHOD_TO_STORE );
		}

		// Check for required actions (redirect to another service or 3DS modal).
		if ( Payment_Intent_Status::REQUIRES_ACTION === $intent->get_status() ) {
			$result = $this->redirect_if_action_is_required( $payment, $intent );
			$payment->complete( $result );
			return;
		}

		$payment->complete( [ 'result' => 'success' ] );
	}

	/**
	 * Loads a payment or setup intent for the payment.
	 *
	 * @param Order_Payment $payment The payment object.
	 * @return WC_Payments_API_Intention|WC_Payments_API_Setup_Intention
	 * @throws Process_Payment_Exception In case the intent could not be loaded.
	 */
	protected function get_intent_object( Order_Payment $payment ) {
		$order = $payment->get_order();

		// Get payment or setup intent to check the status of the payment.
		if ( 0 < $order->get_total() ) { // It's a payment only for positive amounts.
			$request = Get_Intention::create( $payment->get_intent_id() );
			$intent  = $request->send( 'wcpay_get_intent_request', $order );
		} else {
			$intent = $this->payments_api_client->get_setup_intent( $payment->get_intent_id() );
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
