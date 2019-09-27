<?php
/**
 * Class WC_Payment_Gateway_WCPay_Test
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * WC_Payment_Gateway_WCPay unit tests.
 */
class WC_Payment_Gateway_WCPay_Test extends WP_UnitTestCase {

	/**
	 * System under test.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	private $wcpay_gateway;

	/**
	 * Mock WC_Payments_API_Client.
	 *
	 * @var WC_Payments_API_Client|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_api_client;

	/**
	 * Pre-test setup
	 */
	public function setUp() {
		parent::setUp();

		$this->mock_api_client = $this->getMockBuilder( 'WC_Payments_API_Client' )
			->disableOriginalConstructor()
			->setMethods( array( 'get_account_data' ) )
			->getMock();

		$this->wcpay_gateway = new WC_Payment_Gateway_WCPay( $this->mock_api_client );
	}

	/**
	 * Test if get_account_data returns false when the account object has no requirements property.
	 *
	 * @throws Exception - In the event of test failure.
	 */
	public function test_get_account_data_no_requirement_property() {
		$account_with_no_requirement_property = array();

		$this->expect_acccount_to_have_pending_requirements_equals( $account_with_no_requirement_property, false );
	}

	/**
	 * Test if get_account_data returns false when requirements is empty.
	 *
	 * @throws Exception - In the event of test failure.
	 */
	public function test_get_account_data_empty_requirement_property() {
		$account_with_empty_requirement_property = array(
			'requirements' => array(),
		);

		$this->expect_acccount_to_have_pending_requirements_equals( $account_with_empty_requirement_property, false );
	}

	/**
	 * Test if get_account_data returns false when there are no currently_due, past_due and eventually_due requirements.
	 *
	 * @throws Exception - In the event of test failure.
	 */
	public function test_get_account_data_no_requirements() {
		$account_with_no_requirements = array(
			'requirements' => array(
				'currently_due'  => array(),
				'past_due'       => array(),
				'eventually_due' => array(),
			),
		);

		$this->expect_acccount_to_have_pending_requirements_equals( $account_with_no_requirements, false );
	}

	/**
	 * Test if get_account_data returns true when there is at least one currently_due requirement.
	 *
	 * @throws Exception - In the event of test failure.
	 */
	public function test_get_account_data_with_currently_due_requirement() {
		$account_with_currently_due_requirement = array(
			'requirements' => array(
				'currently_due' => array(
					array(),
				),
			),
		);

		$this->expect_acccount_to_have_pending_requirements_equals( $account_with_currently_due_requirement, true );
	}

	/**
	 * Test if get_account_data returns true when there is at least one past_due requirement.
	 *
	 * @throws Exception - In the event of test failure.
	 */
	public function test_get_account_data_with_past_due_requirement() {
		$account_with_past_due_requirements = array(
			'requirements' => array(
				'past_due' => array(
					array(),
					array(),
				),
			),
		);

		$this->expect_acccount_to_have_pending_requirements_equals( $account_with_past_due_requirements, true );
	}

	/**
	 * Test if get_account_data returns true when there is at least one eventually_due requirement.
	 *
	 * @throws Exception - In the event of test failure.
	 */
	public function test_get_account_data_with_eventually_due_requirement() {
		$account_with_eventually_due_requirements = array(
			'requirements' => array(
				'eventually_due' => array(
					array(),
					array(),
					array(),
				),
			),
		);

		$this->expect_acccount_to_have_pending_requirements_equals( $account_with_eventually_due_requirements, true );
	}

	/**
	 * Helper method to wrap common code when setting account mock and pending requirements call.
	 *
	 * @param array   $account Account object to be used when testing.
	 * @param boolean $expected_result Expected result from account_has_pending_requirements call.
	 * @return boolean Result of pending requirements for account provided.
	 */
	private function expect_acccount_to_have_pending_requirements_equals( $account, $expected_result ) {
		$this->mock_api_client
			->expects( $this->any() )
			->method( 'get_account_data' )
			->will( $this->returnValue( $account ) );

		$pending_requirements = $this->wcpay_gateway->account_has_pending_requirements();
		$this->assertEquals( $expected_result, $pending_requirements );

		return $pending_requirements;
	}
}
