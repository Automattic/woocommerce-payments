<?php
/**
 * Class Processed_State
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment\State;

use WC_Order;
use WC_Payment_Gateway_WCPay;
use WC_Payments_Order_Service;
use WC_Payment_Tokens;
use WC_Payments_Token_Service;
use WC_Payments;
use WC_Payments_Action_Scheduler_Service;
use WC_Payments_API_Intention;
use WC_Payments_Subscriptions_Utilities;
use WCPay\Core\Mode;
use WCPay\Payment\Duplicate_Payment_Prevention_Service;
use WCPay\Payment\Flags;
use WCPay\Payment\Payment;
use WCPay\Payment_Methods\UPE_Payment_Gateway;
use WCPay\Payment_Methods\UPE_Split_Payment_Gateway;
use WCPay\Payment\Payment_Method\Saved_Payment_Method;

/**
 * Represents a payment in the complete state, which requires no further processing.
 */
final class Processed_State extends Payment_State {
	use WC_Payments_Subscriptions_Utilities;

	/**
	 * Action scheduler service.
	 *
	 * @var WC_Payments_Action_Scheduler_Service
	 */
	protected $action_scheduler_service;

	/**
	 * The mode object.
	 *
	 * @var Mode;
	 */
	protected $mode;

	/**
	 * The WCpay token service.
	 *
	 * @var WC_Payments_Token_Service
	 */
	protected $token_service;

	/**
	 * The order service.
	 *
	 * @var WC_Payments_Order_Service
	 */
	protected $order_service;

	/**
	 * Gateway object.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	protected $gateway;

	/**
	 * Instantiates the state.
	 *
	 * @param Payment $payment The context of the state.
	 */
	public function __construct( Payment $payment ) {
		$this->context = $payment;

		// @todo Replace with DI.
		$this->gateway                  = WC_Payments::get_gateway();
		$this->action_scheduler_service = WC_Payments::get_action_scheduler_service();
		$this->mode                     = WC_Payments::mode();
		$this->token_service            = WC_Payments::get_token_service();
		$this->order_service            = WC_Payments::get_order_service();
	}

	/**
	 * Completes the payment.
	 */
	public function complete() {
		if ( $this->context->get_payment_method() instanceof Saved_Payment_Method ) {
			$this->update_saved_payment_method();
		} elseif ( $this->context->is( Flags::SAVE_PAYMENT_METHOD_TO_STORE ) ) {
			$this->save_payment_method();
		}

		$payment_method = $this->context->get_payment_method();
		if ( $payment_method instanceof Saved_Payment_Method ) {
			$this->add_token_to_order( $payment_method );
		}

		// Regardless.
		$this->update_order();

		$this->cleanup();

		$this->context->switch_state( new Completed_State( $this->context ) );
	}

	/**
	 * Updates the saved PM, which was used for the payment.
	 */
	public function update_saved_payment_method() {
		$this->action_scheduler_service->schedule_job(
			time(),
			WC_Payment_Gateway_WCPay::UPDATE_SAVED_PAYMENT_METHOD,
			[
				'payment_method' => $this->context->get_payment_method()->get_id(),
				'order_id'       => $this->context->get_order()->get_id(),
				'is_test_mode'   => $this->mode->is_test(),
			]
		);
	}

	/**
	 * Saves the payment method if needed (subs, etc.) or chosen (checkbox).
	 */
	public function save_payment_method() {
		$intent = $this->context->get_intent();
		// @todo: This should support SetupIntents as well.
		$user = $this->get_user_from_payment( $this->context );

		// Setup intents are currently not deserialized as payment intents are, so check if it's an array first.
		$payment_method_id = is_array( $intent ) ? $intent['payment_method'] : $intent->get_payment_method_id();

		// Create a new token or load an existing one.
		$wc_token = $this->maybe_load_woopay_subscription_token( $payment_method_id );
		if ( is_null( $wc_token ) ) {
			$wc_token = $this->token_service->add_payment_method_to_user( $payment_method_id, $user );
		}

		// Use the new token for the rest of the payment process.
		$payment_method = new Saved_Payment_Method( $wc_token );
		$this->context->set_payment_method( $payment_method );
	}

	/**
	 * Retrieves the user object for a payment.
	 *
	 * @return WP_User
	 */
	protected function get_user_from_payment() {
		return get_user_by( 'id', $this->context->get_user_id() );
	}

	/**
	 * Checks if an order comes from WooPay and is using a PM, which is already saved.
	 *
	 * @param string $payment_method_id The PM ID, coming from the intent.
	 * @return WC_Payment_Token|null
	 */
	protected function maybe_load_woopay_subscription_token( string $payment_method_id ) {
		$order = $this->context->get_order();

		// Handle orders that are paid via WooPay and contain subscriptions.
		if ( $order->get_meta( 'is_woopay' ) && function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( $order ) ) {
			$customer_tokens = WC_Payment_Tokens::get_customer_tokens( $order->get_user_id(), WC_Payment_Gateway_WCPay::GATEWAY_ID );

			// Use the existing token if we already have one for the incoming payment method.
			foreach ( $customer_tokens as $saved_token ) {
				if ( $saved_token->get_token() === $payment_method_id ) {
					return $saved_token;
				}
			}
		}

		return null;
	}

