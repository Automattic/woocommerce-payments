<?php
/**
 * Class Amount_Too_Small_Exception
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Exceptions;

defined( 'ABSPATH' ) || exit;

/**
 * Class representing Amount_Too_Small_Exception
 */
class Amount_Too_Small_Exception extends API_Exception {
	/**
	 * Holds the minimum amount, returned from the API.
	 *
	 * @var int
	 */
	private $amount;

	/**
	 * Constructor
	 *
	 * @param string     $message        The Exception message to throw.
	 * @param int        $minimum_amount The minimum amount, required to create an intent in the request currency.
	 * @param int        $http_code      HTTP response code.
	 * @param int        $code           The Exception code.
	 * @param \Throwable $previous       The previous exception used for the exception chaining.
	 */
	public function __construct( $message, $minimum_amount, $http_code, $code = 0, $previous = null ) {
		$this->amount = $minimum_amount;

		parent::__construct( $message, 'amount_too_small', $http_code, $code, $previous );
	}

	/**
	 * Returns the minimum required amount.
	 *
	 * @return int
	 */
	public function get_minimum_amount() {
		return $this->amount;
	}
}
