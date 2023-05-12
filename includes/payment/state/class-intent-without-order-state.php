<?php
/**
 * Class Intent_Without_Order_State
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment\State;

use WC_Payments;
use WC_Payments_Customer_Service;
use WC_Payments_Utils;
use WCPay\Core\Exceptions\Amount_Too_Small_Exception;
use WCPay\Core\Server\Request\Create_Intention;
use WCPay\Core\Server\Request\Create_Setup_Intention;
use WCPay\Payment\Flags;
use WCPay\Payment\Payment;

/**
 * This is an initial state, used by UPE to generate an intent without having an order yet.
 */
class Intent_Without_Order_State extends Payment_State {
	/**
	 * Holds the WCPay customer service.
	 *
	 * @var WC_Payments_Customer_Service
	 */
	protected $customer_service;

	/**
	 * Instantiates the state.
	 *
	 * @param Payment $payment The context of the state.
	 */
	public function __construct( Payment $payment ) {
		parent::__construct( $payment );

		$this->customer_service = WC_Payments::get_customer_service();
	}

	/**
	 * Either retrieves the existing intent, or creates a new one.
	 *
	 * @return array The data needed to display payment fields.
	 */
	public function get_or_create_intent() {
		// If there is an intent already, use it for payment.
		// @todo: We might need to switch between setup and payment intents.
		$intent = $this->context->get_intent();
		if ( $intent ) {
			return [
				'id'            => $intent->get_id(),
				'client_secret' => $intent->get_client_secret(),
			];
		}

		$order = $this->context->get_order();
		if ( $order ) {
			$amount                   = $order->get_total();
			$currency                 = $order->get_currency();
			$metadata['order_number'] = $order->get_order_number();
		} else {
			$amount   = WC()->cart->get_total( '' );
			$currency = get_woocommerce_currency();
			$metadata = [];
		}

		// Store the metadata in the payment.
		$this->context->set_metadata( $metadata );

		// Prepare the amount.
		$converted_amount = WC_Payments_Utils::prepare_amount( $amount, $currency );
		$minimum_amount   = WC_Payments_Utils::get_cached_minimum_amount( $currency );
		if ( ! is_null( $minimum_amount ) && $converted_amount < $minimum_amount ) {
			// Use the minimum amount in order to create an intent and display fields.
			$converted_amount = $minimum_amount;
		}

		$intent = 1 > $converted_amount
			? $this->create_setup_intent( $this->context )
			: $this->create_payment_intent( $this->context, $currency, $converted_amount );

		// Store the intent.
		$this->context->set_intent( $intent );

		return [
			'id'            => $intent->get_id(),
			'client_secret' => $intent->get_client_secret(),
		];
	}

	/**
	 * When a positive amount is present, tries to create a payment intent.
	 *
	 * @param Payment $payment           Payment object, for an order nor not.
	 * @param string  $currency          Currency for the payment.
	 * @param int     $converted_amount  A Stripe-formatted amount for the transaction.
	 * @return WC_Payments_API_Intention The created intention.
	 */
	protected function create_payment_intent( Payment $payment, string $currency, int $converted_amount ) {
		$order = $payment->get_order();

		try {
			$request = Create_Intention::create();
			$request->set_amount( $converted_amount );
			$request->set_currency_code( strtolower( $currency ) );
			$request->set_payment_method_types( $payment->get_payment_method_types() );
			$request->set_metadata( $payment->get_metadata() );
			$request->set_capture_method( $payment->is( Flags::MANUAL_CAPTURE ) );
			$request->set_fingerprint( $payment->get_fingerprint() );
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
	protected function create_setup_intent( Payment $payment ) {
		// Determine the customer managing the payment methods, create one if we don't have one already.
		$user        = wp_get_current_user();
		$customer_id = $this->customer_service->get_customer_id_by_user_id( $user->ID );
		if ( null === $customer_id ) {
			$customer_data = WC_Payments_Customer_Service::map_customer_data( null, new \WC_Customer( $user->ID ) );
			$customer_id   = $this->customer_service->create_customer_for_user( $user, $customer_data );
		}

		$request = Create_Setup_Intention::create();
		$request->set_customer( $customer_id );
		$request->set_payment_method_types( $payment->get_payment_method_types() );
		$setup_intent = $request->send( 'wcpay_create_setup_intention_request' );

		// @todo: Add an actual setup intent object...
		return $setup_intent;
	}

	/**
	 * Updates an intent once an order is available.
	 */
	public function update_intent_with_order() {
		$this->context->switch_state( new Initial_State( $this->context ) );
	}
}
