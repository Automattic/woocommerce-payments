<?php
/**
 * Check model class.
 *
 * @package WCPay\Fraud_Prevention\Models
 */

namespace WCPay\Fraud_Prevention\Models;

use WCPay\Exceptions\Fraud_Ruleset_Exception;

/**
 * Class Check
 */
class Check {
	// Check operators.
	const OPERATOR_EQUALS     = 'equals';
	const OPERATOR_NOT_EQUALS = 'not_equals';
	const OPERATOR_GTE        = 'greater_or_equal';
	const OPERATOR_GT         = 'greater_than';
	const OPERATOR_LTE        = 'less_or_equal';
	const OPERATOR_LT         = 'less_than';

	/**
	 * The key of this check.
	 *
	 * @var string
	 */
	private $key;

	/**
	 * The operator of this check.
	 *
	 * @var string
	 */
	private $operator;

	/**
	 * The value of this check.
	 *
	 * @var mixed
	 */
	private $value;

	/**
	 * Class constructor.
	 *
	 * @param   string $key       The key of this check.
	 * @param   string $operator  The operator of this check.
	 * @param   string $value     The value of this check.
	 *
	 * @return  void
	 */
	public function __construct( string $key, string $operator, string $value ) {
		$this->key      = $key;
		$this->operator = $this->validate_operator( $operator );
		$this->value    = $value;
	}

	/**
	 * Validates the operator on instance creation.
	 *
	 * @param   string $operator  The operator.
	 *
	 * @return  string             The operator back if it's a valid one
	 * @throws  Fraud_Ruleset_Exception When the operator is not a valid one.
	 */
	private function validate_operator( string $operator ) {
		if ( ! in_array(
			$operator,
			[
				self::OPERATOR_EQUALS,
				self::OPERATOR_NOT_EQUALS,
				self::OPERATOR_GT,
				self::OPERATOR_GTE,
				self::OPERATOR_LT,
				self::OPERATOR_LTE,
			],
			true
		) ) {
			throw new Fraud_Ruleset_Exception( 'Invalid operator defined for a check generation.' );
		}

		return $operator;
	}

	/**
	 * Returns the array representation of the object.
	 *
	 * @return  array   The array which will represent this instance in the JSON serialization.
	 */
	public function to_array() {
		return [
			'key'      => $this->key,
			'operator' => $this->operator,
			'value'    => $this->value,
		];
	}
}
