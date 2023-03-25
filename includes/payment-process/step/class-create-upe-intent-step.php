<?php
/**
 * Class Create_UPE_Intent_Step
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment_Process\Step;

use WC_Payments;
use WC_Payments_Customer_Service;
use WC_Payments_Utils;
use WCPay\Core\Exceptions\Amount_Too_Small_Exception;
use WCPay\Core\Server\Request\Create_Intention;
use WCPay\Core\Server\Request\Create_Setup_Intention;
use WCPay\Payment_Process\Order_Payment;
use WCPay\Payment_Process\Payment;

/**
 * Handles the creation of intents in order to display fields on the checkout page.
 * At this point typically there is no order, and even if there was one, it might change.
 */
class Create_UPE_Intent_Step extends Abstract_Step {
	/**
	 * Holds the WCPay customer service.
	 *
	 * @var WC_Payments_Customer_Service
	 */
	protected $customer_service;

	/**
	 * Returns the ID of the step.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'create-upe-intent';
	}

	/**
	 * Loads all needed dependencies.
	 */
	public function __construct() {
		$this->customer_service = WC_Payments::get_customer_service();
	}

	/**
	 * Performs all actions, related to the creation of an intent.
	 *
	 * @param Payment $payment The payment, which we're working with.
	 */
	public function action( Payment $payment ) {
		$metadata = $payment->get_var( 'metadata' ) ?? [];

		if ( $payment instanceof Order_Payment ) {
			$order = $payment->get_order();

			$amount                   = $order->get_total();
			$currency                 = $order->get_currency();
			$metadata['order_number'] = $order->get_order_number();
		} else {
			// Use the cart as a base for creating intents without an order.
			$amount   = WC()->cart->get_total( '' );
			$currency = get_woocommerce_currency();
		}

		// The metadata should be set/updated. @todo: This might be possible through the metadata step.
		$payment->set_var( 'metadata', $metadata );

		$converted_amount = WC_Payments_Utils::prepare_amount( $amount, $currency );
		$minimum_amount   = WC_Payments_Utils::get_cached_minimum_amount( $currency );

		if ( 1 > $converted_amount ) {
			// Zero-amount intents just require setup, not payment.
			$intent = $this->request_create_setup_intent_from_server( $payment );
		} else {
			// Use the minimum amount in order to create an intent and display fields.
			// The amount might change later. If insufficient at the end, there will still be an error.
			if ( ! is_null( $minimum_amount ) && $converted_amount < $minimum_amount ) {
				$converted_amount = $minimum_amount;
			}

			$intent = $this->request_create_payment_intent_from_server( $payment, $currency, $converted_amount );
		}

		// Store the intent within the payment object.
		$payment->set_var( 'intent', $intent );

		// ToDo: This should probably not be called `complete`, as nothing is really completed yet.
		$payment->complete(
			[
				'id'            => is_array( $intent ) ? $intent['id'] : $intent->get_id(),
				'client_secret' => is_array( $intent ) ? $intent['client_secret'] : $intent->get_client_secret(),
			]
		);
	}

	/**
	 * When a positive amount is present, tries to create a payment intent.
	 *
	 * @param Payment $payment           Payment object, for an order nor not.
	 * @param string  $currency          Currency for the payment.
	 * @param int     $converted_amount  A Stripe-formatted amount for the transaction.
	 * @return WC_Payments_API_Intention The created intention.
	 */
	protected function request_create_payment_intent_from_server( Payment $payment, string $currency, int $converted_amount ) {
		$order = $payment instanceof Order_Payment
			? $payment->get_order()
			: false;

		try {
			$request = Create_Intention::create();
			$request->set_amount( $converted_amount );
			$request->set_currency_code( strtolower( $currency ) );
			$request->set_payment_method_types( $payment->get_var( 'payment_method_types' ) );
			$request->set_metadata( $payment->get_var( 'metadata' ) );
			$request->set_capture_method( $payment->is( Payment::MANUAL_CAPTURE ) );
			$request->set_fingerprint( $payment->get_var( 'fingerprint' ) );
			$payment_intent = $request->send( 'wcpay_create_intent_request', $order );
		} catch ( Amount_Too_Small_Exception $e ) {
			$minimum_amount = $e->get_amount();

			WC_Payments_Utils::cache_minimum_amount( $e->get_currency(), $minimum_amount );

			/**
			 * Try to create a new payment intent with the minimum amount
			 * in order to display fields on the checkout page and allow
			 * customers to select a shipping method, which might make
			 * the total amount of the order higher than the minimum
			 * amount for the API.
			 */
			$request->set_amount( $minimum_amount );
			$payment_intent = $request->send( 'wcpay_create_intent_request', $order );
		}

		return $payment_intent;
	}

	/**
	 * Requests a setup intent from the server.
	 *
	 * @param Payment $payment The payment object, containing the necessary details.
	 * @return array           Setup intent object.
	 */
	protected function request_create_setup_intent_from_server( Payment $payment ) {
		// Determine the customer managing the payment methods, create one if we don't have one already.
		$user        = wp_get_current_user();
		$customer_id = $this->customer_service->get_customer_id_by_user_id( $user->ID );
		if ( null === $customer_id ) {
			$customer_data = WC_Payments_Customer_Service::map_customer_data( null, new \WC_Customer( $user->ID ) );
			$customer_id   = $this->customer_service->create_customer_for_user( $user, $customer_data );
		}

		$request = Create_Setup_Intention::create();
		$request->set_customer( $customer_id );
		$request->set_payment_method_types( $payment->get_var( 'payment_method_types' ) );
		$setup_intent = $request->send( 'wcpay_create_setup_intention_request' );

		// @todo: Add an actual setup intent object...
		return $setup_intent;
	}
}
