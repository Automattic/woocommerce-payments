<?php
/**
 * Class file for WCPay\Core\Server\Request\Paginated.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core\Server\Request;

use WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception;
use WCPay\Core\Server\Request;

/**
 * Request class for paginated requests.
 */
class Paginated extends Request {


	const DEFAULTS = [
		'page'      => 0,
		'pagesize'  => 25,
		'sort'      => 'created',
		'direction' => 'desc',
		'limit'     => 100,
	];

	/**
	 * Request uri
	 *
	 * @var mixed (int|string)
	 */
	private $uri = null;

	/**
	 * Returns the request's API.
	 *
	 * @return string
	 * @throws Invalid_Request_Parameter_Exception
	 */
	public function get_api(): string {
		return $this->uri; // Will be changed on every request by overriding this method or use set_uri method.
	}

	/**
	 * Returns the request's HTTP method.
	 */
	public function get_method(): string {
		return 'GET';
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
		return ! array_key_exists( $key, self::DEFAULTS );
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
			if ( $this->is_filter_key_mutable( $key ) ) {
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

	/**
	 * Uri to set.
	 *
	 * @param string $uri Request URI.
	 *
	 * @return void
	 */
	public function set_uri( string $uri ) {
		if ( null === $this->uri ) {
			$this->uri = $uri;
		}
	}

}
