<?php
/**
 * Class Payment_Service
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment;

use Exception;
use WC_Order;
use WC_Payment_Token;
use WC_Payments;
use WCPay\Exceptions\Process_Payment_Exception;
use WCPay\Payment\Payment_Method\{ New_Payment_Method, Payment_Method_Factory, Saved_Payment_Method };
use WCPay\Payment\State\{ Authentication_Required_State, Completed_State, Completed_Without_Payment_State, Failed_Preparation_State, Intent_Without_Order_State, Processed_State, Processing_Failed_State };
use WCPay\Payment\Strategy\{ Load_WooPay_Intent_Strategy, Setup_Payment_Strategy, Standard_Payment_Strategy, Subscription_Renewal_Strategy, UPE_Update_Intent_Strategy };
use WCPay\Platform_Checkout\Platform_Checkout_Utilities;

/**
 * Orchestrates the standard payment process.
 */
class Payment_Service {
	/**
	 * Holds the payment loader.
	 *
	 * @var Loader
	 */
	protected $loader;

	/**
	 * A helper for $_POST-related loaders.
	 *
	 * @var Post_Request
	 */
	protected $request;

	/**
	 * Holds the gateway.
	 *
	 * This is just used for subscriptions right now, and should
	 * be changed when the trait gets isolated.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	protected $gateway;

	/**
	 * Platform checkout utilities.
	 *
	 * @var Platform_Checkout_Utilities
	 */
	protected $platform_checkout_util;

	/**
	 * Payment method factory.
	 *
	 * @var Payment_Method_Factory
	 */
	protected $payment_method_factory;

	/**
	 * Constructs the service.
	 */
	public function __construct() {
		$this->loader                 = new Loader();
		$this->request                = new Post_Request();
		$this->platform_checkout_util = new Platform_Checkout_Utilities();
		$this->gateway                = WC_Payments::get_gateway();
		$this->payment_method_factory = new Payment_Method_Factory();
	}

	/**
	 * Processes a standard payment.
	 *
	 * @param WC_Order $order The order whose payment to process.
	 * @param bool     $manual_capture Whether the payment should be manually captured.
	 */
	public function process_standard_payment( WC_Order $order, $manual_capture = false ) {
		// @todo: What do we do if there is a payment already?
		$payment = $this->loader->create_payment( $order );

		$payment_method = $this->payment_method_factory->from_request();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( $payment_method instanceof New_Payment_Method && New_Payment_Method::should_be_saved( $_POST ) ) {
			$payment->set_flag( Flags::SAVE_PAYMENT_METHOD_TO_STORE );
		}
		if ( $manual_capture ) {
			$payment->set_flag( Flags::MANUAL_CAPTURE );
		}
		if ( $this->platform_checkout_util->should_save_platform_customer() ) {
			do_action( 'woocommerce_payments_save_user_in_platform_checkout' );
			$payment->set_flag( Flags::SAVE_PAYMENT_METHOD_TO_PLATFORM );
		}
		$this->maybe_prepare_subscription_payment( $payment, $order );

		// Prepare all needed data in advance.
		$payment->prepare();
		if ( $payment->get_state() instanceof Failed_Preparation_State ) {
			return $payment->get_response();
		}

		// Verify that we can proceed with the payment process.
		$payment->verify( $payment_method, $this->request->get_fraud_prevention_token() );
		if ( $payment->get_state() instanceof Completed_State || $payment->get_state() instanceof Completed_Without_Payment_State ) {
			return $payment->get_response();
		}

		// Unless the payment is already processed, do it.
		if ( ! $payment->get_state() instanceof Processed_State ) {
			$platform_checkout_intent_id = $this->request->get_woopay_intent_id();

			// Determing whether to use the WooPay flow, or not.
			if ( ! empty( $platform_checkout_intent_id ) ) {
				$payment->process( new Load_WooPay_Intent_Strategy( $platform_checkout_intent_id ) );
			} elseif ( $order->get_total() < 1 ) {
				$payment->process( new Setup_Payment_Strategy() );
			} else {
				$payment->process( new Standard_Payment_Strategy( $this->request->get_fingerprint() ) );
			}
		}

		// If processing failed, or authentication is required, we should not proceed.
		if ( $payment->get_state() instanceof Processing_Failed_State || $payment->get_state() instanceof Authentication_Required_State ) {
			return $payment->get_response();
		}

		// Complete the payment, and return the response.
		$payment->complete();
		return $payment->get_response();
	}

