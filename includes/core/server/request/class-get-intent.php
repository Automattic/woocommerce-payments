<?php
/**
 * Class file for WCPay\Core\Server\Request\Create_Intent.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core\Server\Request;

use WC_Payments;
use WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception;
use WCPay\Core\Server\Request;
use WC_Payments_API_Client;

/**
 * Request class for getting intents.
 */
class Get_Intent extends Request {


	/**
	 * Intent id.
	 *
	 * @var string|null $intent_id
	 */
	private $intent_id = null;

	/**
	 * Set intent id.
	 *
	 * @param string $intent_id Intent id.
	 *
	 * @return void
	 * @throws Invalid_Request_Parameter_Exception
	 */
	public function set_intent_id( string $intent_id ) {
		$this->validate_stripe_id( $intent_id, [ 'pi' ] );

		// Prevent mutation of intent id. It can be only set once.
		if ( null === $this->intent_id ) {
			$this->intent_id = $intent_id;
		}
	}

	/**
	 * Returns the request's API.
	 *
	 * @return string
	 * @throws Invalid_Request_Parameter_Exception
	 */
	public function get_api(): string {
		if ( null === $this->intent_id ) {
			throw new Invalid_Request_Parameter_Exception( __( 'Intent ID is not set.', 'woocommerce-payments' ), 'wcpay_core_request_intent_not_set' );
		}
		return WC_Payments_API_Client::INTENTIONS_API . '/' . $this->intent_id;
	}

	/**
	 * Returns the request's HTTP method.
	 */
	public function get_method(): string {
		return 'GET';
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
