<?php
/**
 * The check model class.
 *
 * @package WCPay\Fraud_Prevention\Models
 */

namespace WCPay\Fraud_Prevention\Models;

use WCPay\Exceptions\Fraud_Ruleset_Exception;

/**
 * Check model.
 */
class Check {
	// Check operators.
	const OPERATOR_EQUALS     = 'equals';
	const OPERATOR_NOT_EQUALS = 'not_equals';
	const OPERATOR_GTE        = 'greater_or_equal';
	const OPERATOR_GT         = 'greater_than';
	const OPERATOR_LTE        = 'less_or_equal';
	const OPERATOR_LT         = 'less_than';
	const OPERATOR_IN         = 'in';
	const OPERATOR_NOT_IN     = 'not_in';

	// Checklist operators.
	const LIST_OPERATOR_AND = 'and';
	const LIST_OPERATOR_OR  = 'or';

	/**
	 * List of check operators.
	 *
	 * @var array
	 */
	private static $check_operators = [
		self::OPERATOR_EQUALS,
		self::OPERATOR_NOT_EQUALS,
		self::OPERATOR_GT,
		self::OPERATOR_GTE,
		self::OPERATOR_LT,
		self::OPERATOR_LTE,
		self::OPERATOR_IN,
		self::OPERATOR_NOT_IN,
	];

	/**
	 * List of checklist operators.
	 *
	 * @var array
	 */
	private static $list_operators = [
		self::LIST_OPERATOR_AND,
		self::LIST_OPERATOR_OR,
	];

	/**
	 * Operator for the Check.
	 *
	 * @var string
	 */
	public $operator = null;

	/**
	 * The key of the source which contains the data. Is mapped to a real data to compare with the value on the Fraud_Ruleset_Service.
	 *
	 * @var string
	 */
	public $key = null;

	/**
	 * Value to check against the source.
	 *
	 * @var mixed
	 */
	public $value = null;

	/**
	 * Subchecks array that when filled, indicates this is a checklist.
	 *
	 * @var array
	 */
	public $checks = [];

	/**
	 * Creates a Check instance from an array.
	 *
	 * @param  array $array  The Check configuration.
	 *
	 * @return Check
	 * @throws Fraud_Ruleset_Exception When the array validation fails.
	 */
	public static function from_array( array $array ): Check {
		// Check if this is a valid candidate for a rule. Rules should have keys, outcomes, and checks defined and not empty.
		if ( ! self::validate_array( $array ) ) {
			throw new Fraud_Ruleset_Exception( 'Check definition not valid.' );
		}
		$check           = new self();
		$check->key      = $array['key'] ?? null;
		$check->operator = $array['operator'];
		$check->value    = $array['value'] ?? null;
		if ( isset( $array['checks'] ) ) {
			foreach ( $array['checks'] as $check_definition ) {
				$check->checks[] = self::from_array( $check_definition );
			}
		}
		return $check;
	}

	/**
	 * Validates the given array if it's structured to become a Check object.
	 *
	 * @param   array $array  The array to validate.
	 *
	 * @return  bool          Whether it is a valid Check array.
	 */
	public static function validate_array( array $array ): bool {
		// Check if this array contains an operator. In all cases it should have an operator field.
		if ( ! isset( $array['operator'] ) ) {
			return false;
		}
		if ( in_array( $array['operator'], self::$list_operators, true ) ) {
			// This should be a checklist, and should have checks.
			if ( ! isset( $array['checks'] ) || empty( $array['checks'] ) ) {
				return false;
			}
			// Validate child checks.
			foreach ( $array['checks'] as $check ) {
				if ( ! self::validate_array( $check ) ) {
					return false;
				}
			}
		} elseif ( in_array( $array['operator'], self::$check_operators, true ) ) {
			// This should be a single check, and should have key and value.
			if ( ! isset( $array['value'] ) ) {
				return false;
			}
			if ( ! isset( $array['key'] ) ) {
				return false;
			}
		} else {
			return false;
		}
		return true;
	}

	/**
	 * Creates a list type of check with the given parameters.
	 *
	 * @param   string $operator  The checklist operator.
	 * @param   array  $checks    The child checks array.
	 *
	 * @return  Check
	 * @throws Fraud_Ruleset_Exception When the validation fails.
	 */
	public static function list( string $operator, array $checks ) {
		if ( ! in_array( $operator, self::$list_operators, true ) ) {
			throw new Fraud_Ruleset_Exception( 'Operator for the check is invalid: ' . $operator );
		}
		if ( 0 < count(
			array_filter(
				$checks,
				function( $check ) {
					return ! ( $check instanceof Check ); }
			)
		) ) {
			throw new Fraud_Ruleset_Exception( 'The checklist checks should only contain Check objects.' );
		}
		$checklist           = new Check();
		$checklist->operator = $operator;
		$checklist->checks   = $checks;
		return $checklist;
	}

	/**
	 * Creates a list type of check with the given parameters.
	 *
	 * @param   string $key       The key of the check.
	 * @param   string $operator  The check operator.
	 * @param   mixed  $value     The value to compare against.
	 *
	 * @return  Check
	 * @throws Fraud_Ruleset_Exception When the validation fails.
	 */
	public static function check( string $key, string $operator, $value ) {
		if ( ! in_array( $operator, self::$check_operators, true ) ) {
			throw new Fraud_Ruleset_Exception( 'Operator for the check is invalid: ' . $operator );
		}

		$check           = new Check();
		$check->operator = $operator;
		$check->key      = $key;
		$check->value    = $value;
		return $check;
	}

	/**
	 * Converts the class to it's array representation for transmission.
	 *
	 * @return  array
	 */
	public function to_array() {
		if ( ! empty( $this->checks ) ) {
			return [
				'operator' => $this->operator,
				'checks'   => array_map(
					function( Check $check ) {
						return $check->to_array();
					},
					$this->checks
				),
			];
		}
		return [
			'key'      => $this->key,
			'operator' => $this->operator,
			'value'    => $this->value,
		];
	}
}
