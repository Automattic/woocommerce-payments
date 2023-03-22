<?php
/**
 * Class Save_Payment_Method_Step
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment_Process\Step;

use WCPay\Payment_Process\Order_Payment;
use WCPay\Payment_Process\Payment;
use WC_Payments_API_Intention;
use WC_Payments;
use WCPay\Payment_Process\Payment_Method\Saved_Payment_Method;

/**
 * Saves the payment method as token after a successful intent.
 */
class Save_Payment_Method_Step extends Abstract_Step {
	/**
	 * The WCpay token service.
	 *
	 * @var WC_Payments_Token_Service
	 */
	protected $token_service;

	/**
	 * Loads all required dependencies.
	 */
	public function __construct() {
		$this->token_service = WC_Payments::get_token_service();
	}

	/**
	 * Returns the ID of the step.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'save-payment-method';
	}

	/**
	 * Checks if the step is applicable.
	 *
	 * @param Payment $payment A payment, which is being processed.
	 * @return bool
	 */
	public function is_applicable( Payment $payment ) {
		// This is only applicable for order payments, at least for now.
		if ( ! $payment instanceof Order_Payment ) {
			return false;
		}

		// Bail there is no requirement to save the PM.
		if ( ! $payment->is( Payment::SAVE_PAYMENT_METHOD_TO_STORE ) ) {
			return false;
		}

		// Failing payment methods should not be saved.
		$intent = $this->get_intent_from_payment( $payment );
		if ( ! $intent->is_successful() ) {
			return false;
		}

		return true;
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
		return $payment->get_var( 'intent' );
	}

	/**
	 * Retrieves the user object for a payment.
	 *
	 * @param Order_Payment $payment Payment object.
	 * @return WP_User
	 */
	protected function get_user_from_payment( Order_Payment $payment ) {
		return get_user_by( 'id', $payment->get_var( 'customer_id' ) );
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

		// @todo: This should support SetupIntents as well.
		$intent = $this->get_intent_from_payment( $payment );
		$user   = $this->get_user_from_payment( $payment );

		// Setup intents are currently not deserialized as payment intents are, so check if it's an array first.
		$payment_method_id = is_array( $intent ) ? $intent['payment_method'] : $intent->get_payment_method_id();

		// Create a new token.
		$wc_token = $this->token_service->add_payment_method_to_user( $payment_method_id, $user );

		// Use the new token for the rest of the payment process.
		$payment_method = new Saved_Payment_Method( $wc_token );
		$payment->set_payment_method( $payment_method );
	}
}
