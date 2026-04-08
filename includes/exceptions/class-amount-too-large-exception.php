<?php
/**
 * Class Amount_Too_Large_Exception
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Exceptions;

defined( 'ABSPATH' ) || exit;

/**
 * Class representing Amount_Too_Large_Exception
 */
class Amount_Too_Large_Exception extends API_Exception {

	/**
	 * Constructor
	 *
	 * @param string $message        The Exception message to throw.
	 * @param int    $http_code      HTTP response code.
	 */
	public function __construct( $message, $http_code ) {
		parent::__construct( $message, 'amount_too_large', $http_code, null, null );
	}
}