	/**
	 * Adds the saved payment method (token) to the order.
	 *
	 * @param Saved_Payment_Method $payment_method The payment method.
	 */
	public function add_token_to_order( Saved_Payment_Method $payment_method ) {
		// We need to make sure the saved payment method is saved to the order so we can
		// charge the payment method for a future payment.
		$payment_token = $payment_method->get_token();
		$order         = $this->context->get_order();

		// Load the existing token, if any.
		$order_token = $this->get_order_token( $order );

		// This could lead to tokens being saved twice in an order's payment tokens, but it is needed so that shoppers
		// may re-use a previous card for the same subscription, as we consider the last token to be the active one.
		// We can't remove the previous entry for the token because WC_Order does not support removal of tokens [1] and
		// we can't delete the token as it might be used somewhere else.
		// [1] https://github.com/woocommerce/woocommerce/issues/11857.
		if ( is_null( $order_token ) || $payment_token->get_id() !== $order_token->get_id() ) {
			$order->add_payment_token( $payment_token );
		}

		if ( $this->is_subscriptions_enabled() ) {
			$subscriptions = wcs_get_subscriptions_for_order( $order->get_id() );
			if ( is_array( $subscriptions ) ) {
				foreach ( $subscriptions as $subscription ) {
					$subscription_token = $this->get_order_token( $subscription );
					if ( is_null( $subscription_token ) || $payment_token->get_id() !== $subscription_token->get_id() ) {
						$subscription->add_payment_token( $payment_token );
					}
				}
			}
		}
	}

	/**
	 * Retrieves the payment token, associated with an order.
	 *
	 * @param WC_Order $order Order, which might have a token.
	 * @return WC_Payment_Token|null
	 */
	protected function get_order_token( WC_Order $order ) {
		$tokens   = $order->get_payment_tokens();
		$token_id = end( $tokens );
		$token    = $token_id ? null : WC_Payment_Tokens::get( $token_id );

		return $token;
	}

	/**
	 * Performs a cleanup, once the payment has been completely processed.
	 */
	public function cleanup() {
		UPE_Payment_Gateway::remove_upe_payment_intent_from_session();
		UPE_Split_Payment_Gateway::remove_upe_payment_intent_from_session();
	}

	/**
	 * Updates the order.
	 */
	public function update_order() {
		$order = $this->context->get_order();

		$this->order_service->set_payment_method_id_for_order( $order, $this->context->get_payment_method()->get_id() );
		$this->order_service->set_customer_id_for_order( $order, $this->context->get_customer_id() );

		// @todo: Store the test/live mode in the payment object.
		$this->order_service->set_mode_for_order( $order, $this->mode->is_test() ? 'test' : 'prod' );

		// From the update order step.
		$intent = $this->context->get_intent();

		// Prepare details from the intent.
		$intent_id  = $intent->get_id();
		$status     = $intent->get_status();
		$charge     = $intent->get_charge();
		$charge_id  = $charge ? $charge->get_id() : null;
		$currency   = $intent->get_currency();
		$processing = $intent->get_processing();

		// Associate the saved payment method to the order.
		$payment_method = $this->context->get_payment_method();

		// Attach the intent, exchange info, update the status, and add a notification note.
		$this->order_service->attach_intent_info_to_order( $order, $intent_id, $status, $payment_method ? $payment_method->get_id() : null, $this->context->get_customer_id(), $charge_id, $currency );
		$this->gateway->attach_exchange_info_to_order( $order, $charge_id );
		// @todo: For some payments (ex. update status after 3DS), `REQUIRES_PAYMENT_METHOD` should fail the order, instead of marking it as started.
		$this->gateway->update_order_status_from_intent( $order, $intent_id, $status, $charge_id );
		$this->gateway->maybe_add_customer_notification_note( $order, $processing );

		wc_reduce_stock_levels( $this->context->get_order()->get_id() );

		// For standard (non-merchant-initiated) payments, clear the cart as well.
		if ( ! $this->context->is( Flags::MERCHANT_INITIATED ) ) {
			$cart = $this->get_wc_cart();
			if ( isset( $cart ) ) {
				$cart->empty_cart();
			}
		}

		$this->set_payment_method_title_for_order( $intent );
	}

	/**
	 * Changes the name of the payment method on various screens, incl. order received page.
	 *
	 * @param WC_Payments_API_Intention $intent The intent, which was used.
	 */
	protected function set_payment_method_title_for_order( WC_Payments_API_Intention $intent ) {
		$order = $this->context->get_order();

		if ( $order->get_total() > 0 ) {
			$charge                 = $intent ? $intent->get_charge() : null;
			$payment_method_details = $charge ? $charge->get_payment_method_details() : [];
			$payment_method_type    = $payment_method_details ? $payment_method_details['type'] : null;

			// @todo: There is additional WooPay logic here, check the gateway.
		} else {
			$payment_method_details = false;
			$payment_method_options = isset( $intent['payment_method_options'] ) ? array_keys( $intent['payment_method_options'] ) : null;
			$payment_method_type    = $payment_method_options ? $payment_method_options[0] : null;
		}

		// @todo: Instead of checking $_POST directly, use a flag in the payment.
		if ( empty( $_POST['payment_request_type'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$this->gateway->set_payment_method_title_for_order(
				$order,
				$payment_method_type,
				$payment_method_details
			);
		}
	}

	/**
	 * Retrieves the WC cart object. Separate method for unit tests.
	 *
	 * @return WC_Cart
	 */
	protected function get_wc_cart() {
		return WC()->cart;
	}
}
