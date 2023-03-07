<?php
/**
 * Class file for WCPay\Core\Server\Request\List_Transactions.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core\Server\Request;

use Exception;
use WC_Payments_API_Client;
use WC_Payments_DB;
use WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception;
use WCPay\Core\Server\Response;
use WCPay\Logger;
use WP_REST_Request;

/**
 * Request class for listing disputes.
 */
class List_Disputes extends Paginated {

	use Order_Info;

	/**
	 * Get api URI.
	 *
	 * @return string
	 */
	public function get_api(): string {
		return WC_Payments_API_Client::DISPUTES_API;
	}

	/**
	 * Fill request data from request.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return static
	 */
	public static function from_rest_request( $request ) {
		$wcpay_request = parent::from_rest_request( $request );

		$filters = [
			'match'           => $request->get_param( 'match' ),
			'currency_is'     => $request->get_param( 'store_currency_is' ),
			'created_before'  => $request->get_param( 'date_before' ),
			'created_after'   => $request->get_param( 'date_after' ),
			'created_between' => (array) $request->get_param( 'date_between' ),
			'search'          => $request->get_param( 'search' ),
			'status_is'       => $request->get_param( 'status_is' ),
			'status_is_not'   => $request->get_param( 'status_is_not' ),
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
	 * @param string $currency_is Store currency.
	 *
	 * @return void
	 */
	public function set_currency_is( string $currency_is ) {
		$this->set_param( 'currency_is', $currency_is );
	}

	/**
	 * Set created after.
	 *
	 * @param string $created_after Created after.
	 *
	 * @return void
	 * @throws \WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception
	 */
	public function set_created_after( string $created_after ) {
		$this->validate_date( $created_after );
		$this->set_param( 'created_after', $created_after );
	}

	/**
	 * Set created before.
	 *
	 * @param string $created_before Created before.
	 *
	 * @return void
	 * @throws \WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception
	 */
	public function set_created_before( string $created_before ) {
		$this->validate_date( $created_before );
		$this->set_param( 'created_before', $created_before );
	}

	/**
	 * Set created between.
	 *
	 * @param array $created_between Created between.
	 *
	 * @return void
	 */
	public function set_created_between( array $created_between ) {
		if ( $created_between ) {
			$this->set_param( 'created_between', $created_between );
		}
	}

	/**
	 * Set search.
	 *
	 * @param string|string[] $search Search term or terms.
	 * @return void
	 * @throws Invalid_Request_Parameter_Exception Whenever the parameter is not a string or array.
	 */
	public function set_search( $search ) {
		if ( ! is_string( $search ) && ! is_array( $search ) ) {
			throw new Invalid_Request_Parameter_Exception(
				__( 'The search parameter must be a string, or an array of strings.', 'woocommerce-payments' ),
				'wcpay_core_invalid_request_parameter_invalid_search'
			);
		}

		$this->set_param( 'search', $search );
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

	/**
	 * Return formatted response.
	 *
	 * @param mixed $response Transactions from server.
	 *
	 * @return Response
	 */
	public function format_response( $response ) {
		// Add WooCommerce order information to each dispute.
		if ( isset( $response['data'] ) ) {
			$wcpay_db = new WC_Payments_DB();
			foreach ( $response['data'] as &$dispute ) {
				try {
					// Wrap with try/catch to avoid failing whole request because of a single dispute.
					$dispute['order'] = null;
					$order            = $wcpay_db->order_from_charge_id( $dispute['charge_id'] );
					if ( $order ) {
						$dispute['order'] = $this->build_order_info( $order );
					}
				} catch ( Exception $e ) {
					Logger::error( 'Error adding order info to dispute ' . $dispute['dispute_id'] . ' : ' . $e->getMessage() );
					continue;
				}
			}
		}

		return new Response( $response );
	}



}
