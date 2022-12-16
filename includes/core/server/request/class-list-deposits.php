<?php
/**
 * Class file for WCPay\Core\Server\Request\List_Deposits.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core\Server\Request;

use WC_Payments_API_Client;
use WP_REST_Request;

/**
 * Request class for paginated requests.
 */
class List_Deposits extends Paginated {


	/**
	 * Returns the request's API.
	 *
	 * @return string
	 */
	public function get_api(): string {
		return WC_Payments_API_Client::DEPOSITS_API;
	}

	/**
	 * Used to prepare request from WP Rest data.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return Paginated|self
	 */
	public static function from_rest_request( $request ) {
		$wcpay_request = parent::from_rest_request( $request );
		$filters       = [
			'match'             => $request->get_param( 'match' ),
			'store_currency_is' => $request->get_param( 'store_currency_is' ),
			'date_before'       => $request->get_param( 'date_before' ),
			'date_after'        => $request->get_param( 'date_after' ),
			'date_between'      => (array) $request->get_param( 'date_between' ),
			'status_is'         => $request->get_param( 'status_is' ),
			'status_is_not'     => $request->get_param( 'status_is_not' ),
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
	 * Set currency is.
	 *
	 * @param string $store_currency_is Store currency.
	 *
	 * @return void
	 */
	public function set_store_currency_is( string $store_currency_is ) {
		$this->set_param( 'store_currency_is', $store_currency_is );
	}

	/**
	 * Set date after.
	 *
	 * @param string $date_after Date after.
	 *
	 * @return void
	 * @throws \WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception
	 */
	public function set_date_after( string $date_after ) {
		$this->validate_date( $date_after );
		$this->set_param( 'date_after', $date_after );
	}

	/**
	 * Set date before.
	 *
	 * @param string $date_before Date before.
	 *
	 * @return void
	 * @throws \WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception
	 */
	public function set_date_before( string $date_before ) {
		$this->validate_date( $date_before );
		$this->set_param( 'date_before', $date_before );
	}

	/**
	 * Set date between.
	 *
	 * @param array $date_between Date between.
	 *
	 * @return void
	 */
	public function set_date_between( array $date_between ) {
		if ( $date_between ) {
			$this->set_param( 'date_between', $date_between );
		}
	}

	/**
	 * Set status is.
	 *
	 * @param string $status_is Status is.
	 *
	 * @return void
	 */
	public function set_status_is( string $status_is ) {
		$this->set_param( 'status_is', $status_is );
	}

	/**
	 * Set status is not.
	 *
	 * @param string $status_is_not Status is not.
	 *
	 * @return void
	 */
	public function set_status_is_not( string $status_is_not ) {
		$this->set_param( 'status_is_not', $status_is_not );
	}
}
