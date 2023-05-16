<?php
/**
 * Trait Vars
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment;

/**
 * A helper trait, containing payment setters and getters for vars.
 *
 * This could live in the `Payment` class as well, but we
 * should try to keep the main class undiluted.
 */
trait Vars {
	/**
	 * Retrieves variables, set in previous steps.
	 *
	 * @param string $key Key of the value.
	 * @return mixed|null
	 */
	abstract protected function get_var( $key );

	/**
	 * Allows any step to store variables, related to the process.
	 *
	 * @param string $key   Name of the value.
	 * @param mixed  $value Value to set.
	 */
	abstract protected function set_var( $key, $value );

	/**
	 * Updates the payment/setup intent, used for the payment.
	 *
	 * The ID is generally used as an input parameter, before
	 * the full object is retrieved, and available for follow-up steps.
	 *
	 * @param string $intent_id The ID of the intent.
	 */
	public function set_intent_id( string $intent_id ) {
		$this->set_var( 'intent_id', $intent_id );
	}

	/**
	 * Returns the payment/setup intent ID, if one is set.
	 *
	 * @return string|null
	 */
	public function get_intent_id() {
		return $this->get_var( 'intent_id' );
	}

	/**
	 * Stores a full payment/setup intent object within the process.
	 * This generally happens in the main (action) part of the process.
	 *
	 * @param WC_Payments_API_Intention|WC_Payments_API_Setup_Intention $intent The intent object.
	 */
	public function set_intent( $intent ) {
		$this->set_var( 'intent', $intent );
	}

	/**
	 * Returns the intent object, if set.
	 *
	 * @return WC_Payments_API_Intention|WC_Payments_API_Setup_Intention|null
	 */
	public function get_intent() {
		return $this->get_var( 'intent' );
	}

	/**
	 * Sets the metadata, used for the payment.
	 *
	 * @param array $metadata Generated metadata.
	 */
	public function set_metadata( array $metadata ) {
		$this->set_var( 'metadata', $metadata );
	}

	/**
	 * Retrieves the prepared metadata.
	 *
	 * @return array|null
	 */
	public function get_metadata() {
		return $this->get_var( 'metadata' );
	}

	/**
	 * Stores a customer ID.
	 *
	 * @param string $customer_id The Stripe-formatted customer ID.
	 */
	public function set_customer_id( string $customer_id ) {
		$this->set_var( 'customer_id', $customer_id );
	}

	/**
	 * Retrieves the customer ID, if any.
	 *
	 * @return string|null
	 */
	public function get_customer_id() {
		return $this->get_var( 'customer_id' );
	}

	/**
	 * Stores a user ID.
	 *
	 * @param int $user_id The WP user ID.
	 */
	public function set_user_id( int $user_id = null ) {
		$this->set_var( 'user_id', $user_id );
	}

	/**
	 * Retrieves the user ID, if any.
	 *
	 * @return int|null
	 */
	public function get_user_id() {
		return $this->get_var( 'user_id' );
	}

	/**
	 * Sets the payment method types.
	 *
	 * @param string[] $payment_method_types The allowed payment method types.
	 */
	public function set_payment_method_types( array $payment_method_types ) {
		$this->set_var( 'payment_method_types', $payment_method_types );
	}

	/**
	 * Retrieves the payment method types.
	 *
	 * @return string[]|null
	 */
	public function get_payment_method_types() {
		return $this->get_var( 'payment_method_types' );
	}

	/**
	 * Sets the fingerprint.
	 *
	 * @param string $fingerprint The fingerprint.
	 */
	public function set_fingerprint( string $fingerprint ) {
		$this->set_var( 'fingerprint', $fingerprint );
	}

	/**
	 * Retrieves the fingerprint.
	 *
	 * @return string
	 */
	public function get_fingerprint() {
		return $this->get_var( 'fingerprint' );
	}

	/**
	 * Sets the selected UPE payment type.
	 *
	 * @param string $selected_upe_payment_type The type.
	 */
	public function set_selected_upe_payment_type( string $selected_upe_payment_type ) {
		$this->set_var( 'selected_upe_payment_type', $selected_upe_payment_type );
	}

	/**
	 * Retrieves the selected UPE payment type.
	 *
	 * @return string|null
	 */
	public function get_selected_upe_payment_type() {
		return $this->get_var( 'selected_upe_payment_type' );
	}

	/**
	 * Sets the payment country.
	 *
	 * @param string $payment_country The country.
	 */
	public function set_payment_country( string $payment_country ) {
		$this->set_var( 'payment_country', $payment_country );
	}

	/**
	 * Retrieves the payment country.
	 *
	 * @return string|null
	 */
	public function get_payment_country() {
		return $this->get_var( 'payment_country' );
	}

	/**
	 * Stores the response from processing.
	 * This should follow the format, returned by gateways' process_payment().
	 *
	 * @param array $response The response to use.
	 */
	public function set_response( array $response ) {
		$this->set_var( 'response', $response );
	}

	/**
	 * Returns the response from the payment.
	 *
	 * @return array
	 */
	public function get_response() {
		return $this->get_var( 'response' );
	}
}
