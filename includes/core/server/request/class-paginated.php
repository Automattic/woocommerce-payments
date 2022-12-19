<?php
/**
 * Class file for WCPay\Core\Server\Request\Paginated.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core\Server\Request;

use WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception;
use WCPay\Core\Server\Request;
use WP_REST_Request;

/**
 * Request class for paginated requests.
 */
class Paginated extends Request {


	const DEFAULT_PARAMS = [
		'page'      => 0,
		'pagesize'  => 25,
		'sort'      => 'created',
		'direction' => 'desc',
		'limit'     => 100,
	];


	/**
	 * Returns the request's API.
	 *
	 * @return string
	 * @throws Invalid_Request_Parameter_Exception
	 */
	public function get_api(): string {
		return ''; // Will be changed on every request that extends this class.
	}

	/**
	 * Returns the request's HTTP method.
	 */
	public function get_method(): string {
		return 'GET';
	}

	/**
	 * Used to prepare request from WP Rest data.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return Paginated
	 */
	public static function from_rest_request( $request ) {
		$wcpay_request = static::create();
		$wcpay_request->set_page( (int) $request->get_param( 'page' ) );
		$wcpay_request->set_page_size( (int) ( $request->get_param( 'pagesize' ) ?? 25 ) );
		$sort = $request->get_param( 'sort' );
		if ( null !== $sort ) {
			$wcpay_request->set_sort_by( (string) $sort );
		}
		$direction = $request->get_param( 'direction' );
		if ( null !== $direction ) {
			$wcpay_request->set_sort_direction( (string) $direction );
		}
		return $wcpay_request;
	}

	/**
	 * Used to provide custom check for setting filter keys. Some requests might have their own logic of which filters keys are mutable.
	 *
	 * @param string $key Key to check.
	 *
	 * @return bool
	 */
	public function is_filter_key_mutable( string $key ) {
		// In most cases, the default parameters are keys that cannot be changed via filters.
		return ! array_key_exists( $key, self::DEFAULT_PARAMS );
	}

	/**
	 * Set filters.
	 *
	 * @param array $filters Filterd to set.
	 *
	 * @return void
	 */
	public function set_filters( array $filters ) {

		foreach ( $filters as $key => $value ) {
			// Nullable filters are not needed and mutable keys can only be changed with this method.
			if ( null !== $value && $this->is_filter_key_mutable( $key ) ) {
				// Make sure that setter is called for this specific key to preform validations and other things in setter function.
				if ( method_exists( $this, 'set_' . $key ) ) {
					$this->{'set_' . $key}( $value );
				} else {
					$this->set_param( $key, $value );
				}
			}
		}
	}

	/**
	 * Set page.
	 *
	 * @param int $page Page to set.
	 *
	 * @return void
	 */
	public function set_page( int $page ) {
		$this->set_param( 'page', $page );
	}


	/**
	 * Set page size.
	 *
	 * @param int $page_size Page size to set.
	 *
	 * @return void
	 */
	public function set_page_size( int $page_size ) {
		$this->set_param( 'pagesize', $page_size );
	}

	/**
	 * Set sort by.
	 *
	 * @param string $sort Sort value.
	 *
	 * @return void
	 */
	public function set_sort_by( string $sort ) {
		$this->set_param( 'sort', $sort );
	}

	/**
	 * Set sort direction.
	 *
	 * @param string $direction Sort direction.
	 *
	 * @return void
	 */
	public function set_sort_direction( string $direction ) {
		$this->set_param( 'direction', $direction );
	}
}
