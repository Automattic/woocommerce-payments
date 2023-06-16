<?php
/**
 * Class file for WCPay\Core\Server\Request\Create_Intention.
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
class Create_Intention extends Request {
	use Intention;
	use Level3;

	const IMMUTABLE_PARAMS = [ 'amount' ];
	const REQUIRED_PARAMS  = [ 'amount', 'currency' ];

	/**
	 * Returns the request's API.
	 *
	 * @return string
	 */
	public function get_api(): string {
		return WC_Payments_API_Client::INTENTIONS_API;
	}

	/**
	 * Returns the request's HTTP method.
	 */
	public function get_method(): string {
		return 'POST';
	}

	/**
	 * Payment method setter.
	 *
	 * @param string $payment_method_id ID of payment method to process charge with.
	 *
	 * @return void
	 * @throws Invalid_Request_Parameter_Exception
	 */
	public function set_payment_method( string $payment_method_id ) {
		// Including the 'card' prefix to support subscription renewals using legacy payment method IDs.
		$this->validate_stripe_id( $payment_method_id, [ 'pm', 'src', 'card' ] );
		$this->set_param( 'payment_method', $payment_method_id );
	}

	/**
	 * Payment methods type setter.
	 *
	 * @param array $payment_methods List of payment methods.
	 *
	 * @return void
	 */
	public function set_payment_method_types( array $payment_methods ) {
		$this->set_param( 'payment_method_types', $payment_methods );
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
	 * Capture method setter.
	 *
	 * @param bool $manual_capture Whether to capture funds via manual action.
	 */
	public function set_capture_method( bool $manual_capture = false ) {
		$this->set_param( 'capture_method', $manual_capture ? 'manual' : 'automatic' );
	}

	/**
	 * Metadata setter.
	 *
	 * @param  array $metadata                     Meta data values to be sent along with payment intent creation.
	 * @throws Invalid_Request_Parameter_Exception In case there is no order number provided.
	 */
	public function set_metadata( array $metadata ) {
		$this->set_param( 'metadata', $metadata );

		if ( ! isset( $metadata['order_number'] ) ) {
			return; // No description to generate.
		}

		// The description is based on the order number here.
		$description = $this->get_intent_description( $metadata['order_number'] ?? 0 );
		$this->set_param( 'description', $description );
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
	 * Set fingerprint.
	 *
	 * @param string $fingerprint Fingerprint data.
	 *
	 * @return void
	 * @throws Invalid_Request_Parameter_Exception
	 */
	public function set_fingerprint( string $fingerprint = '' ) {
		$metadata = $this->get_param( 'metadata' );
		$metadata = array_merge( $metadata, $this->get_fingerprint_metadata( $fingerprint ) );
		$this->set_param( 'metadata', $metadata );
	}

	/**
	 * Set mandate.
	 *
	 * @param string $mandate Mandate.
	 *
	 * @return void
	 */
	public function set_mandate( string $mandate ) {
		$this->set_param( 'mandate', $mandate );
	}

	/**
	 * Shipping data setter.
	 *
	 * @param array $shipping Shipping data.
	 */
	public function set_shipping( array $shipping ) {
		if ( empty( $shipping ) || ! is_array( $shipping ) ) {
			return;
		}

		$this->set_param( 'shipping', $shipping );
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
