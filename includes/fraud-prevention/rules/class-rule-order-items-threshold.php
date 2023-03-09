<?php
/**
 * CVC Verification rule class.
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
 * Class Rule_Order_Items_Threshold
 */
class Rule_Order_Items_Threshold extends Base_Rule {

	/**
	 * Minimum items needed to be included in the order.
	 *
	 * @var int
	 */
	private $min_items;

	/**
	 * Max items needed to be included in the order.
	 *
	 * @var int
	 */
	private $max_items;

	/**
	 * The key of this rule.
	 *
	 * @var string
	 */
	protected $key = Fraud_Risk_Tools::RULE_ORDER_ITEMS_THRESHOLD;

	/**
	 * Class constructor
	 *
	 * @param   bool     $enabled    The state of this rule.
	 * @param   bool     $block      Whether the rule should block, or review.
	 * @param   int|null $min_items  Minimum items needed to be included in the order.
	 * @param   int|null $max_items  Maximum items needed to be included in the order.
	 *
	 * @return  void
	 */
	public function __construct( bool $enabled, bool $block, $min_items, $max_items ) {
		$this->enabled   = $enabled;
		$this->block     = $block;
		$this->min_items = intval( $min_items );
		$this->max_items = intval( $max_items );
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
		if ( ! $this->min_items && ! $this->max_items ) {
			throw new Fraud_Ruleset_Exception( 'Both min and max item counts can not be null in the order items threshold rule.' );
		}
		if ( $this->min_items > $this->max_items ) {
			throw new Fraud_Ruleset_Exception( 'Minimum item count should be smaller than the maximum item count in the order items threshold rule.' );
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
						$this->min_items ? new Check( 'item_count', Check::OPERATOR_LT, $this->min_items ) : null,
						$this->max_items ? new Check( 'item_count', Check::OPERATOR_GT, $this->max_items ) : null,
					]
				)
			)
		) : null;
	}
}
