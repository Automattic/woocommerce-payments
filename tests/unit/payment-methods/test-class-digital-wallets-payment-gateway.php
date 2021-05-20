<?php
/**
 * Class Digital_Wallets_Payment_Gateway_Test
 * @package WCPay\Payment_Gateways\Tests
 */

namespace WCPay\Payment_Methods;

use PHPUnit_Framework_MockObject_MockObject;
use WC_Payments_Account;
use WC_Payments_Action_Scheduler_Service;
use WC_Payments_API_Client;
use WC_Payments_Customer_Service;
use WC_Payments_Token_Service;
use WP_UnitTestCase;

/**
 * WCPay\Payment_Gateway\Digital_Wallets_Payment_Gateway Unit tests
 */
class Digital_Wallets_Payment_Gateway_Test extends WP_UnitTestCase {
	/**
	 * System under test.
	 *
	 * @var Digital_Wallets_Payment_Gateway
	 */
	private $mock_wcpay_gateway;

	/**
	 * Mock WC_Payments_Customer_Service.
	 *
	 * @var WC_Payments_Customer_Service|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_customer_service;

	/**
	 * Mock WC_Payments_Token_Service.
	 *
	 * @var WC_Payments_Token_Service|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_token_service;

	/**
	 * Mock WC_Payments_API_Client.
	 *
	 * @var WC_Payments_API_Client|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_api_client;

	/**
	 * Mock WC_Payments_Action_Scheduler_Service.
	 *
	 * @var WC_Payments_Action_Scheduler_Service|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_action_scheduler_service;

	/**
	 * WC_Payments_Account instance.
	 *
	 * @var WC_Payments_Account
	 */
	private $wcpay_account;

	/**
	 * Mocked value of return_url.
	 * The value is used in the set up and tests, so it's set as a private
	 * variable for easy reference.
	 *
	 * @var string
	 */
	private $return_url = 'test_url';

	public function setUp() {
		parent::setUp();

		// Arrange: Mock WC_Payments_API_Client so we can configure the
		// return value of create_and_confirm_intention().
		// Note that we cannot use createStub here since it's not defined in PHPUnit 6.5.
		$this->mock_api_client = $this->getMockBuilder( 'WC_Payments_API_Client' )
			->disableOriginalConstructor()
			->setMethods( [ 'create_and_confirm_intention', 'get_payment_method', 'is_server_connected' ] )
			->getMock();

		// Arrange: Create new WC_Payments_Account instance to use later.
		$this->wcpay_account = new WC_Payments_Account( $this->mock_api_client );

		// Arrange: Mock WC_Payments_Customer_Service so its methods aren't called directly.
		$this->mock_customer_service = $this->getMockBuilder( 'WC_Payments_Customer_Service' )
			->disableOriginalConstructor()
			->getMock();

		// Arrange: Mock WC_Payments_Customer_Service so its methods aren't called directly.
		$this->mock_token_service = $this->getMockBuilder( 'WC_Payments_Token_Service' )
			->disableOriginalConstructor()
			->getMock();

		// Arrange: Mock WC_Payments_Action_Scheduler_Service so its methods aren't called directly.
		$this->mock_action_scheduler_service = $this->getMockBuilder( 'WC_Payments_Action_Scheduler_Service' )
			->disableOriginalConstructor()
			->getMock();

		$this->mock_wcpay_gateway = $this->getMockBuilder( Digital_Wallets_Payment_Gateway::class )
			->setConstructorArgs(
				[
					$this->mock_api_client,
					$this->wcpay_account,
					$this->mock_customer_service,
					$this->mock_token_service,
					$this->mock_action_scheduler_service,
				]
			)
			->setMethods( [ 'get_option' ] )
			->getMock();
	}

	/**
	 * Test first time setting up such that digital_wallets_enabled_sections is not set. It should return defaults.
	 */
	public function test_get_digital_wallets_enabled_sections_defaults() {
		$this->mock_wcpay_gateway->expects( $this->once() )
			->method( 'get_option' )
			->with( 'digital_wallets_enabled_sections' )
			->willReturn( '' );

		$actual   = $this->mock_wcpay_gateway->get_digital_wallets_enabled_sections();
		$expected = [
			'cart'         => true,
			'checkout'     => true,
			'product_page' => true,
		];

		$this->assertEquals( $expected, $actual );
	}

	/**
	 * Test retrieving data from wp_options digital_wallets_enabled_sections.
	 */
	public function test_get_digital_wallets_enabled_sections() {
		$this->mock_wcpay_gateway->expects( $this->once() )
			->method( 'get_option' )
			->with( 'digital_wallets_enabled_sections' )
			->willReturn(
				[
					'cart'         => false,
					'checkout'     => true,
					'product_page' => false,
				]
			);

		$actual   = $this->mock_wcpay_gateway->get_digital_wallets_enabled_sections();
		$expected = [
			'cart'         => false,
			'checkout'     => true,
			'product_page' => false,
		];

		$this->assertEquals( $expected, $actual );
	}
}
