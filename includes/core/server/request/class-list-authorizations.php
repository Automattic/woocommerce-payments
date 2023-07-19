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
	 * Used to prepare request data from reports api request.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return static
	 */
	public static function from_reports_rest_request( $request ) {
		$wcpay_request = static::create();

		$wcpay_request->set_page( $request->get_param( 'page' ) );
		$wcpay_request->set_page_size( $request->get_param( 'per_page' ) );
		$wcpay_request->set_sort_by( $request->get_param( 'orderby' ) );
		$wcpay_request->set_sort_direction( $request->get_param( 'order' ) );

		$date_between_filter = $request->get_param( 'created_between' );
		$user_timezone       = $request->get_param( 'user_timezone' );

		if ( ! is_null( $date_between_filter ) ) {
			$date_between_filter = array_map(
				function ( $created_date ) use ( $user_timezone ) {
					return Paginated::format_transaction_date_with_timestamp( $created_date, $user_timezone );
				},
				$date_between_filter
			);
		}

		$filters = [
			'match'                => $request->get_param( 'match' ),
			'created_before'       => self::format_transaction_date_with_timestamp( $request->get_param( 'date_before' ), $user_timezone ),
			'created_after'        => self::format_transaction_date_with_timestamp( $request->get_param( 'date_after' ), $user_timezone ),
			'created_between'      => $date_between_filter,
			'order_id_is'          => $request->get_param( 'order_id' ),
			'customer_email_is'    => $request->get_param( 'deposit_id' ),
			'payment_method_id_is' => $request->get_param( 'payment_method_id' ),
			'type_is'              => (string) $request->get_param( 'type' ),
			'transaction_id_is'    => $request->get_param( 'transaction_id' ),
			'payment_intent_id_is' => $request->get_param( 'payment_intent_id' ),
			'search'               => (array) $request->get_param( 'search' ),
		];
		$wcpay_request->set_filters( $filters );

		return $wcpay_request;
	}

	/**
	 * Set payment intent id is.
	 *
	 * @param string $payment_intent_id Payment intent id.
	 *
	 * @return void
	 */
	public function set_payment_intent_id_is( string $payment_intent_id ) {
		$this->set_param( 'payment_intent_id_is', $payment_intent_id );
	}

	/**
	 * Set type is.
	 *
	 * @param string $type_id Type id.
	 *
	 * @return void
	 */
	public function set_type_id( string $type_id ) {
		$this->set_param( 'type_is', $type_id );
	}

}
