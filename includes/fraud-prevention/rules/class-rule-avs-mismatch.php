<?php
/**
 * AVS Mismatch rule class.
 *
 * @package WCPay\Fraud_Prevention\Rules
 */

namespace WCPay\Fraud_Prevention\Rules;

use WCPay\Fraud_Prevention\Fraud_Risk_Tools;
use WCPay\Fraud_Prevention\Models\Check;
use WCPay\Fraud_Prevention\Models\Checklist;
use WCPay\Fraud_Prevention\Models\Rule;

/**
 * Class Rule_Avs_Mismatch
 */
class Rule_Avs_Mismatch extends Base_Rule {

	/**
	 * The key of this rule.
	 *
	 * @var string
	 */
	protected $key = Fraud_Risk_Tools::RULE_AVS_MISMATCH;

	/**
	 * Class constructor
	 *
	 * @param   bool $enabled  The state of this rule.
	 * @param   bool $block    Whether the rule should block, or review.
	 *
	 * @return  void
	 */
	public function __construct( bool $enabled, bool $block ) {
		$this->enabled = $enabled;
		$this->block   = $block;
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
				Checklist::LIST_OPERATOR_AND,
				[ new Check( 'avs_check', Check::OPERATOR_EQUALS, false ) ]
			)
		) : null;
	}
}
