<?php
/**
 * Class WC_REST_Payments_Accounts_Controller_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;

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

	public function setUp() {
		parent::setUp();

		// Set the user so that we can pass the authentication.
		wp_set_current_user( 1 );

		$this->mock_api_client = $this->createMock( WC_Payments_API_Client::class );
		$this->controller      = new WC_REST_Payments_Accounts_Controller( $this->mock_api_client );
	}

	public function test_get_account_data_with_connected_account() {
		$this->mock_api_client
			->expects( $this->once() )
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
		$this->assertSame( 'complete', $response_data['status'] );
		$this->assertSame( 'DE', $response_data['country'] );
		$this->assertSame( 'EUR', $response_data['store_currencies']['default'] );
	}

	public function test_get_account_data_without_connected_account() {
		$this->mock_api_client
			->expects( $this->once() )
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
		$this->assertSame( 'NOACCOUNT', $response_data['status'] );
		$this->assertSame( 'US', $response_data['country'] );
		$this->assertSame( 'USD', $response_data['store_currencies']['default'] );
	}
}
