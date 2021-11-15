<?php
/**
 * Class WC_REST_Payments_Reader_Controller_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Exceptions\Rest_Request_Exception;

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
}
