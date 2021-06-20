<?php
/**
 * Class WC_Payments_Admin_Test
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * WC_Payments_Admin unit tests.
 */
class WC_Payments_Admin_Test extends WP_UnitTestCase {

	/**
	 * System under test.
	 *
	 * @var WC_Payments_Admin
	 */
	private $wcpay_admin;

	/**
	 * Mock WC_Payments_API_Client.
	 *
	 * @var WC_Payments_API_Client|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_api_client;

	/**
	 * Mock WC_Payment_Gateway_WCPay.
	 *
	 * @var WC_Payment_Gateway_WCPay|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_gateway;

	/**
	 * Mock WC_Payments_Account.
	 *
	 * @var WC_Payments_Account|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_account;

	/**
	 * Pre-test setup
	 */
	public function setUp() {
		parent::setUp();

		$this->mock_api_client = $this->getMockBuilder( 'WC_Payments_API_Client' )
			->disableOriginalConstructor()
			->getMock();

		$this->mock_gateway = $this->getMockBuilder( 'WC_Payment_Gateway_WCPay' )
			->disableOriginalConstructor()
			->getMock();

		$this->mock_account = $this->getMockBuilder( 'WC_Payments_Account' )
			->disableOriginalConstructor()
			->getMock();

		$this->wcpay_admin = new WC_Payments_Admin(
			$this->mock_api_client,
			$this->mock_gateway,
			$this->mock_account,
		);

		$this->wcpay_admin->add_payments_menu();
	}

	public function tearDown() {
		unset( $_GET );
		parent::tearDown();
	}

	/**
	 * @dataProvider data_current_user_can_access_page
	 */
	public function test_current_user_can_access_page( $expected, $is_stripe_connected, $get_params ) {
		$_GET = $get_params;

		$this->mock_account
			->method( 'is_stripe_connected' )
			->willReturn( $is_stripe_connected );

		$actual = $this->wcpay_admin->current_user_can_access_page();

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Data provider for test_current_user_can_access_page
	 */
	public function data_current_user_can_access_page() {
		return [
			'no_get_params'        => [
				true,
				false,
				[],
			],
			'empty_page_param'     => [
				true,
				false,
				[
					'path' => '/payments/overview',
				],
			],
			'incorrect_page_param' => [
				true,
				false,
				[
					'page' => 'wc-settings',
					'path' => '/payments/overview',
				],
			],
			'empty_path_param'     => [
				true,
				false,
				[
					'page' => 'wc-admin',
				],
			],
			'incorrect_path_param' => [
				true,
				false,
				[
					'page' => 'wc-admin',
					'path' => '/payments/does-not-exist',
				],
			],
			'stripe_connected'     => [
				true,
				true,
				[
					'page' => 'wc-admin',
					'path' => '/payments/overview',
				],
			],
			'happy_path'           => [
				false,
				false,
				[
					'page' => 'wc-admin',
					'path' => '/payments/overview',
				],
			],
		];
	}
}
