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
use WCPay\Logger;
use WCPay\Payment_Process\Order_Payment;
use WCPay\Payment_Process\Payment;
use WC_Payments_API_Client;
use WC_Payments_Account;

/**
 * Handles the creation of intents in order to display fields on the checkout page.
 * At this point typically there is no order, and even if there was one, it might change.
 */
class Redirect_UPE_Payment_Step extends Abstract_Step {
	use Redirect_if_Action_is_Required;

	/**
	 * WC_Payments_Account instance to get information about the account.
	 *
	 * @var WC_Payment_Account
	 */
	protected $account;

	/**
	 * The client for connection with the server.
	 * Should be replaced with request classes soon.
	 *
	 * @var WC_Payments_API_Client
	 */
	protected $payments_api_client;

	/**
	 * Returns the ID of the step.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'redirect-upe-payment';
	}

	/**
	 * Instantiates the step.
	 */
	public function __construct() {
		// @todo: Replace this with proper dependencies.
		$this->account             = WC_Payments::get_account_service();
		$this->payments_api_client = WC_Payments::get_payments_api_client();
	}
	/**
	 * Checks if the step is applicable to the given payment process.
	 *
	 * @param Payment $payment The process.
	 * @return bool
	 */
	public function is_applicable( Payment $payment ) {
		if ( ! $payment instanceof Order_Payment ) {
			return;
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
	 */
	public function action( Payment $payment ) {
		if ( ! $payment instanceof Order_Payment ) {
			return; // keep IDEs happy.
		}

		$order = $payment->get_order();

		Logger::log( "Begin processing UPE redirect payment for order {$order->get_id()} for the amount of {$order->get_total()}" );

		// Get payment intent to confirm status.
		if ( 0 < $order->get_total() ) {
			$request = Get_Intention::create( $payment->get_var( 'intent_id' ) );

			$intent                 = $request->send( 'wcpay_get_intent_request', $order );
			$status                 = $intent->get_status();
			$charge                 = $intent->get_charge();
			$payment_method_id      = $intent->get_payment_method_id();
			$payment_method_details = $charge ? $charge->get_payment_method_details() : [];
			$payment_method_type    = $payment_method_details ? $payment_method_details['type'] : null;
			$error                  = $intent->get_last_payment_error();
		} else {
			$intent                 = $this->payments_api_client->get_setup_intent( $payment->get_var( 'intent_id' ) );
			$status                 = $intent['status'];
			$payment_method_id      = $intent['payment_method'];
			$payment_method_details = false;
			$payment_method_options = array_keys( $intent['payment_method_options'] );
			$payment_method_type    = $payment_method_options ? $payment_method_options[0] : null;
			$error                  = $intent['last_setup_error'];
		}

		if ( ! empty( $error ) ) {
			Logger::log( 'Error when processing payment: ' . $error['message'] );
			throw new Process_Payment_Exception(
				__( "We're not able to process this payment. Please try again later.", 'woocommerce-payments' ),
				'upe_payment_intent_error'
			);
		}

		$payment_method = $this->get_selected_payment_method( $payment_method_type );
		if ( ! $payment_method ) {
			return;
		}

		if ( $payment->is( Payment::SAVE_PAYMENT_METHOD_TO_STORE ) && $payment_method->is_reusable() ) {
			try {
				$token = $payment_method->get_payment_token_for_user( get_user_by( 'id', $payment->get_var( 'user_id' ) ), $payment_method_id );
				$this->add_token_to_order( $order, $token );
			} catch ( Exception $e ) {
				// If saving the token fails, log the error message but catch the error to avoid crashing the checkout flow.
				Logger::log( 'Error when saving payment method: ' . $e->getMessage() );
			}
		}

		// update-order-details
		// remove-upe-payment-intent-from-session

		if ( Payment_Intent_Status::REQUIRES_ACTION === $status ) {
			$result = $this->redirect_if_action_is_required( $payment, $intent );
			$payment->complete( $result );
			return;
		}

		// @todo: This needs some sort of a formatted response.
		$payment->complete( [] );
	}
}
