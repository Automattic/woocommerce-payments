<?php
/**
 * Class file for WCPay\Core\Server\Request\List_Documents.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core\Server\Request;

use WC_Payments_API_Client;
use WP_REST_Request;

/**
 * Request class for listing documents.
 */
class List_Documents extends Paginated {

	use Date_Parameters;

	/**
	 * Returns the request's API.
	 *
	 * @return string
	 */
	public function get_api(): string {
		return WC_Payments_API_Client::DOCUMENTS_API;
	}
	/**
	 * Used to prepare request from WP Rest data.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return static
	 */
	public static function from_rest_request( $request ) {
		$wcpay_request = parent::from_rest_request( $request );
		$filters       = [
			'match'        => $request->get_param( 'match' ),
			'date_before'  => $request->get_param( 'date_before' ),
			'date_after'   => $request->get_param( 'date_after' ),
			'date_between' => (array) $request->get_param( 'date_between' ),
			'type_is'      => $request->get_param( 'type_is' ),
			'type_is_not'  => $request->get_param( 'type_is_not' ),
		];
		$wcpay_request->set_filters( $filters );
		return $wcpay_request;
	}

	/**
	 * Set match.
	 *
	 * @param string $match Match.
	 *
	 * @return void
	 */
	public function set_match( string $match ) {
		$this->set_param( 'match', $match );
	}


	/**
	 * Set type is.
	 *
	 * @param string $type_is Type is.
	 *
	 * @return void
	 */
	public function set_type_is( string $type_is ) {
		$this->set_param( 'type_is', $type_is );
	}

	/**
	 * Set type is not.
	 *
	 * @param string $type_is_not Type is not.
	 *
	 * @return void
	 */
	public function set_type_is_not( string $type_is_not ) {
		$this->set_param( 'type_is_not', $type_is_not );
	}
}
