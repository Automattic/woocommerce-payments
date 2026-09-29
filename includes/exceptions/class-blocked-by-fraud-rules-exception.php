<?php
/**
 * Class Blocked_By_Fraud_Rules_Exception
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Exceptions;

defined( 'ABSPATH' ) || exit;

/**
 * Thrown when the server blocks a payment through the fraud rule engine
 * (`wcpay_blocked_by_fraud_rule`). Carries the risk filter results the server
 * returned with the error, so callers can surface which rules fired without an
 * extra API request.
 */
class Blocked_By_Fraud_Rules_Exception extends API_Exception {
	/**
	 * The risk filter results that blocked the payment, as a map of rule key => outcome.
	 *
	 * @var array
	 */
	private $ruleset_results;

	/**
	 * Constructor
	 *
	 * @param string     $message         The Exception message to throw.
	 * @param array      $ruleset_results The risk filter results returned by the server, as a map of rule key => outcome.
	 * @param int        $http_code       HTTP response code.
	 * @param int        $code            The Exception code.
	 * @param \Throwable $previous        The previous exception used for the exception chaining.
	 */
	public function __construct( $message, array $ruleset_results, $http_code, $code = 0, $previous = null ) {
		$this->ruleset_results = $ruleset_results;

		parent::__construct( $message, 'wcpay_blocked_by_fraud_rule', $http_code, null, null, $code, $previous );
	}

	/**
	 * Returns the risk filter results that blocked the payment.
	 *
	 * @return array Map of rule key => outcome, e.g. [ 'avs_verification' => 'block' ]. Empty when the server sent none.
	 */
	public function get_ruleset_results(): array {
		return $this->ruleset_results;
	}
}
