<?php
/**
 * Class WC_REST_Payments_Reader_Controller_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Exceptions\Rest_Request_Exception;
use WC_REST_Payments_Reader_Controller as Controller;

/**
 * WC_REST_Payments_Reader_Controller_Test unit tests.
 */
class WC_REST_Payments_Reader_Controller_Test extends WP_UnitTestCase {
	/**
	 * Controller under test.
	 *
	 * @var WC_REST_Payments_Reader_Controller
	 */
	private $controller;

	/**
	 * @var WC_Payments_API_Client|MockObject
	 */
	private $mock_api_client;

	public function setUp() {
		parent::setUp();

		$this->mock_api_client = $this->createMock( WC_Payments_API_Client::class );
		$this->controller      = new WC_REST_Payments_Reader_Controller( $this->mock_api_client );
		$this->reader          = [
			'id'          => 'tmr_P400-123-456-789',
			'device_type' => 'verifone_P400',
			'label'       => 'Blue Rabbit',
			'livemode'    => false,
			'location'    => null,
			'metadata'    => [],
			'status'      => 'online',
			'is_active'   => true,
		];
	}

	/**
	 * Post test cleanup
	 */
	public function tearDown() {
		parent::tearDown();
		delete_transient( Controller::STORE_READERS_TRANSIENT_KEY );
	}

	public function test_get_summary_no_transaction() {
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_transaction' )
			->willReturn( [] );

		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'transaction_id', 1 );

		$response = $this->controller->get_summary( $request );
		$this->assertSame( [], $response->get_data() );
	}

	public function test_get_summary_no_readers_charge_summary() {
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_transaction' )
			->willReturn( [ 'created' => 1634291278 ] );

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_readers_charge_summary' )
			->with( gmdate( 'Y-m-d', 1634291278 ) )
			->willReturn( [] );

		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'transaction_id', 1 );

		$response = $this->controller->get_summary( $request );
		$this->assertSame( [], $response->get_data() );

	}

	public function test_get_summary_error() {
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_transaction' )
			->will( $this->throwException( new \WCPay\Exceptions\API_Exception( 'test exception', 'test', 0 ) ) );

		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'transaction_id', 1 );
		$response = $this->controller->get_summary( $request );

		$this->assertInstanceOf( 'WP_Error', $response );
	}

	public function test_get_summary() {

		$readers = [
			[
				'reader_id' => 1,
				'count'     => 3,
				'status'    => 'active',
				'fee'       => [
					'amount'   => 300,
					'currency' => 'usd',
				],
			],
			[
				'reader_id' => 2,
				'count'     => 1,
				'status'    => 'inactive',
				'fee'       => [
					'amount'   => 0,
					'currency' => 'usd',
				],
			],
		];

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_transaction' )
			->willReturn( [ 'created' => 1634291278 ] );

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_readers_charge_summary' )
			->with( gmdate( 'Y-m-d', 1634291278 ) )
			->willReturn( $readers );

		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'transaction_id', 1 );
		$response = $this->controller->get_summary( $request );
		$this->assertSame( $readers, $response->get_data() );
	}

	public function test_getting_all_readers_uses_cache_for_existing_readers() {
		set_transient( Controller::STORE_READERS_TRANSIENT_KEY, [ $this->reader ] );

		$this->mock_api_client
			->expects( $this->never() )
			->method( 'get_terminal_readers' );

		$this->mock_api_client
			->expects( $this->never() )
			->method( 'get_readers_charge_summary' );

		// Setup the request.
		$request = new WP_REST_Request(
			'GET',
			'/wc/v3/payments/readers'
		);
		$request->set_header( 'Content-Type', 'application/json' );
		$result = $this->controller->get_all_readers( $request );

		$this->assertEquals( [ $this->reader ], $result->get_data() );
	}
}