	/**
	 * Processes payments during the redirect after authentication.
	 *
	 * @throws Exception In case the payment cannot be loaded/processed.
	 * @return Payment A payment object.
	 */
	public function process_standard_payment_after_authenticaton() {
		$is_nonce_valid = check_ajax_referer( 'wcpay_update_order_status_nonce', false, false );
		if ( ! $is_nonce_valid ) {
			throw new Process_Payment_Exception(
				__( "We're not able to process this payment. Please refresh the page and try again.", 'woocommerce-payments' ),
				'invalid_referrer'
			);
		}

		$order_id = $this->request->get_order_id();
		$order    = wc_get_order( $order_id );
		if ( ! $order ) {
			throw new Process_Payment_Exception(
				__( "We're not able to process this payment. Please try again later.", 'woocommerce-payments' ),
				'order_not_found'
			);
		}

		$loader  = new Loader();
		$payment = $loader->load_payment( $order );

		if ( ! $payment->get_state() instanceof Authentication_Required_State ) {
			throw new Exception( 'The payment is not currently awaiting authentication!' );
		}

		$intent_id_received = $this->request->get_redirect_intent_id();
		if ( is_null( $intent_id_received ) ) {
			/* translators: This will be used to indicate an unknown value for an ID. */
			$intent_id_received = __( 'unknown', 'woocommerce-payments' );
		}

		// @todo: This is a weird way to do it.
		// The parameter was used to transfer the PM to store. The PM is in the intent, and will be used in `Save_Payment_Method_Step`.
		if ( $this->request->get_payment_method_id() ) {
			$payment->set_flag( Flags::SAVE_PAYMENT_METHOD_TO_STORE );
		}

		// This should yield the next state.
		$payment->load_intent_after_authentication( $intent_id_received );
		$payment->complete();
		$payment->save_to_order();

		return $payment;
	}

	/**
	 * Creates payment intent using current cart or order and store details.
	 *
	 * @param array    $displayed_payment_methods Array of enabled payment methods to display in payment element.
	 * @param int|null $order_id The id of the order if intent created from Order.
	 * @param string   $fingerprint User fingerprint.
	 * @param bool     $manual_capture Whether the payment should be manually captured.
	 *
	 * @return array
	 */
	public function create_upe_payment_intent( $displayed_payment_methods, $order_id = null, $fingerprint = '', $manual_capture = false ) {
		$loader = new Loader();

		// Load and setup the payment.
		if ( $order_id ) {
			$order   = wc_get_order( $order_id );
			$payment = $loader->load_or_create_payment( $order );
		} else {
			$payment = $loader->create_payment();
		}

		if ( $manual_capture ) {
			$payment->set_flag( Flags::MANUAL_CAPTURE );
		}

		// This state comes before the standard Initial_State.
		$payment->switch_state( new Intent_Without_Order_State( $payment ) );

		// Load the intent.
		$response = $payment->get_or_create_intent( array_values( $displayed_payment_methods ), $fingerprint );

		// Save the prepared payment before trying to process it.
		if ( $order_id ) {
			$payment->save_to_order( $order );
		} else {
			$payment->save_without_order();
		}

		// Let the payment be processed. The process should yield the response array.
		$response['payment_id'] = $payment->get_id(); // Used to store the payment in session.
		return $response;
	}

