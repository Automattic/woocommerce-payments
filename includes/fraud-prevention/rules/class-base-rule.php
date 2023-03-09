<?php
/**
 * The base rule class.
 *
 * @package WCPay\Fraud_Prevention\Rules
 */

namespace WCPay\Fraud_Prevention\Rules;

use WCPay\Fraud_Prevention\Models\Rule;

/**
 * Class Base_Rule
 */
abstract class Base_Rule {

	/**
	 * The state of this rule.
	 *
	 * @var bool
	 */
	protected $enabled = false;

	/**
	 * Flag showing whether this rule will block the transaction.
	 *
	 * @var bool
	 */
	protected $block = false;

	/**
	 * The key of this rule. Should be filled by the child class.
	 *
	 * @var string
	 */
	protected $key = null;

	/**
	 * Returns the Rule object from the class.
	 *
	 * @return  Rule   The rule object which will represent this instance.
	 */
	abstract public function get_rule();

	/**
	 * Returns the array representation of the class.
	 *
	 * @return  array   The array which will represent this instance in the JSON serialization.
	 */
	public function to_array() {
		$rule = $this->get_rule();
		return $rule ? $rule->to_array() : null;
	}
}
