<?php
/**
 * Class file for WCPay\Core\Server\Request\Capture_Intention.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core\Server\Request;

use WC_Payments;
use WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception;
use WCPay\Core\Server\Request;
use WC_Payments_API_Client;

/**
 * Request class for capturing intents.
 */
class Capture_Intention extends Request {
	use Intention;
	use Level3;

	const IMMUTABLE_PARAMS = [ 'amount_to_capture' ];
	const REQUIRED_PARAMS  = [ 'amount_to_capture' ];
	const DEFAULT_PARAMS   = [
		'level3' => [],
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
		return WC_Payments_API_Client::INTENTIONS_API . '/' . $this->id . '/capture';
	}

	/**
	 * Returns the request's HTTP method.
	 */
	public function get_method(): string {
		return 'POST';
	}

	/**
	 * Stores the amount for the intent.
	 *
	 * @param int $amount Amount to capture.
	 */
	public function set_amount_to_capture( int $amount ) {
		$this->set_param( 'amount_to_capture', $amount );
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
	 * Formats the response from the server.
	 *
	 * @param  mixed $response The response from `WC_Payments_API_Client::request`.
	 * @return mixed           Either the same response, or the correct object.
	 */
	public function format_response( $response ) {
		return WC_Payments::get_payments_api_client()->deserialize_intention_object_from_array( $response );
	}
}
