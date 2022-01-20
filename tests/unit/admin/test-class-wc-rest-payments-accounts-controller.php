<?php
/**
 * Class WC_REST_Payments_Accounts_Controller_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Exceptions\API_Exception;

/**
 * WC_REST_Payments_Accounts_Controller unit tests.
 */
class WC_REST_Payments_Accounts_Controller_Test extends WP_UnitTestCase {
	/**
	 * Controller under test.
	 *
	 * @var WC_REST_Payments_Accounts_Controller
	 */
	private $controller;

	/**
	 * @var WC_Payments_API_Client|MockObject
	 */
	private $mock_api_client;

	/**
	 * @var WC_Payments_API_Client
	 */
	private $original_api_client;

	public function setUp() {
		parent::setUp();

		// Set the user so that we can pass the authentication.
		wp_set_current_user( 1 );
		WC_Payments::get_gateway()->update_option( 'test_mode', 'yes' );

		$this->mock_api_client = $this->createMock( WC_Payments_API_Client::class );
		$this->controller      = new WC_REST_Payments_Accounts_Controller( $this->mock_api_client );

		// Inject the mocked client into service (happens in WCPay int, so reflection here).
		$account_service     = WC_Payments::get_account_service();
		$property_reflection = ( new ReflectionClass( $account_service ) )->getProperty( 'payments_api_client' );
		$property_reflection->setAccessible( true );
		$this->original_api_client = $property_reflection->getValue( $account_service );
		$property_reflection->setValue( $account_service, $this->mock_api_client );
	}

	public function tearDown() {
		parent::tearDown();

		WC_Payments::get_gateway()->update_option( 'test_mode', 'no' );

		// Restore the original client.
		$account_service     = WC_Payments::get_account_service();
		$property_reflection = ( new ReflectionClass( $account_service ) )->getProperty( 'payments_api_client' );
		$property_reflection->setAccessible( true );
		$property_reflection->setValue( $account_service, $this->original_api_client );
	}

	public function test_get_account_data_with_connected_account() {
		$this->mock_api_client
			->expects( $this->atLeastOnce() )
			->method( 'is_server_connected' )
			->willReturn( true );
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_account_data' )
			->willReturn(
				// We are providing only some of fields, needed for the assertions we are relying to.
				[
					'country'          => 'DE',
					'status'           => 'complete',
					'store_currencies' => [ 'default' => 'EUR' ],
				]
			);

		$response      = $this->controller->get_account_data( new WP_REST_Request( 'GET' ) );
		$response_data = $response->get_data();

		$this->assertSame( 200, $response->status );
		$this->assertTrue( $response_data['test_mode'] );
		$this->assertSame( 'complete', $response_data['status'] );
		$this->assertSame( 'DE', $response_data['country'] );
		$this->assertSame( 'EUR', $response_data['store_currencies']['default'] );
	}

	public function test_get_account_data_without_connected_account_and_enabled_onboarding() {
		$this->mock_api_client
			->expects( $this->atLeastOnce() )
			->method( 'is_server_connected' )
			->willReturn( true );
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_account_data' )
			->willReturn(
				// Indicates that server connection is ok, but no connected accounts available.
				[]
			);

		$response      = $this->controller->get_account_data( new WP_REST_Request( 'GET' ) );
		$response_data = $response->get_data();

		$this->assertSame( 200, $response->status );
		$this->assertTrue( $response_data['test_mode'] );
		$this->assertSame( 'NOACCOUNT', $response_data['status'] );
		// The default country and currency have changed in WC 5.3, hence multiple options in assertions.
		$this->assertContains( $response_data['country'], [ 'US', 'GB' ] );
		$this->assertContains( $response_data['store_currencies']['default'], [ 'USD', 'GBP' ] );
	}

	public function test_get_account_data_without_connected_account_and_disabled_onboarding() {
		$this->mock_api_client
			->expects( $this->atLeastOnce() )
			->method( 'is_server_connected' )
			->willReturn( true );
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_account_data' )
			->willThrowException(
				new API_Exception( 'On-boarding unavailable.', 'wcpay_on_boarding_disabled', 401 )
			);

		$response      = $this->controller->get_account_data( new WP_REST_Request( 'GET' ) );
		$response_data = $response->get_data();

		$this->assertSame( 200, $response->status );
		$this->assertTrue( $response_data['test_mode'] );
		$this->assertSame( 'ONBOARDING_DISABLED', $response_data['status'] );
		// The default country and currency have changed in WC 5.3, hence multiple options in assertions.
		$this->assertContains( $response_data['country'], [ 'US', 'GB' ] );
		$this->assertContains( $response_data['store_currencies']['default'], [ 'USD', 'GBP' ] );
	}

	public function test_get_account_data_with_card_eligible_present_true() {
		$this->mock_api_client
			->expects( $this->atLeastOnce() )
			->method( 'is_server_connected' )
			->willReturn( true );
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_account_data' )
			->willReturn(
				[
					'card_present_eligible' => true,
				]
			);

		$response      = $this->controller->get_account_data( new WP_REST_Request( 'GET' ) );
		$response_data = $response->get_data();

		$this->assertSame( 200, $response->status );
		$this->assertTrue( $response_data['test_mode'] );
		$this->assertFalse( $response_data['card_present_eligible'] );
	}
}
