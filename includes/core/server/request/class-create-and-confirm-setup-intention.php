<?php
/**
 * Class file for WCPay\Core\Server\Request\Create_Setup_Intention.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core\Server\Request;

use WC_Payments;
use WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception;
use WCPay\Core\Server\Request;
use WC_Payments_API_Client;

/**
 * Request class for creating setup intents.
 */
class Create_And_Confirm_Setup_Intention extends Request {
	use Intention;
	use Level3;

	const IMMUTABLE_PARAMS = [ 'customer', 'confirm' ];
	const REQUIRED_PARAMS  = [ 'customer' ];

	const DEFAULT_PARAMS = [
		'confirm'  => 'true',
		'metadata' => [],
	];

	/**
	 * Specifies the WordPress hook name that will be triggered upon calling the send() method.
	 *
	 * @var string
	 */
	protected $hook = 'wcpay_create_and_confirm_setup_intention_request';

	/**
	 * Returns the request's API.
	 *
	 * @return string
	 */
	public function get_api(): string {
		return WC_Payments_API_Client::SETUP_INTENTS_API;
	}

	/**
	 * Returns the request's HTTP method.
	 */
	public function get_method(): string {
		return 'POST';
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
	 * Set metadata.
	 *
	 * @param array $metadata Metadata to set.
	 *
	 * @return void
	 */
	public function set_metadata( array $metadata ) {
		if ( isset( $metadata['order_number'] ) ) {
			$description = $this->get_intent_description( $metadata['order_number'] );
			$this->set_param( 'description', $description );
		}
		$this->set_param( 'metadata', $metadata );
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
	 * Payment methods setter.
	 *
	 * @param  array $payment_methods               An array of payment methods that might be used for the payment.
	 * @throws Invalid_Request_Parameter_Exception  When there are no payment methods provided.
	 */
	public function set_payment_method_types( array $payment_methods ) {
		// Hard to validate without hardcoding a list here.
		if ( empty( $payment_methods ) ) {
			throw new Invalid_Request_Parameter_Exception(
				__( 'Intentions require at least one payment method', 'woocommerce-payments' ),
				'wcpay_core_invalid_request_parameter_missing_payment_method_types'
			);
		}

		$this->set_param( 'payment_method_types', $payment_methods );
	}

	/**
	 * Mandate data setter.
	 *
	 * @param array $mandate_data Array containing details about mandate to create.
	 *
	 * @return void
	 */
	public function set_mandate_data( array $mandate_data ) {
		$this->set_param( 'mandate_data', $mandate_data );

	}

	/**
	 * Formats the response from the server.
	 *
	 * @param  mixed $response The response from `WC_Payments_API_Client::request`.
	 * @return mixed           Either the same response, or the correct object.
	 */
	public function format_response( $response ) {
		return $this->api_client->deserialize_setup_intention_object_from_array( $response );
	}
}
