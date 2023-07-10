<?php
/**
 * Class file for WCPay\Core\Server\Request\List_Authorizations.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core\Server\Request;

use WC_Payments_API_Client;

/**
 * Request class for listing authorizations.
 */
class List_Authorizations extends Paginated {


	/**
	 * Returns the request's API.
	 *
	 * @return string
	 */
	public function get_api(): string {
		return WC_Payments_API_Client::AUTHORIZATIONS_API;
	}

	/**
	 * Used to prepare request from WP Rest data.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return static
	 */
	public static function from_rest_request( $request ) {
		$wcpay_request       = parent::from_rest_request( $request );
		$date_between_filter = $request->get_param( 'date_between' );
		$user_timezone       = $request->get_param( 'user_timezone' );

		if ( ! is_null( $date_between_filter ) ) {
			$date_between_filter = array_map(
				function ( $transaction_date ) use ( $user_timezone ) {
					return Paginated::format_transaction_date_with_timestamp( $transaction_date, $user_timezone );
				},
				$date_between_filter
			);
		}

		$filters = [
			'match'                   => $request->get_param( 'match' ),
			'date_before'             => self::format_transaction_date_with_timestamp( $request->get_param( 'date_before' ), $user_timezone ),
			'date_after'              => self::format_transaction_date_with_timestamp( $request->get_param( 'date_after' ), $user_timezone ),
			'date_between'            => $date_between_filter,
			'outcome_type_is'         => $request->get_param( 'outcome_type_is' ),
			'outcome_type_is_not'     => $request->get_param( 'outcome_type_is_not' ),
			'status_is'               => $request->get_param( 'status_is' ),
			'status_is_not'           => $request->get_param( 'status_is_not' ),
			'currency_is'             => $request->get_param( 'currency_is' ),
			'currency_is_not'         => $request->get_param( 'currency_is_not' ),
			'search'                  => (array) $request->get_param( 'search' ),
			'order_id_is'             => $request->get_param( 'order_id_is' ),
			'payment_method_id_is'    => $request->get_param( 'payment_method_id_is' ),
			'transaction_id_is'       => $request->get_param( 'transaction_id_is' ),
			'authorization_id_is'     => $request->get_param( 'authorization_id_is' ),
			'include_capturable_only' => $request->get_param( 'include_capturable_only' ),
		];
		$wcpay_request->set_filters( $filters );

		return $wcpay_request;
	}

	/**
	 * Set include_capturable_only param.
	 *
	 * @param bool $include_capturable Inclue capturable status value.

	 * @return void
	 */
	public function set_include_capturable_only( $include_capturable ) {
		$this->set_param( 'include_capturable_only', (bool) $include_capturable );
	}
}