	/**
	 * Process an UPE payment when the checkout form is submitted.
	 *
	 * @param int    $order_id     ID of the order.
	 * @param string $session_data Data from session (Optional).
	 */
	public function process_upe_payment( int $order_id, string $session_data = null ) {
		$order  = wc_get_order( $order_id );
		$loader = new Loader();

		$existing_id = $this->get_payment_from_session( $order, $session_data );
		if ( $existing_id ) {
			$payment = $loader->load_payment_by_id( $existing_id );
			$payment->set_order( $order );
			$payment->save_to_order( $order );
		} else {
			$payment = $loader->load_or_create_payment( $order );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		// Start with the payment method. If it is a saved one, we fall-back to standard payment.
		$payment_method = ( new Payment_Method_Factory() )->from_request( null, true );
		if ( $payment_method instanceof Saved_Payment_Method ) {
			return $this->process_standard_payment( $order );
		}

		$intent_id              = $this->request->get_checkout_intent_id();
		$selected_payment_type  = $this->request->get_selected_payment_type();
		$payment_country        = $this->request->get_payment_country();
		$fraud_prevention_token = $this->request->get_fraud_prevention_token();
		$save_payment_method    = New_Payment_Method::should_be_saved( $_POST );

		// phpcs:enable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		// Setup the payment object.
		if ( $save_payment_method ) {
			$payment->set_flag( Flags::SAVE_PAYMENT_METHOD_TO_STORE );
		}

		// This will transition to the initial state.
		$payment->update_intent_with_order();

		// Prepare all data now that the order is available.
		$payment->prepare();
		if ( $payment->get_state() instanceof Failed_Preparation_State ) {
			return $payment->get_response();
		}

		// Verify that we can proceed with the payment process.
		$payment->verify( $payment_method, $fraud_prevention_token );
		// No payment needed.
		if (
			$payment->get_state() instanceof Completed_State
			|| $payment->get_state() instanceof Completed_Without_Payment_State
		) {
			return $payment->get_response();
		}
		// Another intent was loaded.
		if ( $payment->get_state() instanceof Processed_State ) {
			$payment->complete();
			return $payment->get_response();
		}

		// Continue as usual.
		$strategy = new UPE_Update_Intent_Strategy( $intent_id, $selected_payment_type, $payment_country );
		$payment->process( $strategy );

		// Either a failure state (not able to update), or awaiting UPE confirmation.
		return $payment->get_response();
	}

	/**
	 * Processes redirect payments.
	 *
	 * @param int|string $order_id The order ID being processed.
	 * @param string     $intent_id The Stripe setup/payment intent ID for the order payment.
	 * @param bool       $save_payment_method Boolean representing whether payment method for order should be saved.
	 *
	 * @throws Process_Payment_Exception When the payment intent has an error.
	 */
	public function process_redirect_payment( $order_id, $intent_id, $save_payment_method ) {
		$loader = new Loader();

		// Load the order and the payment.
		$order   = wc_get_order( $order_id );
		$payment = $loader->load_payment( $order );

		if ( $save_payment_method ) {
			$payment->set_flag( Flags::SAVE_PAYMENT_METHOD_TO_STORE );
		}

		// restore the remove-upe-payment-intent-from-session step.

		// Load the intent. This should advance to the processed or processing failed state.
		$payment->load_intent_after_confirmation( $intent_id );
		$payment->complete();
		return $payment->get_response();
	}

	/**
	 * Processes a subscription renewal payment.
	 *
	 * @param WC_Order         $renewal_order The renwal order.
	 * @param WC_Payment_Token $token         A saved payment token (core WC format).
	 * @return array                          Response from the payment process.
	 *
	 */
	public function process_renewal_payment( WC_Order $renewal_order, WC_Payment_Token $token ) {
		$payment = $this->loader->create_payment( $renewal_order );
		$payment->set_flag( Flags::RECURRING );
		$payment->set_flag( Flags::MERCHANT_INITIATED );

		// Prepare all needed data in advance.
		$payment->prepare();
		if ( $payment->get_state() instanceof Failed_Preparation_State ) {
			return $payment->get_response();
		}

		// Verify that we can proceed with the payment process.
		$payment->verify( new Saved_Payment_Method( $token ), '' );

		// Unless the payment is already processed, do it.
		$payment->process( new Subscription_Renewal_Strategy( '' ) );

		// If processing failed, or authentication is required, we should not proceed.
		if ( $payment->get_state() instanceof Processing_Failed_State || $payment->get_state() instanceof Authentication_Required_State ) {
			return $payment->get_response();
		}

		// Complete the payment, and return the response.
		$payment->complete();
		return $payment->get_response();
	}

	/**
	 * Retrieves the ID of an existing intent from session.
	 *
	 * @param string $session_data The data, which was stored in session.
	 * @return string|null
	 */
	protected function get_payment_from_session( string $session_data ) {
		if ( ! $session_data ) {
			return null;
		}

		$session_data = explode( '-', $session_data );
		if ( 4 > count( $session_data ) ) {
			return null;
		}

		$payment_id = $session_data['3'];
		if ( empty( $payment_id ) ) {
			return null;
		}

		return $payment_id;
	}

	/**
	 * Prepares details for subscriptions.
	 *
	 * @param Payment  $payment Payment that's being set up.
	 * @param WC_Order $order   The order.
	 */
	protected function maybe_prepare_subscription_payment( $payment, $order ) {
		if ( ! $this->gateway->is_payment_recurring( $order ) ) {
			return;
		}

		// Subs-specific behavior starts here.
		$payment->set_flag( Flags::RECURRING );

		// The payment method is always saved for subscriptions, unless already saved.
		if ( $payment->get_payment_method() instanceof New_Payment_Method ) {
			$payment->set_flag( Flags::SAVE_PAYMENT_METHOD_TO_STORE );
		}

		if ( $this->gateway->is_changing_payment_method_for_subscription() ) {
			$payment->set_flag( Flags::CHANGING_SUBSCRIPTION_PAYMENT_METHOD );
		}
	}
}
