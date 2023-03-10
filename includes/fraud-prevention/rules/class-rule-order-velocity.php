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
use WCPay\Fraud_Prevention\Models\Rule;
use WCPay\Logger;

/**
 * Class Rule_Order_Velocity
 */
class Rule_Order_Velocity extends Base_Rule {
	/**
	 * Interval of the order count.
	 *
	 * @var int
	 */
	private $interval;

	/**
	 * Max orders in the given interval.
	 *
	 * @var int
	 */
	private $max_orders;

	/**
	 * The key of this rule.
	 *
	 * @var string
	 */
	protected $key = Fraud_Risk_Tools::RULE_ORDER_VELOCITY;

	/**
	 * Class constructor
	 *
	 * @param   bool $enabled    The state of this rule.
	 * @param   bool $block      Whether the rule should block, or review.
	 * @param   int  $max_orders Max order number permitted in an interval.
	 * @param   int  $interval   The interval to count orders between.
	 *
	 * @return  void
	 */
	public function __construct( bool $enabled, bool $block, int $max_orders, int $interval ) {
		$this->enabled    = $enabled;
		$this->block      = $block;
		$this->max_orders = $max_orders;
		$this->interval   = $interval;
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
		if ( ! is_numeric( $this->max_orders ) || 1 > $this->max_orders ) {
			throw new Fraud_Ruleset_Exception( 'Given max_orders is not valid for the order velocity rule.' );
		}
		if ( ! in_array( $this->interval, [ 12, 24, 48, 72 ], true ) ) {
			throw new Fraud_Ruleset_Exception( 'Given interval is not valid for the order velocity rule.' );
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
			Check::list(
				Check::LIST_OPERATOR_AND,
				[ Check::check( 'orders_since_' . $this->interval . 'h', Check::OPERATOR_GT, $this->max_orders ) ]
			)
		) : null;
	}

	/**
	 * Returns the server settings as a Rule extension object.
	 *
	 * @param   Rule $rule  The rule taken from the server.
	 *
	 * @return  Base_Rule    One of the `Base_Rule` child classes.
	 */
	public static function from_server_rule( Rule $rule ) {
		$rule_check = $rule->check->checks[0];
		$matches    = [];
		preg_match( '/^orders_since_(\d+)h$/', $rule_check->key, $matches );
		return new static( true, $rule->outcome, intval( $rule_check->value ), intval( $matches[1] ) );
	}
}
