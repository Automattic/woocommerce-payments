<?php
/**
 * Trait UPE_Payment_Gateway_Utilities
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment_Methods;

/**
 * Utility functions for UPE payment gateways
 */
trait UPE_Payment_Gateway_Utilities {

	/**
	 * Stripe ID
	 *
	 * @var string
	 */
	protected $stripe_id;

	/**
	 * Set title and properties for UPE WC Payment Gateway
	 *
	 * @param UPE_Payment_Method $payment_method UPE payment method.
	 */
	public function set_gateway_properties( $payment_method ) {
		$this->stripe_id      = $payment_method->get_id();
		$this->title          = $payment_method->get_title();
		$this->method_title   = "WooCommerce Payments $this->title";
		$this->checkout_title = $this->title;
	}

	/**
	 * Sets the title on checkout correctly before the title is displayed.
	 * Override parent function with static title.
	 *
	 * @param string $title The title of the gateway being filtered.
	 * @param string $id    The id of the gateway being filtered.
	 *
	 * @return string Filtered gateway title.
	 */
	public function maybe_filter_gateway_title( $title, $id ) {
		return $this->title;
	}

	/**
	 * Adds the id and client secret of payment intent needed to mount the UPE element in frontend to WC session.
	 *
	 * @param string $intent_id     The payment intent id.
	 * @param string $client_secret The payment intent client secret.
	 */
	private function add_upe_payment_intent_to_session( string $intent_id = '', string $client_secret = '' ) {
		$cart_hash = 'undefined';

		if ( isset( $_COOKIE['woocommerce_cart_hash'] ) ) {
			$cart_hash = sanitize_text_field( wp_unslash( $_COOKIE['woocommerce_cart_hash'] ) );
		}

		$value = $cart_hash . '-' . $intent_id . '-' . $client_secret;

		WC()->session->set( self::KEY_UPE_PAYMENT_INTENT, $value );
	}

	/**
	 * Returns session key for UPE SEPA payment intents.
	 * Overrides parent.
	 *
	 * @return string
	 */
	public function get_payment_intent_session_key() {
		return self::KEY_UPE_PAYMENT_INTENT . '_' . $this->stripe_id;
	}

	/**
	 * Returns session key for UPE SEPA setup intents.
	 * Overrides parent.
	 *
	 * @return string
	 */
	public function get_setup_intent_session_key() {
		return self::KEY_UPE_SETUP_INTENT . '_' . $this->stripe_id;
	}
}
