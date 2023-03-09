<?php
/**
 * Rule model class.
 *
 * @package WCPay\Fraud_Prevention\Models
 */

namespace WCPay\Fraud_Prevention\Models;

/**
 * Class Rule
 */
class Rule {

	// Rule outcomes.
	const FRAUD_OUTCOME_REVIEW = 'review';
	const FRAUD_OUTCOME_BLOCK  = 'block';

	/**
	 * The key of this rule.
	 *
	 * @var string
	 */
	private $key;

	/**
	 * The outcome of this outcome.
	 *
	 * @var string
	 */
	private $outcome;

	/**
	 * The checklist of this rule.
	 *
	 * @var Checklist
	 */
	private $checklist;

	/**
	 * Class constructor.
	 *
	 * @param   string    $key       The key of this rule.
	 * @param   string    $outcome   The operator of this rule.
	 * @param   Checklist $checklist The checklist of this rule.
	 *
	 * @return  void
	 */
	public function __construct( string $key, string $outcome, Checklist $checklist ) {
		$this->key       = $key;
		$this->outcome   = $outcome;
		$this->checklist = $checklist;
	}

	/**
	 * Returns the array representation of the object.
	 *
	 * @return  array   The array which will represent this instance in the JSON serialization.
	 */
	public function to_array() {
		return [
			'key'     => $this->key,
			'outcome' => $this->outcome,
			'check'   => $this->checklist->to_array(),
		];
	}
}
