<?php
/**
 * Class file for WCPay\Core\Server\Request\Create_Setup_Intention.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core\Server\Request;

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
}
