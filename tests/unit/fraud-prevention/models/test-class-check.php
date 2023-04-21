<?php
/**
 * Class Fraud_Prevention_Models_Check_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Fraud_Prevention\Models\Check;
use WCPay\Exceptions\Fraud_Ruleset_Exception;

/**
 * Fraud_Prevention_Models_Check_Test unit tests.
 */
class Fraud_Prevention_Models_Check_Test extends WCPAY_UnitTestCase {
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
	 * Check with checks array.
	 *
	 * @var array
	 */
	private $mock_check_with_checks = [
		'operator' => Check::LIST_OPERATOR_OR,
		'checks'   => [
			[
				'operator' => Check::OPERATOR_LT,
				'key'      => 'key_mock_1',
				'value'    => 500,
			],
			[
				'operator' => Check::OPERATOR_GT,
				'key'      => 'key_mock_2',
				'value'    => 1500,
			],
		],
	];

	public function test_it_creates_a_check_instance_from_an_array() {
		$check = Check::from_array( $this->mock_check );

		$this->assert_check( $check, 'key_mock', 1000, Check::OPERATOR_EQUALS );
	}

	public function test_it_creates_a_list_check_instance_from_an_array() {
		$check = Check::from_array( $this->mock_check_with_checks );

		$this->assert_check( $check, null, null, Check::LIST_OPERATOR_OR );
		$this->assert_check( $check->checks[0], 'key_mock_1', 500, Check::OPERATOR_LT );
		$this->assert_check( $check->checks[1], 'key_mock_2', 1500, Check::OPERATOR_GT );
	}

	public function test_it_fails_to_create_check_from_an_array_invalid_operator() {
		$this->expectException( Fraud_Ruleset_Exception::class );

		Check::from_array(
			[
				'operator' => 'invalid-operator',
				'key'      => 'key_mock',
				'value'    => 1000,
			]
		);
	}

	public function test_it_fails_to_create_check_from_an_array_operator_not_set() {
		$this->expectException( Fraud_Ruleset_Exception::class );

		Check::from_array(
			[
				'key'   => 'key_mock',
				'value' => 1000,
			]
		);
	}

	public function test_it_fails_to_create_check_from_an_array_list_operator_checks_not_set() {
		$this->expectException( Fraud_Ruleset_Exception::class );

		Check::from_array(
			[
				'operator' => Check::LIST_OPERATOR_AND,
			]
		);
	}

	public function test_it_fails_to_create_check_from_an_array_list_operator_checks_empty() {
		$this->expectException( Fraud_Ruleset_Exception::class );

		Check::from_array(
			[
				'operator' => Check::LIST_OPERATOR_AND,
				'checks'   => [],
			]
		);
	}

	public function test_it_fails_to_create_check_from_an_array_value_not_set() {
		$this->expectException( Fraud_Ruleset_Exception::class );

		Check::from_array(
			[
				'operator' => Check::OPERATOR_IN,
				'key'      => 'key_mock',
			]
		);
	}

	public function test_it_fails_to_create_checklist_invalid_operator() {
		$check = Check::from_array( $this->mock_check_with_checks );

		$this->expectException( Fraud_Ruleset_Exception::class );

		Check::list( 'invalid-operator', $check->checks );
	}

	public function test_it_fails_to_create_checklist_invalid_check_item() {
		$check = Check::from_array( $this->mock_check_with_checks );

		$this->expectException( Fraud_Ruleset_Exception::class );

		Check::list(
			Check::LIST_OPERATOR_AND,
			array_merge( $check->checks, [ [ 'invalid' => 'invalid' ] ] )
		);
	}

	public function test_it_creates_a_checklist() {
		$check = Check::from_array( $this->mock_check_with_checks );

		$checklist = Check::list( Check::LIST_OPERATOR_OR, $check->checks );

		$this->assertInstanceOf( Check::class, $checklist );
		$this->assertEquals( $checklist->operator, Check::LIST_OPERATOR_OR );
		$this->assert_check( $check->checks[0], 'key_mock_1', 500, Check::OPERATOR_LT );
		$this->assert_check( $check->checks[1], 'key_mock_2', 1500, Check::OPERATOR_GT );
	}

	public function test_it_converts_a_check_to_array() {
		$check       = Check::from_array( $this->mock_check );
		$check_array = $check->to_array();

		$this->assertEquals( $check_array, $this->mock_check );
	}

	public function test_it_converts_a_check_with_checks_to_array() {
		$check       = Check::from_array( $this->mock_check_with_checks );
		$check_array = $check->to_array();

		$this->assertEquals( $check_array, $this->mock_check_with_checks );
	}

	public function test_it_creates_a_check_instance() {
		$check = Check::check( 'key_mock', Check::OPERATOR_LTE, 2000 );

		$this->assert_check( $check, 'key_mock', 2000, Check::OPERATOR_LTE );
	}


	public function test_it_fails_to_create_a_check_instance_invalid_operator() {
		$this->expectException( Fraud_Ruleset_Exception::class );

		Check::check( 'key_mock', 'invalid-operator', 2000 );
	}

	private function assert_check( $check, $key, $value, $operator ) {
		$this->assertInstanceOf( Check::class, $check );
		$this->assertEquals( $check->key, $key );
		$this->assertEquals( $check->value, $value );
		$this->assertEquals( $check->operator, $operator );
	}
}
