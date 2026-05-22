<?php
/**
 * Class file for WCPay\Core\Server\Request\Get_Transactions_Summary.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core\Server\Request;

use WC_Payments_API_Client;
use WC_Payments_Utils;
use WCPay\Core\Server\Request;

/**
 * Request class for retrieving transactions summary data.
 */
class Get_Transactions_Summary extends Request {
	/**
	 * Specifies the WordPress hook name that will be triggered upon calling the send() method.
	 *
	 * @var string
	 */
	protected $hook = 'wcpay_get_transactions_summary_request';

	/**
	 * Get api URI.
	 *
	 * @return string
	 */
	public function get_api(): string {
		return WC_Payments_API_Client::TRANSACTIONS_API . '/summary';
	}

	/**
	 * Returns the request's HTTP method.
	 */
	public function get_method(): string {
		return 'GET';
	}

	/**
	 * Set filters.
	 *
	 * @param array $filters Filters to set.
	 *
	 * @return void
	 */
	public function set_filters( array $filters ) {
		foreach ( $filters as $key => $value ) {
			if ( null === $value || '' === $value || [] === $value ) {
				continue;
			}

			if ( method_exists( $this, 'set_' . $key ) ) {
				$this->{'set_' . $key}( $value );
				continue;
			}

			$this->set_param( $key, $value );
		}
	}

	/**
	 * Set deposit id.
	 *
	 * @param string|null $deposit_id Deposit id.
	 *
	 * @return void
	 */
	public function set_deposit_id( ?string $deposit_id ) {
		if ( null === $deposit_id || '' === $deposit_id ) {
			$this->unset_param( 'deposit_id' );
			return;
		}

		$this->set_param( 'deposit_id', $deposit_id );
	}

	/**
	 * Set search.
	 *
	 * @param array $search Search terms.
	 *
	 * @return void
	 */
	public function set_search( array $search ) {
		if ( ! empty( $search ) ) {
			$this->set_param( 'search', WC_Payments_Utils::map_search_orders_to_charge_ids( $search ) );
		}
	}

	/**
	 * Format response to array to prevent calling to_array() every time.
	 *
	 * @param mixed $response The response from `WC_Payments_API_Client::request`.
	 *
	 * @return mixed
	 */
	public function format_response( $response ) {
		return $response;
	}
}
