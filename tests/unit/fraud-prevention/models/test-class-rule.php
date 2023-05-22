<?php
/**
 * Class Fraud_Prevention_Models_Rule_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Fraud_Prevention\Models\Rule;
use WCPay\Fraud_Prevention\Models\Check;
use WCPay\Exceptions\Fraud_Ruleset_Exception;

/**
 * Fraud_Prevention_Models_Rule_Test unit tests.
 */
class Fraud_Prevention_Models_Rule_Test extends WCPAY_UnitTestCase {
	/**
	 * Simple check mock.
	 *
	 * @var array
	 */
	private $mock_check = [
		'operator' => Check::OPERATOR_EQUALS,
		'key'      => 'key_mock',
		'value'    => 1000,
	];

	/**
	 * Simple rule mock.
	 *
	 * @var array
	 */
	private $mock_rule = [
		'key'     => 'rule_mock',
		'outcome' => Rule::FRAUD_OUTCOME_ALLOW,
	];

	public function set_up() {
		parent::set_up();

		$this->mock_rule['check'] = $this->mock_check;
	}

	public function test_it_creates_a_rule_instance_from_array() {
		$rule  = Rule::from_array( $this->mock_rule );
		$check = Check::from_array( $this->mock_check );

		$this->assert_rule( $rule, 'rule_mock', Rule::FRAUD_OUTCOME_ALLOW, $check );
	}

	public function test_it_fails_to_create_a_rule_instance_from_array_missing_key() {
		$this->expectException( Fraud_Ruleset_Exception::class );

		Rule::from_array(
			[
				'key'     => 'rule_mock',
				'outcome' => Rule::FRAUD_OUTCOME_ALLOW,
			]
		);
	}

	public function test_it_fails_to_create_a_rule_instance_from_array_check_is_not_array() {
		$this->expectException( Fraud_Ruleset_Exception::class );

		Rule::from_array(
			[
				'key'     => 'rule_mock',
				'outcome' => Rule::FRAUD_OUTCOME_ALLOW,
				'check'   => null,
			]
		);
	}

	public function test_it_fails_to_create_a_rule_instance_from_array_check_is_empty() {
		$this->expectException( Fraud_Ruleset_Exception::class );

		Rule::from_array(
			[
				'key'     => 'rule_mock',
				'outcome' => Rule::FRAUD_OUTCOME_ALLOW,
				'check'   => [],
			]
		);
	}

	public function test_it_fails_to_create_a_rule_instance_from_array_outcome_is_invalid() {
		$this->expectException( Fraud_Ruleset_Exception::class );

		Rule::from_array(
			[
				'key'     => 'rule_mock',
				'outcome' => 'invalid-outcome',
				'check'   => $this->mock_check,
			]
		);
	}

	public function test_it_fails_to_create_a_rule_instance_from_array_check_is_invalid() {
		$this->expectException( Fraud_Ruleset_Exception::class );

		Rule::from_array(
			[
				'key'     => 'rule_mock',
				'outcome' => Rule::FRAUD_OUTCOME_ALLOW,
				'check'   => [
					'operator' => Check::OPERATOR_IN,
					'key'      => 'key_mock',
				],
			]
		);
	}

	/**
	 * @dataProvider provide_fraud_outcome_status
	 */
	public function test_it_validates_fraud_outcome_status( $option_value, $return_value ) {
		$result = Rule::is_valid_fraud_outcome_status( $option_value );

		$this->assertSame( $return_value, $result );
	}

	public function provide_fraud_outcome_status() {
		return [
			[ 'invalid-outcome', false ],
			[ Rule::FRAUD_OUTCOME_ALLOW, true ],
			[ Rule::FRAUD_OUTCOME_BLOCK, true ],
			[ Rule::FRAUD_OUTCOME_REVIEW, true ],
		];
	}

	public function test_it_converts_a_rule_to_array() {
		$check = Check::from_array( $this->mock_check );
		$rule  = new Rule( 'rule_mock', Rule::FRAUD_OUTCOME_ALLOW, $check );

		$this->assertEquals( $this->mock_rule, $rule->to_array() );
	}

	public function test_it_fails_to_instantiate_rule_class() {
		$this->expectException( Fraud_Ruleset_Exception::class );

		$check = Check::from_array( $this->mock_check );
		new Rule( 'rule_mock', 'invalid-outcome', $check );
	}

	private function assert_rule( $rule, $key, $outcome, $check ) {
		$this->assertInstanceOf( Rule::class, $rule );
		$this->assertInstanceOf( Check::class, $check );
		$this->assertEquals( $rule->key, $key );
		$this->assertEquals( $rule->outcome, $outcome );
	}
}
