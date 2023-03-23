<?php
/**
 * Class Redirect_UPE_Payment_Step
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment_Process\Step;

use WCPay\Constants\Order_Status;
use WCPay\Logger;
use WCPay\Payment_Process\Order_Payment;
use WCPay\Payment_Process\Payment;

/**
 * Handles the creation of intents in order to display fields on the checkout page.
 * At this point typically there is no order, and even if there was one, it might change.
 */
class Redirect_UPE_Payment_Step extends Abstract_Step {
	/**
	 * Returns the ID of the step.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'redirect-upe-payment';
	}

	/**
	 * Loads all needed dependencies.
	 */
	public function __construct() {
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

		try {
			Logger::log( "Begin processing UPE redirect payment for order {$order->get_id()} for the amount of {$order->get_total()}" );

			// Get user/customer for order.
			list( $user, $customer_id ) = $this->manage_customer_details_for_order( $order );

			// Get payment intent to confirm status.
			if ( 0 < $order->get_total() ) {
				$request = Get_Intention::create( $intent_id );

				$intent                 = $request->send( 'wcpay_get_intent_request', $order );
				$client_secret          = $intent->get_client_secret();
				$status                 = $intent->get_status();
				$charge                 = $intent->get_charge();
				$charge_id              = $charge ? $charge->get_id() : null;
				$currency               = $intent->get_currency();
				$payment_method_id      = $intent->get_payment_method_id();
				$payment_method_details = $charge ? $charge->get_payment_method_details() : [];
				$payment_method_type    = $payment_method_details ? $payment_method_details['type'] : null;
				$error                  = $intent->get_last_payment_error();
			} else {
				$intent                 = $this->payments_api_client->get_setup_intent( $intent_id );
				$client_secret          = $intent['client_secret'];
				$status                 = $intent['status'];
				$charge_id              = '';
				$currency               = $order->get_currency();
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

			if ( $save_payment_method && $payment_method->is_reusable() ) {
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
				// I don't think this case should be possible, but just in case...
				$next_action = $intent->get_next_action();
				if ( isset( $next_action['type'] ) && 'redirect_to_url' === $next_action['type'] && ! empty( $next_action['redirect_to_url']['url'] ) ) {
					wp_safe_redirect( $next_action['redirect_to_url']['url'] );
					exit;
				} else {
					$redirect_url = sprintf(
						'#wcpay-confirm-%s:%s:%s:%s',
						0 < $order->get_total() ? 'pi' : 'si',
						$order_id,
						WC_Payments_Utils::encrypt_client_secret( $this->account->get_stripe_account_id(), $client_secret ),
						wp_create_nonce( 'wcpay_update_order_status_nonce' )
					);
					wp_safe_redirect( $redirect_url );
					exit;
				}
			}
		} catch ( Exception $e ) {
			Logger::log( 'Error: ' . $e->getMessage() );

			// Confirm our needed variables are set before using them due to there could be a server issue during the get_intent process.
			$status    = $status ?? null;
			$charge_id = $charge_id ?? null;

			/* translators: localized exception message */
			$message = sprintf( __( 'UPE payment failed: %s', 'woocommerce-payments' ), $e->getMessage() );
			$this->order_service->mark_payment_failed( $order, $intent_id, $status, $charge_id, $message );

			self::remove_upe_payment_intent_from_session();

			wc_add_notice( WC_Payments_Utils::get_filtered_error_message( $e ), 'error' );
			wp_safe_redirect( wc_get_checkout_url() );
			exit;
		}
	}
}
