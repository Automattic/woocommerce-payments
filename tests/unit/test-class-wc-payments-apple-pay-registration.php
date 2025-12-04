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
	 * Mock Gateway (card gateway, passed to constructor).
	 *
	 * @var WC_Payment_Gateway_WCPay|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_gateway;

	/**
	 * Mock Apple Pay Gateway.
	 *
	 * @var WC_Payment_Gateway_WCPay|PHPUnit_Framework_MockObject_MockObject
	 */
	private $mock_apple_pay_gateway;

	/**
	 * Expected domain name for testing.
	 *
	 * @var string
	 */
	private $expected_domain;

	/**
	 * Original payment_gateway_map.
	 *
	 * @var array
	 */
	private $original_payment_gateway_map;

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

		$this->mock_apple_pay_gateway = $this->getMockBuilder( WC_Payment_Gateway_WCPay::class )
			->disableOriginalConstructor()
			->getMock();

		$this->original_payment_gateway_map = $this->get_payment_gateway_map();

		$this->wc_apple_pay_registration = new WC_Payments_Apple_Pay_Registration( $this->mock_api_client, $this->mock_account, $this->mock_gateway );
		$this->wc_apple_pay_registration->init_hooks();
	}

	/**
	 * Tear down test.
	 */
	public function tear_down() {
		$this->set_payment_gateway_map( $this->original_payment_gateway_map );
		delete_option( WC_Payments_Apple_Pay_Registration::APPLE_PAY_DOMAIN_ERROR_OPTION );
		parent::tear_down();
	}

	public function test_verify_domain_on_new_settings_when_enabled() {
		$this->mock_gateway->method( 'is_enabled' )->willReturn( true );
		$this->mock_apple_pay_gateway->method( 'is_enabled' )->willReturn( true );
		$this->set_payment_gateway_map( [ 'apple_pay' => $this->mock_apple_pay_gateway ] );

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
			[ 'enabled' => 'yes' ]
		);
	}

	public function test_verify_domain_on_new_settings_when_not_enabled() {
		$this->mock_apple_pay_gateway->method( 'is_enabled' )->willReturn( false );
		$this->set_payment_gateway_map( [ 'apple_pay' => $this->mock_apple_pay_gateway ] );

		$this->mock_api_client->expects( $this->never() )->method( 'register_domain' );
		$this->mock_gateway->expects( $this->never() )->method( 'update_option' );

		$this->wc_apple_pay_registration->verify_domain_on_new_settings(
			'option_name',
			[ 'enabled' => 'no' ]
		);
	}

	public function test_verify_domain_on_updated_settings_when_not_enabled() {
		$this->mock_apple_pay_gateway->method( 'is_enabled' )->willReturn( false );
		$this->set_payment_gateway_map( [ 'apple_pay' => $this->mock_apple_pay_gateway ] );

		$this->mock_api_client->expects( $this->never() )->method( 'register_domain' );
		$this->mock_gateway->expects( $this->never() )->method( 'update_option' );

		$this->wc_apple_pay_registration->verify_domain_on_updated_settings(
			[ 'enabled' => 'no' ],
			[ 'enabled' => 'no' ]
		);
	}

	public function test_verify_domain_on_updated_settings_when_enabled() {
		$this->mock_gateway->method( 'is_enabled' )->willReturn( true );
		$this->mock_apple_pay_gateway->method( 'is_enabled' )->willReturn( true );
		$this->set_payment_gateway_map( [ 'apple_pay' => $this->mock_apple_pay_gateway ] );

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
			[ 'enabled' => 'no' ],
			[ 'enabled' => 'yes' ]
		);
	}

	public function test_verify_domain_on_updated_settings_when_already_enabled() {
		$this->mock_apple_pay_gateway->method( 'is_enabled' )->willReturn( true );
		$this->set_payment_gateway_map( [ 'apple_pay' => $this->mock_apple_pay_gateway ] );

		$this->mock_api_client->expects( $this->never() )->method( 'register_domain' );
		$this->mock_gateway->expects( $this->never() )->method( 'update_option' );

		$this->wc_apple_pay_registration->verify_domain_on_updated_settings(
			[ 'enabled' => 'yes' ],
			[ 'enabled' => 'yes' ]
		);
	}

	public function test_register_domain_stores_error_on_failure() {
		$this->mock_gateway->method( 'is_enabled' )->willReturn( true );
		$this->mock_apple_pay_gateway->method( 'is_enabled' )->willReturn( true );
		$this->set_payment_gateway_map( [ 'apple_pay' => $this->mock_apple_pay_gateway ] );

		$error_message = 'Domain verification failed: invalid domain';

		$this->mock_api_client->expects( $this->once() )
			->method( 'register_domain' )
			->with( $this->expected_domain )
			->willReturn(
				[
					'id'        => 'domain_123',
					'apple_pay' => [
						'status'         => 'failed',
						'status_details' => [ 'error_message' => $error_message ],
					],
				]
			);

		$this->wc_apple_pay_registration->verify_domain_on_new_settings(
			'option_name',
			[ 'enabled' => 'yes' ]
		);

		$this->assertEquals( $error_message, get_option( WC_Payments_Apple_Pay_Registration::APPLE_PAY_DOMAIN_ERROR_OPTION ) );
	}

	public function test_register_domain_clears_error_on_success() {
		$this->mock_gateway->method( 'is_enabled' )->willReturn( true );
		$this->mock_apple_pay_gateway->method( 'is_enabled' )->willReturn( true );
		$this->set_payment_gateway_map( [ 'apple_pay' => $this->mock_apple_pay_gateway ] );

		update_option( WC_Payments_Apple_Pay_Registration::APPLE_PAY_DOMAIN_ERROR_OPTION, 'Previous error' );

		$this->mock_api_client->expects( $this->once() )
			->method( 'register_domain' )
			->with( $this->expected_domain )
			->willReturn(
				[
					'id'        => 'domain_123',
					'apple_pay' => [ 'status' => 'active' ],
				]
			);

		$this->wc_apple_pay_registration->verify_domain_on_new_settings(
			'option_name',
			[ 'enabled' => 'yes' ]
		);

		$this->assertFalse( get_option( WC_Payments_Apple_Pay_Registration::APPLE_PAY_DOMAIN_ERROR_OPTION ) );
	}

	public function test_display_error_notice_clears_error_after_display() {
		$this->mock_gateway->method( 'is_enabled' )->willReturn( true );
		$this->mock_apple_pay_gateway->method( 'is_enabled' )->willReturn( true );
		$this->set_payment_gateway_map( [ 'apple_pay' => $this->mock_apple_pay_gateway ] );
		$this->mock_account->method( 'get_is_live' )->willReturn( true );
		$this->mock_gateway->method( 'get_option' )
			->with( 'apple_pay_domain_set' )
			->willReturn( 'no' );

		update_option( WC_Payments_Apple_Pay_Registration::APPLE_PAY_DOMAIN_ERROR_OPTION, 'Test error message' );

		ob_start();
		$this->wc_apple_pay_registration->display_error_notice();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Test error message', $output );
		$this->assertFalse( get_option( WC_Payments_Apple_Pay_Registration::APPLE_PAY_DOMAIN_ERROR_OPTION ) );
	}

	public function test_display_error_notice_shows_generic_message_when_no_error_stored() {
		$this->mock_gateway->method( 'is_enabled' )->willReturn( true );
		$this->mock_apple_pay_gateway->method( 'is_enabled' )->willReturn( true );
		$this->set_payment_gateway_map( [ 'apple_pay' => $this->mock_apple_pay_gateway ] );
		$this->mock_account->method( 'get_is_live' )->willReturn( true );
		$this->mock_gateway->method( 'get_option' )
			->with( 'apple_pay_domain_set' )
			->willReturn( 'no' );

		ob_start();
		$this->wc_apple_pay_registration->display_error_notice();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Apple Pay domain verification failed.', $output );
	}
}
