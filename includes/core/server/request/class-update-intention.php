<?php
/**
 * Class file for WCPay\Core\Server\Request\Update_Intent.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core\Server\Request;

use WC_Payments;
use WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception;
use WCPay\Core\Server\Request;
use WC_Payments_API_Client;

/**
 * Request class for creating intents.
 */
class Update_Intention extends Request {
	use Intention;
	use Level3;

	const IMMUTABLE_PARAMS = [ 'amount' ];
	const DEFAULT_PARAMS   = [
		'receipt_email' => '',
		'metadata'      => [],
	];

	/**
	 * Sets the intent ID, which will be used in the request URL.
	 *
	 * @param string $id Sets the intent ID, which will be used in the request URL.
	 *
	 * @throws Invalid_Request_Parameter_Exception
	 */
	protected function set_id( string $id ) {
		$this->validate_stripe_id( $id );
		$this->id = $id;
	}

	/**
	 * Returns the request's API.
	 *
	 * @return string
	 * @throws Invalid_Request_Parameter_Exception
	 */
	public function get_api(): string {
		return WC_Payments_API_Client::INTENTIONS_API . '/' . $this->id;
	}

	/**
	 * Returns the request's HTTP method.
	 */
	public function get_method(): string {
		return 'POST';
	}

	/**
	 * If the payment method should be saved to the store, this enables future usage.
	 */
	public function setup_future_usage() {
		$this->set_param( 'setup_future_usage', 'off_session' );
	}

	/**
	 * Customer setter.
	 *
	 * @param string $customer_id ID of the customer making the payment.
	 * @return void
	 *
	 * @throws Invalid_Request_Parameter_Exception
	 */
	public function set_customer( string $customer_id ) {
		$this->validate_stripe_id( $customer_id, 'cus' );
		$this->set_param( 'customer', $customer_id );
	}

	/**
	 * Stores the amount for the intent.
	 *
	 * @param int $amount The amount in ToDo units.
	 * @throws Invalid_Request_Parameter_Exception
	 */
	public function set_amount( int $amount ) {
		$this->validate_is_larger_then( $amount, 0 );
		$this->set_param( 'amount', $amount );
	}

	/**
	 * Currency code setter.
	 *
	 * @param  string $currency_code Currency to charge in.
	 * @throws Invalid_Request_Parameter_Exception When the currency code is invalid.
	 */
	public function set_currency_code( string $currency_code ) {
		$this->validate_currency_code( $currency_code );
		$this->set_param( 'currency', $currency_code );
	}

	/**
	 * Set/update payment methods.
	 *
	 * @param array $payment_methods Payment methods.
	 *
	 * @return void
	 */
	public function set_payment_method_types( array $payment_methods ) {
		if ( ! empty( $payment_methods ) ) {
			$this->set_param( 'payment_method_types', $payment_methods );
		}
	}

	/**
	 * Set payment country.
	 *
	 * @param string $payment_country Set payment country.
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function set_payment_country( string $payment_country ) {
		if ( $payment_country && ! WC_Payments::mode()->is_dev() ) {
			// Do not update on dev mode, Stripe tests cards don't return the appropriate country.
			$this->set_param( 'payment_country', $payment_country );
		}
	}

	/**
	 * Metadata setter.
	 *
	 * @param  array $metadata Meta data values to be sent along with payment intent creation.
	 */
	public function set_metadata( $metadata ) {
		// The description is based on the order number here.
		$description = $this->get_intent_description( $metadata['order_number'] ?? 0 );
		$this->set_param( 'description', $description );

		// Combine the metadata with the fingerprint.
		$metadata = array_merge( $metadata, $this->get_fingerprint_metadata() );
		$this->set_param( 'metadata', $metadata );
	}

	/**
	 * Set fingerprint.
	 *
	 * @param string $fingerprint Fingerprint data.
	 *
	 * @return void
	 * @throws Invalid_Request_Parameter_Exception
	 */
	public function set_fingerprint( $fingerprint = '' ) {
		$metadata = $this->get_param( 'metadata' );
		$metadata = array_merge( $metadata, $this->get_fingerprint_metadata( $fingerprint ) );
		$this->set_param( 'metadata', $metadata );
	}

	/**
	 * Level 3 data setter.
	 *
	 * @param array $level3 Level 3 data.
	 */
	public function set_level3( $level3 ) {
		if ( empty( $level3 ) || ! is_array( $level3 ) ) {
			return;
		}

		$this->set_param( 'level3', $this->fix_level3_data( $level3 ) );
	}

	/**
	 * Set payment method options.
	 *
	 * @param array $payment_method_options Payment method options.
	 *
	 * @return void
	 */
	public function set_payment_method_options( array $payment_method_options ) {
		$this->set_param( 'payment_method_options', $payment_method_options );
	}

	/**
	 * Formats the response from the server.
	 *
	 * @param  mixed $response The response from `WC_Payments_API_Client::request`.
	 * @return mixed           Either the same response, or the correct object.
	 */
	public function format_response( $response ) {
		return WC_Payments::get_payments_api_client()->deserialize_intention_object_from_array( $response );
	}
}
