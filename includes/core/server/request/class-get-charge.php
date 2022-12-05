<?php
/**
 * Class file for WCPay\Core\Server\Request\Get_Charge.
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
class Get_Charge extends Request {


	/**
	 * Charge id.
	 *
	 * @var string|null $intent_id
	 */
	private $charge_id = null;

	/**
	 * Set intent id.
	 *
	 * @param string $charge_id Charge id.
	 *
	 * @return void
	 * @throws Invalid_Request_Parameter_Exception
	 */
	public function set_charge_id( string $charge_id ) {
		$this->validate_stripe_id( $charge_id, [ 'ch' ] );

		// Prevent mutation of charge id. It can be only set once.
		if ( null === $this->charge_id ) {
			$this->charge_id = $charge_id;
		}
	}

	/**
	 * Returns the request's API.
	 *
	 * @return string
	 * @throws Invalid_Request_Parameter_Exception
	 */
	public function get_api(): string {
		if ( null === $this->charge_id ) {
			throw new Invalid_Request_Parameter_Exception( __( 'Charge ID is not set.', 'woocommerce-payments' ), 'wcpay_core_request_intent_not_set' );
		}
		return WC_Payments_API_Client::CHARGES_API . '/' . $this->charge_id;
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
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return WC_Payments::get_payments_api_client()->add_additional_info_to_charge( $response );
	}
}
