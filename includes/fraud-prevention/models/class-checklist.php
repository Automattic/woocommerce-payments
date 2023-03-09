<?php
/**
 * Checklist model class.
 *
 * @package WCPay\Fraud_Prevention\Models
 */

namespace WCPay\Fraud_Prevention\Models;

use WCPay\Exceptions\Fraud_Ruleset_Exception;

/**
 * Class Checklist
 */
class Checklist {
	// Checklist operators.
	const LIST_OPERATOR_AND = 'and';
	const LIST_OPERATOR_OR  = 'or';

	/**
	 * The operator of this checklist.
	 *
	 * @var string
	 */
	private $operator;

	/**
	 * The checks of this checklist.
	 *
	 * @var mixed
	 */
	private $checks;

	/**
	 * Class constructor.
	 *
	 * @param   string  $operator  The operator of this check.
	 * @param   Check[] $checks     The checks of this check.
	 *
	 * @return  void
	 */
	public function __construct( string $operator, array $checks ) {
		$this->operator = $this->validate_operator( $operator );
		$this->checks   = $this->validate_checks( $checks );
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
				self::LIST_OPERATOR_AND,
				self::LIST_OPERATOR_OR,
			],
			true
		) ) {
			throw new Fraud_Ruleset_Exception( 'Invalid operator defined for a rule generation.' );
		}

		return $operator;
	}

	/**
	 * Validates the checks array.
	 *
	 * @param   array $checks  Array of checks.
	 *
	 * @return  array The validated checks.
	 * @throws  Fraud_Ruleset_Exception When one of the given objects is not a Check object.
	 */
	private function validate_checks( array $checks ) {
		foreach ( $checks as $check ) {
			if ( ! ( $check instanceof Check ) ) {
				throw new Fraud_Ruleset_Exception( 'Given checks should be only containing Check objects.' );
			}
		}
		return $checks;
	}

	/**
	 * Returns the array representation of the class.
	 *
	 * @return  array   The array which will represent this instance in the JSON serialization.
	 */
	public function to_array() {
		return [
			'operator' => $this->operator,
			'checks'   => array_map(
				function( Check $check ) {
					return $check->to_array(); },
				$this->checks
			),
		];
	}
}
