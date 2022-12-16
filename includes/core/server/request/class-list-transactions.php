<?php
/**
 * Class file for WCPay\Core\Server\Request\List_Transactions.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core\Server\Request;

use DateTime;
use DateTimeZone;
use WC_Payments_API_Client;
use WC_Payments_DB;
use WCPay\Core\Server\Response;
use WP_REST_Request;

/**
 * Request class for listing transactions.
 */
class List_Transactions extends Paginated {

	use Order_Info;

	const DEFAULTS = [
		'sort'       => 'date',
		'direction'  => 'desc',
		'deposit_id' => null,
	];

	/**
	 * Set deposit id.
	 *
	 * @param mixed $deposit_id Deposit id.
	 *
	 * @return void
	 */
	public function set_deposit_id( $deposit_id ) {
		$this->set_param( 'deposit_id', $deposit_id );
	}

	/**
	 * Get api URI.
	 *
	 * @return string
	 */
	public function get_api(): string {
		return WC_Payments_API_Client::TRANSACTIONS_API;
	}

	/**
	 * Used to prepare request from WP Rest data.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return Paginated|self
	 */
	public static function from_rest_request( $request ) {
		$wcpay_request       = parent::from_rest_request( $request );
		$date_between_filter = $request->get_param( 'date_between' );
		$user_timezone       = $request->get_param( 'user_timezone' );

		if ( ! is_null( $date_between_filter ) ) {
			$date_between_filter = array_map(
				function ( $transaction_date ) use ( $user_timezone, $wcpay_request ) {
					return $wcpay_request->format_transaction_date_with_timestamp( $transaction_date, $user_timezone );
				},
				$date_between_filter
			);
		}

		$filters = [
			'match'                    => $request->get_param( 'match' ),
			'date_before'              => $wcpay_request->format_transaction_date_with_timestamp( $request->get_param( 'date_before' ), $user_timezone ),
			'date_after'               => $wcpay_request->format_transaction_date_with_timestamp( $request->get_param( 'date_after' ), $user_timezone ),
			'date_between'             => $date_between_filter,
			'type_is'                  => $request->get_param( 'type_is' ),
			'type_is_not'              => $request->get_param( 'type_is_not' ),
			'store_currency_is'        => $request->get_param( 'store_currency_is' ),
			'customer_currency_is'     => $request->get_param( 'customer_currency_is' ),
			'customer_currency_is_not' => $request->get_param( 'customer_currency_is_not' ),
			'loan_id_is'               => $request->get_param( 'loan_id_is' ),
			'search'                   => $request->get_param( 'search' ),
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
	 * Set loan id is.
	 *
	 * @param string $loan_id Loan id.
	 *
	 * @return void
	 */
	public function set_loan_id_is( string $loan_id ) {
		$this->set_param( 'loan_id_is', $loan_id );
	}

	/**
	 * Set currency is.
	 *
	 * @param string $currency Store currency.
	 *
	 * @return void
	 */
	public function set_store_currency_is( string $currency ) {
		$this->set_param( 'store_currency_is', $currency );
	}

	/**
	 * Set customer currency is.
	 *
	 * @param string $customer_currency_is Store currency.
	 *
	 * @return void
	 */
	public function set_customer_currency_is( string $customer_currency_is ) {
		$this->set_param( 'customer_currency_is', $customer_currency_is );
	}

	/**
	 * Set customer currency is not.
	 *
	 * @param string $currency Store currency.
	 *
	 * @return void
	 */
	public function set_customer_currency_is_not( string $currency ) {
		$this->set_param( 'customer_currency_is_not', $currency );
	}

	/**
	 * Set search.
	 *
	 * @param string $search Search term.
	 *
	 * @return void
	 */
	public function set_search( string $search ) {
		$this->set_param( 'search', $search );
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
		// Add only if it's not empty.
		if ( $date_between ) {
			$this->set_param( 'date_between', $date_between );
		}
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

	/**
	 * Return formatted response.
	 *
	 * @param mixed $response Transactions from server.
	 *
	 * @return Response
	 */
	public function format_response( $response ) {
		$wcpay_db               = new WC_Payments_DB();
		$charge_ids             = array_column( $response['data'], 'charge_id' );
		$orders_with_charge_ids = count( $charge_ids ) ? $wcpay_db->orders_with_charge_id_from_charge_ids( $charge_ids ) : [];

		// Add order information to each transaction available.
		// TODO: Throw exception when `$response` or `$transaction` don't have the fields expected?
		if ( isset( $response['data'] ) ) {
			foreach ( $response['data'] as &$transaction ) {
				foreach ( $orders_with_charge_ids as $order_with_charge_id ) {
					if ( $order_with_charge_id['charge_id'] === $transaction['charge_id'] && ! empty( $transaction['charge_id'] ) ) {
						$order                            = $order_with_charge_id['order'];
						$transaction['order']             = $this->build_order_info( $order );
						$transaction['payment_intent_id'] = $order->get_meta( '_intent_id' );
					}
				}
			}
			// Securing future changes from modifying reference content.
			unset( $transaction );
		}

		return new Response( $response );
	}

	/**
	 * Formats the incoming transaction date as per the blog's timezone.
	 *
	 * @param string|null $transaction_date Transaction date to format.
	 * @param string|null $user_timezone         User's timezone passed from client.
	 *
	 * @return string|null The formatted transaction date as per timezone.
	 */
	private function format_transaction_date_with_timestamp( $transaction_date, $user_timezone ) {
		if ( is_null( $transaction_date ) || is_null( $user_timezone ) ) {
			return $transaction_date;
		}

		// Get blog timezone.
		$blog_time = new DateTime( $transaction_date );
		$blog_time->setTimezone( new DateTimeZone( wp_timezone_string() ) );

		// Get local timezone.
		$local_time = new DateTime( $transaction_date );
		$local_time->setTimezone( new DateTimeZone( $user_timezone ) );

		// Compute time difference in minutes.
		$time_difference = ( strtotime( $local_time->format( 'Y-m-d H:i:s' ) ) - strtotime( $blog_time->format( 'Y-m-d H:i:s' ) ) ) / 60;

		// Shift date by time difference.
		$formatted_date = new DateTime( $transaction_date );
		date_modify( $formatted_date, $time_difference . 'minutes' );

		return $formatted_date->format( 'Y-m-d H:i:s' );
	}
}
