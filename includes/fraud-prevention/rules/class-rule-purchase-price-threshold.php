<?php
/**
 * Purchase price threshold rule class.
 *
 * @package WCPay\Fraud_Prevention\Rules
 */

namespace WCPay\Fraud_Prevention\Rules;

use WCPay\Exceptions\Fraud_Ruleset_Exception;
use WCPay\Fraud_Prevention\Fraud_Risk_Tools;
use WCPay\Fraud_Prevention\Models\Check;
use WCPay\Fraud_Prevention\Models\Checklist;
use WCPay\Fraud_Prevention\Models\Rule;

/**
 * Class Rule_Purchase_Price_Threshold
 */
class Rule_Purchase_Price_Threshold extends Base_Rule {
	/**
	 * Minimum price for the order.
	 *
	 * @var int
	 */
	private $min_amount;

	/**
	 * Max price for the order.
	 *
	 * @var int
	 */
	private $max_amount;

	/**
	 * The key of this rule.
	 *
	 * @var string
	 */
	protected $key = Fraud_Risk_Tools::RULE_PURCHASE_PRICE_THRESHOLD;

	/**
	 * Class constructor
	 *
	 * @param   bool     $enabled     The state of this rule.
	 * @param   bool     $block       Whether the rule should block, or review.
	 * @param   int|null $min_amount  Minimum price for order.
	 * @param   int|null $max_amount  Maximum price for the order.
	 *
	 * @return  void
	 */
	public function __construct( bool $enabled, bool $block, $min_amount, $max_amount ) {
		$this->enabled    = $enabled;
		$this->block      = $block;
		$this->min_amount = intval( $min_amount );
		$this->max_amount = intval( $max_amount );
		$this->validate_inputs();
	}

	/**
	 * Validates the given inputs.
	 *
	 * @return  void
	 * @throws Fraud_Ruleset_Exception When one of the inputs is invalid.
	 */
	private function validate_inputs() {
		if ( ! $this->enabled ) {
			return;
		}
		if ( ! $this->min_amount && ! $this->max_amount ) {
			throw new Fraud_Ruleset_Exception( 'Both min and max amounts can not be null in the purchase price threshold rule.' );
		}
		if ( $this->min_amount > $this->max_amount ) {
			throw new Fraud_Ruleset_Exception( 'Minimum order total should be smaller than the maximum order total in the purchase price threshold rule.' );
		}
	}

	/**
	 * Returns the Rule object from the class.
	 *
	 * @return  Rule   The rule object which will represent this instance.
	 */
	public function get_rule() {
		return $this->enabled ? new Rule(
			$this->key,
			$this->block ? Rule::FRAUD_OUTCOME_BLOCK : Rule::FRAUD_OUTCOME_REVIEW,
			new Checklist(
				Checklist::LIST_OPERATOR_OR,
				array_filter(
					[
						$this->min_amount ? new Check( 'order_total', Check::OPERATOR_LT, $this->min_amount ) : null,
						$this->max_amount ? new Check( 'order_total', Check::OPERATOR_GT, $this->max_amount ) : null,
					]
				)
			)
		) : null;
	}
}
