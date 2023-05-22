<?php
/**
 * The rule model class.
 *
 * @package WCPay\Fraud_Prevention\Models
 */

namespace WCPay\Fraud_Prevention\Models;

use WCPay\Exceptions\Fraud_Ruleset_Exception;

/**
 * Rule model.
 */
class Rule {
	/**
	 * Constants that define the outcome of the rule.
	 *
	 * @var string
	 */
	const FRAUD_OUTCOME_ALLOW  = 'allow';
	const FRAUD_OUTCOME_REVIEW = 'review';
	const FRAUD_OUTCOME_BLOCK  = 'block';

	/**
	 * Rule key.
	 *
	 * @var string
	 */
	public $key;

	/**
	 * The action to take when the rule is successful.
	 *
	 * @var string
	 */
	public $outcome;

	/**
	 * The check or checklist that defines the rule clause.
	 *
	 * @var Check
	 */
	public $check;

	/**
	 * Class constructor.
	 *
	 * @param   string $key      The rule key.
	 * @param   string $outcome  The rule outcome.
	 * @param   Check  $check    The single check, or the wrapper checklist.
	 *
	 * @return  void
	 * @throws  Fraud_Ruleset_Exception When the outcome validation fails.
	 */
	public function __construct( string $key, string $outcome, Check $check ) {
		if ( ! in_array(
			$outcome,
			[ self::FRAUD_OUTCOME_ALLOW, self::FRAUD_OUTCOME_BLOCK, self::FRAUD_OUTCOME_REVIEW ],
			true
		) ) {
			throw new Fraud_Ruleset_Exception( 'Given rule outcome is invalid.' );
		}

		$this->key     = $key;
		$this->outcome = $outcome;
		$this->check   = $check;
	}

	/**
	 * Creates a Rule instance from a Fraud_Ruleset rule_config field.
	 *
	 * @param array $array The rule array retrieved from parsing Fraud_Ruleset::rules_config.
	 *
	 * @return Rule
	 * @throws Fraud_Ruleset_Exception
	 */
	public static function from_array( array $array ): Rule {
		// Check if this is a valid candidate for a rule. Rules should have keys, outcomes, and checks defined and not empty.
		if ( ! self::validate_array( $array ) ) {
			throw new Fraud_Ruleset_Exception( 'Rule definition not valid.' );
		}

		return new self(
			$array['key'],
			$array['outcome'],
			Check::from_array( $array['check'] )
		);
	}

	/**
	 * Validates the given array if it's structured to become a Rule object.
	 *
	 * @param   array $array  The array to validate.
	 *
	 * @return  bool          Whether it is a valid Rule array.
	 */
	public static function validate_array( array $array ) {
		if ( ! isset( $array['key'], $array['check'], $array['outcome'] )
			|| ! is_array( $array['check'] )
			|| empty( $array['check'] )
			|| ! in_array(
				$array['outcome'],
				[
					self::FRAUD_OUTCOME_BLOCK,
					self::FRAUD_OUTCOME_REVIEW,
					self::FRAUD_OUTCOME_ALLOW,
				],
				true
			)
		) {
			return false;
		}

		// Validate child checks.
		if ( ! Check::validate_array( $array['check'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Validates the given string to see if it's a valid fraud outcome status.
	 *
	 * @param  string $outcome The array to validate.
	 *
	 * @return bool Whether it is a valid Rule array.
	 */
	public static function is_valid_fraud_outcome_status( string $outcome ): bool {
		return in_array(
			$outcome,
			[
				self::FRAUD_OUTCOME_BLOCK,
				self::FRAUD_OUTCOME_REVIEW,
				self::FRAUD_OUTCOME_ALLOW,
			],
			true
		);
	}

	/**
	 * Converts the class to it's array representation for transmission.
	 *
	 * @return  array
	 */
	public function to_array() {
		return [
			'key'     => $this->key,
			'outcome' => $this->outcome,
			'check'   => $this->check->to_array(),
		];
	}
}
