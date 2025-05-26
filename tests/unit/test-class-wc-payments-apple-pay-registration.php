<?php
/**
 * Class WC_Payments_Apple_Pay_Registration_Test
 *
 * @package WooCommerce\\Payments\\Tests
 */

/**
 * WC_Payments_Apple_Pay_Registration unit tests.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class WC_Payments_Apple_Pay_Registration_Test extends WCPAY_UnitTestCase {

	/**
	 * System under test.
	 *
	 * @var WC_Payments_Apple_Pay_Registration
	 */
	private $wc_apple_pay_registration;

	/**
	 * Mock WC_Payments_API_Client.
	 *
	 * @var WC_Payments_API_Client|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_api_client;

	/**
	 * Mock WC_Payments_Account.
	 *
	 * @var WC_Payments_Account|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_account;

	/**
	 * Mock Gateway.
	 *
	 * @var WC_Payment_Gateway_WCPay|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_gateway;

	/**
	 * Expected domain name for testing.
	 *
	 * @var string
	 */
	private $expected_domain;

	/**
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

		$this->expected_domain = wp_parse_url( get_site_url(), PHP_URL_HOST );

		$this->mock_api_client = $this->getMockBuilder( 'WC_Payments_API_Client' )
			->disableOriginalConstructor()
			->getMock();

		$this->mock_account = $this->getMockBuilder( 'WC_Payments_Account' )
			->disableOriginalConstructor()
			->getMock();

		$this->mock_gateway = $this->getMockBuilder( WC_Payment_Gateway_WCPay::class )
			->disableOriginalConstructor()
			->getMock();

		$this->wc_apple_pay_registration = new WC_Payments_Apple_Pay_Registration( $this->mock_api_client, $this->mock_account, $this->mock_gateway );
		$this->wc_apple_pay_registration->init_hooks();
	}

	public function test_verify_domain_on_new_settings_when_enabled() {
		$this->mock_gateway->method( 'is_enabled' )
			->willReturn( true );

		$this->mock_gateway->method( 'get_option' )
			->with( 'payment_request' )
			->willReturn( 'yes' );

		$this->mock_api_client->expects( $this->once() )
			->method( 'register_domain' )
			->with( $this->expected_domain )
			->willReturn(
				[
					'id'        => 'domain_123',
					'apple_pay' => [ 'status' => 'active' ],
				]
			);

		$this->mock_gateway->expects( $this->exactly( 2 ) )
			->method( 'update_option' )
			->withConsecutive(
				[ 'apple_pay_verified_domain', $this->expected_domain ],
				[ 'apple_pay_domain_set', 'yes' ]
			);

		$this->wc_apple_pay_registration->verify_domain_on_new_settings(
			'option_name',
			[
				'enabled'         => 'yes',
				'payment_request' => 'yes',
			]
		);
	}

	public function test_verify_domain_on_new_settings_when_not_enabled() {
		$this->mock_gateway->method( 'is_enabled' )
			->willReturn( false );

		$this->mock_api_client->expects( $this->never() )
			->method( 'register_domain' );

		$this->mock_gateway->expects( $this->never() )
			->method( 'update_option' );

		$this->wc_apple_pay_registration->verify_domain_on_new_settings(
			'option_name',
			[
				'enabled'         => 'no',
				'payment_request' => 'yes',
			]
		);
	}

	public function test_verify_domain_on_updated_settings_when_not_enabled() {
		$this->mock_gateway->method( 'is_enabled' )
			->willReturn( false );

		$this->mock_api_client->expects( $this->never() )
			->method( 'register_domain' );

		$this->mock_gateway->expects( $this->never() )
			->method( 'update_option' );

		$this->wc_apple_pay_registration->verify_domain_on_updated_settings(
			[
				'enabled'         => 'no',
				'payment_request' => 'yes',
			],
			[
				'enabled'         => 'no',
				'payment_request' => 'yes',
			]
		);
	}

	public function test_verify_domain_on_updated_settings_when_enabled() {
		$this->mock_gateway->method( 'is_enabled' )
			->willReturn( true );

		$this->mock_gateway->method( 'get_option' )
			->with( 'payment_request' )
			->willReturn( 'yes' );

		$this->mock_api_client->expects( $this->once() )
			->method( 'register_domain' )
			->with( $this->expected_domain )
			->willReturn(
				[
					'id'        => 'domain_123',
					'apple_pay' => [ 'status' => 'active' ],
				]
			);

		$this->mock_gateway->expects( $this->exactly( 2 ) )
			->method( 'update_option' )
			->withConsecutive(
				[ 'apple_pay_verified_domain', $this->expected_domain ],
				[ 'apple_pay_domain_set', 'yes' ]
			);

		$this->wc_apple_pay_registration->verify_domain_on_updated_settings(
			[
				'enabled'         => 'no',
				'payment_request' => 'no',
			],
			[
				'enabled'         => 'yes',
				'payment_request' => 'yes',
			]
		);
	}
}
