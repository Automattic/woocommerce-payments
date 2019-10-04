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

	const NO_REQUIREMENTS      = false;
	const PENDING_REQUIREMENTS = true;

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

		$pending_requirements = $this->get_pending_requirements( $account_with_no_requirement_property );

		$this->assertEquals( $pending_requirements, self::NO_REQUIREMENTS );
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

		$pending_requirements = $this->get_pending_requirements( $account_with_empty_requirement_property );

		$this->assertEquals( $pending_requirements, self::NO_REQUIREMENTS );
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

		$pending_requirements = $this->get_pending_requirements( $account_with_no_requirements );

		$this->assertEquals( $pending_requirements, self::NO_REQUIREMENTS );
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

		$pending_requirements = $this->get_pending_requirements( $account_with_currently_due_requirement );

		$this->assertEquals( $pending_requirements, self::PENDING_REQUIREMENTS );
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

		$pending_requirements = $this->get_pending_requirements( $account_with_past_due_requirements );

		$this->assertEquals( $pending_requirements, self::PENDING_REQUIREMENTS );
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

		$pending_requirements = $this->get_pending_requirements( $account_with_eventually_due_requirements );

		$this->assertEquals( $pending_requirements, self::PENDING_REQUIREMENTS );
	}

	/**
	 * Helper method to wrap common code when setting account mock and pending requirements call.
	 *
	 * @param array $account Account object to be used when testing.
	 * @return boolean Result of pending requirements for account provided.
	 */
	private function get_pending_requirements( $account ) {
		$this->mock_api_client
			->expects( $this->any() )
			->method( 'get_account_data' )
			->will( $this->returnValue( $account ) );

		return $this->wcpay_gateway->account_has_pending_requirements( $account );
	}
}
