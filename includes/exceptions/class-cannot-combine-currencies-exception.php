<?php
/**
 * Class Cannot_Combine_Currencies_Exception
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Exceptions;

defined( 'ABSPATH' ) || exit;

/**
 * Class representing Amount_Too_Small_Exception
 */
class Cannot_Combine_Currencies_Exception extends API_Exception {
	/**
	 * Holds the attempted currency, extracted from the error message returned by the API.
	 *
	 * @var string
	 */
	private $currency;

	/**
	 * Constructor
	 *
	 * @param string     $message        The Exception message to throw.
	 * @param string     $currency       The currency for which this is the minimum amount.
	 * @param int        $http_code      HTTP response code.
	 * @param int        $code           The Exception code.
	 * @param \Throwable $previous       The previous exception used for the exception chaining.
	 */
	public function __construct( $message, $currency, $http_code, $code = 0, $previous = null ) {
		$this->currency = $currency;

		parent::__construct( $message, 'cannot_combine_currencies', $http_code, null, null, $code, $previous );
	}

	/**
	 * Returns the currency of the minumum required amount.
	 *
	 * @return string
	 */
	public function get_currency() {
		return $this->currency;
	}
}
